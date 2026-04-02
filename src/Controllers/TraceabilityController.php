<?php

namespace PrecisionInk\Controllers;

class TraceabilityController extends BaseController
{
    private function getService(): \App\Services\LotTraceabilityService
    {
        return new \App\Services\LotTraceabilityService($this->db());
    }

    public function landing(): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->renderView('traceability/index', []);
    }

    // ── Query 1: Raw Material Lot ───────────────────────────────────

    public function rawMaterial(): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $lot = trim($_GET['lot'] ?? $_POST['lot'] ?? '');
        $results = null;

        if ($lot) {
            $results = $this->getService()->traceRawMaterialLot($lot);
        }

        $this->renderView('traceability/raw_material', ['lot' => $lot, 'results' => $results]);
    }

    // ── Query 2: Finished Good Lot ──────────────────────────────────

    public function finishedGood(): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $batch = trim($_GET['batch'] ?? $_POST['batch'] ?? '');
        $results = null;

        if ($batch) {
            $results = $this->getService()->traceFinishedGoodLot($batch);
        }

        $this->renderView('traceability/finished_good', ['batch' => $batch, 'results' => $results]);
    }

    // ── Query 3: Customer Complaint ─────────────────────────────────

    public function complaint(): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $customerId = (int)($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
        $soId = (int)($_GET['so_id'] ?? $_POST['so_id'] ?? 0) ?: null;
        $dateFrom = $_GET['date_from'] ?? $_POST['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? $_POST['date_to'] ?? '';
        $results = null;

        if ($customerId) {
            $results = $this->getService()->traceCustomerComplaint($customerId, $soId, $dateFrom ?: null, $dateTo ?: null);
        }

        $this->renderView('traceability/complaint', [
            'customerId' => $customerId, 'soId' => $soId,
            'dateFrom' => $dateFrom, 'dateTo' => $dateTo, 'results' => $results,
        ]);
    }

    // ── CSV Export ──────────────────────────────────────────────────

    public function export(): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $mode = $_GET['mode'] ?? '';
        $svc = $this->getService();
        $rows = [];
        $headers = [];

        if ($mode === 'raw_material') {
            $lot = trim($_GET['lot'] ?? '');
            $data = $svc->traceRawMaterialLot($lot);
            $headers = ['Supplier Lot', 'Item Code', 'Description', 'Supplier', 'PO#', 'Receipt Date', 'Batch#', 'Customer', 'Ship Date', 'Qty Shipped'];
            foreach ($data['receipts'] ?? [] as $r) {
                foreach ($r['batches'] ?? [] as $b) {
                    foreach ($b['shipments'] ?? [] as $s) {
                        $rows[] = [$lot, $r['item_code'], $r['description'], $r['supplier_name'], $r['po_number'], $r['receipt_date'], $b['batch_number'], $s['company_name'], $s['ship_date'], $s['quantity_shipped']];
                    }
                    if (empty($b['shipments'])) {
                        $rows[] = [$lot, $r['item_code'], $r['description'], $r['supplier_name'], $r['po_number'], $r['receipt_date'], $b['batch_number'], '', '', ''];
                    }
                }
                if (empty($r['batches'])) {
                    $rows[] = [$lot, $r['item_code'], $r['description'], $r['supplier_name'], $r['po_number'], $r['receipt_date'], '', '', '', ''];
                }
            }
        } elseif ($mode === 'finished_good') {
            $batch = trim($_GET['batch'] ?? '');
            $data = $svc->traceFinishedGoodLot($batch);
            $headers = ['Batch#', 'Item', 'Customer', 'Shipment#', 'SO#', 'Ship Date', 'Qty Shipped'];
            foreach ($data['shipments'] ?? [] as $s) {
                $rows[] = [$batch, $data['batch']['item_code'] ?? '', $s['company_name'], $s['shipment_number'], $s['so_number'], $s['ship_date'], $s['quantity_shipped']];
            }
        } elseif ($mode === 'complaint') {
            $customerId = (int)($_GET['customer_id'] ?? 0);
            $data = $svc->traceCustomerComplaint($customerId, (int)($_GET['so_id'] ?? 0) ?: null, $_GET['date_from'] ?? null, $_GET['date_to'] ?? null);
            $headers = ['Shipment#', 'Ship Date', 'SO#', 'Item', 'Lot', 'Qty', 'Batch#'];
            foreach ($data['shipment_lines'] ?? [] as $l) {
                $rows[] = [$l['shipment_number'], $l['ship_date'], $l['so_number'], $l['item_code'], $l['lot_number'], $l['quantity_shipped'], $l['batch']['batch_number'] ?? ''];
            }
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="traceability_' . $mode . '_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $row) fputcsv($out, $row);
        fclose($out);
        exit;
    }
}
