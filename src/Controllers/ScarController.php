<?php

namespace PrecisionInk\Controllers;

class ScarController extends BaseController
{
    public function index(): void
    {
        if (!$this->checkPermission('qc', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterStatus = $_GET['status'] ?? '';
        $filterSupplier = trim($_GET['supplier'] ?? '');

        $where = [];
        $params = [];
        if ($filterStatus) { $where[] = 'sc.status = ?'; $params[] = $filterStatus; }
        if ($filterSupplier) { $where[] = '(s.supplier_code LIKE ? OR s.company_name LIKE ?)'; $params[] = "%{$filterSupplier}%"; $params[] = "%{$filterSupplier}%"; }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM scars sc LEFT JOIN suppliers s ON sc.supplier_id = s.id {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT sc.*, s.supplier_code, s.company_name as supplier_name, u.full_name as created_by_name
            FROM scars sc
            LEFT JOIN suppliers s ON sc.supplier_id = s.id
            LEFT JOIN users u ON sc.created_by = u.id
            {$whereClause}
            ORDER BY sc.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $this->renderView('scars/list', [
            'scars' => $stmt->fetchAll(),
            'filters' => ['status' => $filterStatus, 'supplier' => $filterSupplier],
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    public function create(): void
    {
        if (!$this->checkPermission('qc', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->renderView('scars/form', ['mode' => 'create', 'scar' => null]);
    }

    public function store(): void
    {
        if (!$this->checkPermission('qc', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $data = $this->extractData();
        if (!$data['supplier_id'] || !$data['description']) {
            $this->toast('Supplier and description are required.', 'error');
            $this->renderView('scars/form', ['mode' => 'create', 'scar' => $data]);
            return;
        }

        $scarNumber = $this->generateDocumentNumber('SCAR');

        $this->db()->prepare("
            INSERT INTO scars (scar_number, supplier_id, po_receipt_id, lot_number, issue_date, description, required_action, due_date, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'OPEN', ?)
        ")->execute([
            $scarNumber, $data['supplier_id'], $data['po_receipt_id'] ?: null, $data['lot_number'] ?: null,
            $data['issue_date'], $data['description'], $data['required_action'] ?: null,
            $data['due_date'] ?: null, $this->currentUserId(),
        ]);
        $id = (int)$this->db()->lastInsertId();

        $this->auditCreate('scars', $id, ['scar_number' => $scarNumber]);
        $this->toast("SCAR {$scarNumber} created.", 'success');
        $this->redirect("/scars/{$id}");
    }

    public function view(string $id): void
    {
        if (!$this->checkPermission('qc', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $scar = $this->getOrFail((int)$id);
        $this->renderView('scars/view', ['scar' => $scar, 'record' => $scar]);
    }

    public function editForm(string $id): void
    {
        if (!$this->checkPermission('qc', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $scar = $this->getOrFail((int)$id);
        if ($scar['status'] !== 'OPEN') { $this->toast('Only OPEN SCARs can be edited.', 'error'); $this->redirect("/scars/{$id}"); return; }
        $this->renderView('scars/form', ['mode' => 'edit', 'scar' => $scar]);
    }

    public function update(string $id): void
    {
        if (!$this->checkPermission('qc', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $scar = $this->getOrFail((int)$id);
        $data = $this->extractData();

        $this->db()->prepare("
            UPDATE scars SET supplier_id=?, po_receipt_id=?, lot_number=?, issue_date=?, description=?, required_action=?, due_date=?, updated_at=NOW()
            WHERE id=?
        ")->execute([
            $data['supplier_id'], $data['po_receipt_id'] ?: null, $data['lot_number'] ?: null,
            $data['issue_date'], $data['description'], $data['required_action'] ?: null,
            $data['due_date'] ?: null, (int)$id,
        ]);
        $this->auditUpdate('scars', (int)$id, $scar, $data);
        $this->toast('SCAR updated.', 'success');
        $this->redirect("/scars/{$id}");
    }

    public function respond(string $id): void
    {
        if (!$this->checkPermission('qc', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $scar = $this->getOrFail((int)$id);
        $response = trim($_POST['supplier_response'] ?? '');
        if (!$response) { $this->toast('Supplier response is required.', 'error'); $this->redirect("/scars/{$id}"); return; }

        $this->db()->prepare("UPDATE scars SET supplier_response=?, status='RESPONSE_RECEIVED', updated_at=NOW() WHERE id=?")
            ->execute([$response, (int)$id]);
        $this->auditLog('UPDATE', 'scars', (int)$id, ['status' => $scar['status']], ['status' => 'RESPONSE_RECEIVED']);
        $this->toast('Supplier response recorded.', 'success');
        $this->redirect("/scars/{$id}");
    }

    public function close(string $id): void
    {
        if (!$this->checkPermission('qc', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $scar = $this->getOrFail((int)$id);
        $closureNotes = trim($_POST['closure_notes'] ?? '');
        if (!$closureNotes) { $this->toast('Closure notes are required.', 'error'); $this->redirect("/scars/{$id}"); return; }

        $this->db()->prepare("UPDATE scars SET closure_notes=?, status='CLOSED', closed_by=?, closed_at=NOW(), updated_at=NOW() WHERE id=?")
            ->execute([$closureNotes, $this->currentUserId(), (int)$id]);
        $this->auditLog('UPDATE', 'scars', (int)$id, ['status' => $scar['status']], ['status' => 'CLOSED']);
        $this->toast("SCAR {$scar['scar_number']} closed.", 'success');
        $this->redirect("/scars/{$id}");
    }

    public function cancelScar(string $id): void
    {
        if (!$this->checkPermission('qc', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $scar = $this->getOrFail((int)$id);

        $this->db()->prepare("UPDATE scars SET status='CANCELLED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'scars', (int)$id, ['status' => $scar['status']], ['status' => 'CANCELLED']);
        $this->toast('SCAR cancelled.', 'success');
        $this->redirect("/scars/{$id}");
    }

    public function emailToSupplier(string $id): void
    {
        if (!$this->checkPermission('qc', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $scar = $this->getOrFail((int)$id);

        // Find QUALITY or primary contact
        $contactStmt = $this->db()->prepare("
            SELECT email FROM supplier_contacts
            WHERE supplier_id = ? AND active = 1 AND email IS NOT NULL AND email != ''
            ORDER BY FIELD(contact_type, 'QUALITY', 'GENERAL') ASC, is_primary DESC LIMIT 1
        ");
        $contactStmt->execute([$scar['supplier_id']]);
        $email = $contactStmt->fetchColumn();

        if (!$email) { $this->toast('No supplier contact email found.', 'error'); $this->redirect("/scars/{$id}"); return; }

        // Generate PDF
        $html = $this->buildScarPdfHtml($scar);
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $pdfPath = sys_get_temp_dir() . '/' . $scar['scar_number'] . '.pdf';
        file_put_contents($pdfPath, $dompdf->output());

        if ($this->emailService) {
            $this->emailService->send(
                'scar', [$email],
                ['scar_number' => $scar['scar_number'], 'supplier_name' => $scar['supplier_name'], 'issue_date' => $scar['issue_date']],
                $pdfPath, $scar['scar_number'] . '.pdf', 'scar', (int)$id
            );
        }

        @unlink($pdfPath);
        $this->toast("SCAR emailed to {$email}.", 'success');
        $this->redirect("/scars/{$id}");
    }

    public function downloadPdf(string $id): void
    {
        if (!$this->checkPermission('qc', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $scar = $this->getOrFail((int)$id);

        $html = $this->buildScarPdfHtml($scar);
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $dompdf->stream($scar['scar_number'] . '.pdf', ['Attachment' => false]);
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT sc.*, s.supplier_code, s.company_name as supplier_name,
                   u.full_name as created_by_name, cu.full_name as closed_by_name
            FROM scars sc
            LEFT JOIN suppliers s ON sc.supplier_id = s.id
            LEFT JOIN users u ON sc.created_by = u.id
            LEFT JOIN users cu ON sc.closed_by = cu.id
            WHERE sc.id = ?
        ");
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        if (!$s) { http_response_code(404); echo 'SCAR not found.'; exit; }
        return $s;
    }

    private function extractData(): array
    {
        return [
            'supplier_id' => (int)($_POST['supplier_id'] ?? 0),
            'po_receipt_id' => (int)($_POST['po_receipt_id'] ?? 0),
            'lot_number' => trim($_POST['lot_number'] ?? ''),
            'issue_date' => $_POST['issue_date'] ?? date('Y-m-d'),
            'description' => trim($_POST['description'] ?? ''),
            'required_action' => trim($_POST['required_action'] ?? ''),
            'due_date' => $_POST['due_date'] ?? null,
        ];
    }

    private function buildScarPdfHtml(array $scar): string
    {
        return '<!DOCTYPE html><html><head><style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 30px; }
            h1 { font-size: 20px; margin-bottom: 4px; color: #dc2626; }
            .label { font-weight: bold; color: #555; font-size: 10px; text-transform: uppercase; }
            .section { margin-bottom: 16px; }
            .field { margin-bottom: 8px; }
        </style></head><body>
            <h1>Supplier Corrective Action Request</h1>
            <h2>' . htmlspecialchars($scar['scar_number']) . '</h2>
            <div class="section">
                <div class="field"><span class="label">Supplier:</span> ' . htmlspecialchars($scar['supplier_name'] ?? '') . ' (' . htmlspecialchars($scar['supplier_code'] ?? '') . ')</div>
                <div class="field"><span class="label">Issue Date:</span> ' . date('M j, Y', strtotime($scar['issue_date'])) . '</div>
                <div class="field"><span class="label">Due Date:</span> ' . ($scar['due_date'] ? date('M j, Y', strtotime($scar['due_date'])) : 'N/A') . '</div>
                <div class="field"><span class="label">Lot Number:</span> ' . htmlspecialchars($scar['lot_number'] ?? 'N/A') . '</div>
                <div class="field"><span class="label">Status:</span> ' . htmlspecialchars($scar['status']) . '</div>
            </div>
            <div class="section"><p class="label">Description of Issue:</p><p>' . nl2br(htmlspecialchars($scar['description'])) . '</p></div>
            <div class="section"><p class="label">Required Corrective Action:</p><p>' . nl2br(htmlspecialchars($scar['required_action'] ?? 'N/A')) . '</p></div>'
            . ($scar['supplier_response'] ? '<div class="section"><p class="label">Supplier Response:</p><p>' . nl2br(htmlspecialchars($scar['supplier_response'])) . '</p></div>' : '')
            . ($scar['closure_notes'] ? '<div class="section"><p class="label">Closure Notes:</p><p>' . nl2br(htmlspecialchars($scar['closure_notes'])) . '</p></div>' : '')
            . '</body></html>';
    }
}
