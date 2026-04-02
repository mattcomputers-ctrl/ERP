<?php

namespace PrecisionInk\Controllers;

class InvoiceController extends BaseController
{
    public function index(): void
    {
        if (!$this->checkPermission('invoices', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterStatus = $_GET['status'] ?? '';
        $filterSearch = trim($_GET['q'] ?? '');
        $filterOverdue = isset($_GET['overdue']);

        $where = [];
        $params = [];
        if ($filterStatus) { $where[] = 'i.status = ?'; $params[] = $filterStatus; }
        if ($filterSearch) { $where[] = '(i.invoice_number LIKE ? OR c.company_name LIKE ?)'; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; }
        if ($filterOverdue) { $where[] = "i.status = 'OPEN' AND i.due_date < CURDATE()"; }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT i.*, c.customer_code, c.company_name as customer_name, u.full_name as rep_name,
                   DATEDIFF(CURDATE(), i.due_date) as days_outstanding
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            LEFT JOIN users u ON i.sales_rep_id = u.id
            {$whereClause}
            ORDER BY i.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $this->renderView('invoices/list', [
            'invoices' => $stmt->fetchAll(),
            'filters' => ['status' => $filterStatus, 'q' => $filterSearch, 'overdue' => $filterOverdue],
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    public function show(string $id): void
    {
        if (!$this->checkPermission('invoices', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $invoice = $this->getOrFail((int)$id);

        $lines = $this->db()->prepare("
            SELECT sl.*, i.item_code, i.description as item_description, pe.name as pack_name, u.abbreviation as uom_abbr
            FROM shipment_lines sl
            JOIN sales_order_lines sol ON sl.so_line_id = sol.id
            JOIN items i ON sol.item_id = i.id
            LEFT JOIN item_pack_extensions pe ON sol.pack_extension_id = pe.id
            LEFT JOIN uom u ON sol.uom_id = u.id
            WHERE sl.shipment_id = ?
        ");
        $lines->execute([$invoice['shipment_id']]);

        $editHistory = $this->db()->prepare("SELECT ieh.*, u.full_name as edited_by_name FROM invoice_edit_history ieh LEFT JOIN users u ON ieh.edited_by = u.id WHERE ieh.invoice_id = ? ORDER BY ieh.created_at DESC");
        $editHistory->execute([(int)$id]);

        $this->renderView('invoices/view', [
            'invoice' => $invoice, 'lines' => $lines->fetchAll(),
            'editHistory' => $editHistory->fetchAll(), 'record' => $invoice,
        ]);
    }

    public function pdf(string $id): void
    {
        if (!$this->checkPermission('invoices', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $invoice = $this->getOrFail((int)$id);
        $lines = $this->db()->prepare("SELECT sl.*, i.item_code, i.description as item_description, pe.name as pack_name, u.abbreviation as uom_abbr FROM shipment_lines sl JOIN sales_order_lines sol ON sl.so_line_id = sol.id JOIN items i ON sol.item_id = i.id LEFT JOIN item_pack_extensions pe ON sol.pack_extension_id = pe.id LEFT JOIN uom u ON sol.uom_id = u.id WHERE sl.shipment_id = ?");
        $lines->execute([$invoice['shipment_id']]);

        $html = $this->buildInvoicePdf($invoice, $lines->fetchAll());
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $dompdf->stream("INV_{$invoice['invoice_number']}.pdf", ['Attachment' => false]);
        exit;
    }

    public function email(string $id): void
    {
        if (!$this->checkPermission('invoices', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $invoice = $this->getOrFail((int)$id);

        $lines = $this->db()->prepare("SELECT sl.*, i.item_code, i.description as item_description, pe.name as pack_name, u.abbreviation as uom_abbr FROM shipment_lines sl JOIN sales_order_lines sol ON sl.so_line_id = sol.id JOIN items i ON sol.item_id = i.id LEFT JOIN item_pack_extensions pe ON sol.pack_extension_id = pe.id LEFT JOIN uom u ON sol.uom_id = u.id WHERE sl.shipment_id = ?");
        $lines->execute([$invoice['shipment_id']]);

        $html = $this->buildInvoicePdf($invoice, $lines->fetchAll());
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $pdfPath = sys_get_temp_dir() . "/INV_{$invoice['invoice_number']}.pdf";
        file_put_contents($pdfPath, $dompdf->output());

        $contactStmt = $this->db()->prepare("SELECT email FROM customer_contacts WHERE customer_id = ? AND active = 1 AND email IS NOT NULL ORDER BY FIELD(contact_type, 'BILLING', 'GENERAL') ASC, is_primary DESC LIMIT 1");
        $contactStmt->execute([$invoice['customer_id']]);
        $email = $contactStmt->fetchColumn();

        if ($email && $this->emailService) {
            $this->emailService->send('invoice', [$email], ['invoice_number' => $invoice['invoice_number'], 'customer_name' => $invoice['customer_name'], 'total_due' => $invoice['total_due']], $pdfPath, "INV_{$invoice['invoice_number']}.pdf", 'invoice', (int)$id);
        }
        @unlink($pdfPath);
        $this->toast("Invoice emailed." . (!$email ? ' No billing contact found.' : ''), $email ? 'success' : 'warning');
        $this->redirect("/invoices/{$id}");
    }

    public function edit(string $id): void
    {
        if (!$this->checkPermission('invoices', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $invoice = $this->getOrFail((int)$id);
        if ($invoice['status'] !== 'OPEN') { $this->toast('Cannot edit non-OPEN invoices.', 'error'); $this->redirect("/invoices/{$id}"); return; }

        if (!$this->hasSpecialPermission('edit_invoice_header') && !$this->hasSpecialPermission('edit_invoice_lines')) {
            $this->toast('Permission denied.', 'error'); $this->redirect("/invoices/{$id}"); return;
        }

        $oldData = $invoice;
        $changes = [];

        // Header edits
        if ($this->hasSpecialPermission('edit_invoice_header')) {
            $fields = ['due_date', 'internal_notes', 'external_notes'];
            foreach ($fields as $f) {
                $newVal = trim($_POST[$f] ?? $invoice[$f] ?? '');
                if ($newVal !== ($invoice[$f] ?? '')) {
                    $changes[] = ['field' => $f, 'old' => $invoice[$f], 'new' => $newVal];
                }
            }
            $this->db()->prepare("UPDATE invoices SET due_date=?, internal_notes=?, external_notes=?, last_edited_by=?, last_edited_at=NOW(), updated_at=NOW() WHERE id=?")
                ->execute([
                    $_POST['due_date'] ?? $invoice['due_date'],
                    trim($_POST['internal_notes'] ?? $invoice['internal_notes'] ?? ''),
                    trim($_POST['external_notes'] ?? $invoice['external_notes'] ?? ''),
                    $this->currentUserId(), (int)$id,
                ]);
        }

        // Line edits (freight)
        if ($this->hasSpecialPermission('edit_invoice_lines') && isset($_POST['freight_amount'])) {
            $newFreight = (float)$_POST['freight_amount'];
            if ($newFreight !== (float)$invoice['freight_amount']) {
                $changes[] = ['field' => 'freight_amount', 'old' => $invoice['freight_amount'], 'new' => $newFreight];
            }
            $newTotal = (float)$invoice['subtotal'] + $newFreight - (float)$invoice['deposit_amount'];
            $this->db()->prepare("UPDATE invoices SET freight_amount=?, total_due=?, last_edited_by=?, last_edited_at=NOW(), updated_at=NOW() WHERE id=?")
                ->execute([$newFreight, max(0, $newTotal), $this->currentUserId(), (int)$id]);
        }

        if (!empty($changes)) {
            $this->db()->prepare("INSERT INTO invoice_edit_history (invoice_id, edited_by, field_changes) VALUES (?, ?, ?)")
                ->execute([(int)$id, $this->currentUserId(), json_encode($changes)]);
            $this->auditLog('UPDATE', 'invoices', (int)$id, $oldData, $changes);
        }

        $this->toast('Invoice updated.', 'success');
        $this->redirect("/invoices/{$id}");
    }

    public function void(string $id): void
    {
        if (!$this->hasSpecialPermission('void_invoice')) { $this->toast('Permission denied.', 'error'); $this->redirect("/invoices/{$id}"); return; }
        $invoice = $this->getOrFail((int)$id);
        if ($invoice['status'] !== 'OPEN') { $this->toast('Only OPEN invoices can be voided.', 'error'); $this->redirect("/invoices/{$id}"); return; }

        $reason = trim($_POST['void_reason'] ?? '');
        if (!$reason) { $this->toast('Void reason required.', 'error'); $this->redirect("/invoices/{$id}"); return; }

        $this->db()->prepare("UPDATE invoices SET status='VOID', void_reason=?, last_edited_by=?, last_edited_at=NOW(), updated_at=NOW() WHERE id=?")
            ->execute([$reason, $this->currentUserId(), (int)$id]);
        $this->auditLog('UPDATE', 'invoices', (int)$id, ['status' => 'OPEN'], ['status' => 'VOID', 'reason' => $reason]);
        $this->toast("Invoice {$invoice['invoice_number']} voided.", 'warning');
        $this->redirect("/invoices/{$id}");
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT i.*, c.customer_code, c.company_name as customer_name,
                   c.billing_street, c.billing_city, c.billing_state, c.billing_zip,
                   pt.name as payment_terms_name, sv.name as ship_via_name,
                   s.shipment_number, s.ship_date, s.tracking_number,
                   st.location_name as ship_to_name, st.street as st_street, st.city as st_city, st.state as st_state, st.zip as st_zip
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            LEFT JOIN payment_terms pt ON i.payment_terms_id = pt.id
            LEFT JOIN ship_via sv ON i.ship_via_id = sv.id
            LEFT JOIN shipments s ON i.shipment_id = s.id
            LEFT JOIN ship_to_locations st ON s.ship_to_id = st.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) { http_response_code(404); echo 'Invoice not found.'; exit; }
        return $inv;
    }

    private function buildInvoicePdf(array $inv, array $lines): string
    {
        $lineRows = '';
        $n = 1;
        foreach ($lines as $l) {
            $ext = (float)$l['quantity_shipped'] * (float)$l['unit_price'];
            $lineRows .= '<tr><td>'.$n++.'</td><td>'.htmlspecialchars($l['item_code']).'</td><td>'.htmlspecialchars($l['item_description']).'</td><td>'.htmlspecialchars($l['pack_name']??'').'</td><td style="text-align:right;">'.number_format((float)$l['quantity_shipped'],4).'</td><td>'.htmlspecialchars($l['uom_abbr']??'').'</td><td style="text-align:right;">$'.number_format((float)$l['unit_price'],4).'</td><td style="text-align:right;">$'.number_format($ext,2).'</td></tr>';
        }

        $billTo = implode(', ', array_filter([$inv['billing_street']??'',$inv['billing_city']??'',$inv['billing_state']??'',$inv['billing_zip']??'']));
        $shipTo = implode(', ', array_filter([$inv['st_street']??'',$inv['st_city']??'',$inv['st_state']??'',$inv['st_zip']??'']));

        return '<!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif;font-size:12px;margin:30px;}h1{font-size:20px;margin-bottom:4px;}table{width:100%;border-collapse:collapse;margin:12px 0;}th,td{border:1px solid #ccc;padding:5px 8px;text-align:left;font-size:11px;}th{background:#f0f0f0;}.total td{font-weight:bold;border-top:2px solid #333;}.label{font-weight:bold;color:#555;font-size:10px;text-transform:uppercase;}</style></head><body>'
            .'<h1>INVOICE</h1><p style="font-size:16px;font-weight:bold;">'.htmlspecialchars($inv['invoice_number']).'</p>'
            .'<table style="border:none;margin-bottom:16px;"><tr><td style="border:none;width:50%;vertical-align:top;"><p class="label">Bill To</p><p><strong>'.htmlspecialchars($inv['customer_name']??'').'</strong></p><p>'.htmlspecialchars($billTo).'</p></td><td style="border:none;width:50%;vertical-align:top;"><p class="label">Invoice Date</p><p>'.date('M j, Y',strtotime($inv['invoice_date'])).'</p><p class="label">Due Date</p><p>'.date('M j, Y',strtotime($inv['due_date'])).'</p>'.($inv['ship_to_name']?'<p class="label">Ship To</p><p>'.htmlspecialchars($inv['ship_to_name']).'</p><p>'.htmlspecialchars($shipTo).'</p>':'').'</td></tr></table>'
            .'<table><thead><tr><th>#</th><th>Item</th><th>Description</th><th>Pack</th><th style="text-align:right;">Qty</th><th>UOM</th><th style="text-align:right;">Price</th><th style="text-align:right;">Extended</th></tr></thead><tbody>'.$lineRows
            .'<tr class="total"><td colspan="7" style="text-align:right;">Subtotal</td><td style="text-align:right;">$'.number_format((float)$inv['subtotal'],2).'</td></tr>'
            .((float)$inv['freight_amount']>0?'<tr><td colspan="7" style="text-align:right;">Freight</td><td style="text-align:right;">$'.number_format((float)$inv['freight_amount'],2).'</td></tr>':'')
            .((float)$inv['deposit_amount']>0?'<tr><td colspan="7" style="text-align:right;">Less Deposit</td><td style="text-align:right;">-$'.number_format((float)$inv['deposit_amount'],2).'</td></tr>':'')
            .'<tr class="total"><td colspan="7" style="text-align:right;">TOTAL DUE</td><td style="text-align:right;font-size:14px;">$'.number_format((float)$inv['total_due'],2).'</td></tr>'
            .'</tbody></table>'
            .($inv['payment_terms_name']?'<p><strong>Payment Terms:</strong> '.htmlspecialchars($inv['payment_terms_name']).'</p>':'')
            .($inv['external_notes']?'<p><strong>Notes:</strong> '.htmlspecialchars($inv['external_notes']).'</p>':'')
            .'</body></html>';
    }
}
