<?php

namespace App\Services;

class LotTraceabilityService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function traceRawMaterialLot(string $supplierLotNumber): array
    {
        $stmt = $this->db->prepare('
            SELECT prl.id as receipt_lot_id, fl.id as fifo_lot_id, fl.lot_number,
                   i.item_code, i.description, pr.receipt_date,
                   s.company_name as supplier_name, po.po_number,
                   f.name as facility_name
            FROM po_receipt_lots prl
            JOIN fifo_lots fl ON prl.fifo_lot_id = fl.id
            JOIN po_receipt_lines prline ON prl.receipt_line_id = prline.id
            JOIN po_receipts pr ON prline.receipt_id = pr.id
            JOIN purchase_orders po ON pr.po_id = po.id
            JOIN suppliers s ON po.supplier_id = s.id
            JOIN items i ON fl.item_id = i.id
            JOIN facilities f ON fl.facility_id = f.id
            WHERE prl.supplier_lot_number LIKE ?
        ');
        $stmt->execute(['%' . $supplierLotNumber . '%']);
        $receipts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = ['supplier_lot' => $supplierLotNumber, 'receipts' => []];
        foreach ($receipts as $receipt) {
            $receipt['batches'] = $this->getBatchesUsingLot((int)$receipt['fifo_lot_id']);
            $result['receipts'][] = $receipt;
        }
        return $result;
    }

    public function traceFinishedGoodLot(string $batchNumber): array
    {
        $stmt = $this->db->prepare('
            SELECT bt.*, i.item_code, i.description, f.name as facility_name
            FROM batch_tickets bt
            JOIN items i ON bt.item_id = i.id
            JOIN facilities f ON bt.facility_id = f.id
            WHERE bt.batch_number = ?
        ');
        $stmt->execute([$batchNumber]);
        $batch = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$batch) return ['error' => 'Batch not found: ' . $batchNumber];

        $stmt = $this->db->prepare('SELECT * FROM fifo_lots WHERE source_type = "BATCH" AND source_id = ?');
        $stmt->execute([$batch['id']]);
        $lots = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $shipments = [];
        foreach ($lots as $lot) {
            $stmt = $this->db->prepare('
                SELECT sll.lot_number, sll.quantity_shipped, sl.unit_price,
                       s.shipment_number, s.ship_date,
                       so.so_number, c.customer_code, c.company_name,
                       st.location_name, st.city, st.state
                FROM shipment_line_lots sll
                JOIN shipment_lines sl ON sll.shipment_line_id = sl.id
                JOIN shipments s ON sl.shipment_id = s.id
                JOIN shipment_orders sord ON sord.shipment_id = s.id
                JOIN sales_orders so ON sord.so_id = so.id
                JOIN customers c ON so.customer_id = c.id
                JOIN ship_to_locations st ON s.ship_to_id = st.id
                WHERE sll.fifo_lot_id = ?
            ');
            $stmt->execute([$lot['id']]);
            $shipments = array_merge($shipments, $stmt->fetchAll(\PDO::FETCH_ASSOC));
        }

        $stmt = $this->db->prepare('
            SELECT fl.remaining_quantity, fl.status, f.name as facility_name, fl.lot_number
            FROM fifo_lots fl JOIN facilities f ON fl.facility_id = f.id
            WHERE fl.source_type = "BATCH" AND fl.source_id = ? AND fl.remaining_quantity > 0
        ');
        $stmt->execute([$batch['id']]);
        $currentInventory = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare('
            SELECT bil.*, i.item_code, i.description
            FROM batch_ingredient_lots bil
            JOIN items i ON bil.ingredient_item_id = i.id
            WHERE bil.batch_id = ?
        ');
        $stmt->execute([$batch['id']]);
        $ingredients = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return compact('batch', 'shipments', 'currentInventory', 'ingredients');
    }

    public function traceCustomerComplaint(int $customerId, ?int $soId, ?string $dateFrom, ?string $dateTo): array
    {
        $where = 'c.id = ?';
        $params = [$customerId];
        if ($soId) { $where .= ' AND so.id = ?'; $params[] = $soId; }
        if ($dateFrom) { $where .= ' AND s.ship_date >= ?'; $params[] = $dateFrom; }
        if ($dateTo) { $where .= ' AND s.ship_date <= ?'; $params[] = $dateTo; }

        $stmt = $this->db->prepare("
            SELECT sll.lot_number, sll.quantity_shipped, sl.unit_price,
                   s.shipment_number, s.ship_date,
                   so.so_number, i.item_code, i.description,
                   ipe.name as pack_extension
            FROM shipment_line_lots sll
            JOIN shipment_lines sl ON sll.shipment_line_id = sl.id
            JOIN sales_order_lines sol ON sl.so_line_id = sol.id
            JOIN items i ON sol.item_id = i.id
            LEFT JOIN item_pack_extensions ipe ON sol.pack_extension_id = ipe.id
            JOIN shipments s ON sl.shipment_id = s.id
            JOIN shipment_orders sord ON sord.shipment_id = s.id
            JOIN sales_orders so ON sord.so_id = so.id
            JOIN customers c ON so.customer_id = c.id
            WHERE {$where}
            ORDER BY s.ship_date DESC
        ");
        $stmt->execute($params);
        $lines = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($lines as &$line) {
            $fifoLot = $this->getFifoLotByLotNumber($line['lot_number']);
            if ($fifoLot && $fifoLot['source_type'] === 'BATCH') {
                $batchStmt = $this->db->prepare("SELECT batch_number FROM batch_tickets WHERE id = ?");
                $batchStmt->execute([$fifoLot['source_id']]);
                $bn = $batchStmt->fetchColumn();
                if ($bn) {
                    $trace = $this->traceFinishedGoodLot($bn);
                    $line['batch'] = $trace['batch'] ?? null;
                    $line['ingredients'] = $trace['ingredients'] ?? [];
                }
            }
        }
        return ['shipment_lines' => $lines];
    }

    private function getBatchesUsingLot(int $fifoLotId): array
    {
        $stmt = $this->db->prepare('
            SELECT bt.batch_number, bt.closed_at, i.item_code, i.description, bil.quantity_used
            FROM batch_ingredient_lots bil
            JOIN batch_tickets bt ON bil.batch_id = bt.id
            JOIN items i ON bt.item_id = i.id
            WHERE bil.fifo_lot_id = ?
        ');
        $stmt->execute([$fifoLotId]);
        $batches = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($batches as &$batch) {
            $trace = $this->traceFinishedGoodLot($batch['batch_number']);
            $batch['shipments'] = $trace['shipments'] ?? [];
        }
        return $batches;
    }

    private function getFifoLotByLotNumber(string $lotNumber): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM fifo_lots WHERE lot_number = ? LIMIT 1');
        $stmt->execute([$lotNumber]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
