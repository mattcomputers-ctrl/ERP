<?php

namespace PrecisionInk\Controllers;

class ConsignmentController extends BaseController
{
    public function index(): void
    {
        if (!$this->checkPermission('consignment', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterStatus = $_GET['status'] ?? 'ACTIVE';
        $filterSearch = trim($_GET['q'] ?? '');

        $where = [];
        $params = [];
        if ($filterStatus) { $where[] = 'cp.status = ?'; $params[] = $filterStatus; }
        if ($filterSearch) { $where[] = '(cp.con_number LIKE ? OR c.company_name LIKE ? OR i.item_code LIKE ?)'; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM consignment_placements cp LEFT JOIN customers c ON cp.customer_id = c.id LEFT JOIN items i ON cp.item_id = i.id {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT cp.*, c.customer_code, c.company_name as customer_name,
                   i.item_code, i.description as item_description, i.reorder_min,
                   u.abbreviation as uom_abbr, f.name as facility_name,
                   COALESCE((SELECT SUM(cc.quantity_consumed) FROM consignment_consumption cc WHERE cc.placement_id = cp.id), 0) as total_consumed,
                   (SELECT MAX(cc.consumption_date) FROM consignment_consumption cc WHERE cc.placement_id = cp.id) as last_consumption
            FROM consignment_placements cp
            LEFT JOIN customers c ON cp.customer_id = c.id
            LEFT JOIN items i ON cp.item_id = i.id
            LEFT JOIN uom u ON cp.uom_id = u.id
            LEFT JOIN facilities f ON cp.facility_id = f.id
            {$whereClause}
            ORDER BY cp.placement_date DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $placements = $stmt->fetchAll();

        foreach ($placements as &$p) {
            $p['balance'] = (float)$p['quantity_placed'] - (float)$p['total_consumed'];
            $p['is_low'] = $p['reorder_min'] && $p['balance'] < (float)$p['reorder_min'];
        }
        unset($p);

        $this->renderView('consignment/list', [
            'placements' => $placements,
            'filters' => ['status' => $filterStatus, 'q' => $filterSearch],
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    public function createForm(): void
    {
        if (!$this->checkPermission('consignment', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);
        $active = $this->facilityService->getActiveFacility($userId);
        $uoms = $this->db()->query("SELECT id, abbreviation FROM uom WHERE active=1 ORDER BY abbreviation")->fetchAll();

        $this->renderView('consignment/create', [
            'facilities' => $facilities, 'activeFacilityId' => (int)($active['id'] ?? 0), 'uoms' => $uoms,
        ]);
    }

    public function store(): void
    {
        if (!$this->checkPermission('consignment', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $customerId = (int)($_POST['customer_id'] ?? 0);
        $shipToId = (int)($_POST['ship_to_id'] ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        $packExtId = (int)($_POST['pack_extension_id'] ?? 0) ?: null;
        $qty = (float)($_POST['quantity_placed'] ?? 0);
        $uomId = (int)($_POST['uom_id'] ?? 0);
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $placementDate = $_POST['placement_date'] ?? date('Y-m-d');

        if (!$customerId || !$itemId || $qty <= 0) {
            $this->toast('Customer, item, and quantity are required.', 'error');
            $this->redirect('/consignment/create');
            return;
        }

        $userId = $this->currentUserId();

        $this->db()->beginTransaction();
        try {
            $conNumber = $this->generateDocumentNumber('CONSIGNMENT');

            // Consume from regular inventory
            $this->fifoService->consume($itemId, $facilityId, $qty, 'CONSIGNMENT', 0, $userId);

            $this->db()->prepare("
                INSERT INTO consignment_placements (con_number, customer_id, ship_to_id, facility_id, item_id, pack_extension_id, quantity_placed, uom_id, placement_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE')
            ")->execute([$conNumber, $customerId, $shipToId ?: null, $facilityId, $itemId, $packExtId, $qty, $uomId, $placementDate]);
            $placementId = (int)$this->db()->lastInsertId();

            $this->db()->commit();
            $this->auditCreate('consignment_placements', $placementId, ['con_number' => $conNumber]);
            $this->toast("Consignment {$conNumber} created. Inventory deducted.", 'success');
            $this->redirect("/consignment/{$placementId}");
        } catch (\App\Exceptions\NegativeInventoryException $e) {
            $this->db()->rollBack();
            $this->toast('Insufficient inventory: ' . $e->getMessage(), 'error');
            $this->redirect('/consignment/create');
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect('/consignment/create');
        }
    }

    public function show(string $id): void
    {
        if (!$this->checkPermission('consignment', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $placement = $this->getOrFail((int)$id);

        $consumptions = $this->db()->prepare("
            SELECT cc.*, u.full_name as entered_by_name, inv.invoice_number
            FROM consignment_consumption cc
            LEFT JOIN users u ON cc.entered_by = u.id
            LEFT JOIN invoices inv ON cc.invoice_id = inv.id
            WHERE cc.placement_id = ?
            ORDER BY cc.consumption_date DESC
        ");
        $consumptions->execute([(int)$id]);

        $totalConsumed = $this->db()->prepare("SELECT COALESCE(SUM(quantity_consumed), 0) FROM consignment_consumption WHERE placement_id = ?");
        $totalConsumed->execute([(int)$id]);
        $consumed = (float)$totalConsumed->fetchColumn();

        $this->renderView('consignment/view', [
            'placement' => $placement,
            'consumptions' => $consumptions->fetchAll(),
            'totalConsumed' => $consumed,
            'balance' => (float)$placement['quantity_placed'] - $consumed,
        ]);
    }

    public function consume(string $id): void
    {
        if (!$this->checkPermission('consignment', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $placement = $this->getOrFail((int)$id);
        if ($placement['status'] !== 'ACTIVE') { $this->toast('Placement is closed.', 'error'); $this->redirect("/consignment/{$id}"); return; }

        $consumptionDate = $_POST['consumption_date'] ?? date('Y-m-d');
        $qty = (float)($_POST['quantity_consumed'] ?? 0);
        $customerRef = trim($_POST['customer_reference'] ?? '');

        if ($qty <= 0) { $this->toast('Quantity must be positive.', 'error'); $this->redirect("/consignment/{$id}"); return; }

        $userId = $this->currentUserId();

        $this->db()->beginTransaction();
        try {
            // Create consumption record
            $this->db()->prepare("
                INSERT INTO consignment_consumption (placement_id, consumption_date, quantity_consumed, customer_reference, entered_by)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([(int)$id, $consumptionDate, $qty, $customerRef ?: null, $userId]);
            $consumptionId = (int)$this->db()->lastInsertId();

            // Generate invoice for this consumption
            $pricing = new \App\Services\PricingService($this->db());
            $unitPrice = $pricing->resolvePrice((int)$placement['item_id'], (int)$placement['customer_id'], $qty);
            $subtotal = $qty * $unitPrice;

            $invoiceNumber = $this->generateDocumentNumber('INVOICE');
            $netDaysStmt = $this->db()->prepare("SELECT pt.net_days FROM customers c LEFT JOIN payment_terms pt ON c.payment_terms_id = pt.id WHERE c.id = ?");
            $netDaysStmt->execute([$placement['customer_id']]);
            $netDays = (int)($netDaysStmt->fetchColumn() ?: 30);
            $dueDate = date('Y-m-d', strtotime($consumptionDate . " + {$netDays} days"));

            $this->db()->prepare("
                INSERT INTO invoices (invoice_number, shipment_id, customer_id, invoice_date, due_date, subtotal, total_due, status, internal_notes)
                VALUES (?, 0, ?, ?, ?, ?, ?, 'OPEN', ?)
            ")->execute([
                $invoiceNumber, $placement['customer_id'], $consumptionDate, $dueDate,
                $subtotal, $subtotal, 'Consignment consumption: ' . $placement['con_number'],
            ]);
            $invoiceId = (int)$this->db()->lastInsertId();

            // Link invoice to consumption
            $this->db()->prepare("UPDATE consignment_consumption SET invoice_id = ? WHERE id = ?")->execute([$invoiceId, $consumptionId]);

            $this->db()->commit();
            $this->auditCreate('consignment_consumption', $consumptionId, ['placement' => $placement['con_number'], 'qty' => $qty]);
            $this->auditCreate('invoices', $invoiceId, ['invoice_number' => $invoiceNumber, 'consignment' => $placement['con_number']]);
            $this->toast("Consumption recorded. Invoice {$invoiceNumber} created (\$" . number_format($subtotal, 2) . ").", 'success');
            $this->redirect("/consignment/{$id}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/consignment/{$id}");
        }
    }

    public function close(string $id): void
    {
        if (!$this->checkPermission('consignment', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $placement = $this->getOrFail((int)$id);

        $this->db()->prepare("UPDATE consignment_placements SET status='CLOSED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'consignment_placements', (int)$id, ['status' => 'ACTIVE'], ['status' => 'CLOSED']);
        $this->toast("Consignment {$placement['con_number']} closed.", 'success');
        $this->redirect("/consignment/{$id}");
    }

    public function statement(string $id): void
    {
        if (!$this->checkPermission('consignment', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $placement = $this->getOrFail((int)$id);

        $consumptions = $this->db()->prepare("
            SELECT cc.*, inv.invoice_number, inv.total_due as invoice_amount
            FROM consignment_consumption cc
            LEFT JOIN invoices inv ON cc.invoice_id = inv.id
            WHERE cc.placement_id = ? ORDER BY cc.consumption_date
        ");
        $consumptions->execute([(int)$id]);
        $rows = $consumptions->fetchAll();

        $totalConsumed = array_sum(array_column($rows, 'quantity_consumed'));
        $balance = (float)$placement['quantity_placed'] - $totalConsumed;

        $lineRows = '';
        $totalInvoiced = 0;
        foreach ($rows as $r) {
            $invAmt = (float)($r['invoice_amount'] ?? 0);
            $totalInvoiced += $invAmt;
            $lineRows .= '<tr><td>' . date('M j, Y', strtotime($r['consumption_date'])) . '</td><td style="text-align:right;">' . number_format((float)$r['quantity_consumed'], 4) . '</td><td>' . htmlspecialchars($r['customer_reference'] ?? '') . '</td><td>' . htmlspecialchars($r['invoice_number'] ?? '—') . '</td><td style="text-align:right;">' . ($invAmt ? '$' . number_format($invAmt, 2) : '—') . '</td></tr>';
        }

        $html = '<!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif;font-size:12px;margin:30px;}h1{font-size:18px;}table{width:100%;border-collapse:collapse;margin:12px 0;}th,td{border:1px solid #ccc;padding:5px 8px;text-align:left;font-size:11px;}th{background:#f0f0f0;}.label{font-weight:bold;color:#555;font-size:10px;text-transform:uppercase;}</style></head><body>'
            . '<h1>CONSIGNMENT RECONCILIATION STATEMENT</h1>'
            . '<p><strong>Customer:</strong> ' . htmlspecialchars($placement['customer_name'] ?? '') . '</p>'
            . '<p><strong>Placement:</strong> ' . htmlspecialchars($placement['con_number']) . ' | <strong>Item:</strong> ' . htmlspecialchars($placement['item_code'] . ' — ' . $placement['item_description']) . '</p>'
            . '<p><strong>Placed:</strong> ' . number_format((float)$placement['quantity_placed'], 4) . ' ' . htmlspecialchars($placement['uom_abbr'] ?? '') . ' on ' . date('M j, Y', strtotime($placement['placement_date'])) . '</p>'
            . '<h3>Consumption Detail</h3>'
            . '<table><thead><tr><th>Date</th><th style="text-align:right;">Qty</th><th>Reference</th><th>Invoice</th><th style="text-align:right;">Amount</th></tr></thead><tbody>' . ($lineRows ?: '<tr><td colspan="5" style="text-align:center;color:#999;">No consumption recorded.</td></tr>') . '</tbody></table>'
            . '<p><strong>Total Consumed:</strong> ' . number_format($totalConsumed, 4) . ' | <strong>Current Balance:</strong> ' . number_format($balance, 4) . '</p>'
            . '<p><strong>Total Invoiced:</strong> $' . number_format($totalInvoiced, 2) . '</p>'
            . '<p style="margin-top:24px;font-style:italic;">Statement Date: ' . date('M j, Y') . ' — Please verify and confirm balance.</p>'
            . '</body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $dompdf->stream("CON_STMT_{$placement['con_number']}.pdf", ['Attachment' => false]);
        exit;
    }

    public function emailStatement(string $id): void
    {
        if (!$this->checkPermission('consignment', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $placement = $this->getOrFail((int)$id);

        // Reuse statement generation logic by capturing output
        ob_start();
        $this->statement($id);
        // statement() exits, so this won't run. Instead, generate PDF separately:
        ob_end_clean();

        // Generate PDF to temp file
        $consumptions = $this->db()->prepare("SELECT cc.*, inv.invoice_number, inv.total_due as invoice_amount FROM consignment_consumption cc LEFT JOIN invoices inv ON cc.invoice_id = inv.id WHERE cc.placement_id = ? ORDER BY cc.consumption_date");
        $consumptions->execute([(int)$id]);
        $rows = $consumptions->fetchAll();
        $totalConsumed = array_sum(array_column($rows, 'quantity_consumed'));
        $balance = (float)$placement['quantity_placed'] - $totalConsumed;

        $lineRows = '';
        foreach ($rows as $r) {
            $lineRows .= '<tr><td>' . date('M j, Y', strtotime($r['consumption_date'])) . '</td><td style="text-align:right;">' . number_format((float)$r['quantity_consumed'], 4) . '</td><td>' . htmlspecialchars($r['customer_reference'] ?? '') . '</td><td>' . htmlspecialchars($r['invoice_number'] ?? '—') . '</td></tr>';
        }

        $html = '<!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif;font-size:12px;margin:30px;}h1{font-size:18px;}table{width:100%;border-collapse:collapse;margin:12px 0;}th,td{border:1px solid #ccc;padding:5px 8px;text-align:left;}th{background:#f0f0f0;}</style></head><body><h1>CONSIGNMENT RECONCILIATION STATEMENT</h1><p><strong>' . htmlspecialchars($placement['con_number']) . '</strong> | ' . htmlspecialchars($placement['customer_name'] ?? '') . '</p><p>Item: ' . htmlspecialchars($placement['item_code'] ?? '') . ' | Placed: ' . number_format((float)$placement['quantity_placed'], 4) . ' | Balance: ' . number_format($balance, 4) . '</p><table><thead><tr><th>Date</th><th>Qty</th><th>Reference</th><th>Invoice</th></tr></thead><tbody>' . $lineRows . '</tbody></table></body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $pdfPath = sys_get_temp_dir() . "/CON_STMT_{$placement['con_number']}.pdf";
        file_put_contents($pdfPath, $dompdf->output());

        $contactStmt = $this->db()->prepare("SELECT email FROM customer_contacts WHERE customer_id = ? AND active = 1 AND email IS NOT NULL ORDER BY FIELD(contact_type, 'BILLING', 'GENERAL') ASC, is_primary DESC LIMIT 1");
        $contactStmt->execute([$placement['customer_id']]);
        $email = $contactStmt->fetchColumn();

        if ($email && $this->emailService) {
            $this->emailService->send('consignment_reconciliation', [$email],
                ['con_number' => $placement['con_number'], 'customer_name' => $placement['customer_name'] ?? ''],
                $pdfPath, "CON_STMT_{$placement['con_number']}.pdf", 'consignment', (int)$id);
        }
        @unlink($pdfPath);
        $this->toast("Statement emailed." . (!$email ? ' No contact found.' : ''), $email ? 'success' : 'warning');
        $this->redirect("/consignment/{$id}");
    }

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT cp.*, c.customer_code, c.company_name as customer_name,
                   i.item_code, i.description as item_description, i.reorder_min,
                   u.abbreviation as uom_abbr, f.name as facility_name
            FROM consignment_placements cp
            LEFT JOIN customers c ON cp.customer_id = c.id
            LEFT JOIN items i ON cp.item_id = i.id
            LEFT JOIN uom u ON cp.uom_id = u.id
            LEFT JOIN facilities f ON cp.facility_id = f.id
            WHERE cp.id = ?
        ");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if (!$p) { http_response_code(404); echo 'Consignment placement not found.'; exit; }
        return $p;
    }
}
