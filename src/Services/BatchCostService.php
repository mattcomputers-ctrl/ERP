<?php

namespace App\Services;

class BatchCostService
{
    private \PDO $db;

    public function __construct(\PDO $db, FIFOService $fifo)
    {
        $this->db = $db;
    }

    public function calculateActual(int $batchId): array
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(quantity_used * unit_cost), 0) as total_cost FROM batch_ingredient_lots WHERE batch_id = ?');
        $stmt->execute([$batchId]);
        $totalCost = (float)$stmt->fetchColumn();

        $stmt = $this->db->prepare('SELECT COALESCE(SUM(actual_quantity), 0) as total_yield FROM batch_ticket_packs WHERE batch_id = ? AND actual_quantity IS NOT NULL');
        $stmt->execute([$batchId]);
        $totalYield = (float)$stmt->fetchColumn();

        return [
            'total_cost' => $totalCost,
            'cost_per_unit' => $totalYield > 0 ? $totalCost / $totalYield : 0,
            'total_yield' => $totalYield,
        ];
    }

    public function calculateTheoretical(int $batchId): array
    {
        $stmt = $this->db->prepare('
            SELECT btl.item_id, btl.theoretical_quantity, b.facility_id
            FROM batch_ticket_lines btl
            JOIN batch_tickets b ON btl.batch_id = b.id
            WHERE btl.batch_id = ?
        ');
        $stmt->execute([$batchId]);
        $lines = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalCost = 0;
        foreach ($lines as $line) {
            $stmt2 = $this->db->prepare('
                SELECT CASE WHEN SUM(remaining_quantity) > 0
                       THEN SUM(remaining_quantity * unit_cost) / SUM(remaining_quantity)
                       ELSE 0 END as avg_cost
                FROM fifo_lots
                WHERE item_id = ? AND facility_id = ? AND status = "AVAILABLE" AND remaining_quantity > 0
            ');
            $stmt2->execute([$line['item_id'], $line['facility_id']]);
            $avgCost = (float)$stmt2->fetchColumn();
            $totalCost += (float)$line['theoretical_quantity'] * $avgCost;
        }

        $stmt = $this->db->prepare('SELECT target_quantity FROM batch_tickets WHERE id = ?');
        $stmt->execute([$batchId]);
        $targetQty = (float)$stmt->fetchColumn();

        return [
            'total_cost' => $totalCost,
            'cost_per_unit' => $targetQty > 0 ? $totalCost / $targetQty : 0,
        ];
    }
}
