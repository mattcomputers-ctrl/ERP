<?php

namespace App\Services;

class PricingService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

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
}
