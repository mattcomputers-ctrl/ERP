<?php

namespace App\Services;

class SupplierService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function getPerformanceMetrics(int $supplierId): array
    {
        // On-time delivery rate
        $stmt = $this->db->prepare('
            SELECT
                COUNT(*) as total_receipts,
                SUM(CASE WHEN pr.receipt_date <= po.expected_delivery_date THEN 1 ELSE 0 END) as on_time_receipts,
                AVG(DATEDIFF(pr.receipt_date, po.order_date)) as avg_actual_lead_days
            FROM po_receipts pr
            JOIN purchase_orders po ON pr.po_id = po.id
            WHERE po.supplier_id = ?
        ');
        $stmt->execute([$supplierId]);
        $delivery = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Avg quoted lead time from AVL
        $stmt = $this->db->prepare('
            SELECT AVG(lead_time_days) as avg_quoted_lead
            FROM approved_vendor_list
            WHERE supplier_id = ? AND lead_time_days IS NOT NULL AND active = 1
        ');
        $stmt->execute([$supplierId]);
        $quoted = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Total spend
        $stmt = $this->db->prepare('
            SELECT
                SUM(CASE WHEN pr.receipt_date >= DATE_FORMAT(NOW(), "%Y-01-01") THEN prl.received_quantity * prl.unit_cost ELSE 0 END) as spend_ytd,
                SUM(prl.received_quantity * prl.unit_cost) as spend_all_time
            FROM po_receipt_lines prl
            JOIN po_receipts pr ON prl.receipt_id = pr.id
            JOIN purchase_orders po ON pr.po_id = po.id
            WHERE po.supplier_id = ?
        ');
        $stmt->execute([$supplierId]);
        $spend = $stmt->fetch(\PDO::FETCH_ASSOC);

        $totalReceipts = (int)($delivery['total_receipts'] ?? 0);
        $onTimeReceipts = (int)($delivery['on_time_receipts'] ?? 0);
        $avgActual = $delivery['avg_actual_lead_days'] ? round((float)$delivery['avg_actual_lead_days'], 1) : null;
        $avgQuoted = $quoted['avg_quoted_lead'] ? round((float)$quoted['avg_quoted_lead'], 1) : null;

        return [
            'total_receipts' => $totalReceipts,
            'on_time_rate' => $totalReceipts > 0 ? round($onTimeReceipts / $totalReceipts * 100, 1) : null,
            'avg_actual_lead_days' => $avgActual,
            'avg_quoted_lead_days' => $avgQuoted,
            'lead_time_variance' => ($avgActual !== null && $avgQuoted !== null) ? round($avgActual - $avgQuoted, 1) : null,
            'spend_ytd' => (float)($spend['spend_ytd'] ?? 0),
            'spend_all_time' => (float)($spend['spend_all_time'] ?? 0),
            'has_data' => $totalReceipts > 0,
        ];
    }
}
