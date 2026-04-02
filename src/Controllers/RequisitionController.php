<?php

namespace PrecisionInk\Controllers;

class RequisitionController extends BaseController
{
    // ── List ────────────────────────────────────────────────────────

    public function index(): void
    {
        if (!$this->checkPermission('purchase_requisitions', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterStatus = $_GET['status'] ?? '';
        $filterMine = isset($_GET['mine']);
        $filterSearch = trim($_GET['q'] ?? '');

        $where = [];
        $params = [];

        if ($filterStatus) { $where[] = 'r.status = ?'; $params[] = $filterStatus; }
        if ($filterMine) { $where[] = 'r.requested_by = ?'; $params[] = $this->currentUserId(); }
        if ($filterSearch) { $where[] = '(r.req_number LIKE ? OR r.justification LIKE ?)'; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM purchase_requisitions r {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT r.*, u.full_name as requested_by_name,
                   (SELECT COUNT(*) FROM purchase_requisition_lines WHERE requisition_id = r.id) as line_count
            FROM purchase_requisitions r
            LEFT JOIN users u ON r.requested_by = u.id
            {$whereClause}
            ORDER BY r.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $requisitions = $stmt->fetchAll();

        // Pending approvals for users with special permission
        $pendingApprovals = [];
        if ($this->hasSpecialPermission('approve_purchase_requisitions')) {
            $pendingApprovals = $this->db()->query("
                SELECT r.*, u.full_name as requested_by_name,
                       (SELECT COUNT(*) FROM purchase_requisition_lines WHERE requisition_id = r.id) as line_count
                FROM purchase_requisitions r
                LEFT JOIN users u ON r.requested_by = u.id
                WHERE r.status = 'SUBMITTED'
                ORDER BY r.required_by_date ASC, r.request_date ASC
            ")->fetchAll();
        }

        $this->renderView('requisitions/list', [
            'requisitions' => $requisitions,
            'pendingApprovals' => $pendingApprovals,
            'filters' => ['status' => $filterStatus, 'mine' => $filterMine, 'q' => $filterSearch],
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
            'canApprove' => $this->hasSpecialPermission('approve_purchase_requisitions'),
        ]);
    }

    // ── Create ──────────────────────────────────────────────────────

    public function create(): void
    {
        if (!$this->checkPermission('purchase_requisitions', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->renderView('requisitions/form', $this->formData('create'));
    }

    public function store(): void
    {
        if (!$this->checkPermission('purchase_requisitions', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $data = $this->extractHeaderData();
        $lines = $this->extractLines();

        if (empty($lines)) {
            $this->toast('At least one line item is required.', 'error');
            $this->renderView('requisitions/form', $this->formData('create', $data, $lines));
            return;
        }

        $reqNumber = $this->generateDocumentNumber('REQUISITION');

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("
                INSERT INTO purchase_requisitions (req_number, requested_by, request_date, required_by_date, justification, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, 'DRAFT')
            ")->execute([
                $reqNumber, $this->currentUserId(), $data['request_date'],
                $data['required_by_date'] ?: null, $data['justification'] ?: null, $data['notes'] ?: null,
            ]);
            $reqId = (int)$this->db()->lastInsertId();

            $this->insertLines($reqId, $lines);
            $this->db()->commit();

            $this->auditCreate('purchase_requisitions', $reqId, ['req_number' => $reqNumber, 'status' => 'DRAFT']);
            $this->toast("Requisition {$reqNumber} created.", 'success');
            $this->redirect("/purchase-requisitions/{$reqId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect('/purchase-requisitions/create');
        }
    }

    // ── View ────────────────────────────────────────────────────────

    public function view(string $id): void
    {
        if (!$this->checkPermission('purchase_requisitions', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $req = $this->getOrFail((int)$id);
        $lines = $this->getLines((int)$id);

        $this->renderView('requisitions/view', [
            'req' => $req,
            'lines' => $lines,
            'canApprove' => $this->hasSpecialPermission('approve_purchase_requisitions'),
        ]);
    }

    // ── Edit ────────────────────────────────────────────────────────

    public function editForm(string $id): void
    {
        if (!$this->checkPermission('purchase_requisitions', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $req = $this->getOrFail((int)$id);
        if (!in_array($req['status'], ['DRAFT', 'REJECTED'])) {
            $this->toast('Only DRAFT or REJECTED requisitions can be edited.', 'error');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        $lines = $this->getLines((int)$id);
        $this->renderView('requisitions/form', $this->formData('edit', $req, $lines));
    }

    public function update(string $id): void
    {
        if (!$this->checkPermission('purchase_requisitions', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $req = $this->getOrFail((int)$id);
        if (!in_array($req['status'], ['DRAFT', 'REJECTED'])) {
            $this->toast('Only DRAFT or REJECTED requisitions can be edited.', 'error');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        $data = $this->extractHeaderData();
        $lines = $this->extractLines();

        if (empty($lines)) {
            $this->toast('At least one line item is required.', 'error');
            $this->renderView('requisitions/form', $this->formData('edit', array_merge($req, $data), $lines));
            return;
        }

        $this->db()->beginTransaction();
        try {
            // Reset to DRAFT if was REJECTED
            $newStatus = $req['status'] === 'REJECTED' ? 'DRAFT' : $req['status'];

            $this->db()->prepare("
                UPDATE purchase_requisitions SET request_date=?, required_by_date=?, justification=?, notes=?,
                    status=?, approved_by=NULL, approved_at=NULL, approval_notes=NULL, updated_at=NOW()
                WHERE id=?
            ")->execute([
                $data['request_date'], $data['required_by_date'] ?: null,
                $data['justification'] ?: null, $data['notes'] ?: null, $newStatus, (int)$id,
            ]);

            // Delete old lines and re-insert
            $this->db()->prepare("DELETE FROM purchase_requisition_lines WHERE requisition_id=?")->execute([(int)$id]);
            $this->insertLines((int)$id, $lines);

            $this->db()->commit();
            $this->auditUpdate('purchase_requisitions', (int)$id, $req, $data);
            $this->toast('Requisition updated.', 'success');
            $this->redirect("/purchase-requisitions/{$id}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/purchase-requisitions/{$id}/edit");
        }
    }

    // ── Workflow ─────────────────────────────────────────────────────

    public function submit(string $id): void
    {
        if (!$this->checkPermission('purchase_requisitions', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $req = $this->getOrFail((int)$id);
        if ($req['status'] !== 'DRAFT') {
            $this->toast('Only DRAFT requisitions can be submitted.', 'error');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        $lineCount = $this->db()->prepare("SELECT COUNT(*) FROM purchase_requisition_lines WHERE requisition_id=?");
        $lineCount->execute([(int)$id]);
        if ((int)$lineCount->fetchColumn() === 0) {
            $this->toast('Cannot submit a requisition with no line items.', 'error');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        $this->db()->prepare("UPDATE purchase_requisitions SET status='SUBMITTED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'purchase_requisitions', (int)$id, ['status'=>'DRAFT'], ['status'=>'SUBMITTED']);

        if ($this->notificationService) {
            $this->notificationService->sendAlert('requisition_submitted', [
                'subject' => 'Purchase Requisition ' . $req['req_number'] . ' awaiting approval',
                'body' => "Requested by: {$req['requested_by_name']}\nRequired by: " . ($req['required_by_date'] ?? 'N/A') . "\nJustification: " . ($req['justification'] ?? 'N/A'),
            ], 'purchase_requisition', (int)$id);
        }

        $this->toast("Requisition {$req['req_number']} submitted for approval.", 'success');
        $this->redirect("/purchase-requisitions/{$id}");
    }

    public function approve(string $id): void
    {
        if (!$this->hasSpecialPermission('approve_purchase_requisitions')) {
            $this->toast('You do not have permission to approve requisitions.', 'error');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        $req = $this->getOrFail((int)$id);
        if ($req['status'] !== 'SUBMITTED') {
            $this->toast('Only SUBMITTED requisitions can be approved.', 'error');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        $approvalNotes = trim($_POST['approval_notes'] ?? '');

        $this->db()->prepare("
            UPDATE purchase_requisitions SET status='APPROVED', approved_by=?, approved_at=NOW(), approval_notes=?, updated_at=NOW()
            WHERE id=?
        ")->execute([$this->currentUserId(), $approvalNotes ?: null, (int)$id]);

        $this->auditLog('UPDATE', 'purchase_requisitions', (int)$id, ['status'=>'SUBMITTED'], ['status'=>'APPROVED']);
        $this->toast("Requisition {$req['req_number']} approved.", 'success');
        $this->redirect("/purchase-requisitions/{$id}");
    }

    public function reject(string $id): void
    {
        if (!$this->hasSpecialPermission('approve_purchase_requisitions')) {
            $this->toast('You do not have permission to reject requisitions.', 'error');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        $req = $this->getOrFail((int)$id);
        if ($req['status'] !== 'SUBMITTED') {
            $this->toast('Only SUBMITTED requisitions can be rejected.', 'error');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        $reason = trim($_POST['approval_notes'] ?? '');
        if (!$reason) {
            $this->toast('Rejection reason is required.', 'error');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        $this->db()->prepare("
            UPDATE purchase_requisitions SET status='REJECTED', approved_by=?, approved_at=NOW(), approval_notes=?, updated_at=NOW()
            WHERE id=?
        ")->execute([$this->currentUserId(), $reason, (int)$id]);

        $this->auditLog('UPDATE', 'purchase_requisitions', (int)$id, ['status'=>'SUBMITTED'], ['status'=>'REJECTED']);
        $this->toast("Requisition {$req['req_number']} rejected.", 'warning');
        $this->redirect("/purchase-requisitions/{$id}");
    }

    public function convert(string $id): void
    {
        if (!$this->checkPermission('purchase_requisitions', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $req = $this->getOrFail((int)$id);
        if ($req['status'] !== 'APPROVED') {
            $this->toast('Only APPROVED requisitions can be converted to POs.', 'error');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        // Get unconverted lines
        $lines = $this->db()->prepare("
            SELECT rl.*, i.item_code, i.description as item_description, s.supplier_code, s.company_name as supplier_name
            FROM purchase_requisition_lines rl
            JOIN items i ON rl.item_id = i.id
            LEFT JOIN suppliers s ON rl.preferred_supplier_id = s.id
            WHERE rl.requisition_id = ? AND rl.converted_po_id IS NULL
        ");
        $lines->execute([(int)$id]);
        $unconvertedLines = $lines->fetchAll();

        if (empty($unconvertedLines)) {
            $this->toast('All lines have already been converted.', 'warning');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        // Group by supplier (POST may override supplier assignments)
        $supplierAssignments = $_POST['supplier'] ?? [];
        $groups = [];
        foreach ($unconvertedLines as $line) {
            $suppId = (int)($supplierAssignments[$line['id']] ?? $line['preferred_supplier_id'] ?? 0);
            if (!$suppId) continue; // Skip lines with no supplier
            $groups[$suppId][] = $line;
        }

        if (empty($groups)) {
            $this->toast('No lines have a supplier assigned. Assign suppliers before converting.', 'error');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        $userId = $this->currentUserId();
        $active = $this->facilityService ? $this->facilityService->getActiveFacility($userId) : null;
        $facilityId = (int)($active['id'] ?? 1);
        $createdPOs = [];

        $this->db()->beginTransaction();
        try {
            foreach ($groups as $supplierId => $groupLines) {
                $poNumber = $this->generateDocumentNumber('PURCHASE_ORDER');

                $this->db()->prepare("
                    INSERT INTO purchase_orders (po_number, supplier_id, facility_id, order_date, status, requisition_id, created_at, updated_at)
                    VALUES (?, ?, ?, CURDATE(), 'DRAFT', ?, NOW(), NOW())
                ")->execute([$poNumber, $supplierId, $facilityId, (int)$id]);
                $poId = (int)$this->db()->lastInsertId();
                $createdPOs[] = ['id' => $poId, 'po_number' => $poNumber];

                foreach ($groupLines as $line) {
                    $this->db()->prepare("
                        INSERT INTO purchase_order_lines (po_id, item_id, pack_extension_id, ordered_quantity, uom_id, unit_cost, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $poId, $line['item_id'], $line['pack_extension_id'],
                        $line['quantity'], $line['uom_id'],
                        $line['estimated_unit_cost'] ?? 0, $line['notes'],
                    ]);

                    $this->db()->prepare("UPDATE purchase_requisition_lines SET converted_po_id=? WHERE id=?")
                        ->execute([$poId, $line['id']]);
                }

                $this->auditCreate('purchase_orders', $poId, ['po_number' => $poNumber, 'from_requisition' => $req['req_number']]);
            }

            // Check if all lines are now converted
            $remaining = $this->db()->prepare("SELECT COUNT(*) FROM purchase_requisition_lines WHERE requisition_id=? AND converted_po_id IS NULL");
            $remaining->execute([(int)$id]);
            if ((int)$remaining->fetchColumn() === 0) {
                $this->db()->prepare("UPDATE purchase_requisitions SET status='CONVERTED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
                $this->auditLog('UPDATE', 'purchase_requisitions', (int)$id, ['status'=>'APPROVED'], ['status'=>'CONVERTED']);
            }

            $this->db()->commit();

            $poNames = implode(', ', array_column($createdPOs, 'po_number'));
            $this->toast("Created PO(s): {$poNames}", 'success');

            if (count($createdPOs) === 1) {
                $this->redirect("/purchase-orders/{$createdPOs[0]['id']}");
            } else {
                $this->redirect("/purchase-requisitions/{$id}");
            }
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error converting: ' . $e->getMessage(), 'error');
            $this->redirect("/purchase-requisitions/{$id}");
        }
    }

    public function cancel(string $id): void
    {
        if (!$this->checkPermission('purchase_requisitions', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $req = $this->getOrFail((int)$id);
        if (in_array($req['status'], ['CONVERTED', 'CANCELLED'])) {
            $this->toast('Cannot cancel this requisition.', 'error');
            $this->redirect("/purchase-requisitions/{$id}");
            return;
        }

        $this->db()->prepare("UPDATE purchase_requisitions SET status='CANCELLED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'purchase_requisitions', (int)$id, ['status'=>$req['status']], ['status'=>'CANCELLED']);
        $this->toast("Requisition {$req['req_number']} cancelled.", 'success');
        $this->redirect("/purchase-requisitions/{$id}");
    }

    // ── Dashboard Query ─────────────────────────────────────────────

    public static function getPendingCount(): int
    {
        $db = self::$pdo;
        if (!$db) return 0;
        return (int)$db->query("SELECT COUNT(*) FROM purchase_requisitions WHERE status = 'SUBMITTED'")->fetchColumn();
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT r.*, u.full_name as requested_by_name, au.full_name as approved_by_name
            FROM purchase_requisitions r
            LEFT JOIN users u ON r.requested_by = u.id
            LEFT JOIN users au ON r.approved_by = au.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if (!$r) { http_response_code(404); echo 'Requisition not found.'; exit; }
        return $r;
    }

    private function getLines(int $reqId): array
    {
        $stmt = $this->db()->prepare("
            SELECT rl.*, i.item_code, i.description as item_description,
                   u.abbreviation as uom_abbr, s.supplier_code, s.company_name as supplier_name,
                   pe.name as pack_name, po.po_number as converted_po_number
            FROM purchase_requisition_lines rl
            JOIN items i ON rl.item_id = i.id
            LEFT JOIN uom u ON rl.uom_id = u.id
            LEFT JOIN suppliers s ON rl.preferred_supplier_id = s.id
            LEFT JOIN item_pack_extensions pe ON rl.pack_extension_id = pe.id
            LEFT JOIN purchase_orders po ON rl.converted_po_id = po.id
            WHERE rl.requisition_id = ?
            ORDER BY rl.id
        ");
        $stmt->execute([$reqId]);
        return $stmt->fetchAll();
    }

    private function extractHeaderData(): array
    {
        return [
            'request_date' => $_POST['request_date'] ?? date('Y-m-d'),
            'required_by_date' => $_POST['required_by_date'] ?? null,
            'justification' => trim($_POST['justification'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
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
                'estimated_unit_cost' => ($l['estimated_unit_cost'] ?? '') !== '' ? (float)$l['estimated_unit_cost'] : null,
                'preferred_supplier_id' => (int)($l['preferred_supplier_id'] ?? 0) ?: null,
                'notes' => trim($l['notes'] ?? '') ?: null,
            ];
        }
        return $lines;
    }

    private function insertLines(int $reqId, array $lines): void
    {
        $stmt = $this->db()->prepare("
            INSERT INTO purchase_requisition_lines (requisition_id, item_id, pack_extension_id, quantity, uom_id, estimated_unit_cost, preferred_supplier_id, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($lines as $l) {
            $stmt->execute([$reqId, $l['item_id'], $l['pack_extension_id'], $l['quantity'], $l['uom_id'], $l['estimated_unit_cost'], $l['preferred_supplier_id'], $l['notes']]);
        }
    }

    private function formData(string $mode, ?array $req = null, ?array $lines = null): array
    {
        return [
            'mode' => $mode,
            'req' => $req,
            'lines' => $lines ?? [],
            'uoms' => $this->db()->query("SELECT id, abbreviation FROM uom WHERE active=1 ORDER BY abbreviation")->fetchAll(),
        ];
    }
}
