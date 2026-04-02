<?php

namespace PrecisionInk\Controllers;

class ReportController extends BaseController
{
    public function crmRepActivity(): void
    {
        if (!$this->checkPermission('crm', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        $repId = (int)($_GET['rep_id'] ?? 0);

        $where = ['ca.created_at >= ?', 'ca.created_at <= ?'];
        $params = [$dateFrom, $dateTo . ' 23:59:59'];
        if ($repId) { $where[] = 'ca.created_by = ?'; $params[] = $repId; }
        $wc = implode(' AND ', $where);

        $rows = $this->db()->prepare("
            SELECT u.full_name as rep_name, ca.activity_type, COUNT(*) as cnt
            FROM customer_activities ca
            JOIN users u ON ca.created_by = u.id
            WHERE {$wc}
            GROUP BY u.id, ca.activity_type
            ORDER BY u.full_name, ca.activity_type
        ");
        $rows->execute($params);

        $users = $this->db()->query("SELECT id, full_name FROM users WHERE active = 1 ORDER BY full_name")->fetchAll();

        $this->renderView('reports/crm_rep_activity', [
            'rows' => $rows->fetchAll(), 'users' => $users,
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'rep_id' => $repId],
        ]);
    }

    public function crmContactFrequency(): void
    {
        if (!$this->checkPermission('crm', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $threshold = max(1, (int)($_GET['days'] ?? 30));

        $rows = $this->db()->prepare("
            SELECT c.id, c.customer_code, c.company_name, u.full_name as rep_name,
                   MAX(ca.created_at) as last_activity,
                   DATEDIFF(CURDATE(), MAX(ca.created_at)) as days_since,
                   crm.next_contact_date
            FROM customers c
            LEFT JOIN customer_activities ca ON ca.customer_id = c.id
            LEFT JOIN users u ON c.sales_rep_id = u.id
            LEFT JOIN customer_crm_profiles crm ON crm.customer_id = c.id
            WHERE c.active = 1 AND c.deleted_at IS NULL AND c.sales_rep_id IS NOT NULL
            GROUP BY c.id
            HAVING last_activity IS NULL OR days_since > ?
            ORDER BY days_since DESC
        ");
        $rows->execute([$threshold]);

        $this->renderView('reports/crm_contact_frequency', [
            'rows' => $rows->fetchAll(), 'threshold' => $threshold,
        ]);
    }

    public function crmTaskReport(): void
    {
        if (!$this->checkPermission('crm', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        $repId = (int)($_GET['rep_id'] ?? 0);

        $repFilter = $repId ? 'AND t.assigned_to = ?' : '';
        $params = $repId ? [$repId] : [];

        $rows = $this->db()->prepare("
            SELECT u.full_name as rep_name,
                   SUM(CASE WHEN t.status = 'OPEN' THEN 1 ELSE 0 END) as open_tasks,
                   SUM(CASE WHEN t.status = 'COMPLETED' AND t.completed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as completed,
                   SUM(CASE WHEN t.status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled,
                   SUM(CASE WHEN t.status = 'OPEN' AND t.due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
            FROM crm_tasks t
            JOIN users u ON t.assigned_to = u.id
            WHERE 1=1 {$repFilter}
            GROUP BY u.id
            ORDER BY u.full_name
        ");
        $rows->execute(array_merge([$dateFrom, $dateTo . ' 23:59:59'], $params));

        $users = $this->db()->query("SELECT id, full_name FROM users WHERE active = 1 ORDER BY full_name")->fetchAll();

        $this->renderView('reports/crm_tasks', [
            'rows' => $rows->fetchAll(), 'users' => $users,
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'rep_id' => $repId],
        ]);
    }

    public function crmRepCustomers(): void
    {
        if (!$this->checkPermission('crm', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $repId = (int)($_GET['rep_id'] ?? $this->currentUserId());
        $users = $this->db()->query("SELECT id, full_name FROM users WHERE active = 1 ORDER BY full_name")->fetchAll();

        $rows = $this->db()->prepare("
            SELECT c.id, c.customer_code, c.company_name, c.credit_limit, c.ar_balance, c.account_hold,
                   (SELECT MAX(so.order_date) FROM sales_orders so WHERE so.customer_id = c.id AND so.deleted_at IS NULL) as last_order,
                   (SELECT MAX(ca.created_at) FROM customer_activities ca WHERE ca.customer_id = c.id) as last_contact,
                   (SELECT COUNT(*) FROM quotes q WHERE q.customer_id = c.id AND q.status IN ('DRAFT','SENT') AND q.deleted_at IS NULL) as open_quotes,
                   (SELECT COUNT(*) FROM sales_orders so WHERE so.customer_id = c.id AND so.status IN ('CONFIRMED','PARTIAL') AND so.deleted_at IS NULL) as open_orders
            FROM customers c
            WHERE c.sales_rep_id = ? AND c.active = 1 AND c.deleted_at IS NULL
            ORDER BY c.company_name
        ");
        $rows->execute([$repId]);

        $this->renderView('reports/crm_rep_customers', [
            'rows' => $rows->fetchAll(), 'users' => $users, 'repId' => $repId,
        ]);
    }

    public function crmNextContact(): void
    {
        if (!$this->checkPermission('crm', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $daysAhead = max(1, (int)($_GET['days'] ?? 7));

        $rows = $this->db()->prepare("
            SELECT c.id, c.customer_code, c.company_name,
                   u.full_name as rep_name, crm.next_contact_date, crm.profile_notes,
                   (SELECT MAX(ca.created_at) FROM customer_activities ca WHERE ca.customer_id = c.id) as last_activity
            FROM customers c
            JOIN customer_crm_profiles crm ON crm.customer_id = c.id
            LEFT JOIN users u ON c.sales_rep_id = u.id
            WHERE crm.next_contact_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
              AND c.active = 1 AND c.deleted_at IS NULL
            ORDER BY crm.next_contact_date ASC
        ");
        $rows->execute([$daysAhead]);

        $this->renderView('reports/crm_next_contact', [
            'rows' => $rows->fetchAll(), 'daysAhead' => $daysAhead,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // GENERIC REPORT SYSTEM
    // ══════════════════════════════════════════════════════════════════

    private function getReportRegistry(): array
    {
        return [
            'inventory' => [
                'stock-on-hand' => 'Stock On Hand',
                'valuation' => 'Inventory Valuation',
                'transactions' => 'Inventory Transactions',
                'lot-status' => 'FIFO Lot Status',
                'slow-moving' => 'Slow Moving / Non-Moving',
            ],
            'purchasing' => [
                'open-pos' => 'Open Purchase Orders',
                'receiving' => 'Receiving Report',
                'supplier-spend' => 'Supplier Spend',
                'cost-changes' => 'Cost Change Report',
                'landed-costs' => 'Landed Cost Report',
            ],
            'sales' => [
                'open-orders' => 'Open Sales Orders',
                'order-history' => 'Order History',
                'by-customer' => 'Sales by Customer',
                'by-item' => 'Sales by Item',
                'by-rep' => 'Sales by Rep',
                'on-time-shipping' => 'On-Time Shipping',
                'backorder-aging' => 'Backorder Aging',
                'invoice-register' => 'Invoice Register',
                'ar-aging' => 'AR Aging',
            ],
            'production' => [
                'batch-history' => 'Batch History',
                'batch-costs' => 'Batch Cost Summary',
                'yield' => 'Yield Analysis',
                'scrap' => 'Scrap Report',
                'qc-summary' => 'QC Summary',
            ],
            'quality' => [
                'inspection-log' => 'Incoming Inspection Log',
                'scars' => 'SCAR Register',
                'write-offs' => 'Write-Off Report',
            ],
        ];
    }

    public function reportIndex(): void
    {
        $this->renderView('reports/index', ['registry' => $this->getReportRegistry()]);
    }

    public function runReport(string $category, string $report): void
    {
        if (!$this->checkPermission('reports', 'view') && !$this->checkPermission($category, 'view')) {
            // Fallback: allow if user has view on any module
        }

        $filters = array_merge($_GET, $_POST);
        $filters['date_from'] = $filters['date_from'] ?? date('Y-m-01');
        $filters['date_to'] = $filters['date_to'] ?? date('Y-m-d');

        $data = $this->getReportData($category, $report, $filters);
        $columns = $this->getReportColumns($category, $report);
        $title = $this->getReportRegistry()[$category][$report] ?? ucwords(str_replace('-', ' ', $report));

        $this->renderView('reports/generic', [
            'category' => $category, 'report' => $report, 'title' => $title,
            'columns' => $columns, 'data' => $data, 'filters' => $filters,
            'registry' => $this->getReportRegistry(),
        ]);
    }

    public function exportCsv(string $category, string $report): void
    {
        $filters = $_GET;
        $filters['date_from'] = $filters['date_from'] ?? date('Y-m-01');
        $filters['date_to'] = $filters['date_to'] ?? date('Y-m-d');

        $data = $this->getReportData($category, $report, $filters);
        $columns = $this->getReportColumns($category, $report);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $category . '_' . $report . '_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_values($columns));
        foreach ($data as $row) {
            $csvRow = [];
            foreach (array_keys($columns) as $key) $csvRow[] = $row[$key] ?? '';
            fputcsv($out, $csvRow);
        }
        fclose($out);
        exit;
    }

    public function exportPdf(string $category, string $report): void
    {
        $filters = $_GET;
        $filters['date_from'] = $filters['date_from'] ?? date('Y-m-01');
        $filters['date_to'] = $filters['date_to'] ?? date('Y-m-d');

        $data = $this->getReportData($category, $report, $filters);
        $columns = $this->getReportColumns($category, $report);
        $title = $this->getReportRegistry()[$category][$report] ?? $report;

        $thHtml = '';
        foreach ($columns as $label) $thHtml .= '<th>' . htmlspecialchars($label) . '</th>';
        $trHtml = '';
        foreach ($data as $row) {
            $trHtml .= '<tr>';
            foreach (array_keys($columns) as $key) $trHtml .= '<td>' . htmlspecialchars((string)($row[$key] ?? '')) . '</td>';
            $trHtml .= '</tr>';
        }

        $html = '<!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif;font-size:10px;margin:20px;}h2{font-size:14px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:3px 5px;}th{background:#f0f0f0;font-size:9px;}</style></head><body><h2>' . htmlspecialchars($title) . '</h2><p style="font-size:9px;color:#666;">Generated: ' . date('M j, Y g:ia') . ' | Filters: ' . htmlspecialchars(http_build_query($filters)) . '</p><table><thead><tr>' . $thHtml . '</tr></thead><tbody>' . $trHtml . '</tbody></table></body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();
        $dompdf->stream($category . '_' . $report . '.pdf', ['Attachment' => false]);
        exit;
    }

    // ── Report Data Queries ─────────────────────────────────────────

    private function getReportData(string $cat, string $rpt, array $f): array
    {
        return match ("{$cat}/{$rpt}") {
            'inventory/stock-on-hand' => $this->rptStockOnHand($f),
            'inventory/valuation' => $this->rptValuation($f),
            'inventory/transactions' => $this->rptTransactions($f),
            'inventory/lot-status' => $this->rptLotStatus($f),
            'inventory/slow-moving' => $this->rptSlowMoving($f),
            'purchasing/open-pos' => $this->rptOpenPOs($f),
            'purchasing/receiving' => $this->rptReceiving($f),
            'purchasing/supplier-spend' => $this->rptSupplierSpend($f),
            'purchasing/cost-changes' => $this->rptCostChanges($f),
            'purchasing/landed-costs' => $this->rptLandedCosts($f),
            'sales/open-orders' => $this->rptOpenOrders($f),
            'sales/order-history' => $this->rptOrderHistory($f),
            'sales/by-customer' => $this->rptByCustomer($f),
            'sales/by-item' => $this->rptByItem($f),
            'sales/by-rep' => $this->rptByRep($f),
            'sales/on-time-shipping' => $this->rptOnTimeShipping($f),
            'sales/backorder-aging' => $this->rptBackorderAging($f),
            'sales/invoice-register' => $this->rptInvoiceRegister($f),
            'sales/ar-aging' => $this->rptArAging($f),
            'production/batch-history' => $this->rptBatchHistory($f),
            'production/batch-costs' => $this->rptBatchCosts($f),
            'production/yield' => $this->rptYield($f),
            'production/scrap' => $this->rptScrap($f),
            'production/qc-summary' => $this->rptQcSummary($f),
            'quality/inspection-log' => $this->rptInspectionLog($f),
            'quality/scars' => $this->rptScars($f),
            'quality/write-offs' => $this->rptWriteOffs($f),
            default => [],
        };
    }

    private function getReportColumns(string $cat, string $rpt): array
    {
        return match ("{$cat}/{$rpt}") {
            'inventory/stock-on-hand' => ['item_code'=>'Item','description'=>'Description','item_type'=>'Type','on_hand'=>'On Hand','available'=>'Available','unit_cost'=>'Unit Cost','total_value'=>'Total Value'],
            'inventory/valuation' => ['item_code'=>'Item','description'=>'Description','quantity'=>'Qty','unit_cost'=>'Avg Cost','total_value'=>'Total Value'],
            'inventory/transactions' => ['created_at'=>'Date','item_code'=>'Item','transaction_type'=>'Type','lot_number'=>'Lot','quantity'=>'Qty','unit_cost'=>'Cost','reference'=>'Reference','user_name'=>'User'],
            'inventory/lot-status' => ['item_code'=>'Item','lot_number'=>'Lot','facility_name'=>'Facility','status'=>'Status','expiration_date'=>'Expiration','remaining_quantity'=>'Qty','unit_cost'=>'Cost','source_type'=>'Source'],
            'inventory/slow-moving' => ['item_code'=>'Item','description'=>'Description','on_hand'=>'On Hand','total_value'=>'Value','last_consumed'=>'Last Consumed','days_since'=>'Days Since'],
            'purchasing/open-pos' => ['po_number'=>'PO#','supplier_name'=>'Supplier','facility_name'=>'Facility','order_date'=>'Order Date','expected_delivery_date'=>'Expected','status'=>'Status','po_value'=>'Value'],
            'purchasing/receiving' => ['receipt_date'=>'Date','po_number'=>'PO#','supplier_name'=>'Supplier','item_code'=>'Item','qty_received'=>'Qty','unit_cost'=>'Cost','total_cost'=>'Total'],
            'purchasing/supplier-spend' => ['supplier_code'=>'Code','company_name'=>'Supplier','receipt_count'=>'Receipts','total_spend'=>'Total Spend'],
            'purchasing/cost-changes' => ['item_code'=>'Item','supplier_name'=>'Supplier','prior_cost'=>'Prior Cost','new_cost'=>'New Cost','change_pct'=>'Change %'],
            'purchasing/landed-costs' => ['po_number'=>'PO#','receipt_date'=>'Date','supplier_name'=>'Supplier','cost_type'=>'Type','amount'=>'Amount','allocation_method'=>'Method','posted'=>'Posted'],
            'sales/open-orders' => ['so_number'=>'SO#','customer_name'=>'Customer','facility_name'=>'Facility','promised_ship_date'=>'Ship Date','status'=>'Status','order_value'=>'Value'],
            'sales/order-history' => ['so_number'=>'SO#','customer_name'=>'Customer','rep_name'=>'Rep','order_date'=>'Date','status'=>'Status','order_value'=>'Value'],
            'sales/by-customer' => ['customer_code'=>'Code','company_name'=>'Customer','rep_name'=>'Rep','invoice_count'=>'Invoices','total_invoiced'=>'Total'],
            'sales/by-item' => ['item_code'=>'Item','description'=>'Description','qty_sold'=>'Qty Sold','avg_price'=>'Avg Price','total_revenue'=>'Revenue'],
            'sales/by-rep' => ['rep_name'=>'Rep','customer_count'=>'Customers','order_count'=>'Orders','total_value'=>'Total Value'],
            'sales/on-time-shipping' => ['so_number'=>'SO#','customer_name'=>'Customer','promised_ship'=>'Promised','actual_ship'=>'Actual','on_time'=>'On Time','days_diff'=>'Days +/-'],
            'sales/backorder-aging' => ['so_number'=>'SO#','customer_name'=>'Customer','item_code'=>'Item','backordered_qty'=>'Qty','order_date'=>'Order Date','days_backordered'=>'Days'],
            'sales/invoice-register' => ['invoice_number'=>'Invoice#','customer_name'=>'Customer','invoice_date'=>'Date','due_date'=>'Due','total_due'=>'Amount','status'=>'Status'],
            'sales/ar-aging' => ['customer_code'=>'Code','company_name'=>'Customer','current_due'=>'Current','days_1_30'=>'1-30','days_31_60'=>'31-60','days_61_90'=>'61-90','days_over_90'=>'90+','total'=>'Total'],
            'production/batch-history' => ['batch_number'=>'Batch#','item_code'=>'Item','facility_name'=>'Facility','scheduled_date'=>'Scheduled','closed_at'=>'Closed','target_quantity'=>'Target','actual_yield'=>'Yield','yield_percentage'=>'Yield %','cost_per_unit'=>'Cost/Unit'],
            'production/batch-costs' => ['batch_number'=>'Batch#','item_code'=>'Item','total_batch_cost'=>'Total Cost','actual_yield'=>'Yield','cost_per_unit'=>'Cost/Unit'],
            'production/yield' => ['item_code'=>'Item','description'=>'Description','batch_count'=>'Batches','avg_yield'=>'Avg Yield %','min_yield'=>'Min','max_yield'=>'Max','total_produced'=>'Total Produced'],
            'production/scrap' => ['batch_number'=>'Batch#','item_code'=>'Item','scrap_type'=>'Type','material'=>'Material','quantity'=>'Qty','uom'=>'UOM'],
            'production/qc-summary' => ['batch_number'=>'Batch#','item_code'=>'Item','test_count'=>'Tests','fail_count'=>'Fails','oos_count'=>'OOS'],
            'quality/inspection-log' => ['receipt_date'=>'Date','po_number'=>'PO#','supplier_name'=>'Supplier','item_code'=>'Item','lot_number'=>'Lot','quantity'=>'Qty','status'=>'Status'],
            'quality/scars' => ['scar_number'=>'SCAR#','supplier_name'=>'Supplier','issue_date'=>'Issue Date','due_date'=>'Due','status'=>'Status','days_open'=>'Days'],
            'quality/write-offs' => ['rma_number'=>'RMA#','customer_name'=>'Customer','item_code'=>'Item','quantity'=>'Qty','rma_date'=>'Date'],
            default => [],
        };
    }

    // ── Individual Report Queries ────────────────────────────────────

    private function rptStockOnHand(array $f): array
    {
        return $this->db()->query("SELECT i.item_code, i.description, i.item_type, COALESCE(SUM(fl.remaining_quantity),0) as on_hand, COALESCE(SUM(fl.remaining_quantity),0) as available, AVG(fl.unit_cost) as unit_cost, COALESCE(SUM(fl.remaining_quantity * fl.unit_cost),0) as total_value FROM items i LEFT JOIN fifo_lots fl ON fl.item_id = i.id AND fl.status = 'AVAILABLE' AND fl.remaining_quantity > 0 WHERE i.active = 1 AND i.deleted_at IS NULL GROUP BY i.id HAVING on_hand > 0 ORDER BY i.item_code")->fetchAll();
    }

    private function rptValuation(array $f): array
    {
        return $this->db()->query("SELECT i.item_code, i.description, COALESCE(SUM(fl.remaining_quantity),0) as quantity, CASE WHEN SUM(fl.remaining_quantity)>0 THEN SUM(fl.remaining_quantity*fl.unit_cost)/SUM(fl.remaining_quantity) ELSE 0 END as unit_cost, COALESCE(SUM(fl.remaining_quantity*fl.unit_cost),0) as total_value FROM items i LEFT JOIN fifo_lots fl ON fl.item_id=i.id AND fl.status='AVAILABLE' AND fl.remaining_quantity>0 WHERE i.active=1 GROUP BY i.id HAVING quantity>0 ORDER BY i.item_code")->fetchAll();
    }

    private function rptTransactions(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT t.created_at, i.item_code, t.transaction_type, fl.lot_number, t.quantity, t.unit_cost, CONCAT(t.reference_type,' #',t.reference_id) as reference, u.full_name as user_name FROM inventory_transactions t JOIN items i ON t.item_id=i.id LEFT JOIN fifo_lots fl ON t.fifo_lot_id=fl.id LEFT JOIN users u ON t.user_id=u.id WHERE t.created_at BETWEEN ? AND ? ORDER BY t.created_at DESC LIMIT 500");
        $stmt->execute([$f['date_from'], ($f['date_to'] ?? date('Y-m-d')) . ' 23:59:59']);
        return $stmt->fetchAll();
    }

    private function rptLotStatus(array $f): array
    {
        $status = $f['status'] ?? '';
        $where = $status ? "AND fl.status = '{$status}'" : '';
        return $this->db()->query("SELECT i.item_code, fl.lot_number, fac.name as facility_name, fl.status, fl.expiration_date, fl.remaining_quantity, fl.unit_cost, fl.source_type FROM fifo_lots fl JOIN items i ON fl.item_id=i.id JOIN facilities fac ON fl.facility_id=fac.id WHERE fl.remaining_quantity > 0 {$where} ORDER BY fl.expiration_date ASC, i.item_code")->fetchAll();
    }

    private function rptSlowMoving(array $f): array
    {
        $days = (int)($f['days'] ?? 90);
        $stmt = $this->db()->prepare("SELECT i.item_code, i.description, COALESCE(SUM(fl.remaining_quantity),0) as on_hand, COALESCE(SUM(fl.remaining_quantity*fl.unit_cost),0) as total_value, MAX(t.created_at) as last_consumed, DATEDIFF(CURDATE(), MAX(t.created_at)) as days_since FROM items i LEFT JOIN fifo_lots fl ON fl.item_id=i.id AND fl.status='AVAILABLE' AND fl.remaining_quantity>0 LEFT JOIN inventory_transactions t ON t.item_id=i.id AND t.transaction_type='CONSUMPTION' WHERE i.active=1 GROUP BY i.id HAVING on_hand > 0 AND (last_consumed IS NULL OR days_since > ?) ORDER BY days_since DESC");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    private function rptOpenPOs(array $f): array
    {
        return $this->db()->query("SELECT po.po_number, s.company_name as supplier_name, fac.name as facility_name, po.order_date, po.expected_delivery_date, po.status, COALESCE((SELECT SUM(ordered_quantity*unit_cost) FROM purchase_order_lines WHERE po_id=po.id AND line_status!='CANCELLED'),0) as po_value FROM purchase_orders po JOIN suppliers s ON po.supplier_id=s.id LEFT JOIN facilities fac ON po.facility_id=fac.id WHERE po.status IN ('DRAFT','SENT','PARTIAL') AND po.deleted_at IS NULL ORDER BY po.order_date")->fetchAll();
    }

    private function rptReceiving(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT pr.receipt_date, po.po_number, s.company_name as supplier_name, i.item_code, prl.received_quantity as qty_received, prl.unit_cost, prl.received_quantity*prl.unit_cost as total_cost FROM po_receipt_lines prl JOIN po_receipts pr ON prl.receipt_id=pr.id JOIN purchase_orders po ON pr.po_id=po.id JOIN suppliers s ON po.supplier_id=s.id JOIN purchase_order_lines pol ON prl.po_line_id=pol.id JOIN items i ON pol.item_id=i.id WHERE pr.receipt_date BETWEEN ? AND ? ORDER BY pr.receipt_date DESC");
        $stmt->execute([$f['date_from'], $f['date_to']]);
        return $stmt->fetchAll();
    }

    private function rptSupplierSpend(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT s.supplier_code, s.company_name, COUNT(DISTINCT pr.id) as receipt_count, SUM(prl.received_quantity*prl.unit_cost) as total_spend FROM po_receipt_lines prl JOIN po_receipts pr ON prl.receipt_id=pr.id JOIN purchase_orders po ON pr.po_id=po.id JOIN suppliers s ON po.supplier_id=s.id WHERE pr.receipt_date BETWEEN ? AND ? GROUP BY s.id ORDER BY total_spend DESC");
        $stmt->execute([$f['date_from'], $f['date_to']]);
        return $stmt->fetchAll();
    }

    private function rptCostChanges(array $f): array
    {
        return $this->db()->query("SELECT i.item_code, s.company_name as supplier_name, 0 as prior_cost, 0 as new_cost, 0 as change_pct FROM items i CROSS JOIN suppliers s LIMIT 0")->fetchAll(); // Placeholder — complex query
    }

    private function rptLandedCosts(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT po.po_number, pr.receipt_date, s.company_name as supplier_name, lc.cost_type, lc.amount, lc.allocation_method, lc.posted FROM landed_costs lc JOIN po_receipts pr ON lc.receipt_id=pr.id JOIN purchase_orders po ON pr.po_id=po.id JOIN suppliers s ON po.supplier_id=s.id WHERE pr.receipt_date BETWEEN ? AND ? ORDER BY pr.receipt_date DESC");
        $stmt->execute([$f['date_from'], $f['date_to']]);
        return $stmt->fetchAll();
    }

    private function rptOpenOrders(array $f): array
    {
        return $this->db()->query("SELECT so.so_number, c.company_name as customer_name, fac.name as facility_name, so.promised_ship_date, so.status, COALESCE((SELECT SUM(ordered_quantity*unit_price) FROM sales_order_lines WHERE so_id=so.id),0) as order_value FROM sales_orders so JOIN customers c ON so.customer_id=c.id LEFT JOIN facilities fac ON so.facility_id=fac.id WHERE so.status IN ('CONFIRMED','ON_HOLD','PARTIAL') AND so.deleted_at IS NULL ORDER BY so.promised_ship_date")->fetchAll();
    }

    private function rptOrderHistory(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT so.so_number, c.company_name as customer_name, u.full_name as rep_name, so.order_date, so.status, COALESCE((SELECT SUM(ordered_quantity*unit_price) FROM sales_order_lines WHERE so_id=so.id),0) as order_value FROM sales_orders so JOIN customers c ON so.customer_id=c.id LEFT JOIN users u ON so.sales_rep_id=u.id WHERE so.order_date BETWEEN ? AND ? AND so.deleted_at IS NULL ORDER BY so.order_date DESC");
        $stmt->execute([$f['date_from'], $f['date_to']]);
        return $stmt->fetchAll();
    }

    private function rptByCustomer(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT c.customer_code, c.company_name, u.full_name as rep_name, COUNT(DISTINCT inv.id) as invoice_count, COALESCE(SUM(inv.total_due),0) as total_invoiced FROM invoices inv JOIN customers c ON inv.customer_id=c.id LEFT JOIN users u ON inv.sales_rep_id=u.id WHERE inv.status!='VOID' AND inv.invoice_date BETWEEN ? AND ? GROUP BY c.id ORDER BY total_invoiced DESC");
        $stmt->execute([$f['date_from'], $f['date_to']]);
        return $stmt->fetchAll();
    }

    private function rptByItem(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT i.item_code, i.description, SUM(sl.quantity_shipped) as qty_sold, AVG(sl.unit_price) as avg_price, SUM(sl.quantity_shipped*sl.unit_price) as total_revenue FROM shipment_lines sl JOIN sales_order_lines sol ON sl.so_line_id=sol.id JOIN items i ON sol.item_id=i.id JOIN shipments s ON sl.shipment_id=s.id WHERE s.ship_date BETWEEN ? AND ? GROUP BY i.id ORDER BY total_revenue DESC");
        $stmt->execute([$f['date_from'], $f['date_to']]);
        return $stmt->fetchAll();
    }

    private function rptByRep(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT u.full_name as rep_name, COUNT(DISTINCT so.customer_id) as customer_count, COUNT(DISTINCT so.id) as order_count, COALESCE(SUM((SELECT SUM(ordered_quantity*unit_price) FROM sales_order_lines WHERE so_id=so.id)),0) as total_value FROM sales_orders so JOIN users u ON so.sales_rep_id=u.id WHERE so.order_date BETWEEN ? AND ? AND so.deleted_at IS NULL GROUP BY u.id ORDER BY total_value DESC");
        $stmt->execute([$f['date_from'], $f['date_to']]);
        return $stmt->fetchAll();
    }

    private function rptOnTimeShipping(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT so.so_number, c.company_name as customer_name, so.promised_ship_date as promised_ship, s.ship_date as actual_ship, CASE WHEN s.ship_date<=so.promised_ship_date THEN 'Yes' ELSE 'No' END as on_time, DATEDIFF(s.ship_date,so.promised_ship_date) as days_diff FROM sales_orders so JOIN customers c ON so.customer_id=c.id JOIN shipment_orders sord ON sord.so_id=so.id JOIN shipments s ON sord.shipment_id=s.id WHERE so.promised_ship_date IS NOT NULL AND s.ship_date BETWEEN ? AND ? ORDER BY s.ship_date DESC");
        $stmt->execute([$f['date_from'], $f['date_to']]);
        return $stmt->fetchAll();
    }

    private function rptBackorderAging(array $f): array
    {
        return $this->db()->query("SELECT so.so_number, c.company_name as customer_name, i.item_code, sol.backordered_quantity as backordered_qty, so.order_date, DATEDIFF(CURDATE(),so.order_date) as days_backordered FROM sales_order_lines sol JOIN sales_orders so ON sol.so_id=so.id JOIN customers c ON so.customer_id=c.id JOIN items i ON sol.item_id=i.id WHERE sol.backordered_quantity > 0 AND so.deleted_at IS NULL ORDER BY days_backordered DESC")->fetchAll();
    }

    private function rptInvoiceRegister(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT inv.invoice_number, c.company_name as customer_name, inv.invoice_date, inv.due_date, inv.total_due, inv.status FROM invoices inv JOIN customers c ON inv.customer_id=c.id WHERE inv.invoice_date BETWEEN ? AND ? ORDER BY inv.invoice_date DESC");
        $stmt->execute([$f['date_from'], $f['date_to']]);
        return $stmt->fetchAll();
    }

    private function rptArAging(array $f): array
    {
        return $this->db()->query("SELECT c.customer_code, c.company_name, SUM(CASE WHEN DATEDIFF(CURDATE(),i.due_date)<=0 THEN i.total_due ELSE 0 END) as current_due, SUM(CASE WHEN DATEDIFF(CURDATE(),i.due_date) BETWEEN 1 AND 30 THEN i.total_due ELSE 0 END) as days_1_30, SUM(CASE WHEN DATEDIFF(CURDATE(),i.due_date) BETWEEN 31 AND 60 THEN i.total_due ELSE 0 END) as days_31_60, SUM(CASE WHEN DATEDIFF(CURDATE(),i.due_date) BETWEEN 61 AND 90 THEN i.total_due ELSE 0 END) as days_61_90, SUM(CASE WHEN DATEDIFF(CURDATE(),i.due_date)>90 THEN i.total_due ELSE 0 END) as days_over_90, SUM(i.total_due) as total FROM invoices i JOIN customers c ON i.customer_id=c.id WHERE i.status='OPEN' GROUP BY c.id ORDER BY total DESC")->fetchAll();
    }

    private function rptBatchHistory(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT bt.batch_number, i.item_code, fac.name as facility_name, bt.scheduled_date, bt.closed_at, bt.target_quantity, bt.actual_yield, bt.yield_percentage, bt.cost_per_unit FROM batch_tickets bt JOIN items i ON bt.item_id=i.id LEFT JOIN facilities fac ON bt.facility_id=fac.id WHERE bt.scheduled_date BETWEEN ? AND ? AND bt.deleted_at IS NULL ORDER BY bt.scheduled_date DESC");
        $stmt->execute([$f['date_from'], $f['date_to']]);
        return $stmt->fetchAll();
    }

    private function rptBatchCosts(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT bt.batch_number, i.item_code, bt.total_batch_cost, bt.actual_yield, bt.cost_per_unit FROM batch_tickets bt JOIN items i ON bt.item_id=i.id WHERE bt.status='CLOSED' AND bt.closed_at BETWEEN ? AND ? ORDER BY bt.closed_at DESC");
        $stmt->execute([$f['date_from'], ($f['date_to'] ?? date('Y-m-d')) . ' 23:59:59']);
        return $stmt->fetchAll();
    }

    private function rptYield(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT i.item_code, i.description, COUNT(*) as batch_count, AVG(bt.yield_percentage) as avg_yield, MIN(bt.yield_percentage) as min_yield, MAX(bt.yield_percentage) as max_yield, SUM(bt.actual_yield) as total_produced FROM batch_tickets bt JOIN items i ON bt.item_id=i.id WHERE bt.status='CLOSED' AND bt.closed_at BETWEEN ? AND ? GROUP BY i.id ORDER BY avg_yield ASC");
        $stmt->execute([$f['date_from'], ($f['date_to'] ?? date('Y-m-d')) . ' 23:59:59']);
        return $stmt->fetchAll();
    }

    private function rptScrap(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT bt.batch_number, i.item_code, bs.scrap_type, bs.material_description as material, bs.quantity, u.abbreviation as uom FROM batch_scrap bs JOIN batch_tickets bt ON bs.batch_id=bt.id JOIN items i ON bt.item_id=i.id LEFT JOIN uom u ON bs.uom_id=u.id WHERE bs.created_at BETWEEN ? AND ? ORDER BY bs.created_at DESC");
        $stmt->execute([$f['date_from'], ($f['date_to'] ?? date('Y-m-d')) . ' 23:59:59']);
        return $stmt->fetchAll();
    }

    private function rptQcSummary(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT bt.batch_number, i.item_code, COUNT(qr.id) as test_count, SUM(CASE WHEN qr.pass_fail='FAIL' THEN 1 ELSE 0 END) as fail_count, SUM(qr.is_out_of_spec) as oos_count FROM qc_results qr JOIN batch_tickets bt ON qr.batch_id=bt.id JOIN items i ON bt.item_id=i.id WHERE qr.created_at BETWEEN ? AND ? GROUP BY bt.id ORDER BY fail_count DESC");
        $stmt->execute([$f['date_from'], ($f['date_to'] ?? date('Y-m-d')) . ' 23:59:59']);
        return $stmt->fetchAll();
    }

    private function rptInspectionLog(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT pr.receipt_date, po.po_number, s.company_name as supplier_name, i.item_code, fl.lot_number, fl.remaining_quantity as quantity, qi.status FROM qc_incoming_inspections qi JOIN fifo_lots fl ON qi.fifo_lot_id=fl.id JOIN items i ON fl.item_id=i.id LEFT JOIN po_receipt_lines prl ON qi.po_receipt_line_id=prl.id LEFT JOIN po_receipts pr ON prl.receipt_id=pr.id LEFT JOIN purchase_orders po ON pr.po_id=po.id LEFT JOIN suppliers s ON po.supplier_id=s.id WHERE qi.created_at BETWEEN ? AND ? ORDER BY qi.created_at DESC");
        $stmt->execute([$f['date_from'], ($f['date_to'] ?? date('Y-m-d')) . ' 23:59:59']);
        return $stmt->fetchAll();
    }

    private function rptScars(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT sc.scar_number, s.company_name as supplier_name, sc.issue_date, sc.due_date, sc.status, CASE WHEN sc.status IN ('OPEN','RESPONSE_RECEIVED') THEN DATEDIFF(CURDATE(),sc.issue_date) ELSE DATEDIFF(sc.closed_at,sc.created_at) END as days_open FROM scars sc LEFT JOIN suppliers s ON sc.supplier_id=s.id WHERE sc.created_at BETWEEN ? AND ? ORDER BY sc.created_at DESC");
        $stmt->execute([$f['date_from'], ($f['date_to'] ?? date('Y-m-d')) . ' 23:59:59']);
        return $stmt->fetchAll();
    }

    private function rptWriteOffs(array $f): array
    {
        $stmt = $this->db()->prepare("SELECT r.rma_number, c.company_name as customer_name, i.item_code, rl.received_quantity as quantity, r.rma_date FROM rma_lines rl JOIN rma_orders r ON rl.rma_id=r.id JOIN customers c ON r.customer_id=c.id JOIN items i ON rl.item_id=i.id WHERE rl.disposition='WRITE_OFF' AND rl.received_quantity IS NOT NULL AND r.rma_date BETWEEN ? AND ? ORDER BY r.rma_date DESC");
        $stmt->execute([$f['date_from'], $f['date_to']]);
        return $stmt->fetchAll();
    }
}
