<?php

namespace PrecisionInk\Controllers;

class QcController extends BaseController
{
    // ══════════════════════════════════════════════════════════════════
    // QC SPECS
    // ══════════════════════════════════════════════════════════════════

    public function specsList(): void
    {
        if (!$this->checkPermission('qc', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $specs = $this->db()->query("
            SELECT qs.*, i.item_code, i.description as item_description, u.full_name as created_by_name,
                   (SELECT COUNT(*) FROM qc_spec_tests WHERE spec_id = qs.id) as test_count
            FROM qc_specs qs
            JOIN items i ON qs.item_id = i.id
            LEFT JOIN users u ON qs.created_by = u.id
            ORDER BY i.item_code, qs.version_number DESC
        ")->fetchAll();

        $this->renderView('qc/specs_list', ['specs' => $specs]);
    }

    public function specCreate(): void
    {
        if (!$this->checkPermission('qc', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $prefilledItemId = (int)($_GET['item_id'] ?? 0);
        $prefilledItem = null;
        if ($prefilledItemId) {
            $stmt = $this->db()->prepare("SELECT id, item_code, description FROM items WHERE id = ?");
            $stmt->execute([$prefilledItemId]);
            $prefilledItem = $stmt->fetch();
        }

        $this->renderView('qc/spec_form', [
            'mode' => 'create', 'spec' => null, 'tests' => [],
            'prefilledItem' => $prefilledItem,
        ]);
    }

    public function specStore(): void
    {
        if (!$this->checkPermission('qc', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $itemId = (int)($_POST['item_id'] ?? 0);
        $tests = $this->extractTests();

        if (!$itemId) { $this->toast('Item is required.', 'error'); $this->redirect('/qc/specs/create'); return; }
        if (empty($tests)) { $this->toast('At least one test is required.', 'error'); $this->redirect('/qc/specs/create'); return; }

        // Deactivate existing active spec for this item
        $this->db()->prepare("UPDATE qc_specs SET is_active = 0, updated_at = NOW() WHERE item_id = ? AND is_active = 1")->execute([$itemId]);

        // Get next version number
        $maxV = $this->db()->prepare("SELECT COALESCE(MAX(version_number), 0) FROM qc_specs WHERE item_id = ?");
        $maxV->execute([$itemId]);
        $nextVersion = (int)$maxV->fetchColumn() + 1;

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("INSERT INTO qc_specs (item_id, version_number, is_active, created_by) VALUES (?, ?, 1, ?)")
                ->execute([$itemId, $nextVersion, $this->currentUserId()]);
            $specId = (int)$this->db()->lastInsertId();

            $this->insertTests($specId, $tests);
            $this->db()->commit();

            $this->auditCreate('qc_specs', $specId, ['item_id' => $itemId, 'version' => $nextVersion]);
            $this->toast('QC spec created (version ' . $nextVersion . ').', 'success');
            $this->redirect("/qc/specs/{$specId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect('/qc/specs/create');
        }
    }

    public function specView(string $id): void
    {
        if (!$this->checkPermission('qc', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $spec = $this->getSpecOrFail((int)$id);
        $tests = $this->getSpecTests((int)$id);

        // Version history for this item
        $history = $this->db()->prepare("
            SELECT qs.*, u.full_name as created_by_name
            FROM qc_specs qs LEFT JOIN users u ON qs.created_by = u.id
            WHERE qs.item_id = ? ORDER BY qs.version_number DESC
        ");
        $history->execute([$spec['item_id']]);

        $this->renderView('qc/spec_view', [
            'spec' => $spec, 'tests' => $tests, 'history' => $history->fetchAll(),
        ]);
    }

    public function specEditForm(string $id): void
    {
        if (!$this->checkPermission('qc', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $spec = $this->getSpecOrFail((int)$id);
        $tests = $this->getSpecTests((int)$id);

        $this->renderView('qc/spec_form', ['mode' => 'edit', 'spec' => $spec, 'tests' => $tests, 'prefilledItem' => null]);
    }

    public function specUpdate(string $id): void
    {
        if (!$this->checkPermission('qc', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $spec = $this->getSpecOrFail((int)$id);
        $tests = $this->extractTests();

        if (empty($tests)) { $this->toast('At least one test required.', 'error'); $this->redirect("/qc/specs/{$id}/edit"); return; }

        // Editing creates a new version — deactivate old, create new
        $this->db()->prepare("UPDATE qc_specs SET is_active = 0, updated_at = NOW() WHERE item_id = ? AND is_active = 1")->execute([$spec['item_id']]);

        $nextVersion = (int)$spec['version_number'] + 1;

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("INSERT INTO qc_specs (item_id, version_number, is_active, created_by) VALUES (?, ?, 1, ?)")
                ->execute([$spec['item_id'], $nextVersion, $this->currentUserId()]);
            $newId = (int)$this->db()->lastInsertId();

            $this->insertTests($newId, $tests);
            $this->db()->commit();

            $this->auditCreate('qc_specs', $newId, ['item_id' => $spec['item_id'], 'version' => $nextVersion, 'from_version' => $spec['version_number']]);
            $this->toast('New spec version ' . $nextVersion . ' created.', 'success');
            $this->redirect("/qc/specs/{$newId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/qc/specs/{$id}/edit");
        }
    }

    public function specDeactivate(string $id): void
    {
        if (!$this->checkPermission('qc', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $this->db()->prepare("UPDATE qc_specs SET is_active = 0, updated_at = NOW() WHERE id = ?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'qc_specs', (int)$id, ['is_active' => 1], ['is_active' => 0]);
        $this->toast('QC spec deactivated.', 'success');
        $this->redirect('/qc/specs');
    }

    // ══════════════════════════════════════════════════════════════════
    // INCOMING INSPECTION
    // ══════════════════════════════════════════════════════════════════

    public function inspectionQueue(): void
    {
        if (!$this->checkPermission('qc', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $filterFacility = (int)($_GET['facility_id'] ?? 0);
        $where = "fl.status = 'PENDING_INSPECTION'";
        $params = [];
        if ($filterFacility) { $where .= ' AND fl.facility_id = ?'; $params[] = $filterFacility; }

        $stmt = $this->db()->prepare("
            SELECT qi.id, qi.status as insp_status, qi.created_at as insp_created,
                   fl.id as lot_id, fl.lot_number, fl.remaining_quantity, fl.facility_id, fl.created_at as lot_date,
                   i.item_code, i.description as item_description,
                   f.name as facility_name,
                   DATEDIFF(CURDATE(), qi.created_at) as days_waiting
            FROM qc_incoming_inspections qi
            JOIN fifo_lots fl ON qi.fifo_lot_id = fl.id
            JOIN items i ON fl.item_id = i.id
            JOIN facilities f ON fl.facility_id = f.id
            WHERE {$where} AND qi.status = 'PENDING'
            ORDER BY qi.created_at ASC
        ");
        $stmt->execute($params);

        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);

        $this->renderView('qc/inspection_queue', [
            'inspections' => $stmt->fetchAll(),
            'facilities' => $facilities,
            'filterFacility' => $filterFacility,
        ]);
    }

    public function inspectLot(string $id): void
    {
        if (!$this->checkPermission('qc', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $insp = $this->getInspectionOrFail((int)$id);

        // Load lot details
        $lotStmt = $this->db()->prepare("
            SELECT fl.*, i.item_code, i.description as item_description, i.id as item_id,
                   f.name as facility_name, COALESCE(ifl.location, '') as location
            FROM fifo_lots fl
            JOIN items i ON fl.item_id = i.id
            JOIN facilities f ON fl.facility_id = f.id
            LEFT JOIN item_facility_locations ifl ON ifl.item_id = fl.item_id AND ifl.facility_id = fl.facility_id
            WHERE fl.id = ?
        ");
        $lotStmt->execute([$insp['fifo_lot_id']]);
        $lot = $lotStmt->fetch();

        // Get active QC spec for this item
        $specStmt = $this->db()->prepare("SELECT * FROM qc_specs WHERE item_id = ? AND is_active = 1 LIMIT 1");
        $specStmt->execute([$lot['item_id']]);
        $spec = $specStmt->fetch();

        $tests = [];
        if ($spec) {
            $tests = $this->getSpecTests((int)$spec['id']);
        }

        $this->renderView('qc/inspect_lot', [
            'insp' => $insp, 'lot' => $lot, 'spec' => $spec, 'tests' => $tests,
        ]);
    }

    public function passLot(string $id): void
    {
        if (!$this->checkPermission('qc', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $insp = $this->getInspectionOrFail((int)$id);
        if ($insp['status'] !== 'PENDING') { $this->toast('Inspection already completed.', 'error'); $this->redirect('/qc/inspection'); return; }

        // Collect test results as JSON notes
        $results = $_POST['results'] ?? [];
        $resultsSummary = [];
        foreach ($results as $testId => $val) {
            $resultsSummary[] = ['test_id' => (int)$testId, 'value' => $val['value'] ?? '', 'pass_fail' => $val['pass_fail'] ?? 'PASS'];
        }
        $notesJson = !empty($resultsSummary) ? json_encode($resultsSummary) : null;
        $additionalNotes = trim($_POST['notes'] ?? '');
        $finalNotes = $notesJson;
        if ($additionalNotes) {
            $finalNotes = ($finalNotes ? $finalNotes . "\n" : '') . $additionalNotes;
        }

        // Release lot
        $this->db()->prepare("UPDATE fifo_lots SET status = 'AVAILABLE', updated_at = NOW() WHERE id = ?")->execute([$insp['fifo_lot_id']]);

        // Update inspection
        $this->db()->prepare("
            UPDATE qc_incoming_inspections SET status = 'PASSED', inspected_by = ?, inspection_date = CURDATE(), notes = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$this->currentUserId(), $finalNotes, (int)$id]);

        $this->auditLog('UPDATE', 'qc_incoming_inspections', (int)$id, ['status' => 'PENDING'], ['status' => 'PASSED']);
        $this->toast('Lot passed inspection and released to AVAILABLE.', 'success');
        $this->redirect('/qc/inspection');
    }

    public function failLot(string $id): void
    {
        if (!$this->checkPermission('qc', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $insp = $this->getInspectionOrFail((int)$id);
        if ($insp['status'] !== 'PENDING') { $this->toast('Inspection already completed.', 'error'); $this->redirect('/qc/inspection'); return; }

        $disposition = $_POST['disposition'] ?? 'QUARANTINE';
        $reason = trim($_POST['reason'] ?? '');
        if (!$reason) { $this->toast('Failure reason is required.', 'error'); $this->redirect("/qc/inspection/{$id}"); return; }

        $userId = $this->currentUserId();

        // Quarantine the lot
        $quarantineReason = ($disposition === 'RETURN_TO_SUPPLIER' ? 'RETURN_TO_SUPPLIER: ' : 'QC FAIL: ') . $reason;
        $this->fifoService->quarantine($insp['fifo_lot_id'], $quarantineReason, $userId);

        // Update inspection
        $this->db()->prepare("
            UPDATE qc_incoming_inspections SET status = 'FAILED', inspected_by = ?, inspection_date = CURDATE(), notes = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$userId, $reason, (int)$id]);

        $this->auditLog('UPDATE', 'qc_incoming_inspections', (int)$id, ['status' => 'PENDING'], ['status' => 'FAILED']);

        // If RETURN_TO_SUPPLIER, create a SCAR
        if ($disposition === 'RETURN_TO_SUPPLIER') {
            // Get supplier from PO receipt
            $supplierId = null;
            $lotNumber = null;
            $receiptId = null;
            if ($insp['po_receipt_line_id']) {
                $supStmt = $this->db()->prepare("
                    SELECT po.supplier_id, fl.lot_number, pr.id as receipt_id
                    FROM po_receipt_lines prl
                    JOIN po_receipts pr ON prl.receipt_id = pr.id
                    JOIN purchase_orders po ON pr.po_id = po.id
                    JOIN fifo_lots fl ON fl.id = ?
                    WHERE prl.id = ?
                ");
                $supStmt->execute([$insp['fifo_lot_id'], $insp['po_receipt_line_id']]);
                $supInfo = $supStmt->fetch();
                if ($supInfo) {
                    $supplierId = (int)$supInfo['supplier_id'];
                    $lotNumber = $supInfo['lot_number'];
                    $receiptId = (int)$supInfo['receipt_id'];
                }
            }

            if ($supplierId) {
                $scarNumber = $this->generateDocumentNumber('SCAR');
                $this->db()->prepare("
                    INSERT INTO scars (scar_number, supplier_id, po_receipt_id, lot_number, issue_date, description, required_action, status, created_by)
                    VALUES (?, ?, ?, ?, CURDATE(), ?, 'Return defective lot to supplier', 'OPEN', ?)
                ")->execute([$scarNumber, $supplierId, $receiptId, $lotNumber, 'Incoming inspection failure: ' . $reason, $userId]);
                $scarId = (int)$this->db()->lastInsertId();
                $this->auditCreate('scars', $scarId, ['scar_number' => $scarNumber]);
                $this->toast("Lot quarantined for return. SCAR {$scarNumber} created.", 'warning');
            } else {
                $this->toast('Lot quarantined. Could not determine supplier for SCAR.', 'warning');
            }
        } else {
            $this->toast('Lot failed inspection and quarantined.', 'warning');
        }

        $this->redirect('/qc/inspection');
    }

    // ══════════════════════════════════════════════════════════════════
    // EQUIPMENT MAINTENANCE
    // ══════════════════════════════════════════════════════════════════

    public function maintenanceHistory(string $equipmentId): void
    {
        if (!$this->checkPermission('settings', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $eqStmt = $this->db()->prepare("SELECT e.*, f.name as facility_name FROM equipment e LEFT JOIN facilities f ON e.facility_id = f.id WHERE e.id = ?");
        $eqStmt->execute([(int)$equipmentId]);
        $equipment = $eqStmt->fetch();
        if (!$equipment) { http_response_code(404); echo 'Equipment not found.'; exit; }

        $logStmt = $this->db()->prepare("
            SELECT eml.*, u.full_name as logged_by_name
            FROM equipment_maintenance_log eml
            LEFT JOIN users u ON eml.logged_by = u.id
            WHERE eml.equipment_id = ?
            ORDER BY eml.maintenance_date DESC
        ");
        $logStmt->execute([(int)$equipmentId]);

        $this->renderView('qc/maintenance', [
            'equipment' => $equipment, 'logs' => $logStmt->fetchAll(),
        ]);
    }

    public function logMaintenance(string $equipmentId): void
    {
        if (!$this->checkPermission('settings', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $maintDate = $_POST['maintenance_date'] ?? date('Y-m-d');
        $maintType = $_POST['maintenance_type'] ?? 'PREVENTIVE';
        $description = trim($_POST['description'] ?? '');
        $performedBy = trim($_POST['performed_by'] ?? '');
        $nextDue = $_POST['next_due_date'] ?? null ?: null;

        if (!$description || !$performedBy) {
            $this->toast('Description and performed by are required.', 'error');
            $this->redirect("/equipment/{$equipmentId}/maintenance");
            return;
        }

        $this->db()->prepare("
            INSERT INTO equipment_maintenance_log (equipment_id, maintenance_date, maintenance_type, description, performed_by, next_due_date, logged_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([(int)$equipmentId, $maintDate, $maintType, $description, $performedBy, $nextDue, $this->currentUserId()]);
        $logId = (int)$this->db()->lastInsertId();

        // Update equipment next_maintenance_due
        if ($nextDue) {
            $this->db()->prepare("UPDATE equipment SET next_maintenance_due = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$nextDue, (int)$equipmentId]);
        }

        $this->auditCreate('equipment_maintenance_log', $logId, ['equipment_id' => (int)$equipmentId, 'type' => $maintType]);
        $this->toast('Maintenance event logged.', 'success');
        $this->redirect("/equipment/{$equipmentId}/maintenance");
    }

    // ══════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════

    private function getSpecOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT qs.*, i.item_code, i.description as item_description, u.full_name as created_by_name
            FROM qc_specs qs
            JOIN items i ON qs.item_id = i.id
            LEFT JOIN users u ON qs.created_by = u.id
            WHERE qs.id = ?
        ");
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        if (!$s) { http_response_code(404); echo 'QC Spec not found.'; exit; }
        return $s;
    }

    private function getSpecTests(int $specId): array
    {
        $stmt = $this->db()->prepare("SELECT * FROM qc_spec_tests WHERE spec_id = ? ORDER BY display_sequence, id");
        $stmt->execute([$specId]);
        return $stmt->fetchAll();
    }

    private function getInspectionOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("SELECT * FROM qc_incoming_inspections WHERE id = ?");
        $stmt->execute([$id]);
        $i = $stmt->fetch();
        if (!$i) { http_response_code(404); echo 'Inspection not found.'; exit; }
        return $i;
    }

    private function extractTests(): array
    {
        $tests = [];
        foreach ($_POST['tests'] ?? [] as $t) {
            $name = trim($t['test_name'] ?? '');
            if (!$name) continue;
            $tests[] = [
                'test_name' => $name,
                'test_type' => $t['test_type'] ?? 'PASS_FAIL',
                'min_value' => ($t['test_type'] ?? '') === 'NUMERIC_RANGE' && ($t['min_value'] ?? '') !== '' ? (float)$t['min_value'] : null,
                'max_value' => ($t['test_type'] ?? '') === 'NUMERIC_RANGE' && ($t['max_value'] ?? '') !== '' ? (float)$t['max_value'] : null,
                'uom' => trim($t['uom'] ?? '') ?: null,
                'is_required' => isset($t['is_required']) ? 1 : 0,
            ];
        }
        return $tests;
    }

    private function insertTests(int $specId, array $tests): void
    {
        $stmt = $this->db()->prepare("
            INSERT INTO qc_spec_tests (spec_id, test_name, test_type, min_value, max_value, uom, is_required, display_sequence)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($tests as $i => $t) {
            $stmt->execute([$specId, $t['test_name'], $t['test_type'], $t['min_value'], $t['max_value'], $t['uom'], $t['is_required'], ($i + 1) * 10]);
        }
    }
}
