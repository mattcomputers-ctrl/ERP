<?php

namespace PrecisionInk\Controllers;

class RmaController extends BaseController
{
    public function index(): void
    {
        if (!$this->checkPermission('rma', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterStatus = $_GET['status'] ?? '';
        $filterSearch = trim($_GET['q'] ?? '');

        $where = [];
        $params = [];
        if ($filterStatus) { $where[] = 'r.status = ?'; $params[] = $filterStatus; }
        if ($filterSearch) { $where[] = '(r.rma_number LIKE ? OR c.company_name LIKE ?)'; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM rma_orders r LEFT JOIN customers c ON r.customer_id = c.id {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT r.*, c.customer_code, c.company_name as customer_name, f.name as facility_name,
                   (SELECT COUNT(*) FROM rma_lines WHERE rma_id = r.id) as line_count
            FROM rma_orders r
            LEFT JOIN customers c ON r.customer_id = c.id
            LEFT JOIN facilities f ON r.facility_id = f.id
            {$whereClause}
            ORDER BY r.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $this->renderView('rma/list', [
            'rmas' => $stmt->fetchAll(),
            'filters' => ['status' => $filterStatus, 'q' => $filterSearch],
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    public function create(): void
    {
        if (!$this->checkPermission('rma', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->renderView('rma/edit', $this->formData('create'));
    }

    public function store(): void
    {
        if (!$this->checkPermission('rma', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $data = $this->extractHeader();
        $lines = $this->extractLines();

        if (!$data['customer_id'] || empty($lines)) {
            $this->toast('Customer and at least one line required.', 'error');
            $this->renderView('rma/edit', $this->formData('create', $data, $lines));
            return;
        }

        $rmaNumber = $this->generateDocumentNumber('RMA');

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("
                INSERT INTO rma_orders (rma_number, customer_id, so_id, rma_date, return_reason, facility_id, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $rmaNumber, $data['customer_id'], $data['so_id'] ?: null,
                $data['rma_date'], $data['return_reason'], $data['facility_id'],
                $data['notes'] ?: null,
            ]);
            $rmaId = (int)$this->db()->lastInsertId();

            $lineInsert = $this->db()->prepare("
                INSERT INTO rma_lines (rma_id, item_id, pack_extension_id, authorized_quantity, lot_number, disposition, condition_notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($lines as $l) {
                $lineInsert->execute([
                    $rmaId, $l['item_id'], $l['pack_extension_id'],
                    $l['authorized_quantity'], $l['lot_number'] ?: null,
                    $l['disposition'], $l['condition_notes'] ?: null,
                ]);
            }

            $this->db()->commit();
            $this->auditCreate('rma_orders', $rmaId, ['rma_number' => $rmaNumber]);
            $this->toast("RMA {$rmaNumber} created.", 'success');
            $this->redirect("/rma/{$rmaId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect('/rma/create');
        }
    }

    public function show(string $id): void
    {
        if (!$this->checkPermission('rma', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $rma = $this->getOrFail((int)$id);
        $lines = $this->getLines((int)$id);
        $attachments = $this->attachmentService ? $this->attachmentService->getForRecord('rma', (int)$id) : [];

        $this->renderView('rma/view', [
            'rma' => $rma, 'lines' => $lines, 'attachments' => $attachments, 'record' => $rma,
        ]);
    }

    public function editForm(string $id): void
    {
        if (!$this->checkPermission('rma', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $rma = $this->getOrFail((int)$id);
        if ($rma['status'] !== 'OPEN') { $this->toast('Only OPEN RMAs can be edited.', 'error'); $this->redirect("/rma/{$id}"); return; }
        $lines = $this->getLines((int)$id);
        $this->renderView('rma/edit', $this->formData('edit', $rma, $lines));
    }

    public function update(string $id): void
    {
        if (!$this->checkPermission('rma', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $rma = $this->getOrFail((int)$id);
        if ($rma['status'] !== 'OPEN') { $this->toast('Only OPEN RMAs can be edited.', 'error'); $this->redirect("/rma/{$id}"); return; }

        $data = $this->extractHeader();
        $lines = $this->extractLines();

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("UPDATE rma_orders SET customer_id=?, so_id=?, rma_date=?, return_reason=?, facility_id=?, notes=?, updated_at=NOW() WHERE id=?")
                ->execute([$data['customer_id'], $data['so_id'] ?: null, $data['rma_date'], $data['return_reason'], $data['facility_id'], $data['notes'] ?: null, (int)$id]);

            $this->db()->prepare("DELETE FROM rma_lines WHERE rma_id = ? AND received_quantity IS NULL")->execute([(int)$id]);
            $lineInsert = $this->db()->prepare("INSERT INTO rma_lines (rma_id, item_id, pack_extension_id, authorized_quantity, lot_number, disposition, condition_notes) VALUES (?,?,?,?,?,?,?)");
            foreach ($lines as $l) {
                $lineInsert->execute([(int)$id, $l['item_id'], $l['pack_extension_id'], $l['authorized_quantity'], $l['lot_number'] ?: null, $l['disposition'], $l['condition_notes'] ?: null]);
            }

            $this->db()->commit();
            $this->auditUpdate('rma_orders', (int)$id, $rma, $data);
            $this->toast('RMA updated.', 'success');
            $this->redirect("/rma/{$id}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/rma/{$id}/edit");
        }
    }

    public function receive(string $id): void
    {
        if (!$this->checkPermission('rma', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $rma = $this->getOrFail((int)$id);
        if ($rma['status'] !== 'OPEN') { $this->toast('Only OPEN RMAs can receive returns.', 'error'); $this->redirect("/rma/{$id}"); return; }

        $userId = $this->currentUserId();
        $receiveData = $_POST['recv'] ?? [];

        $this->db()->beginTransaction();
        try {
            foreach ($receiveData as $lineId => $rd) {
                $receivedQty = (float)($rd['received_quantity'] ?? 0);
                if ($receivedQty <= 0) continue;

                $lotNumber = trim($rd['lot_number'] ?? '');
                $disposition = $rd['disposition'] ?? 'HOLD_FOR_INSPECTION';
                $conditionNotes = trim($rd['condition_notes'] ?? '');

                // Get line item info
                $lineStmt = $this->db()->prepare("SELECT rl.*, i.unit_cost as item_unit_cost FROM rma_lines rl JOIN items i ON rl.item_id = i.id WHERE rl.id = ?");
                $lineStmt->execute([(int)$lineId]);
                $line = $lineStmt->fetch();
                if (!$line) continue;

                // Try to find original lot cost
                $unitCost = (float)$line['item_unit_cost'];
                if ($lotNumber) {
                    $costStmt = $this->db()->prepare("SELECT unit_cost FROM fifo_lots WHERE lot_number = ? ORDER BY created_at DESC LIMIT 1");
                    $costStmt->execute([$lotNumber]);
                    $origCost = $costStmt->fetchColumn();
                    if ($origCost !== false) $unitCost = (float)$origCost;
                }

                $fifoLotId = null;

                if ($disposition === 'RETURN_TO_STOCK') {
                    $fifoLotId = $this->fifoService->addLot(
                        (int)$line['item_id'], (int)$rma['facility_id'],
                        $receivedQty, $unitCost, $lotNumber ?: 'RMA-' . $rma['rma_number'],
                        'RMA', (int)$id, null, 'AVAILABLE', $userId,
                        $line['pack_extension_id'] ? (int)$line['pack_extension_id'] : null
                    );
                } elseif ($disposition === 'WRITE_OFF') {
                    // Log write-off transaction (no lot created)
                    $this->db()->prepare("
                        INSERT INTO inventory_transactions (item_id, facility_id, transaction_type, quantity, unit_cost, reference_type, reference_id, user_id, created_at)
                        VALUES (?, ?, 'WRITE_OFF', ?, ?, 'RMA', ?, ?, NOW())
                    ")->execute([(int)$line['item_id'], (int)$rma['facility_id'], -$receivedQty, $unitCost, (int)$id, $userId]);
                } elseif ($disposition === 'HOLD_FOR_INSPECTION') {
                    $fifoLotId = $this->fifoService->addLot(
                        (int)$line['item_id'], (int)$rma['facility_id'],
                        $receivedQty, $unitCost, $lotNumber ?: 'RMA-' . $rma['rma_number'],
                        'RMA', (int)$id, null, 'QUARANTINED', $userId,
                        $line['pack_extension_id'] ? (int)$line['pack_extension_id'] : null
                    );
                    // Quarantine with reason
                    if ($fifoLotId) {
                        $this->fifoService->quarantine($fifoLotId, 'RMA: ' . ($conditionNotes ?: $rma['return_reason']), $userId);
                        // Create inspection record
                        $this->db()->prepare("INSERT INTO qc_incoming_inspections (fifo_lot_id, status) VALUES (?, 'PENDING')")->execute([$fifoLotId]);
                    }
                }

                // Update line
                $this->db()->prepare("UPDATE rma_lines SET received_quantity=?, lot_number=?, disposition=?, condition_notes=?, fifo_lot_id=?, updated_at=NOW() WHERE id=?")
                    ->execute([$receivedQty, $lotNumber ?: null, $disposition, $conditionNotes ?: null, $fifoLotId, (int)$lineId]);
            }

            $this->db()->prepare("UPDATE rma_orders SET status='RECEIVED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
            $this->db()->commit();

            $this->auditLog('UPDATE', 'rma_orders', (int)$id, ['status' => 'OPEN'], ['status' => 'RECEIVED']);
            $this->toast("RMA {$rma['rma_number']} received.", 'success');
            $this->redirect("/rma/{$id}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/rma/{$id}");
        }
    }

    public function close(string $id): void
    {
        if (!$this->checkPermission('rma', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $rma = $this->getOrFail((int)$id);
        if ($rma['status'] !== 'RECEIVED') { $this->toast('Only RECEIVED RMAs can be closed.', 'error'); $this->redirect("/rma/{$id}"); return; }

        $lines = $this->getLines((int)$id);

        // Calculate credit amount
        $subtotal = 0;
        foreach ($lines as $l) {
            $qty = (float)($l['received_quantity'] ?? 0);
            // Try original SO line price, fallback to item sale_price
            $unitPrice = 0;
            if ($rma['so_id']) {
                $priceStmt = $this->db()->prepare("SELECT unit_price FROM sales_order_lines WHERE so_id = ? AND item_id = ? LIMIT 1");
                $priceStmt->execute([$rma['so_id'], $l['item_id']]);
                $p = $priceStmt->fetchColumn();
                if ($p !== false) $unitPrice = (float)$p;
            }
            if ($unitPrice <= 0) {
                $priceStmt = $this->db()->prepare("SELECT sale_price FROM items WHERE id = ?");
                $priceStmt->execute([$l['item_id']]);
                $unitPrice = (float)$priceStmt->fetchColumn();
            }
            $subtotal += $qty * $unitPrice;
        }

        $this->db()->beginTransaction();
        try {
            // Create credit memo as negative invoice
            $cmNumber = 'CM-' . $rma['rma_number'];
            $this->db()->prepare("
                INSERT INTO invoices (invoice_number, shipment_id, customer_id, so_id, invoice_date, due_date, subtotal, total_due, status, internal_notes)
                VALUES (?, 0, ?, ?, CURDATE(), CURDATE(), ?, ?, 'OPEN', ?)
            ")->execute([
                $cmNumber, $rma['customer_id'], $rma['so_id'] ?: null,
                -$subtotal, -$subtotal,
                'Credit Memo for RMA ' . $rma['rma_number'] . '. Reason: ' . $rma['return_reason'],
            ]);
            $cmId = (int)$this->db()->lastInsertId();

            // Generate credit memo PDF
            $html = $this->buildCreditMemoPdf($rma, $lines, $subtotal);
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();
            $pdfPath = sys_get_temp_dir() . "/{$cmNumber}.pdf";
            file_put_contents($pdfPath, $dompdf->output());

            // Email
            $contactStmt = $this->db()->prepare("SELECT email FROM customer_contacts WHERE customer_id = ? AND active = 1 AND email IS NOT NULL ORDER BY FIELD(contact_type, 'BILLING', 'GENERAL') ASC, is_primary DESC LIMIT 1");
            $contactStmt->execute([$rma['customer_id']]);
            $email = $contactStmt->fetchColumn();

            if ($email && $this->emailService) {
                $this->emailService->send('credit_memo', [$email],
                    ['rma_number' => $rma['rma_number'], 'customer_name' => $rma['customer_name'], 'credit_amount' => number_format($subtotal, 2)],
                    $pdfPath, "{$cmNumber}.pdf", 'rma', (int)$id);
            }
            @unlink($pdfPath);

            $this->db()->prepare("UPDATE rma_orders SET status='CLOSED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
            $this->db()->commit();

            $this->auditLog('UPDATE', 'rma_orders', (int)$id, ['status' => 'RECEIVED'], ['status' => 'CLOSED']);
            $this->auditCreate('invoices', $cmId, ['invoice_number' => $cmNumber, 'type' => 'CREDIT_MEMO']);
            $this->toast("RMA closed. Credit memo {$cmNumber} created (\${$subtotal}).", 'success');
            $this->redirect("/rma/{$id}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/rma/{$id}");
        }
    }

    public function cancel(string $id): void
    {
        if (!$this->checkPermission('rma', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $rma = $this->getOrFail((int)$id);
        if (in_array($rma['status'], ['CLOSED', 'CANCELLED'])) { $this->toast('Cannot cancel.', 'error'); $this->redirect("/rma/{$id}"); return; }

        $this->db()->prepare("UPDATE rma_orders SET status='CANCELLED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'rma_orders', (int)$id, ['status' => $rma['status']], ['status' => 'CANCELLED']);
        $this->toast("RMA {$rma['rma_number']} cancelled.", 'success');
        $this->redirect("/rma/{$id}");
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT r.*, c.customer_code, c.company_name as customer_name,
                   c.billing_street, c.billing_city, c.billing_state, c.billing_zip,
                   f.name as facility_name, so.so_number
            FROM rma_orders r
            LEFT JOIN customers c ON r.customer_id = c.id
            LEFT JOIN facilities f ON r.facility_id = f.id
            LEFT JOIN sales_orders so ON r.so_id = so.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if (!$r) { http_response_code(404); echo 'RMA not found.'; exit; }
        return $r;
    }

    private function getLines(int $rmaId): array
    {
        $stmt = $this->db()->prepare("
            SELECT rl.*, i.item_code, i.description as item_description, pe.name as pack_name
            FROM rma_lines rl
            JOIN items i ON rl.item_id = i.id
            LEFT JOIN item_pack_extensions pe ON rl.pack_extension_id = pe.id
            WHERE rl.rma_id = ? ORDER BY rl.id
        ");
        $stmt->execute([$rmaId]);
        return $stmt->fetchAll();
    }

    private function extractHeader(): array
    {
        $userId = $this->currentUserId();
        $active = $this->facilityService ? $this->facilityService->getActiveFacility($userId) : null;
        return [
            'customer_id' => (int)($_POST['customer_id'] ?? 0),
            'so_id' => (int)($_POST['so_id'] ?? 0),
            'rma_date' => $_POST['rma_date'] ?? date('Y-m-d'),
            'return_reason' => trim($_POST['return_reason'] ?? ''),
            'facility_id' => (int)($_POST['facility_id'] ?? ($active['id'] ?? 1)),
            'notes' => trim($_POST['notes'] ?? ''),
        ];
    }

    private function extractLines(): array
    {
        $lines = [];
        foreach ($_POST['lines'] ?? [] as $l) {
            $itemId = (int)($l['item_id'] ?? 0);
            $qty = (float)($l['authorized_quantity'] ?? 0);
            if (!$itemId || $qty <= 0) continue;
            $lines[] = [
                'item_id' => $itemId,
                'pack_extension_id' => (int)($l['pack_extension_id'] ?? 0) ?: null,
                'authorized_quantity' => $qty,
                'lot_number' => trim($l['lot_number'] ?? ''),
                'disposition' => $l['disposition'] ?? 'HOLD_FOR_INSPECTION',
                'condition_notes' => trim($l['condition_notes'] ?? ''),
            ];
        }
        return $lines;
    }

    private function formData(string $mode, ?array $rma = null, ?array $lines = null): array
    {
        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);
        $active = $this->facilityService->getActiveFacility($userId);
        return [
            'mode' => $mode, 'rma' => $rma, 'lines' => $lines ?? [],
            'facilities' => $facilities, 'activeFacilityId' => (int)($active['id'] ?? 0),
            'reasons' => $this->db()->query("SELECT id, name FROM rma_return_reasons WHERE active=1 ORDER BY name")->fetchAll(),
            'uoms' => $this->db()->query("SELECT id, abbreviation FROM uom WHERE active=1 ORDER BY abbreviation")->fetchAll(),
        ];
    }

    private function buildCreditMemoPdf(array $rma, array $lines, float $subtotal): string
    {
        $lineRows = '';
        $n = 1;
        foreach ($lines as $l) {
            $qty = (float)($l['received_quantity'] ?? $l['authorized_quantity']);
            $lineRows .= '<tr><td>'.$n++.'</td><td>'.htmlspecialchars($l['item_code']).'</td><td>'.htmlspecialchars($l['item_description']).'</td><td style="text-align:right;">'.number_format($qty, 4).'</td><td>'.htmlspecialchars($l['disposition']).'</td></tr>';
        }

        $addr = implode(', ', array_filter([$rma['billing_street'] ?? '', $rma['billing_city'] ?? '', $rma['billing_state'] ?? '', $rma['billing_zip'] ?? '']));

        return '<!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif;font-size:12px;margin:30px;}h1{font-size:20px;color:#dc2626;}table{width:100%;border-collapse:collapse;margin:12px 0;}th,td{border:1px solid #ccc;padding:5px 8px;text-align:left;font-size:11px;}th{background:#f0f0f0;}.label{font-weight:bold;color:#555;font-size:10px;text-transform:uppercase;}</style></head><body>'
            .'<h1>CREDIT MEMO</h1>'
            .'<p style="font-size:16px;font-weight:bold;">CM-'.htmlspecialchars($rma['rma_number']).'</p>'
            .'<table style="border:none;margin-bottom:16px;"><tr><td style="border:none;width:50%;"><p class="label">Customer</p><p><strong>'.htmlspecialchars($rma['customer_name'] ?? '').'</strong></p><p>'.htmlspecialchars($addr).'</p></td><td style="border:none;width:50%;"><p class="label">RMA Reference</p><p>'.htmlspecialchars($rma['rma_number']).'</p><p class="label">Reason</p><p>'.htmlspecialchars($rma['return_reason']).'</p></td></tr></table>'
            .'<table><thead><tr><th>#</th><th>Item</th><th>Description</th><th style="text-align:right;">Qty</th><th>Disposition</th></tr></thead><tbody>'.$lineRows.'</tbody></table>'
            .'<p style="font-size:14px;font-weight:bold;margin-top:16px;">Total Credit: $'.number_format($subtotal, 2).'</p>'
            .'</body></html>';
    }
}
