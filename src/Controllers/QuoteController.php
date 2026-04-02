<?php

namespace PrecisionInk\Controllers;

class QuoteController extends BaseController
{
    // ── List ────────────────────────────────────────────────────────

    public function index(): void
    {
        if (!$this->checkPermission('quotes', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterStatus = $_GET['status'] ?? '';
        $filterSearch = trim($_GET['q'] ?? '');

        $where = ['q.deleted_at IS NULL'];
        $params = [];
        if ($filterStatus) { $where[] = 'q.status = ?'; $params[] = $filterStatus; }
        if ($filterSearch) { $where[] = '(q.quote_number LIKE ? OR c.company_name LIKE ?)'; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; }
        $whereClause = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM quotes q LEFT JOIN customers c ON q.customer_id = c.id WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT q.*, c.customer_code, c.company_name as customer_name,
                   u.full_name as created_by_name,
                   COALESCE((SELECT SUM(ql.quantity * ql.unit_price) FROM quote_lines ql WHERE ql.quote_id = q.id), 0) as total_value
            FROM quotes q
            LEFT JOIN customers c ON q.customer_id = c.id
            LEFT JOIN users u ON q.created_by = u.id
            WHERE {$whereClause}
            ORDER BY q.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $this->renderView('quotes/list', [
            'quotes' => $stmt->fetchAll(),
            'filters' => ['status' => $filterStatus, 'q' => $filterSearch],
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    // ── Create ──────────────────────────────────────────────────────

    public function create(): void
    {
        if (!$this->checkPermission('quotes', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $expDays = $this->getSetting('quote_expiration_days', '30');
        $this->renderView('quotes/edit', $this->formData('create', ['expiration_date' => date('Y-m-d', strtotime("+{$expDays} days"))]));
    }

    public function store(): void
    {
        if (!$this->checkPermission('quotes', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $data = $this->extractHeader();
        $lines = $this->extractLines();

        if (!$data['customer_id'] || empty($lines)) {
            $this->toast('Customer and at least one line required.', 'error');
            $this->renderView('quotes/edit', $this->formData('create', $data, $lines));
            return;
        }

        $quoteNumber = $this->generateDocumentNumber('QUOTE');
        $userId = $this->currentUserId();

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("
                INSERT INTO quotes (quote_number, customer_id, ship_to_id, quote_date, expiration_date, notes, soft_reserve, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $quoteNumber, $data['customer_id'], $data['ship_to_id'] ?: null,
                $data['quote_date'], $data['expiration_date'], $data['notes'] ?: null,
                $data['soft_reserve'], $userId,
            ]);
            $quoteId = (int)$this->db()->lastInsertId();

            $this->insertLines($quoteId, $lines);

            // Soft reservations
            if ($data['soft_reserve'] && $this->reservationService) {
                $facilityId = $this->getActiveFacilityId();
                foreach ($lines as $l) {
                    $this->reservationService->reserve((int)$l['item_id'], $facilityId, (float)$l['quantity'], 'QUOTE', $quoteId);
                }
            }

            $this->db()->commit();
            $this->auditCreate('quotes', $quoteId, ['quote_number' => $quoteNumber]);
            $this->toast("Quote {$quoteNumber} created.", 'success');
            $this->redirect("/quotes/{$quoteId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect('/quotes/create');
        }
    }

    // ── View ────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        if (!$this->checkPermission('quotes', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $quote = $this->getOrFail((int)$id);
        $lines = $this->getLines((int)$id);
        $attachments = $this->attachmentService ? $this->attachmentService->getForRecord('quote', (int)$id) : [];
        $lostReasons = $this->db()->query("SELECT id, name FROM lost_quote_reasons WHERE active = 1 ORDER BY name")->fetchAll();

        $this->renderView('quotes/view', [
            'quote' => $quote, 'lines' => $lines, 'attachments' => $attachments,
            'lostReasons' => $lostReasons, 'record' => $quote,
        ]);
    }

    // ── Edit ────────────────────────────────────────────────────────

    public function editForm(string $id): void
    {
        if (!$this->checkPermission('quotes', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $quote = $this->getOrFail((int)$id);
        if (!in_array($quote['status'], ['DRAFT', 'SENT'])) { $this->toast('Only DRAFT or SENT quotes can be edited.', 'error'); $this->redirect("/quotes/{$id}"); return; }
        $lines = $this->getLines((int)$id);
        $this->renderView('quotes/edit', $this->formData('edit', $quote, $lines));
    }

    public function update(string $id): void
    {
        if (!$this->checkPermission('quotes', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $quote = $this->getOrFail((int)$id);
        if (!in_array($quote['status'], ['DRAFT', 'SENT'])) { $this->toast('Cannot edit.', 'error'); $this->redirect("/quotes/{$id}"); return; }

        $data = $this->extractHeader();
        $lines = $this->extractLines();

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("
                UPDATE quotes SET customer_id=?, ship_to_id=?, quote_date=?, expiration_date=?, notes=?, soft_reserve=?, updated_at=NOW() WHERE id=?
            ")->execute([
                $data['customer_id'], $data['ship_to_id'] ?: null,
                $data['quote_date'], $data['expiration_date'], $data['notes'] ?: null,
                $data['soft_reserve'], (int)$id,
            ]);

            $this->db()->prepare("DELETE FROM quote_lines WHERE quote_id = ?")->execute([(int)$id]);
            $this->insertLines((int)$id, $lines);

            // Re-handle reservations
            if ($this->reservationService) {
                $this->reservationService->releaseAllForReference('QUOTE', (int)$id);
                if ($data['soft_reserve']) {
                    $facilityId = $this->getActiveFacilityId();
                    foreach ($lines as $l) {
                        $this->reservationService->reserve((int)$l['item_id'], $facilityId, (float)$l['quantity'], 'QUOTE', (int)$id);
                    }
                }
            }

            $this->db()->commit();
            $this->auditUpdate('quotes', (int)$id, $quote, $data);
            $this->toast('Quote updated.', 'success');
            $this->redirect("/quotes/{$id}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/quotes/{$id}/edit");
        }
    }

    // ── Send ────────────────────────────────────────────────────────

    public function send(string $id): void
    {
        if (!$this->checkPermission('quotes', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $quote = $this->getOrFail((int)$id);
        if ($quote['status'] !== 'DRAFT') { $this->toast('Only DRAFT quotes can be sent.', 'error'); $this->redirect("/quotes/{$id}"); return; }

        $lines = $this->getLines((int)$id);
        $html = $this->buildQuotePdfHtml($quote, $lines);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $pdfPath = sys_get_temp_dir() . '/' . $quote['quote_number'] . '.pdf';
        file_put_contents($pdfPath, $dompdf->output());

        // Store as attachment
        if ($this->attachmentService) {
            try {
                $this->attachmentService->upload('quote', (int)$id, [
                    'name' => $quote['quote_number'] . '.pdf', 'type' => 'application/pdf',
                    'tmp_name' => $pdfPath, 'error' => 0, 'size' => filesize($pdfPath),
                ], 'Quote PDF sent to customer', $this->currentUserId());
            } catch (\Throwable $e) {}
        }

        // Email to customer contact
        $contactStmt = $this->db()->prepare("
            SELECT email FROM customer_contacts
            WHERE customer_id = ? AND active = 1 AND email IS NOT NULL AND email != ''
            ORDER BY FIELD(contact_type, 'GENERAL', 'BILLING') ASC, is_primary DESC LIMIT 1
        ");
        $contactStmt->execute([$quote['customer_id']]);
        $email = $contactStmt->fetchColumn();

        if ($email && $this->emailService) {
            $this->emailService->send('quote', [$email],
                ['quote_number' => $quote['quote_number'], 'customer_name' => $quote['customer_name'], 'expiration_date' => $quote['expiration_date']],
                $pdfPath, $quote['quote_number'] . '.pdf', 'quote', (int)$id);
        }

        $this->db()->prepare("UPDATE quotes SET status='SENT', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        @unlink($pdfPath);

        $this->auditLog('UPDATE', 'quotes', (int)$id, ['status' => 'DRAFT'], ['status' => 'SENT']);
        $this->toast("Quote {$quote['quote_number']} sent." . (!$email ? ' Warning: no customer contact email found.' : ''), $email ? 'success' : 'warning');
        $this->redirect("/quotes/{$id}");
    }

    // ── Accept / Decline / Convert ──────────────────────────────────

    public function accept(string $id): void
    {
        if (!$this->checkPermission('quotes', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $quote = $this->getOrFail((int)$id);
        if ($quote['status'] !== 'SENT') { $this->toast('Only SENT quotes can be accepted.', 'error'); $this->redirect("/quotes/{$id}"); return; }

        $this->db()->prepare("UPDATE quotes SET status='ACCEPTED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'quotes', (int)$id, ['status' => 'SENT'], ['status' => 'ACCEPTED']);
        $this->toast("Quote {$quote['quote_number']} accepted.", 'success');
        $this->redirect("/quotes/{$id}");
    }

    public function decline(string $id): void
    {
        if (!$this->checkPermission('quotes', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $quote = $this->getOrFail((int)$id);
        if ($quote['status'] !== 'SENT') { $this->toast('Only SENT quotes can be declined.', 'error'); $this->redirect("/quotes/{$id}"); return; }

        $lostReason = trim($_POST['lost_reason'] ?? '');
        $lostNotes = trim($_POST['lost_notes'] ?? '');

        if (!$lostReason) { $this->toast('Lost reason is required.', 'error'); $this->redirect("/quotes/{$id}"); return; }

        $this->db()->prepare("UPDATE quotes SET status='DECLINED', lost_reason=?, lost_notes=?, updated_at=NOW() WHERE id=?")
            ->execute([$lostReason, $lostNotes ?: null, (int)$id]);

        if ($this->reservationService) {
            $this->reservationService->releaseAllForReference('QUOTE', (int)$id);
        }

        $this->auditLog('UPDATE', 'quotes', (int)$id, ['status' => 'SENT'], ['status' => 'DECLINED']);
        $this->toast("Quote {$quote['quote_number']} declined.", 'warning');
        $this->redirect("/quotes/{$id}");
    }

    public function convert(string $id): void
    {
        if (!$this->checkPermission('quotes', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $quote = $this->getOrFail((int)$id);
        if ($quote['status'] !== 'ACCEPTED') { $this->toast('Only ACCEPTED quotes can be converted.', 'error'); $this->redirect("/quotes/{$id}"); return; }

        $lines = $this->getLines((int)$id);

        $this->db()->beginTransaction();
        try {
            $soNumber = $this->generateDocumentNumber('SALES_ORDER');
            $userId = $this->currentUserId();

            $this->db()->prepare("
                INSERT INTO sales_orders (so_number, customer_id, ship_to_id, facility_id, order_date, status, sales_rep_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, CURDATE(), 'DRAFT', ?, NOW(), NOW())
            ")->execute([
                $soNumber, $quote['customer_id'], $quote['ship_to_id'] ?: null,
                $this->getActiveFacilityId(),
                $quote['created_by'],
            ]);
            $soId = (int)$this->db()->lastInsertId();

            $lineInsert = $this->db()->prepare("
                INSERT INTO sales_order_lines (so_id, item_id, pack_extension_id, ordered_quantity, uom_id, unit_price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($lines as $l) {
                $lineInsert->execute([$soId, $l['item_id'], $l['pack_extension_id'], $l['quantity'], $l['uom_id'], $l['unit_price']]);
            }

            $this->db()->prepare("UPDATE quotes SET status='CONVERTED', converted_so_id=?, updated_at=NOW() WHERE id=?")
                ->execute([$soId, (int)$id]);

            if ($this->reservationService) {
                $this->reservationService->releaseAllForReference('QUOTE', (int)$id);
            }

            $this->db()->commit();
            $this->auditLog('UPDATE', 'quotes', (int)$id, ['status' => 'ACCEPTED'], ['status' => 'CONVERTED', 'so_id' => $soId]);
            $this->auditCreate('sales_orders', $soId, ['so_number' => $soNumber, 'from_quote' => $quote['quote_number']]);
            $this->toast("Converted to SO {$soNumber}.", 'success');
            $this->redirect("/sales-orders/{$soId}/edit");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/quotes/{$id}");
        }
    }

    // ── Clone ───────────────────────────────────────────────────────

    public function cloneQuote(string $id): void
    {
        if (!$this->checkPermission('quotes', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $quote = $this->getOrFail((int)$id);
        $lines = $this->getLines((int)$id);

        $quoteNumber = $this->generateDocumentNumber('QUOTE');
        $expDays = $this->getSetting('quote_expiration_days', '30');

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("
                INSERT INTO quotes (quote_number, customer_id, ship_to_id, quote_date, expiration_date, notes, soft_reserve, created_by)
                VALUES (?, ?, ?, CURDATE(), ?, ?, 0, ?)
            ")->execute([
                $quoteNumber, $quote['customer_id'], $quote['ship_to_id'],
                date('Y-m-d', strtotime("+{$expDays} days")), $quote['notes'], $this->currentUserId(),
            ]);
            $newId = (int)$this->db()->lastInsertId();

            $lineInsert = $this->db()->prepare("INSERT INTO quote_lines (quote_id, item_id, pack_extension_id, quantity, uom_id, unit_price, notes) VALUES (?,?,?,?,?,?,?)");
            foreach ($lines as $l) {
                $lineInsert->execute([$newId, $l['item_id'], $l['pack_extension_id'], $l['quantity'], $l['uom_id'], $l['unit_price'], $l['notes']]);
            }

            $this->db()->commit();
            $this->auditCreate('quotes', $newId, ['quote_number' => $quoteNumber, 'cloned_from' => $quote['quote_number']]);
            $this->toast("Cloned as {$quoteNumber}.", 'success');
            $this->redirect("/quotes/{$newId}/edit");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/quotes/{$id}");
        }
    }

    // ── Pricing AJAX ────────────────────────────────────────────────

    public function priceCheck(): void
    {
        $itemId = (int)($_GET['item_id'] ?? 0);
        $customerId = (int)($_GET['customer_id'] ?? 0);
        $qty = (float)($_GET['qty'] ?? 1);

        if (!$itemId) { $this->jsonResponse(['unit_price' => 0, 'moq' => null]); return; }

        $pricing = new \App\Services\PricingService($this->db());

        if ($customerId) {
            $result = $pricing->resolveCustomerPrice($itemId, $customerId, $qty);
            $moq = $pricing->checkMOQ($itemId, $customerId, $qty);
        } else {
            $legacyPrice = $pricing->resolvePrice($itemId, null, $qty);
            $result = ['price' => $legacyPrice, 'source' => 'manual', 'list_name' => null,
                        'package_type' => null, 'qty_per_package' => null, 'external_code' => null];
            $moq = null;
        }

        $this->jsonResponse([
            'unit_price' => $result['price'],
            'source' => $result['source'],
            'list_name' => $result['list_name'],
            'package_type' => $result['package_type'],
            'qty_per_package' => $result['qty_per_package'],
            'external_code' => $result['external_code'],
            'moq' => $moq,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT q.*, c.customer_code, c.company_name as customer_name, c.account_hold,
                   c.billing_street, c.billing_city, c.billing_state, c.billing_zip,
                   u.full_name as created_by_name
            FROM quotes q
            LEFT JOIN customers c ON q.customer_id = c.id
            LEFT JOIN users u ON q.created_by = u.id
            WHERE q.id = ? AND q.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $q = $stmt->fetch();
        if (!$q) { http_response_code(404); echo 'Quote not found.'; exit; }
        return $q;
    }

    private function getLines(int $quoteId): array
    {
        $stmt = $this->db()->prepare("
            SELECT ql.*, i.item_code, i.description as item_description, u.abbreviation as uom_abbr, pe.name as pack_name
            FROM quote_lines ql
            JOIN items i ON ql.item_id = i.id
            LEFT JOIN uom u ON ql.uom_id = u.id
            LEFT JOIN item_pack_extensions pe ON ql.pack_extension_id = pe.id
            WHERE ql.quote_id = ?
            ORDER BY ql.id
        ");
        $stmt->execute([$quoteId]);
        return $stmt->fetchAll();
    }

    private function extractHeader(): array
    {
        return [
            'customer_id' => (int)($_POST['customer_id'] ?? 0),
            'ship_to_id' => (int)($_POST['ship_to_id'] ?? 0),
            'quote_date' => $_POST['quote_date'] ?? date('Y-m-d'),
            'expiration_date' => $_POST['expiration_date'] ?? date('Y-m-d', strtotime('+30 days')),
            'notes' => trim($_POST['notes'] ?? ''),
            'soft_reserve' => isset($_POST['soft_reserve']) ? 1 : 0,
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
                'quantity' => $qty,
                'uom_id' => (int)($l['uom_id'] ?? 0),
                'unit_price' => (float)($l['unit_price'] ?? 0),
                'notes' => trim($l['notes'] ?? '') ?: null,
            ];
        }
        return $lines;
    }

    private function insertLines(int $quoteId, array $lines): void
    {
        $stmt = $this->db()->prepare("INSERT INTO quote_lines (quote_id, item_id, pack_extension_id, quantity, uom_id, unit_price, notes) VALUES (?,?,?,?,?,?,?)");
        foreach ($lines as $l) {
            $stmt->execute([$quoteId, $l['item_id'], $l['pack_extension_id'], $l['quantity'], $l['uom_id'], $l['unit_price'], $l['notes']]);
        }
    }

    private function formData(string $mode, ?array $quote = null, ?array $lines = null): array
    {
        return [
            'mode' => $mode, 'quote' => $quote, 'lines' => $lines ?? [],
            'uoms' => $this->db()->query("SELECT id, abbreviation FROM uom WHERE active=1 ORDER BY abbreviation")->fetchAll(),
        ];
    }

    private function getActiveFacilityId(): int
    {
        $userId = $this->currentUserId();
        $active = $this->facilityService ? $this->facilityService->getActiveFacility($userId) : null;
        return (int)($active['id'] ?? 1);
    }

    private function getSetting(string $key, string $default = ''): string
    {
        $stmt = $this->db()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn() ?: $default;
    }

    private function buildQuotePdfHtml(array $quote, array $lines): string
    {
        $total = 0;
        $lineRows = '';
        $n = 1;
        foreach ($lines as $l) {
            $ext = (float)$l['quantity'] * (float)$l['unit_price'];
            $total += $ext;
            $lineRows .= '<tr><td>'.$n++.'</td><td>'.htmlspecialchars($l['item_code']).'</td><td>'.htmlspecialchars($l['item_description']).'</td><td>'.htmlspecialchars($l['pack_name']??'').'</td><td style="text-align:right;">'.number_format((float)$l['quantity'],4).'</td><td>'.htmlspecialchars($l['uom_abbr']??'').'</td><td style="text-align:right;">$'.number_format((float)$l['unit_price'],4).'</td><td style="text-align:right;">$'.number_format($ext,2).'</td></tr>';
        }

        return '<!DOCTYPE html><html><head><style>
            body{font-family:Arial,sans-serif;font-size:12px;margin:30px;}
            h1{font-size:20px;margin-bottom:4px;}
            h2{font-size:12px;color:#dc2626;margin-bottom:16px;}
            table{width:100%;border-collapse:collapse;margin:12px 0;}
            th,td{border:1px solid #ccc;padding:5px 8px;text-align:left;font-size:11px;}
            th{background:#f0f0f0;}
            .total-row td{font-weight:bold;border-top:2px solid #333;}
            .label{font-weight:bold;color:#555;font-size:10px;text-transform:uppercase;}
        </style></head><body>
            <h2>QUOTE — Not a Tax Invoice</h2>
            <h1>Quote '.htmlspecialchars($quote['quote_number']).'</h1>
            <table style="border:none;margin-bottom:16px;"><tr>
                <td style="border:none;width:50%;vertical-align:top;"><p class="label">Customer</p><p><strong>'.htmlspecialchars($quote['customer_name']??'').'</strong></p><p>'.htmlspecialchars($quote['customer_code']??'').'</p></td>
                <td style="border:none;width:50%;vertical-align:top;"><p class="label">Quote Date</p><p>'.date('M j, Y',strtotime($quote['quote_date'])).'</p><p class="label">Expires</p><p>'.date('M j, Y',strtotime($quote['expiration_date'])).'</p></td>
            </tr></table>
            <table><thead><tr><th>#</th><th>Item</th><th>Description</th><th>Pack</th><th style="text-align:right;">Qty</th><th>UOM</th><th style="text-align:right;">Price</th><th style="text-align:right;">Extended</th></tr></thead><tbody>'.$lineRows.'<tr class="total-row"><td colspan="7" style="text-align:right;">Total</td><td style="text-align:right;">$'.number_format($total,2).'</td></tr></tbody></table>'
            .($quote['notes']?'<p style="margin-top:12px;"><strong>Notes:</strong> '.htmlspecialchars($quote['notes']).'</p>':'')
            .'</body></html>';
    }
}
