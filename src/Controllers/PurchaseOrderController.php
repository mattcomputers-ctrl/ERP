<?php

namespace PrecisionInk\Controllers;

class PurchaseOrderController extends BaseController
{
    // ── List ────────────────────────────────────────────────────────

    public function index(): void
    {
        if (!$this->checkPermission('purchase_orders', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterStatus = $_GET['status'] ?? '';
        $filterSupplier = trim($_GET['supplier'] ?? '');
        $filterFacility = (int)($_GET['facility_id'] ?? 0);
        $filterDateFrom = $_GET['date_from'] ?? '';
        $filterDateTo = $_GET['date_to'] ?? '';

        $where = ['po.deleted_at IS NULL'];
        $params = [];

        if ($filterStatus) { $where[] = 'po.status = ?'; $params[] = $filterStatus; }
        if ($filterSupplier) { $where[] = '(s.supplier_code LIKE ? OR s.company_name LIKE ?)'; $params[] = "%{$filterSupplier}%"; $params[] = "%{$filterSupplier}%"; }
        if ($filterFacility) { $where[] = 'po.facility_id = ?'; $params[] = $filterFacility; }
        if ($filterDateFrom) { $where[] = 'po.order_date >= ?'; $params[] = $filterDateFrom; }
        if ($filterDateTo) { $where[] = 'po.order_date <= ?'; $params[] = $filterDateTo; }

        $whereClause = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id = s.id WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT po.*, s.supplier_code, s.company_name as supplier_name,
                   f.name as facility_name,
                   (SELECT COUNT(*) FROM purchase_order_lines WHERE po_id = po.id) as line_count,
                   (SELECT COALESCE(SUM(ordered_quantity * unit_cost), 0) FROM purchase_order_lines WHERE po_id = po.id AND line_status != 'CANCELLED') as po_value
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN facilities f ON po.facility_id = f.id
            WHERE {$whereClause}
            ORDER BY po.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);

        $this->renderView('purchase_orders/list', [
            'orders' => $orders,
            'facilities' => $facilities,
            'filters' => ['status' => $filterStatus, 'supplier' => $filterSupplier, 'facility_id' => $filterFacility, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo],
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    // ── Create ──────────────────────────────────────────────────────

    public function create(): void
    {
        if (!$this->checkPermission('purchase_orders', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->renderView('purchase_orders/edit', $this->formData('create'));
    }

    public function store(): void
    {
        if (!$this->checkPermission('purchase_orders', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $data = $this->extractHeader();
        $lines = $this->extractLines();

        if (!$data['supplier_id']) {
            $this->toast('Supplier is required.', 'error');
            $this->renderView('purchase_orders/edit', $this->formData('create', $data, $lines));
            return;
        }
        if (empty($lines)) {
            $this->toast('At least one line item is required.', 'error');
            $this->renderView('purchase_orders/edit', $this->formData('create', $data, $lines));
            return;
        }

        $poNumber = $this->generateDocumentNumber('PURCHASE_ORDER');
        $userId = $this->currentUserId();

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("
                INSERT INTO purchase_orders (po_number, po_type, supplier_id, facility_id, order_date,
                    expected_delivery_date, notes, shipping_instructions, contract_start_date, contract_end_date,
                    contracted_quantity, contracted_value, status, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT', ?, NOW(), NOW())
            ")->execute([
                $poNumber, $data['po_type'], $data['supplier_id'], $data['facility_id'],
                $data['order_date'], $data['expected_delivery_date'] ?: null,
                $data['notes'] ?: null, $data['shipping_instructions'] ?: null,
                $data['contract_start_date'] ?: null, $data['contract_end_date'] ?: null,
                $data['contracted_quantity'] ?: null, $data['contracted_value'] ?: null, $userId,
            ]);
            $poId = (int)$this->db()->lastInsertId();

            $this->insertLines($poId, $lines);
            $this->db()->commit();

            $this->customFieldService->saveValues('purchase_orders', $poId, $_POST);
            $this->auditCreate('purchase_orders', $poId, ['po_number' => $poNumber]);
            $this->toast("Purchase order {$poNumber} created.", 'success');
            $this->redirect("/purchase-orders/{$poId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect('/purchase-orders/create');
        }
    }

    // ── View ────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        if (!$this->checkPermission('purchase_orders', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $po = $this->getOrFail((int)$id);
        $lines = $this->getLines((int)$id);

        $receipts = $this->db()->prepare("
            SELECT pr.*, u.full_name as received_by_name,
                   (SELECT COUNT(*) FROM po_receipt_lines WHERE receipt_id = pr.id) as line_count
            FROM po_receipts pr
            LEFT JOIN users u ON pr.received_by = u.id
            WHERE pr.po_id = ?
            ORDER BY pr.receipt_date DESC
        ");
        $receipts->execute([(int)$id]);
        $receipts = $receipts->fetchAll();

        $landedCosts = $this->db()->prepare("
            SELECT lc.*, pr.supplier_invoice_number
            FROM landed_costs lc
            JOIN po_receipts pr ON lc.receipt_id = pr.id
            WHERE pr.po_id = ?
            ORDER BY lc.created_at DESC
        ");
        $landedCosts->execute([(int)$id]);
        $landedCosts = $landedCosts->fetchAll();

        $revisions = $this->db()->prepare("
            SELECT prh.*, u.full_name as changed_by_name
            FROM po_revision_history prh
            LEFT JOIN users u ON prh.changed_by = u.id
            WHERE prh.po_id = ?
            ORDER BY prh.revision_number DESC
        ");
        $revisions->execute([(int)$id]);
        $revisions = $revisions->fetchAll();

        $attachments = [];
        if ($this->attachmentService) {
            $attachments = $this->attachmentService->getForRecord('purchase_order', (int)$id);
        }

        $this->renderView('purchase_orders/view', [
            'po' => $po, 'lines' => $lines, 'receipts' => $receipts,
            'landedCosts' => $landedCosts, 'revisions' => $revisions,
            'attachments' => $attachments, 'record' => $po,
        ]);
    }

    // ── Edit ────────────────────────────────────────────────────────

    public function editForm(string $id): void
    {
        if (!$this->checkPermission('purchase_orders', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $po = $this->getOrFail((int)$id);
        if (!in_array($po['status'], ['DRAFT', 'SENT'])) {
            $this->toast('Only DRAFT or SENT purchase orders can be edited.', 'error');
            $this->redirect("/purchase-orders/{$id}");
            return;
        }

        $lines = $this->getLines((int)$id);
        $this->renderView('purchase_orders/edit', $this->formData('edit', $po, $lines));
    }

    public function update(string $id): void
    {
        if (!$this->checkPermission('purchase_orders', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $po = $this->getOrFail((int)$id);
        if (!in_array($po['status'], ['DRAFT', 'SENT'])) {
            $this->toast('Only DRAFT or SENT purchase orders can be edited.', 'error');
            $this->redirect("/purchase-orders/{$id}");
            return;
        }

        $data = $this->extractHeader();
        $lines = $this->extractLines();

        if (!$data['supplier_id'] || empty($lines)) {
            $this->toast('Supplier and at least one line required.', 'error');
            $this->renderView('purchase_orders/edit', $this->formData('edit', array_merge($po, $data), $lines));
            return;
        }

        $this->db()->beginTransaction();
        try {
            // If SENT status, create revision history
            if ($po['status'] === 'SENT') {
                $newRevision = (int)$po['revision_number'] + 1;
                $diff = $this->computeDiff($po, $data, $this->getLines((int)$id), $lines);

                $this->db()->prepare("
                    INSERT INTO po_revision_history (po_id, revision_number, changed_by, field_changes, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ")->execute([(int)$id, $newRevision, $this->currentUserId(), json_encode($diff)]);

                $this->db()->prepare("UPDATE purchase_orders SET revision_number = ? WHERE id = ?")
                    ->execute([$newRevision, (int)$id]);
            }

            $this->db()->prepare("
                UPDATE purchase_orders SET po_type=?, supplier_id=?, facility_id=?, order_date=?,
                    expected_delivery_date=?, notes=?, shipping_instructions=?,
                    contract_start_date=?, contract_end_date=?, contracted_quantity=?, contracted_value=?,
                    updated_at=NOW()
                WHERE id=?
            ")->execute([
                $data['po_type'], $data['supplier_id'], $data['facility_id'],
                $data['order_date'], $data['expected_delivery_date'] ?: null,
                $data['notes'] ?: null, $data['shipping_instructions'] ?: null,
                $data['contract_start_date'] ?: null, $data['contract_end_date'] ?: null,
                $data['contracted_quantity'] ?: null, $data['contracted_value'] ?: null, (int)$id,
            ]);

            // Replace lines (only non-received, non-cancelled)
            $this->db()->prepare("DELETE FROM purchase_order_lines WHERE po_id = ? AND line_status IN ('OPEN')")->execute([(int)$id]);
            $this->insertLines((int)$id, $lines);

            $this->db()->commit();

            $this->customFieldService->saveValues('purchase_orders', (int)$id, $_POST);
            $this->auditUpdate('purchase_orders', (int)$id, $po, $data);
            $this->toast('Purchase order updated.', 'success');
            $this->redirect("/purchase-orders/{$id}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/purchase-orders/{$id}/edit");
        }
    }

    // ── Cancel ──────────────────────────────────────────────────────

    public function cancel(string $id): void
    {
        if (!$this->checkPermission('purchase_orders', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $po = $this->getOrFail((int)$id);
        if (in_array($po['status'], ['RECEIVED', 'CANCELLED'])) {
            $this->toast('Cannot cancel this PO.', 'error');
            $this->redirect("/purchase-orders/{$id}");
            return;
        }

        $reason = trim($_POST['cancellation_reason'] ?? '');
        if (!$reason) {
            $this->toast('Cancellation reason is required.', 'error');
            $this->redirect("/purchase-orders/{$id}");
            return;
        }

        $this->db()->prepare("UPDATE purchase_orders SET status='CANCELLED', notes=CONCAT(COALESCE(notes,''), '\n\nCancelled: ', ?), updated_at=NOW() WHERE id=?")
            ->execute([$reason, (int)$id]);
        $this->db()->prepare("UPDATE purchase_order_lines SET line_status='CANCELLED' WHERE po_id=? AND line_status='OPEN'")->execute([(int)$id]);

        $this->auditLog('UPDATE', 'purchase_orders', (int)$id, ['status' => $po['status']], ['status' => 'CANCELLED', 'reason' => $reason]);
        $this->toast("PO {$po['po_number']} cancelled.", 'success');
        $this->redirect("/purchase-orders/{$id}");
    }

    // ── Cancel Line ─────────────────────────────────────────────────

    public function cancelLine(string $id, string $lineId): void
    {
        if (!$this->checkPermission('purchase_orders', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $po = $this->getOrFail((int)$id);
        $reason = trim($_POST['cancellation_reason'] ?? '');
        if (!$reason) {
            $this->toast('Cancellation reason is required.', 'error');
            $this->redirect("/purchase-orders/{$id}");
            return;
        }

        $this->db()->prepare("UPDATE purchase_order_lines SET line_status='CANCELLED', cancellation_reason=?, updated_at=NOW() WHERE id=? AND po_id=?")
            ->execute([$reason, (int)$lineId, (int)$id]);

        // Recalculate PO status
        $this->recalculatePOStatus((int)$id);

        $this->auditLog('UPDATE', 'purchase_order_lines', (int)$lineId, ['line_status' => 'OPEN'], ['line_status' => 'CANCELLED']);
        $this->toast('Line cancelled.', 'success');
        $this->redirect("/purchase-orders/{$id}");
    }

    // ── Clone ───────────────────────────────────────────────────────

    public function clonePO(string $id): void
    {
        if (!$this->checkPermission('purchase_orders', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $po = $this->getOrFail((int)$id);
        $lines = $this->getLines((int)$id);

        $poNumber = $this->generateDocumentNumber('PURCHASE_ORDER');
        $userId = $this->currentUserId();

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("
                INSERT INTO purchase_orders (po_number, po_type, supplier_id, facility_id, order_date,
                    expected_delivery_date, notes, shipping_instructions, contract_start_date, contract_end_date,
                    contracted_quantity, contracted_value, status, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, 'DRAFT', ?, NOW(), NOW())
            ")->execute([
                $poNumber, $po['po_type'], $po['supplier_id'], $po['facility_id'],
                $po['expected_delivery_date'], $po['notes'], $po['shipping_instructions'],
                $po['contract_start_date'], $po['contract_end_date'],
                $po['contracted_quantity'], $po['contracted_value'], $userId,
            ]);
            $newId = (int)$this->db()->lastInsertId();

            $lineInsert = $this->db()->prepare("
                INSERT INTO purchase_order_lines (po_id, item_id, pack_extension_id, ordered_quantity, uom_id, unit_cost, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($lines as $l) {
                if ($l['line_status'] === 'CANCELLED') continue;
                $lineInsert->execute([$newId, $l['item_id'], $l['pack_extension_id'], $l['ordered_quantity'], $l['uom_id'], $l['unit_cost'], $l['notes']]);
            }

            $this->db()->commit();
            $this->auditCreate('purchase_orders', $newId, ['po_number' => $poNumber, 'cloned_from' => $po['po_number']]);
            $this->toast("Cloned as {$poNumber}.", 'success');
            $this->redirect("/purchase-orders/{$newId}/edit");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/purchase-orders/{$id}");
        }
    }

    // ── Line Cost Lookup (AJAX) ─────────────────────────────────────

    public function lineCost(): void
    {
        $itemId = (int)($_GET['item_id'] ?? 0);
        $supplierId = (int)($_GET['supplier_id'] ?? 0);
        if (!$itemId || !$supplierId) { $this->jsonResponse(['unit_cost' => null]); return; }

        $stmt = $this->db()->prepare("
            SELECT approved_unit_cost FROM approved_vendor_list
            WHERE item_id = ? AND supplier_id = ? AND active = 1
            ORDER BY is_preferred DESC LIMIT 1
        ");
        $stmt->execute([$itemId, $supplierId]);
        $cost = $stmt->fetchColumn();
        $this->jsonResponse(['unit_cost' => $cost !== false ? (float)$cost : null]);
    }

    // ── Send ────────────────────────────────────────────────────────

    public function send(string $id): void
    {
        if (!$this->checkPermission('purchase_orders', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $po = $this->getOrFail((int)$id);
        if ($po['status'] !== 'DRAFT') {
            $this->toast('Only DRAFT POs can be sent.', 'error');
            $this->redirect("/purchase-orders/{$id}");
            return;
        }

        $lines = $this->getLines((int)$id);

        // Find supplier contact email
        $contactStmt = $this->db()->prepare("
            SELECT email FROM supplier_contacts
            WHERE supplier_id = ? AND active = 1 AND email IS NOT NULL AND email != ''
            ORDER BY FIELD(contact_type, 'SALES', 'GENERAL') ASC, is_primary DESC
            LIMIT 1
        ");
        $contactStmt->execute([$po['supplier_id']]);
        $contactEmail = $contactStmt->fetchColumn();

        // Generate PDF
        $html = $this->buildPoPdfHtml($po, $lines);
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();

        $pdfDir = sys_get_temp_dir();
        $pdfPath = $pdfDir . '/' . $po['po_number'] . '.pdf';
        file_put_contents($pdfPath, $pdfContent);

        // Store as attachment
        if ($this->attachmentService) {
            $fakeFile = [
                'name' => $po['po_number'] . '.pdf',
                'type' => 'application/pdf',
                'tmp_name' => $pdfPath,
                'error' => 0,
                'size' => strlen($pdfContent),
            ];
            try {
                $this->attachmentService->upload('purchase_order', (int)$id, $fakeFile, 'PO PDF sent to supplier', $this->currentUserId());
            } catch (\Throwable $e) {
                // Non-fatal — continue even if attachment storage fails
            }
        }

        // Email to supplier
        if ($contactEmail && $this->emailService) {
            $revSuffix = $po['revision_number'] > 0 ? ' Rev ' . $po['revision_number'] : '';
            $this->emailService->send(
                'purchase_order',
                [$contactEmail],
                [
                    'po_number' => $po['po_number'] . $revSuffix,
                    'supplier_name' => $po['supplier_name'],
                    'order_date' => $po['order_date'],
                    'expected_delivery' => $po['expected_delivery_date'] ?? 'TBD',
                    'facility_name' => $po['facility_name'],
                ],
                $pdfPath,
                $po['po_number'] . '.pdf',
                'purchase_order',
                (int)$id
            );
        }

        // Update status
        $this->db()->prepare("UPDATE purchase_orders SET status='SENT', updated_at=NOW() WHERE id=?")->execute([(int)$id]);

        @unlink($pdfPath);

        $this->auditLog('UPDATE', 'purchase_orders', (int)$id, ['status' => 'DRAFT'], ['status' => 'SENT']);
        $msg = "PO {$po['po_number']} sent.";
        if (!$contactEmail) $msg .= ' Warning: no supplier contact email found — PDF saved as attachment only.';
        $this->toast($msg, $contactEmail ? 'success' : 'warning');
        $this->redirect("/purchase-orders/{$id}");
    }

    // ── Receive ─────────────────────────────────────────────────────

    public function receiveForm(string $id): void
    {
        if (!$this->checkPermission('purchase_orders', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $po = $this->getOrFail((int)$id);
        if (!in_array($po['status'], ['SENT', 'PARTIAL'])) {
            $this->toast('Only SENT or PARTIAL POs can receive shipments.', 'error');
            $this->redirect("/purchase-orders/{$id}");
            return;
        }

        // Load open/partial lines with item details (including shelf_life_days and requires_inspection)
        $stmt = $this->db()->prepare("
            SELECT pol.*, i.item_code, i.description as item_description,
                   i.shelf_life_days, i.requires_inspection,
                   u.abbreviation as uom_abbr, pe.name as pack_name
            FROM purchase_order_lines pol
            JOIN items i ON pol.item_id = i.id
            LEFT JOIN uom u ON pol.uom_id = u.id
            LEFT JOIN item_pack_extensions pe ON pol.pack_extension_id = pe.id
            WHERE pol.po_id = ? AND pol.line_status IN ('OPEN','PARTIAL')
            ORDER BY pol.id
        ");
        $stmt->execute([(int)$id]);
        $lines = $stmt->fetchAll();

        // Add remaining qty
        foreach ($lines as &$l) {
            $l['remaining'] = (float)$l['ordered_quantity'] - (float)$l['received_quantity'];
        }
        unset($l);

        $this->renderView('purchase_orders/receive', [
            'po' => $po,
            'lines' => $lines,
        ]);
    }

    public function receive(string $id): void
    {
        if (!$this->checkPermission('purchase_orders', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $po = $this->getOrFail((int)$id);
        if (!in_array($po['status'], ['SENT', 'PARTIAL'])) {
            $this->toast('Only SENT or PARTIAL POs can receive shipments.', 'error');
            $this->redirect("/purchase-orders/{$id}");
            return;
        }

        $supplierInvoice = trim($_POST['supplier_invoice_number'] ?? '');
        $receiptDate = $_POST['receipt_date'] ?? date('Y-m-d');
        $receiptNotes = trim($_POST['notes'] ?? '');
        $lineData = $_POST['recv'] ?? [];

        if (!$supplierInvoice) {
            $this->toast('Supplier invoice number is required.', 'error');
            $this->redirect("/purchase-orders/{$id}/receive");
            return;
        }

        $userId = $this->currentUserId();

        $this->db()->beginTransaction();
        try {
            // Create receipt header
            $this->db()->prepare("
                INSERT INTO po_receipts (po_id, facility_id, received_by, receipt_date, supplier_invoice_number, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([(int)$id, $po['facility_id'], $userId, $receiptDate, $supplierInvoice, $receiptNotes ?: null]);
            $receiptId = (int)$this->db()->lastInsertId();

            foreach ($lineData as $poLineId => $ld) {
                $receivedQty = (float)($ld['received_quantity'] ?? 0);
                if ($receivedQty <= 0) continue;

                $unitCost = (float)($ld['unit_cost'] ?? 0);
                $cocReceived = isset($ld['coc_received']) ? 1 : 0;
                $cocRef = trim($ld['coc_reference'] ?? '') ?: null;
                $cocDate = $ld['coc_date'] ?? null ?: null;
                $discrepancyNote = trim($ld['discrepancy_note'] ?? '') ?: null;

                // Get the PO line to know item details
                $polStmt = $this->db()->prepare("SELECT pol.*, i.shelf_life_days, i.requires_inspection FROM purchase_order_lines pol JOIN items i ON pol.item_id = i.id WHERE pol.id = ?");
                $polStmt->execute([(int)$poLineId]);
                $poLine = $polStmt->fetch();
                if (!$poLine) continue;

                // Create receipt line
                $this->db()->prepare("
                    INSERT INTO po_receipt_lines (receipt_id, po_line_id, received_quantity, unit_cost, coc_received, coc_reference, coc_date, discrepancy_note)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$receiptId, (int)$poLineId, $receivedQty, $unitCost, $cocReceived, $cocRef, $cocDate, $discrepancyNote]);
                $receiptLineId = (int)$this->db()->lastInsertId();

                // Process lots
                $lots = $ld['lots'] ?? [];
                foreach ($lots as $lot) {
                    $lotNumber = trim($lot['supplier_lot_number'] ?? '');
                    $lotQty = (float)($lot['quantity'] ?? 0);
                    $expDate = $lot['expiration_date'] ?? null ?: null;

                    if (!$lotNumber || $lotQty <= 0) continue;

                    // Auto-calculate expiration if item has shelf_life_days and no manual date
                    if (!$expDate && $poLine['shelf_life_days']) {
                        $expDate = date('Y-m-d', strtotime($receiptDate . ' + ' . (int)$poLine['shelf_life_days'] . ' days'));
                    }

                    // Determine FIFO lot status
                    $lotStatus = $poLine['requires_inspection'] ? 'PENDING_INSPECTION' : 'AVAILABLE';

                    // Create FIFO lot
                    $fifoLotId = $this->fifoService->addLot(
                        (int)$poLine['item_id'], (int)$po['facility_id'],
                        $lotQty, $unitCost, $lotNumber,
                        'RECEIPT', $receiptId, $expDate, $lotStatus, $userId,
                        $poLine['pack_extension_id'] ? (int)$poLine['pack_extension_id'] : null
                    );

                    // Create receipt lot record
                    $this->db()->prepare("
                        INSERT INTO po_receipt_lots (receipt_line_id, supplier_lot_number, quantity, expiration_date, fifo_lot_id)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$receiptLineId, $lotNumber, $lotQty, $expDate, $fifoLotId]);

                    // Create inspection record if needed
                    if ($lotStatus === 'PENDING_INSPECTION') {
                        $this->db()->prepare("
                            INSERT INTO qc_incoming_inspections (fifo_lot_id, po_receipt_line_id, status)
                            VALUES (?, ?, 'PENDING')
                        ")->execute([$fifoLotId, $receiptLineId]);
                    }
                }

                // Update PO line received quantity and status
                $newReceived = (float)$poLine['received_quantity'] + $receivedQty;
                $newLineStatus = $newReceived >= (float)$poLine['ordered_quantity'] ? 'RECEIVED' : 'PARTIAL';

                $this->db()->prepare("
                    UPDATE purchase_order_lines SET received_quantity = ?, line_status = ?, updated_at = NOW()
                    WHERE id = ?
                ")->execute([$newReceived, $newLineStatus, (int)$poLineId]);
            }

            // Recalculate PO status
            $this->recalculatePOStatus((int)$id);

            $this->db()->commit();

            $this->auditCreate('po_receipts', $receiptId, [
                'po_id' => (int)$id,
                'po_number' => $po['po_number'],
                'supplier_invoice' => $supplierInvoice,
            ]);
            $this->toast('Receipt posted successfully.', 'success');
            $this->redirect("/purchase-orders/{$id}#receipts");
        } catch (\App\Exceptions\NegativeInventoryException $e) {
            $this->db()->rollBack();
            $this->toast('Inventory error: ' . $e->getMessage(), 'error');
            $this->redirect("/purchase-orders/{$id}/receive");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/purchase-orders/{$id}/receive");
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function buildPoPdfHtml(array $po, array $lines): string
    {
        $poValue = 0;
        $lineRows = '';
        $n = 1;
        foreach ($lines as $l) {
            if ($l['line_status'] === 'CANCELLED') continue;
            $lt = (float)$l['ordered_quantity'] * (float)$l['unit_cost'];
            $poValue += $lt;
            $lineRows .= '<tr>'
                . '<td>' . $n++ . '</td>'
                . '<td>' . htmlspecialchars($l['item_code']) . '</td>'
                . '<td>' . htmlspecialchars($l['item_description']) . '</td>'
                . '<td>' . htmlspecialchars($l['pack_name'] ?? '') . '</td>'
                . '<td style="text-align:right;">' . number_format((float)$l['ordered_quantity'], 4) . '</td>'
                . '<td>' . htmlspecialchars($l['uom_abbr'] ?? '') . '</td>'
                . '<td style="text-align:right;">$' . number_format((float)$l['unit_cost'], 4) . '</td>'
                . '<td style="text-align:right;">$' . number_format($lt, 2) . '</td>'
                . '</tr>';
        }

        $revLabel = $po['revision_number'] > 0 ? ' Rev ' . $po['revision_number'] : '';

        return '<!DOCTYPE html><html><head><style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 30px; }
            h1 { font-size: 20px; margin-bottom: 4px; }
            .meta { margin-bottom: 16px; color: #555; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; font-size: 11px; }
            th { background: #f0f0f0; }
            .total-row td { font-weight: bold; border-top: 2px solid #333; }
            .header-grid { display: flex; justify-content: space-between; margin-bottom: 16px; }
            .header-block { width: 48%; }
            .header-block p { margin: 2px 0; }
            .label { font-weight: bold; color: #555; font-size: 10px; text-transform: uppercase; }
        </style></head><body>
            <h1>Purchase Order ' . htmlspecialchars($po['po_number']) . $revLabel . '</h1>
            <div class="meta">Order Date: ' . date('M j, Y', strtotime($po['order_date'])) . '
                | Expected Delivery: ' . ($po['expected_delivery_date'] ? date('M j, Y', strtotime($po['expected_delivery_date'])) : 'TBD') . '</div>
            <table style="border:none; margin-bottom:16px;"><tr>
                <td style="border:none; vertical-align:top; width:50%;">
                    <p class="label">Supplier</p>
                    <p><strong>' . htmlspecialchars($po['supplier_name']) . '</strong></p>
                    <p>' . htmlspecialchars($po['supplier_code']) . '</p>
                </td>
                <td style="border:none; vertical-align:top; width:50%;">
                    <p class="label">Ship To Facility</p>
                    <p><strong>' . htmlspecialchars($po['facility_name']) . '</strong></p>
                </td>
            </tr></table>'
            . ($po['shipping_instructions'] ? '<p><strong>Shipping Instructions:</strong> ' . htmlspecialchars($po['shipping_instructions']) . '</p>' : '')
            . '<table><thead><tr><th>#</th><th>Item Code</th><th>Description</th><th>Pack</th><th style="text-align:right;">Qty</th><th>UOM</th><th style="text-align:right;">Unit Cost</th><th style="text-align:right;">Total</th></tr></thead><tbody>'
            . $lineRows
            . '<tr class="total-row"><td colspan="7" style="text-align:right;">Total</td><td style="text-align:right;">$' . number_format($poValue, 2) . '</td></tr>'
            . '</tbody></table>'
            . ($po['notes'] ? '<p style="margin-top:12px;"><strong>Notes:</strong> ' . htmlspecialchars($po['notes']) . '</p>' : '')
            . '</body></html>';
    }

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT po.*, s.supplier_code, s.company_name as supplier_name,
                   f.name as facility_name, u.full_name as created_by_name
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN facilities f ON po.facility_id = f.id
            LEFT JOIN users u ON po.created_by = u.id
            WHERE po.id = ? AND po.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $po = $stmt->fetch();
        if (!$po) { http_response_code(404); echo 'Purchase order not found.'; exit; }
        return $po;
    }

    private function getLines(int $poId): array
    {
        $stmt = $this->db()->prepare("
            SELECT pol.*, i.item_code, i.description as item_description,
                   u.abbreviation as uom_abbr, pe.name as pack_name
            FROM purchase_order_lines pol
            JOIN items i ON pol.item_id = i.id
            LEFT JOIN uom u ON pol.uom_id = u.id
            LEFT JOIN item_pack_extensions pe ON pol.pack_extension_id = pe.id
            WHERE pol.po_id = ?
            ORDER BY pol.id
        ");
        $stmt->execute([$poId]);
        return $stmt->fetchAll();
    }

    private function extractHeader(): array
    {
        return [
            'po_type' => $_POST['po_type'] ?? 'STANDARD',
            'supplier_id' => (int)($_POST['supplier_id'] ?? 0),
            'facility_id' => (int)($_POST['facility_id'] ?? 0),
            'order_date' => $_POST['order_date'] ?? date('Y-m-d'),
            'expected_delivery_date' => $_POST['expected_delivery_date'] ?? null,
            'notes' => trim($_POST['notes'] ?? ''),
            'shipping_instructions' => trim($_POST['shipping_instructions'] ?? ''),
            'contract_start_date' => $_POST['contract_start_date'] ?? null,
            'contract_end_date' => $_POST['contract_end_date'] ?? null,
            'contracted_quantity' => ($_POST['contracted_quantity'] ?? '') !== '' ? (float)$_POST['contracted_quantity'] : null,
            'contracted_value' => ($_POST['contracted_value'] ?? '') !== '' ? (float)$_POST['contracted_value'] : null,
        ];
    }

    private function extractLines(): array
    {
        $lines = [];
        foreach ($_POST['lines'] ?? [] as $l) {
            $itemId = (int)($l['item_id'] ?? 0);
            $qty = (float)($l['quantity'] ?? 0);
            if (!$itemId || $qty <= 0) continue;
            $lines[] = [
                'item_id' => $itemId,
                'pack_extension_id' => (int)($l['pack_extension_id'] ?? 0) ?: null,
                'ordered_quantity' => $qty,
                'uom_id' => (int)($l['uom_id'] ?? 0),
                'unit_cost' => (float)($l['unit_cost'] ?? 0),
                'notes' => trim($l['notes'] ?? '') ?: null,
            ];
        }
        return $lines;
    }

    private function insertLines(int $poId, array $lines): void
    {
        $stmt = $this->db()->prepare("
            INSERT INTO purchase_order_lines (po_id, item_id, pack_extension_id, ordered_quantity, uom_id, unit_cost, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($lines as $l) {
            $stmt->execute([$poId, $l['item_id'], $l['pack_extension_id'], $l['ordered_quantity'], $l['uom_id'], $l['unit_cost'], $l['notes']]);
        }
    }

    private function recalculatePOStatus(int $poId): void
    {
        $stmt = $this->db()->prepare("SELECT line_status FROM purchase_order_lines WHERE po_id = ?");
        $stmt->execute([$poId]);
        $statuses = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $nonCancelled = array_filter($statuses, fn($s) => $s !== 'CANCELLED');
        if (empty($nonCancelled)) {
            $newStatus = 'CANCELLED';
        } elseif (count(array_filter($nonCancelled, fn($s) => $s === 'RECEIVED')) === count($nonCancelled)) {
            $newStatus = 'RECEIVED';
        } elseif (in_array('PARTIAL', $nonCancelled) || in_array('RECEIVED', $nonCancelled)) {
            $newStatus = 'PARTIAL';
        } else {
            return; // No change needed
        }

        $this->db()->prepare("UPDATE purchase_orders SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $poId]);
    }

    private function computeDiff(array $oldPO, array $newData, array $oldLines, array $newLines): array
    {
        $diff = [];
        $fields = ['po_type', 'supplier_id', 'facility_id', 'order_date', 'expected_delivery_date', 'notes', 'shipping_instructions'];
        foreach ($fields as $f) {
            $oldVal = $oldPO[$f] ?? null;
            $newVal = $newData[$f] ?? null;
            if ((string)$oldVal !== (string)$newVal) {
                $diff[] = ['field' => $f, 'old' => $oldVal, 'new' => $newVal];
            }
        }
        $diff[] = ['field' => 'lines', 'old' => count($oldLines) . ' lines', 'new' => count($newLines) . ' lines'];
        return $diff;
    }

    private function formData(string $mode, ?array $po = null, ?array $lines = null): array
    {
        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);
        $active = $this->facilityService->getActiveFacility($userId);

        return [
            'mode' => $mode,
            'po' => $po,
            'lines' => $lines ?? [],
            'facilities' => $facilities,
            'activeFacilityId' => (int)($active['id'] ?? 0),
            'uoms' => $this->db()->query("SELECT id, abbreviation FROM uom WHERE active=1 ORDER BY abbreviation")->fetchAll(),
        ];
    }

    private function getSetting(string $key, string $default = ''): string
    {
        $stmt = $this->db()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn() ?: $default;
    }
}
