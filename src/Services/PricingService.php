<?php

namespace App\Services;

class PricingService
{
    private \PDO $db;

    public function __construct(\PDO $db) { $this->db = $db; }

    /**
     * Resolve unit price for a customer + item + quantity.
     *
     * Resolution order:
     * 1. Customer's assigned price lists, searched by priority (lowest number first)
     * 2. Falls back to legacy customer_prices table
     * 3. Falls back to items.sale_price
     */
    public function resolveCustomerPrice(int $itemId, int $customerId, float $quantity): array
    {
        // Get customer's price lists ordered by effective priority
        $stmt = $this->db->prepare('
            SELECT pl.id, pl.name,
                   COALESCE(cpla.priority_override, pl.default_priority) as effective_priority
            FROM customer_price_list_assignments cpla
            JOIN price_lists pl ON cpla.price_list_id = pl.id
            WHERE cpla.customer_id = ? AND cpla.active = 1 AND pl.active = 1
              AND pl.list_type = "CUSTOMER"
              AND pl.effective_date <= CURDATE()
              AND (pl.expiration_date IS NULL OR pl.expiration_date >= CURDATE())
            ORDER BY effective_priority ASC
        ');
        $stmt->execute([$customerId]);
        $lists = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($lists as $list) {
            $result = $this->findBestBreak($list['id'], $itemId, $quantity);
            if ($result !== null) {
                return array_merge($result, ['list_name' => $list['name'], 'source' => 'price_list']);
            }
        }

        // Fall back to legacy customer_prices
        $legacyPrice = $this->resolvePrice($itemId, $customerId, $quantity);
        if ($legacyPrice > 0) {
            return ['price' => $legacyPrice, 'source' => 'legacy', 'list_name' => null,
                    'package_type' => null, 'qty_per_package' => null, 'external_code' => null];
        }

        return ['price' => 0.0, 'source' => 'manual', 'list_name' => null,
                'package_type' => null, 'qty_per_package' => null, 'external_code' => null];
    }

    /**
     * Resolve supplier price for PO line pre-fill.
     */
    public function resolveSupplierPrice(int $itemId, int $supplierId, float $quantity): array
    {
        $stmt = $this->db->prepare('
            SELECT pl.id, pl.name,
                   COALESCE(spla.priority_override, pl.default_priority) as effective_priority
            FROM supplier_price_list_assignments spla
            JOIN price_lists pl ON spla.price_list_id = pl.id
            WHERE spla.supplier_id = ? AND spla.active = 1 AND pl.active = 1
              AND pl.list_type = "SUPPLIER"
              AND pl.effective_date <= CURDATE()
              AND (pl.expiration_date IS NULL OR pl.expiration_date >= CURDATE())
            ORDER BY effective_priority ASC
        ');
        $stmt->execute([$supplierId]);
        $lists = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($lists as $list) {
            $result = $this->findBestBreak($list['id'], $itemId, $quantity);
            if ($result !== null) {
                return array_merge($result, ['list_name' => $list['name'], 'source' => 'price_list']);
            }
        }

        // Fall back to AVL cost
        $avlStmt = $this->db->prepare('
            SELECT approved_unit_cost FROM approved_vendor_list
            WHERE item_id = ? AND supplier_id = ? AND active = 1
            ORDER BY is_preferred DESC LIMIT 1
        ');
        $avlStmt->execute([$itemId, $supplierId]);
        $avlCost = $avlStmt->fetchColumn();
        if ($avlCost !== false) {
            return ['price' => (float)$avlCost, 'source' => 'avl', 'list_name' => null,
                    'package_type' => null, 'qty_per_package' => null, 'external_code' => null];
        }

        return ['price' => 0.0, 'source' => 'manual', 'list_name' => null,
                'package_type' => null, 'qty_per_package' => null, 'external_code' => null];
    }

    private function findBestBreak(int $listId, int $itemId, float $quantity): ?array
    {
        $stmt = $this->db->prepare('
            SELECT pll.*, pt.name as package_type_name
            FROM price_list_lines pll
            LEFT JOIN package_types pt ON pll.package_type_id = pt.id
            WHERE pll.price_list_id = ? AND pll.item_id = ? AND pll.active = 1
            ORDER BY pll.id ASC
        ');
        $stmt->execute([$listId, $itemId]);
        $lines = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($lines as $line) {
            $best = null;
            for ($i = 1; $i <= 5; $i++) {
                $bQty = $line["break_qty_$i"];
                $bPrice = $line["break_price_$i"];
                if ($bQty === null) break;
                if ($quantity >= (float)$bQty) {
                    $best = (float)$bPrice;
                }
            }
            if ($best !== null) {
                return [
                    'price' => $best,
                    'external_code' => $line['external_code'],
                    'package_type' => $line['package_type_name'],
                    'qty_per_package' => $line['qty_per_package'] ? (float)$line['qty_per_package'] : null,
                ];
            }
        }
        return null;
    }

    /**
     * Legacy price resolution (customer_prices table).
     */
    public function resolvePrice(int $itemId, ?int $customerId, float $quantity, ?\DateTime $date = null): float
    {
        $dateStr = ($date ?? new \DateTime())->format('Y-m-d');

        if ($customerId) {
            $stmt = $this->db->prepare('
                SELECT unit_price FROM customer_prices
                WHERE item_id = ? AND customer_id = ? AND min_quantity <= ?
                  AND effective_date <= ? AND active = 1
                ORDER BY min_quantity DESC LIMIT 1
            ');
            $stmt->execute([$itemId, $customerId, $quantity, $dateStr]);
            $price = $stmt->fetchColumn();
            if ($price !== false) return (float)$price;
        }

        $stmt = $this->db->prepare('
            SELECT unit_price FROM customer_prices
            WHERE item_id = ? AND customer_id IS NULL AND min_quantity <= ?
              AND effective_date <= ? AND active = 1
            ORDER BY min_quantity DESC LIMIT 1
        ');
        $stmt->execute([$itemId, $quantity, $dateStr]);
        $price = $stmt->fetchColumn();
        if ($price !== false) return (float)$price;

        $stmt = $this->db->prepare('SELECT sale_price FROM items WHERE id = ?');
        $stmt->execute([$itemId]);
        return (float)($stmt->fetchColumn() ?: 0);
    }

    /**
     * Calculate replacement cost from supplier price lists.
     */
    public function calculateReplacementCost(int $itemId, int $quantity = 100): float
    {
        $stmt = $this->db->prepare('
            SELECT rs.percentage, rs.item_id as ingredient_item_id
            FROM recipe_versions rv
            JOIN recipe_steps rs ON rs.recipe_version_id = rv.id
            WHERE rv.item_id = ? AND rv.is_default = 1 AND rv.is_active = 1
              AND rs.step_type = "INGREDIENT"
        ');
        $stmt->execute([$itemId]);
        $steps = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($steps)) return 0.0;

        $totalCost = 0;
        foreach ($steps as $step) {
            $pct = (float)($step['percentage'] ?? 0);
            if ($pct <= 0) continue;
            $ingredientQty = ($pct / 100) * $quantity;
            $price = $this->getBestSupplierPrice((int)$step['ingredient_item_id'], $ingredientQty);
            $totalCost += $ingredientQty * $price;
        }
        return $totalCost / $quantity;
    }

    /**
     * Calculate cost from current FIFO inventory.
     */
    public function calculateInventoryCost(int $itemId, ?int $facilityId = null): float
    {
        $stmt = $this->db->prepare('
            SELECT rs.percentage, rs.item_id as ingredient_item_id
            FROM recipe_versions rv
            JOIN recipe_steps rs ON rs.recipe_version_id = rv.id
            WHERE rv.item_id = ? AND rv.is_default = 1 AND rv.is_active = 1
              AND rs.step_type = "INGREDIENT"
        ');
        $stmt->execute([$itemId]);
        $steps = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($steps)) return 0.0;

        $totalCost = 0;
        foreach ($steps as $step) {
            $pct = (float)($step['percentage'] ?? 0);
            if ($pct <= 0) continue;
            $sql = 'SELECT CASE WHEN SUM(remaining_quantity) > 0
                       THEN SUM(remaining_quantity * unit_cost) / SUM(remaining_quantity)
                       ELSE 0 END as avg_cost
                    FROM fifo_lots
                    WHERE item_id = ? AND status = "AVAILABLE" AND remaining_quantity > 0';
            $params = [(int)$step['ingredient_item_id']];
            if ($facilityId) { $sql .= ' AND facility_id = ?'; $params[] = $facilityId; }
            $stmt2 = $this->db->prepare($sql);
            $stmt2->execute($params);
            $avgCost = (float)$stmt2->fetchColumn();
            $totalCost += ($pct / 100) * $avgCost;
        }
        return $totalCost;
    }

    private function getBestSupplierPrice(int $itemId, float $quantity): float
    {
        $stmt = $this->db->prepare('
            SELECT pll.*
            FROM price_list_lines pll
            JOIN price_lists pl ON pll.price_list_id = pl.id
            WHERE pll.item_id = ? AND pll.active = 1 AND pl.active = 1
              AND pl.list_type = "SUPPLIER"
              AND pl.effective_date <= CURDATE()
              AND (pl.expiration_date IS NULL OR pl.expiration_date >= CURDATE())
        ');
        $stmt->execute([$itemId]);
        $lines = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $best = 0.0;
        foreach ($lines as $line) {
            for ($i = 1; $i <= 5; $i++) {
                if ($line["break_qty_$i"] === null) break;
                if ($quantity >= (float)$line["break_qty_$i"]) {
                    $price = (float)$line["break_price_$i"];
                    if ($best === 0.0 || $price < $best) $best = $price;
                }
            }
        }

        // Fall back to AVL
        if ($best === 0.0) {
            $avlStmt = $this->db->prepare('
                SELECT MIN(approved_unit_cost) FROM approved_vendor_list
                WHERE item_id = ? AND active = 1
            ');
            $avlStmt->execute([$itemId]);
            $avlMin = $avlStmt->fetchColumn();
            if ($avlMin !== false && (float)$avlMin > 0) $best = (float)$avlMin;
        }

        return $best;
    }

    public function checkMOQ(int $itemId, int $customerId, float $quantity): ?array
    {
        $stmt = $this->db->prepare('
            SELECT min_quantity FROM customer_moq
            WHERE item_id = ? AND customer_id = ? AND effective_date <= CURDATE() AND active = 1
            ORDER BY effective_date DESC LIMIT 1
        ');
        $stmt->execute([$itemId, $customerId]);
        $moq = $stmt->fetchColumn();
        if ($moq === false || $quantity >= (float)$moq) return null;
        return ['moq' => (float)$moq, 'shortfall' => (float)$moq - $quantity];
    }

    /**
     * Get all price list lines containing this item, grouped by list.
     */
    public function getItemPriceListInfo(int $itemId): array
    {
        $stmt = $this->db->prepare('
            SELECT pll.*, pl.name as list_name, pl.list_type, pt.name as package_type_name
            FROM price_list_lines pll
            JOIN price_lists pl ON pll.price_list_id = pl.id
            LEFT JOIN package_types pt ON pll.package_type_id = pt.id
            WHERE pll.item_id = ? AND pll.active = 1 AND pl.active = 1
            ORDER BY pl.list_type, pl.name
        ');
        $stmt->execute([$itemId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
