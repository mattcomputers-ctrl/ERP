<?php

namespace App\Services;

use App\Exceptions\NegativeInventoryException;

class FIFOService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Consume quantity from oldest available FIFO lots at a facility.
     * HARD BLOCKS if on-hand would go below zero.
     *
     * @return array [{lot_id, lot_number, quantity_consumed, unit_cost}, ...]
     * @throws NegativeInventoryException
     */
    public function consume(
        int $itemId,
        int $facilityId,
        float $quantity,
        string $sourceType,
        int $sourceId,
        int $userId = 0
    ): array {
        $onHand = $this->getOnHand($itemId, $facilityId);
        if ($onHand < $quantity) {
            $stmt = $this->db->prepare('SELECT item_code FROM items WHERE id = ?');
            $stmt->execute([$itemId]);
            $itemCode = $stmt->fetchColumn() ?: "Item #{$itemId}";
            throw new NegativeInventoryException($itemCode, $onHand, $quantity);
        }

        $stmt = $this->db->prepare('
            SELECT id, lot_number, remaining_quantity, unit_cost
            FROM fifo_lots
            WHERE item_id = ? AND facility_id = ?
              AND status = "AVAILABLE"
              AND remaining_quantity > 0
            ORDER BY created_at ASC, id ASC
            FOR UPDATE
        ');
        $stmt->execute([$itemId, $facilityId]);
        $lots = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $consumed = [];
        $remaining = $quantity;

        foreach ($lots as $lot) {
            if ($remaining <= 0) break;

            $take = min($remaining, (float)$lot['remaining_quantity']);
            $newRemaining = (float)$lot['remaining_quantity'] - $take;

            $this->db->prepare(
                'UPDATE fifo_lots SET remaining_quantity = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$newRemaining, $lot['id']]);

            $this->logTransaction(
                $itemId, $facilityId, $lot['id'],
                'CONSUMPTION', -$take, (float)$lot['unit_cost'],
                $sourceType, $sourceId, $userId
            );

            $consumed[] = [
                'lot_id' => $lot['id'],
                'lot_number' => $lot['lot_number'],
                'quantity_consumed' => $take,
                'unit_cost' => (float)$lot['unit_cost'],
            ];

            $remaining -= $take;
        }

        return $consumed;
    }

    /**
     * Add a new FIFO lot to inventory.
     * @return int New lot ID
     */
    public function addLot(
        int $itemId,
        int $facilityId,
        float $quantity,
        float $unitCost,
        string $lotNumber,
        string $sourceType,
        int $sourceId,
        ?string $expirationDate = null,
        string $status = 'AVAILABLE',
        int $userId = 0,
        ?int $packExtensionId = null
    ): int {
        $stmt = $this->db->prepare('
            INSERT INTO fifo_lots
            (item_id, pack_extension_id, facility_id, lot_number, quantity, remaining_quantity,
             unit_cost, source_type, source_id, expiration_date, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ');
        $stmt->execute([
            $itemId, $packExtensionId, $facilityId, $lotNumber,
            $quantity, $quantity, $unitCost,
            $sourceType, $sourceId, $expirationDate, $status
        ]);
        $lotId = (int)$this->db->lastInsertId();

        $this->logTransaction(
            $itemId, $facilityId, $lotId,
            'RECEIPT', $quantity, $unitCost,
            $sourceType, $sourceId, $userId
        );

        return $lotId;
    }

    public function getOnHand(int $itemId, int $facilityId): float
    {
        $stmt = $this->db->prepare('
            SELECT COALESCE(SUM(remaining_quantity), 0)
            FROM fifo_lots
            WHERE item_id = ? AND facility_id = ? AND status = "AVAILABLE"
        ');
        $stmt->execute([$itemId, $facilityId]);
        return (float)$stmt->fetchColumn();
    }

    public function getAvailable(int $itemId, int $facilityId): float
    {
        $onHand = $this->getOnHand($itemId, $facilityId);
        $stmt = $this->db->prepare('
            SELECT COALESCE(SUM(quantity), 0)
            FROM inventory_reservations
            WHERE item_id = ? AND facility_id = ?
        ');
        $stmt->execute([$itemId, $facilityId]);
        $reserved = (float)$stmt->fetchColumn();
        return max(0, $onHand - $reserved);
    }

    public function getAvailableAllFacilities(int $itemId): array
    {
        $stmt = $this->db->prepare('
            SELECT f.id, f.name,
                   COALESCE(SUM(fl.remaining_quantity), 0) as on_hand
            FROM facilities f
            LEFT JOIN fifo_lots fl ON fl.item_id = ? AND fl.facility_id = f.id AND fl.status = "AVAILABLE"
            WHERE f.active = 1
            GROUP BY f.id, f.name
        ');
        $stmt->execute([$itemId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $resvStmt = $this->db->prepare('
                SELECT COALESCE(SUM(quantity), 0) FROM inventory_reservations
                WHERE item_id = ? AND facility_id = ?
            ');
            $resvStmt->execute([$itemId, $row['id']]);
            $reserved = (float)$resvStmt->fetchColumn();

            $result[$row['id']] = [
                'facility_name' => $row['name'],
                'on_hand' => (float)$row['on_hand'],
                'available' => max(0, (float)$row['on_hand'] - $reserved),
            ];
        }
        return $result;
    }

    public function quarantine(int $lotId, string $reason, int $userId): void
    {
        $this->db->prepare('
            UPDATE fifo_lots SET status = "QUARANTINED", quarantine_reason = ?,
            quarantined_by = ?, quarantined_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ')->execute([$reason, $userId, $lotId]);
    }

    public function releaseQuarantine(int $lotId, int $userId): void
    {
        $this->db->prepare('
            UPDATE fifo_lots SET status = "AVAILABLE", quarantine_reason = NULL,
            quarantined_by = NULL, quarantined_at = NULL, updated_at = NOW()
            WHERE id = ? AND status = "QUARANTINED"
        ')->execute([$lotId]);
    }

    public function getLotById(int $lotId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM fifo_lots WHERE id = ?');
        $stmt->execute([$lotId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function getLotsForItem(int $itemId, int $facilityId, bool $availableOnly = false): array
    {
        $where = $availableOnly ? 'AND fl.status = "AVAILABLE" AND fl.remaining_quantity > 0' : '';
        $stmt = $this->db->prepare("
            SELECT fl.*, i.item_code, COALESCE(ifl.location, '') as location
            FROM fifo_lots fl
            JOIN items i ON fl.item_id = i.id
            LEFT JOIN item_facility_locations ifl ON ifl.item_id = fl.item_id AND ifl.facility_id = fl.facility_id
            WHERE fl.item_id = ? AND fl.facility_id = ? {$where}
            ORDER BY fl.created_at ASC, fl.id ASC
        ");
        $stmt->execute([$itemId, $facilityId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function logTransaction(
        int $itemId, int $facilityId, int $lotId,
        string $type, float $quantity, float $unitCost,
        string $refType, int $refId, int $userId
    ): void {
        $this->db->prepare('
            INSERT INTO inventory_transactions
            (item_id, facility_id, fifo_lot_id, transaction_type, quantity, unit_cost,
             reference_type, reference_id, user_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ')->execute([$itemId, $facilityId, $lotId, $type, $quantity, $unitCost, $refType, $refId, $userId]);
    }
}
