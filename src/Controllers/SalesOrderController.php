<?php

namespace PrecisionInk\Controllers;

class SalesOrderController extends BaseController
{
    // ── List ────────────────────────────────────────────────────────

    public function index(): void
    {
        if (!$this->checkPermission('sales_orders', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterStatus = $_GET['status'] ?? '';
        $filterSearch = trim($_GET['q'] ?? '');
        $filterFacility = (int)($_GET['facility_id'] ?? 0);

        $where = ['so.deleted_at IS NULL'];
        $params = [];
        if ($filterStatus) { $where[] = 'so.status = ?'; $params[] = $filterStatus; }
        if ($filterSearch) { $where[] = '(so.so_number LIKE ? OR c.company_name LIKE ? OR so.customer_po_number LIKE ?)'; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; }
        if ($filterFacility) { $where[] = 'so.facility_id = ?'; $params[] = $filterFacility; }
        $whereClause = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM sales_orders so LEFT JOIN customers c ON so.customer_id = c.id WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT so.*, c.customer_code, c.company_name as customer_name, f.name as facility_name,
                   COALESCE((SELECT SUM(sol.ordered_quantity * sol.unit_price) FROM sales_order_lines sol WHERE sol.so_id = so.id), 0) as order_value,
                   (SELECT SUM(sol.backordered_quantity) FROM sales_order_lines sol WHERE sol.so_id = so.id AND sol.backordered_quantity > 0) as total_backorder
            FROM sales_orders so
            LEFT JOIN customers c ON so.customer_id = c.id
            LEFT JOIN facilities f ON so.facility_id = f.id
            WHERE {$whereClause}
            ORDER BY so.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);

        $this->renderView('sales_orders/list', [
            'orders' => $stmt->fetchAll(), 'facilities' => $facilities,
            'filters' => ['status' => $filterStatus, 'q' => $filterSearch, 'facility_id' => $filterFacility],
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    // ── Create ──────────────────────────────────────────────────────

    public function create(): void
    {
        if (!$this->checkPermission('sales_orders', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->renderView('sales_orders/edit', $this->formData('create'));
    }

    public function store(): void
    {
        if (!$this->checkPermission('sales_orders', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $data = $this->extractHeader();
        $lines = $this->extractLines();

        // Account hold hard block
        if ($data['customer_id']) {
            $holdCheck = $this->db()->prepare("SELECT account_hold, account_hold_reason FROM customers WHERE id = ?");
            $holdCheck->execute([$data['customer_id']]);
            $cust = $holdCheck->fetch();
            if ($cust && $cust['account_hold']) {
                $this->toast('Account is on hold: ' . ($cust['account_hold_reason'] ?? 'No reason specified'), 'error');
                $this->renderView('sales_orders/edit', $this->formData('create', $data, $lines));
                return;
            }
        }

        if (!$data['customer_id'] || empty($lines)) {
            $this->toast('Customer and at least one line required.', 'error');
            $this->renderView('sales_orders/edit', $this->formData('create', $data, $lines));
            return;
        }

        // MOQ check
        $moqErrors = $this->checkMOQs($data['customer_id'], $lines);
        if (!empty($moqErrors) && !$this->hasSpecialPermission('override_moq')) {
            $this->toast('MOQ requirements not met: ' . implode('; ', $moqErrors), 'error');
            $this->renderView('sales_orders/edit', $this->formData('create', $data, $lines));
            return;
        }

        $soNumber = $this->generateDocumentNumber('SALES_ORDER');
        $userId = $this->currentUserId();

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("
                INSERT INTO sales_orders (so_number, customer_id, ship_to_id, facility_id, order_date,
                    requested_ship_date, promised_ship_date, promised_delivery_date, payment_terms_id,
                    ship_via_id, external_notes, internal_notes, customer_po_number, is_sample,
                    deposit_amount, sales_rep_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT')
            ")->execute([
                $soNumber, $data['customer_id'], $data['ship_to_id'] ?: null, $data['facility_id'],
                $data['order_date'], $data['requested_ship_date'] ?: null,
                $data['promised_ship_date'] ?: null, $data['promised_delivery_date'] ?: null,
                $data['payment_terms_id'] ?: null, $data['ship_via_id'] ?: null,
                $data['external_notes'] ?: null, $data['internal_notes'] ?: null,
                $data['customer_po_number'] ?: null, $data['is_sample'], $data['deposit_amount'],
                $data['sales_rep_id'] ?: null,
            ]);
            $soId = (int)$this->db()->lastInsertId();

            $this->insertLines($soId, $lines);
            $this->insertSurcharges($soId);
            $this->insertFreightQuotes($soId);

            $this->db()->commit();

            // Log MOQ override if applicable
            if (!empty($moqErrors)) {
                $this->auditLog('CREATE', 'sales_orders', $soId, [], ['moq_override' => implode('; ', $moqErrors)]);
            }

            $this->customFieldService->saveValues('sales_orders', $soId, $_POST);
            $this->auditCreate('sales_orders', $soId, ['so_number' => $soNumber]);
            $this->toast("Sales order {$soNumber} created.", 'success');
            $this->redirect("/orders/{$soId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect('/orders/create');
        }
    }

    // ── View ────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        if (!$this->checkPermission('sales_orders', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $so = $this->getOrFail((int)$id);
        $lines = $this->getLines((int)$id);
        $surcharges = $this->db()->prepare("SELECT os.*, st.name as surcharge_name FROM order_surcharges os LEFT JOIN surcharge_types st ON os.surcharge_type_id = st.id WHERE os.so_id = ?")->execute([(int)$id]) ? [] : [];
        $surchStmt = $this->db()->prepare("SELECT os.*, st.name as surcharge_name FROM order_surcharges os LEFT JOIN surcharge_types st ON os.surcharge_type_id = st.id WHERE os.so_id = ?");
        $surchStmt->execute([(int)$id]);
        $surcharges = $surchStmt->fetchAll();

        $freightStmt = $this->db()->prepare("SELECT * FROM freight_quotes WHERE so_id = ? ORDER BY is_selected DESC, id");
        $freightStmt->execute([(int)$id]);
        $freightQuotes = $freightStmt->fetchAll();

        $credit = $this->creditService ? $this->creditService->checkAtOrderEntry((int)$so['customer_id']) : [];
        $attachments = $this->attachmentService ? $this->attachmentService->getForRecord('sales_order', (int)$id) : [];

        $this->renderView('sales_orders/view', [
            'so' => $so, 'lines' => $lines, 'surcharges' => $surcharges,
            'freightQuotes' => $freightQuotes, 'credit' => $credit,
            'attachments' => $attachments, 'record' => $so,
        ]);
    }

    // ── Edit ────────────────────────────────────────────────────────

    public function editForm(string $id): void
    {
        if (!$this->checkPermission('sales_orders', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $so = $this->getOrFail((int)$id);
        if (!in_array($so['status'], ['DRAFT', 'CONFIRMED'])) { $this->toast('Cannot edit.', 'error'); $this->redirect("/orders/{$id}"); return; }
        $lines = $this->getLines((int)$id);
        $this->renderView('sales_orders/edit', $this->formData('edit', $so, $lines));
    }

    public function update(string $id): void
    {
        if (!$this->checkPermission('sales_orders', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $so = $this->getOrFail((int)$id);
        if (!in_array($so['status'], ['DRAFT', 'CONFIRMED'])) { $this->toast('Cannot edit.', 'error'); $this->redirect("/orders/{$id}"); return; }

        $data = $this->extractHeader();
        $lines = $this->extractLines();

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("
                UPDATE sales_orders SET customer_id=?, ship_to_id=?, facility_id=?, order_date=?,
                    requested_ship_date=?, promised_ship_date=?, promised_delivery_date=?,
                    payment_terms_id=?, ship_via_id=?, external_notes=?, internal_notes=?,
                    customer_po_number=?, is_sample=?, deposit_amount=?, sales_rep_id=?, updated_at=NOW()
                WHERE id=?
            ")->execute([
                $data['customer_id'], $data['ship_to_id'] ?: null, $data['facility_id'], $data['order_date'],
                $data['requested_ship_date'] ?: null, $data['promised_ship_date'] ?: null,
                $data['promised_delivery_date'] ?: null, $data['payment_terms_id'] ?: null,
                $data['ship_via_id'] ?: null, $data['external_notes'] ?: null, $data['internal_notes'] ?: null,
                $data['customer_po_number'] ?: null, $data['is_sample'], $data['deposit_amount'],
                $data['sales_rep_id'] ?: null, (int)$id,
            ]);

            // Replace lines that haven't been shipped
            $this->db()->prepare("DELETE FROM sales_order_lines WHERE so_id = ? AND shipped_quantity = 0")->execute([(int)$id]);
            $this->insertLines((int)$id, $lines);

            // Replace surcharges and freight
            $this->db()->prepare("DELETE FROM order_surcharges WHERE so_id = ?")->execute([(int)$id]);
            $this->insertSurcharges((int)$id);
            $this->db()->prepare("DELETE FROM freight_quotes WHERE so_id = ?")->execute([(int)$id]);
            $this->insertFreightQuotes((int)$id);

            $this->db()->commit();
            $this->customFieldService->saveValues('sales_orders', (int)$id, $_POST);
            $this->auditUpdate('sales_orders', (int)$id, $so, $data);
            $this->toast('Order updated.', 'success');
            $this->redirect("/orders/{$id}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/orders/{$id}/edit");
        }
    }

    // ── Workflow ─────────────────────────────────────────────────────

    public function confirm(string $id): void
    {
        if (!$this->checkPermission('sales_orders', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $so = $this->getOrFail((int)$id);
        if ($so['status'] !== 'DRAFT') { $this->toast('Only DRAFT orders can be confirmed.', 'error'); $this->redirect("/orders/{$id}"); return; }

        $this->db()->prepare("UPDATE sales_orders SET status='CONFIRMED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'sales_orders', (int)$id, ['status' => 'DRAFT'], ['status' => 'CONFIRMED']);
        $this->toast("Order {$so['so_number']} confirmed.", 'success');
        $this->redirect("/orders/{$id}");
    }

    public function hold(string $id): void
    {
        if (!$this->checkPermission('sales_orders', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $so = $this->getOrFail((int)$id);

        $reason = trim($_POST['hold_reason'] ?? '');
        if (!$reason) { $this->toast('Hold reason required.', 'error'); $this->redirect("/orders/{$id}"); return; }

        $this->db()->prepare("UPDATE sales_orders SET status='ON_HOLD', hold_reason=?, updated_at=NOW() WHERE id=?")->execute([$reason, (int)$id]);
        $this->auditLog('UPDATE', 'sales_orders', (int)$id, ['status' => $so['status']], ['status' => 'ON_HOLD', 'reason' => $reason]);
        $this->toast("Order placed on hold.", 'warning');
        $this->redirect("/orders/{$id}");
    }

    public function releaseHold(string $id): void
    {
        if (!$this->hasSpecialPermission('release_order_hold')) {
            $this->toast('Permission denied.', 'error'); $this->redirect("/orders/{$id}"); return;
        }
        $so = $this->getOrFail((int)$id);
        if ($so['status'] !== 'ON_HOLD') { $this->toast('Order not on hold.', 'error'); $this->redirect("/orders/{$id}"); return; }

        $this->db()->prepare("UPDATE sales_orders SET status='CONFIRMED', hold_reason=NULL, updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'sales_orders', (int)$id, ['status' => 'ON_HOLD'], ['status' => 'CONFIRMED']);
        $this->toast("Hold released.", 'success');
        $this->redirect("/orders/{$id}");
    }

    public function cancel(string $id): void
    {
        if (!$this->checkPermission('sales_orders', 'delete')) { http_response_code(403); echo 'Access Denied'; exit; }
        $so = $this->getOrFail((int)$id);

        if ($so['status'] === 'PARTIAL' && !$this->hasSpecialPermission('cancel_shipped_orders')) {
            $this->toast('Cancelling partially shipped orders requires special permission.', 'error');
            $this->redirect("/orders/{$id}");
            return;
        }

        $this->db()->prepare("UPDATE sales_orders SET status='CANCELLED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'sales_orders', (int)$id, ['status' => $so['status']], ['status' => 'CANCELLED']);
        $this->toast("Order {$so['so_number']} cancelled.", 'success');
        $this->redirect("/orders/{$id}");
    }

    // ── Clone ───────────────────────────────────────────────────────

    public function cloneOrder(string $id): void
    {
        if (!$this->checkPermission('sales_orders', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $so = $this->getOrFail((int)$id);
        $lines = $this->getLines((int)$id);

        $soNumber = $this->generateDocumentNumber('SALES_ORDER');

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("
                INSERT INTO sales_orders (so_number, customer_id, ship_to_id, facility_id, order_date,
                    payment_terms_id, ship_via_id, external_notes, internal_notes, is_sample, sales_rep_id, status)
                VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'DRAFT')
            ")->execute([
                $soNumber, $so['customer_id'], $so['ship_to_id'], $so['facility_id'],
                $so['payment_terms_id'], $so['ship_via_id'], $so['external_notes'],
                $so['internal_notes'], $so['is_sample'], $so['sales_rep_id'],
            ]);
            $newId = (int)$this->db()->lastInsertId();

            $lineInsert = $this->db()->prepare("INSERT INTO sales_order_lines (so_id, item_id, pack_extension_id, ordered_quantity, uom_id, unit_price, external_notes, packing_slip_note, print_alias) VALUES (?,?,?,?,?,?,?,?,?)");
            foreach ($lines as $l) {
                $lineInsert->execute([$newId, $l['item_id'], $l['pack_extension_id'], $l['ordered_quantity'], $l['uom_id'], $l['unit_price'], $l['external_notes'], $l['packing_slip_note'], $l['print_alias']]);
            }

            // Copy surcharges and freight
            $this->db()->prepare("INSERT INTO order_surcharges (so_id, surcharge_type_id, amount) SELECT ?, surcharge_type_id, amount FROM order_surcharges WHERE so_id = ?")->execute([$newId, (int)$id]);
            $this->db()->prepare("INSERT INTO freight_quotes (so_id, carrier_name, service_level, quoted_cost, transit_days, notes, is_selected) SELECT ?, carrier_name, service_level, quoted_cost, transit_days, notes, is_selected FROM freight_quotes WHERE so_id = ?")->execute([$newId, (int)$id]);

            $this->db()->commit();
            $this->auditCreate('sales_orders', $newId, ['so_number' => $soNumber, 'cloned_from' => $so['so_number']]);
            $this->toast("Cloned as {$soNumber}.", 'success');
            $this->redirect("/orders/{$newId}/edit");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/orders/{$id}");
        }
    }

    // ── Acknowledgment + Proforma ───────────────────────────────────

    public function acknowledgment(string $id): void
    {
        if (!$this->checkPermission('sales_orders', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $so = $this->getOrFail((int)$id);
        $lines = $this->getLines((int)$id);

        $html = $this->buildOrderPdfHtml($so, $lines, 'ORDER ACKNOWLEDGMENT');
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $pdfPath = sys_get_temp_dir() . "/ACK_{$so['so_number']}.pdf";
        file_put_contents($pdfPath, $dompdf->output());

        if ($this->attachmentService) {
            try { $this->attachmentService->upload('sales_order', (int)$id, ['name' => "ACK_{$so['so_number']}.pdf", 'type' => 'application/pdf', 'tmp_name' => $pdfPath, 'error' => 0, 'size' => filesize($pdfPath)], 'Order Acknowledgment', $this->currentUserId()); } catch (\Throwable $e) {}
        }

        $contactStmt = $this->db()->prepare("SELECT email FROM customer_contacts WHERE customer_id = ? AND active = 1 AND email IS NOT NULL ORDER BY FIELD(contact_type, 'GENERAL') ASC, is_primary DESC LIMIT 1");
        $contactStmt->execute([$so['customer_id']]);
        $email = $contactStmt->fetchColumn();

        if ($email && $this->emailService) {
            $this->emailService->send('sales_order', [$email], ['so_number' => $so['so_number'], 'customer_name' => $so['customer_name']], $pdfPath, "ACK_{$so['so_number']}.pdf", 'sales_order', (int)$id);
        }
        @unlink($pdfPath);

        $this->toast("Acknowledgment sent." . (!$email ? ' No contact email found.' : ''), $email ? 'success' : 'warning');
        $this->redirect("/orders/{$id}");
    }

    public function proforma(string $id): void
    {
        if (!$this->checkPermission('sales_orders', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $so = $this->getOrFail((int)$id);
        $lines = $this->getLines((int)$id);

        $html = $this->buildOrderPdfHtml($so, $lines, 'PROFORMA INVOICE — NOT A TAX INVOICE');
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $dompdf->stream("PROFORMA_{$so['so_number']}.pdf", ['Attachment' => false]);
        exit;
    }

    // ── Ship-To Resolution AJAX ─────────────────────────────────────

    public function resolveShipTo(): void
    {
        $shipToId = (int)($_GET['ship_to_id'] ?? 0);
        if (!$shipToId || !$this->customerResolutionService) { $this->jsonResponse([]); return; }

        $resolved = $this->customerResolutionService->resolveAll($shipToId);

        // Enrich with names
        $result = [];
        foreach (['payment_terms_id', 'ship_via_id', 'sales_rep_id'] as $field) {
            $val = $resolved[$field]['value'] ?? null;
            $inherited = $resolved[$field]['inherited'] ?? false;
            $result[$field] = $val;
            $result[str_replace('_id', '_inherited', $field)] = $inherited;
        }

        // Get names
        if ($result['payment_terms_id']) {
            $stmt = $this->db()->prepare("SELECT name FROM payment_terms WHERE id = ?");
            $stmt->execute([$result['payment_terms_id']]);
            $result['payment_terms_name'] = $stmt->fetchColumn() ?: '';
        }
        if ($result['ship_via_id']) {
            $stmt = $this->db()->prepare("SELECT name, transit_days FROM ship_via WHERE id = ?");
            $stmt->execute([$result['ship_via_id']]);
            $sv = $stmt->fetch();
            $result['ship_via_name'] = $sv['name'] ?? '';
            $result['ship_via_transit_days'] = $sv['transit_days'] ?? null;
        }
        if ($result['sales_rep_id']) {
            $stmt = $this->db()->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmt->execute([$result['sales_rep_id']]);
            $result['sales_rep_name'] = $stmt->fetchColumn() ?: '';
        }

        // Internal notes
        $stStmt = $this->db()->prepare("SELECT customer_id FROM ship_to_locations WHERE id = ?");
        $stStmt->execute([$shipToId]);
        $custId = (int)$stStmt->fetchColumn();
        $result['internal_notes'] = $this->customerResolutionService->getEffectiveInternalNotes($custId, $shipToId);

        $this->jsonResponse($result);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT so.*, c.customer_code, c.company_name as customer_name, c.account_hold,
                   f.name as facility_name, pt.name as payment_terms_name,
                   sv.name as ship_via_name, u.full_name as sales_rep_name,
                   st.location_name as ship_to_name, st.city as ship_to_city, st.state as ship_to_state
            FROM sales_orders so
            LEFT JOIN customers c ON so.customer_id = c.id
            LEFT JOIN facilities f ON so.facility_id = f.id
            LEFT JOIN payment_terms pt ON so.payment_terms_id = pt.id
            LEFT JOIN ship_via sv ON so.ship_via_id = sv.id
            LEFT JOIN users u ON so.sales_rep_id = u.id
            LEFT JOIN ship_to_locations st ON so.ship_to_id = st.id
            WHERE so.id = ? AND so.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $so = $stmt->fetch();
        if (!$so) { http_response_code(404); echo 'Sales order not found.'; exit; }
        return $so;
    }

    private function getLines(int $soId): array
    {
        $stmt = $this->db()->prepare("
            SELECT sol.*, i.item_code, i.description as item_description, u.abbreviation as uom_abbr, pe.name as pack_name
            FROM sales_order_lines sol
            JOIN items i ON sol.item_id = i.id
            LEFT JOIN uom u ON sol.uom_id = u.id
            LEFT JOIN item_pack_extensions pe ON sol.pack_extension_id = pe.id
            WHERE sol.so_id = ? ORDER BY sol.id
        ");
        $stmt->execute([$soId]);
        return $stmt->fetchAll();
    }

    private function extractHeader(): array
    {
        return [
            'customer_id' => (int)($_POST['customer_id'] ?? 0),
            'ship_to_id' => (int)($_POST['ship_to_id'] ?? 0),
            'facility_id' => (int)($_POST['facility_id'] ?? 0),
            'order_date' => $_POST['order_date'] ?? date('Y-m-d'),
            'requested_ship_date' => $_POST['requested_ship_date'] ?? null,
            'promised_ship_date' => $_POST['promised_ship_date'] ?? null,
            'promised_delivery_date' => $_POST['promised_delivery_date'] ?? null,
            'payment_terms_id' => (int)($_POST['payment_terms_id'] ?? 0),
            'ship_via_id' => (int)($_POST['ship_via_id'] ?? 0),
            'external_notes' => trim($_POST['external_notes'] ?? ''),
            'internal_notes' => trim($_POST['internal_notes'] ?? ''),
            'customer_po_number' => trim($_POST['customer_po_number'] ?? ''),
            'is_sample' => isset($_POST['is_sample']) ? 1 : 0,
            'deposit_amount' => (float)($_POST['deposit_amount'] ?? 0),
            'sales_rep_id' => (int)($_POST['sales_rep_id'] ?? 0),
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
                'unit_price' => (float)($l['unit_price'] ?? 0),
                'external_notes' => trim($l['external_notes'] ?? '') ?: null,
                'packing_slip_note' => trim($l['packing_slip_note'] ?? '') ?: null,
                'print_alias' => isset($l['print_alias']) ? 1 : 0,
            ];
        }
        return $lines;
    }

    private function insertLines(int $soId, array $lines): void
    {
        $stmt = $this->db()->prepare("INSERT INTO sales_order_lines (so_id, item_id, pack_extension_id, ordered_quantity, uom_id, unit_price, external_notes, packing_slip_note, print_alias) VALUES (?,?,?,?,?,?,?,?,?)");
        foreach ($lines as $l) {
            $stmt->execute([$soId, $l['item_id'], $l['pack_extension_id'], $l['ordered_quantity'], $l['uom_id'], $l['unit_price'], $l['external_notes'], $l['packing_slip_note'], $l['print_alias']]);
        }
    }

    private function insertSurcharges(int $soId): void
    {
        $surcharges = $_POST['surcharges'] ?? [];
        $stmt = $this->db()->prepare("INSERT INTO order_surcharges (so_id, surcharge_type_id, amount) VALUES (?,?,?)");
        foreach ($surcharges as $s) {
            $typeId = (int)($s['surcharge_type_id'] ?? 0);
            $amount = (float)($s['amount'] ?? 0);
            if ($typeId && $amount > 0) $stmt->execute([$soId, $typeId, $amount]);
        }
    }

    private function insertFreightQuotes(int $soId): void
    {
        $quotes = $_POST['freight'] ?? [];
        $stmt = $this->db()->prepare("INSERT INTO freight_quotes (so_id, carrier_name, service_level, quoted_cost, transit_days, notes, is_selected) VALUES (?,?,?,?,?,?,?)");
        foreach ($quotes as $f) {
            $carrier = trim($f['carrier_name'] ?? '');
            if (!$carrier) continue;
            $stmt->execute([$soId, $carrier, trim($f['service_level'] ?? ''), (float)($f['quoted_cost'] ?? 0), (int)($f['transit_days'] ?? 0) ?: null, trim($f['notes'] ?? '') ?: null, isset($f['is_selected']) ? 1 : 0]);
        }
    }

    private function checkMOQs(int $customerId, array $lines): array
    {
        $errors = [];
        $pricing = new \App\Services\PricingService($this->db());
        foreach ($lines as $l) {
            $moq = $pricing->checkMOQ((int)$l['item_id'], $customerId, (float)$l['ordered_quantity']);
            if ($moq) {
                $itemStmt = $this->db()->prepare("SELECT item_code FROM items WHERE id = ?");
                $itemStmt->execute([$l['item_id']]);
                $code = $itemStmt->fetchColumn();
                $errors[] = "{$code}: MOQ {$moq['moq']}, ordered {$l['ordered_quantity']}, shortfall {$moq['shortfall']}";
            }
        }
        return $errors;
    }

    private function formData(string $mode, ?array $so = null, ?array $lines = null): array
    {
        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);
        $active = $this->facilityService->getActiveFacility($userId);

        return [
            'mode' => $mode, 'so' => $so, 'lines' => $lines ?? [],
            'facilities' => $facilities,
            'activeFacilityId' => (int)($active['id'] ?? 0),
            'uoms' => $this->db()->query("SELECT id, abbreviation FROM uom WHERE active=1 ORDER BY abbreviation")->fetchAll(),
            'paymentTerms' => $this->db()->query("SELECT id, name FROM payment_terms WHERE active=1 ORDER BY name")->fetchAll(),
            'shipVias' => $this->db()->query("SELECT id, name, transit_days FROM ship_via WHERE active=1 ORDER BY name")->fetchAll(),
            'surchargeTypes' => $this->db()->query("SELECT id, name, calculation_type FROM surcharge_types WHERE active=1 ORDER BY name")->fetchAll(),
            'users' => $this->db()->query("SELECT id, full_name FROM users WHERE active=1 ORDER BY full_name")->fetchAll(),
        ];
    }

    private function buildOrderPdfHtml(array $so, array $lines, string $title): string
    {
        $subtotal = 0;
        $lineRows = '';
        $n = 1;
        foreach ($lines as $l) {
            $ext = (float)$l['ordered_quantity'] * (float)$l['unit_price'];
            $subtotal += $ext;
            $lineRows .= '<tr><td>'.$n++.'</td><td>'.htmlspecialchars($l['item_code']).'</td><td>'.htmlspecialchars($l['item_description']).'</td><td style="text-align:right;">'.number_format((float)$l['ordered_quantity'],4).'</td><td>'.htmlspecialchars($l['uom_abbr']??'').'</td><td style="text-align:right;">$'.number_format((float)$l['unit_price'],4).'</td><td style="text-align:right;">$'.number_format($ext,2).'</td></tr>';
        }

        return '<!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif;font-size:12px;margin:30px;}h1{font-size:18px;margin-bottom:4px;}h2{font-size:12px;color:#555;margin-bottom:16px;}table{width:100%;border-collapse:collapse;margin:12px 0;}th,td{border:1px solid #ccc;padding:5px 8px;text-align:left;font-size:11px;}th{background:#f0f0f0;}.total td{font-weight:bold;border-top:2px solid #333;}.label{font-weight:bold;color:#555;font-size:10px;text-transform:uppercase;}</style></head><body>'
            .'<h2>'.htmlspecialchars($title).'</h2>'
            .'<h1>'.htmlspecialchars($so['so_number']).'</h1>'
            .'<table style="border:none;margin-bottom:16px;"><tr><td style="border:none;width:50%;"><p class="label">Customer</p><p><strong>'.htmlspecialchars($so['customer_name']??'').'</strong></p></td><td style="border:none;width:50%;"><p class="label">Order Date</p><p>'.date('M j, Y',strtotime($so['order_date'])).'</p>'.($so['customer_po_number']?'<p class="label">Customer PO</p><p>'.htmlspecialchars($so['customer_po_number']).'</p>':'').'</td></tr></table>'
            .($so['ship_to_name']?'<p><strong>Ship To:</strong> '.htmlspecialchars($so['ship_to_name']).($so['ship_to_city']?', '.htmlspecialchars($so['ship_to_city']):'').($so['ship_to_state']?', '.htmlspecialchars($so['ship_to_state']):'').'</p>':'')
            .($so['promised_ship_date']?'<p><strong>Promised Ship:</strong> '.date('M j, Y',strtotime($so['promised_ship_date'])).'</p>':'')
            .'<table><thead><tr><th>#</th><th>Item</th><th>Description</th><th style="text-align:right;">Qty</th><th>UOM</th><th style="text-align:right;">Price</th><th style="text-align:right;">Extended</th></tr></thead><tbody>'.$lineRows
            .'<tr class="total"><td colspan="6" style="text-align:right;">Subtotal</td><td style="text-align:right;">$'.number_format($subtotal,2).'</td></tr>'
            .($so['deposit_amount']>0?'<tr><td colspan="6" style="text-align:right;">Less Deposit</td><td style="text-align:right;">-$'.number_format((float)$so['deposit_amount'],2).'</td></tr>':'')
            .'<tr class="total"><td colspan="6" style="text-align:right;">Total</td><td style="text-align:right;">$'.number_format($subtotal-(float)$so['deposit_amount'],2).'</td></tr>'
            .'</tbody></table>'
            .($so['external_notes']?'<p><strong>Notes:</strong> '.htmlspecialchars($so['external_notes']).'</p>':'')
            .($so['payment_terms_name']?'<p><strong>Payment Terms:</strong> '.htmlspecialchars($so['payment_terms_name']).'</p>':'')
            .'</body></html>';
    }
}
