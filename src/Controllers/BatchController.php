<?php

namespace PrecisionInk\Controllers;

class BatchController extends BaseController
{
    // ── List ────────────────────────────────────────────────────────

    public function index(): void
    {
        if (!$this->checkPermission('batch_tickets', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterStatus = $_GET['status'] ?? '';
        $filterFacility = (int)($_GET['facility_id'] ?? 0);
        $filterSearch = trim($_GET['q'] ?? '');
        $filterPriority = $_GET['priority'] ?? '';

        $where = ['b.deleted_at IS NULL'];
        $params = [];

        if ($filterStatus) { $where[] = 'b.status = ?'; $params[] = $filterStatus; }
        if ($filterFacility) { $where[] = 'b.facility_id = ?'; $params[] = $filterFacility; }
        if ($filterSearch) { $where[] = '(b.batch_number LIKE ? OR i.item_code LIKE ? OR i.description LIKE ?)'; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; }
        if ($filterPriority) { $where[] = 'b.priority = ?'; $params[] = $filterPriority; }

        $whereClause = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM batch_tickets b JOIN items i ON b.item_id = i.id WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT b.*, i.item_code, i.description as item_description,
                   rv.version_name, rv.version_number, f.name as facility_name,
                   u.full_name as assigned_name
            FROM batch_tickets b
            JOIN items i ON b.item_id = i.id
            LEFT JOIN recipe_versions rv ON b.recipe_version_id = rv.id
            LEFT JOIN facilities f ON b.facility_id = f.id
            LEFT JOIN users u ON b.assigned_to = u.id
            WHERE {$whereClause}
            ORDER BY FIELD(b.priority, 'RUSH', 'NORMAL'), b.scheduled_date ASC, b.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);

        $this->renderView('batch_tickets/list', [
            'batches' => $stmt->fetchAll(),
            'facilities' => $facilities,
            'filters' => ['status' => $filterStatus, 'facility_id' => $filterFacility, 'q' => $filterSearch, 'priority' => $filterPriority],
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    // ── Create ──────────────────────────────────────────────────────

    public function create(): void
    {
        if (!$this->checkPermission('batch_tickets', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->renderView('batch_tickets/edit', $this->formData('create'));
    }

    public function store(): void
    {
        if (!$this->checkPermission('batch_tickets', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $data = $this->extractHeader();
        if (!$data['item_id'] || !$data['recipe_version_id'] || !$data['target_quantity']) {
            $this->toast('Item, recipe, and target quantity are required.', 'error');
            $this->redirect('/batches/create');
            return;
        }

        $userId = $this->currentUserId();

        // Lock QC spec
        $qcSpecId = null;
        $specStmt = $this->db()->prepare("SELECT id FROM qc_specs WHERE item_id = ? AND is_active = 1 LIMIT 1");
        $specStmt->execute([$data['item_id']]);
        $spec = $specStmt->fetch();
        if ($spec) $qcSpecId = (int)$spec['id'];

        // Get recipe steps for scaling
        $steps = $this->getRecipeIngredients($data['recipe_version_id']);
        $totalRecipeQty = array_sum(array_column($steps, 'quantity'));
        $scaleFactor = $totalRecipeQty > 0 ? $data['target_quantity'] / $totalRecipeQty : 1;

        $this->db()->beginTransaction();
        try {
            $batchNumber = $this->generateBatchNumber();

            $this->db()->prepare("
                INSERT INTO batch_tickets (batch_number, facility_id, item_id, recipe_version_id, qc_spec_id,
                    target_quantity, priority, scheduled_date, assigned_to, internal_notes, external_notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $batchNumber, $data['facility_id'], $data['item_id'], $data['recipe_version_id'],
                $qcSpecId, $data['target_quantity'], $data['priority'], $data['scheduled_date'],
                $data['assigned_to'] ?: null, $data['internal_notes'] ?: null, $data['external_notes'] ?: null, $userId,
            ]);
            $batchId = (int)$this->db()->lastInsertId();

            // Create ingredient lines from recipe
            $lineInsert = $this->db()->prepare("
                INSERT INTO batch_ticket_lines (batch_id, item_id, uom_id, theoretical_quantity, sequence)
                VALUES (?, ?, ?, ?, ?)
            ");
            $reservationWarnings = [];
            foreach ($steps as $step) {
                $theoreticalQty = round((float)$step['quantity'] * $scaleFactor, 4);
                $lineInsert->execute([$batchId, $step['item_id'], $step['uom_id'], $theoreticalQty, $step['sequence']]);

                // Reserve inventory
                if ($this->reservationService) {
                    $result = $this->reservationService->reserve(
                        (int)$step['item_id'], $data['facility_id'], $theoreticalQty, 'BATCH', $batchId
                    );
                    if ($result['warning']) {
                        $reservationWarnings[] = $result['message'];
                    }
                }
            }

            // Create pack entries
            $packs = $_POST['packs'] ?? [];
            $packInsert = $this->db()->prepare("INSERT INTO batch_ticket_packs (batch_id, pack_extension_id, target_quantity) VALUES (?, ?, ?)");
            foreach ($packs as $p) {
                $peId = (int)($p['pack_extension_id'] ?? 0);
                $pQty = (float)($p['target_quantity'] ?? 0);
                if ($peId && $pQty > 0) $packInsert->execute([$batchId, $peId, $pQty]);
            }

            // Create equipment entries
            $equipIds = $_POST['equipment'] ?? [];
            $eqInsert = $this->db()->prepare("INSERT INTO batch_equipment (batch_id, equipment_id) VALUES (?, ?)");
            foreach ($equipIds as $eqId) {
                if ((int)$eqId) $eqInsert->execute([$batchId, (int)$eqId]);
            }

            // Check warnings vs permission
            if (!empty($reservationWarnings) && !$this->hasSpecialPermission('override_inventory_warning')) {
                $this->db()->rollBack();
                $this->toast('Insufficient inventory: ' . implode(' | ', $reservationWarnings), 'error');
                $this->redirect('/batches/create');
                return;
            }

            $this->db()->commit();

            $this->customFieldService->saveValues('batch_tickets', $batchId, $_POST);
            $this->auditCreate('batch_tickets', $batchId, ['batch_number' => $batchNumber]);

            $msg = "Batch {$batchNumber} created.";
            if (!empty($reservationWarnings)) $msg .= ' Warning: ' . implode(' | ', $reservationWarnings);
            $this->toast($msg, empty($reservationWarnings) ? 'success' : 'warning');
            $this->redirect("/batches/{$batchId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect('/batches/create');
        }
    }

    // ── View ────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        if (!$this->checkPermission('batch_tickets', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $batch = $this->getOrFail((int)$id);
        $lines = $this->db()->prepare("
            SELECT bl.*, i.item_code, i.description as item_description, u.abbreviation as uom_abbr
            FROM batch_ticket_lines bl
            JOIN items i ON bl.item_id = i.id
            LEFT JOIN uom u ON bl.uom_id = u.id
            WHERE bl.batch_id = ?
            ORDER BY bl.sequence, bl.id
        ");
        $lines->execute([(int)$id]);
        $lines = $lines->fetchAll();

        // Add available qty per line
        foreach ($lines as &$l) {
            $l['available'] = $this->fifoService ? $this->fifoService->getAvailable((int)$l['item_id'], (int)$batch['facility_id']) : 0;
        }
        unset($l);

        $packStmt = $this->db()->prepare("
            SELECT bp.*, pe.name as pack_name FROM batch_ticket_packs bp
            JOIN item_pack_extensions pe ON bp.pack_extension_id = pe.id
            WHERE bp.batch_id = ? ORDER BY pe.name
        ");
        $packStmt->execute([(int)$id]);

        $eqStmt = $this->db()->prepare("
            SELECT e.* FROM batch_equipment be JOIN equipment e ON be.equipment_id = e.id WHERE be.batch_id = ?
        ");
        $eqStmt->execute([(int)$id]);

        $scrapStmt = $this->db()->prepare("SELECT bs.*, u.abbreviation as uom_abbr FROM batch_scrap bs LEFT JOIN uom u ON bs.uom_id = u.id WHERE bs.batch_id = ? ORDER BY bs.created_at DESC");
        $scrapStmt->execute([(int)$id]);

        $attachments = $this->attachmentService ? $this->attachmentService->getForRecord('batch_ticket', (int)$id) : [];

        $uoms = $this->db()->query("SELECT id, abbreviation FROM uom WHERE active=1 ORDER BY abbreviation")->fetchAll();

        $this->renderView('batch_tickets/view', [
            'batch' => $batch, 'lines' => $lines,
            'packs' => $packStmt->fetchAll(), 'equipment' => $eqStmt->fetchAll(),
            'scrap' => $scrapStmt->fetchAll(), 'attachments' => $attachments, 'uoms' => $uoms,
            'record' => $batch,
        ]);
    }

    // ── Edit ────────────────────────────────────────────────────────

    public function editForm(string $id): void
    {
        if (!$this->checkPermission('batch_tickets', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $batch = $this->getOrFail((int)$id);
        if ($batch['status'] !== 'OPEN') { $this->toast('Only OPEN batches can be edited.', 'error'); $this->redirect("/batches/{$id}"); return; }

        $lines = $this->db()->prepare("SELECT bl.*, i.item_code, i.description as item_description, u.abbreviation as uom_abbr FROM batch_ticket_lines bl JOIN items i ON bl.item_id = i.id LEFT JOIN uom u ON bl.uom_id = u.id WHERE bl.batch_id = ? ORDER BY bl.sequence");
        $lines->execute([(int)$id]);

        $this->renderView('batch_tickets/edit', $this->formData('edit', $batch, $lines->fetchAll()));
    }

    public function update(string $id): void
    {
        if (!$this->checkPermission('batch_tickets', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $batch = $this->getOrFail((int)$id);
        if ($batch['status'] !== 'OPEN') { $this->toast('Only OPEN batches can be edited.', 'error'); $this->redirect("/batches/{$id}"); return; }

        $data = $this->extractHeader();

        $this->db()->prepare("
            UPDATE batch_tickets SET facility_id=?, target_quantity=?, priority=?, scheduled_date=?,
                assigned_to=?, internal_notes=?, external_notes=?, updated_at=NOW() WHERE id=?
        ")->execute([
            $data['facility_id'], $data['target_quantity'], $data['priority'], $data['scheduled_date'],
            $data['assigned_to'] ?: null, $data['internal_notes'] ?: null, $data['external_notes'] ?: null, (int)$id,
        ]);

        $this->customFieldService->saveValues('batch_tickets', (int)$id, $_POST);
        $this->auditUpdate('batch_tickets', (int)$id, $batch, $data);
        $this->toast('Batch updated.', 'success');
        $this->redirect("/batches/{$id}");
    }

    // ── Workflow ─────────────────────────────────────────────────────

    public function start(string $id): void
    {
        if (!$this->checkPermission('batch_tickets', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $batch = $this->getOrFail((int)$id);
        if ($batch['status'] !== 'OPEN') { $this->toast('Only OPEN batches can be started.', 'error'); $this->redirect("/batches/{$id}"); return; }

        $this->db()->prepare("UPDATE batch_tickets SET status='IN_PROGRESS', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'batch_tickets', (int)$id, ['status' => 'OPEN'], ['status' => 'IN_PROGRESS']);
        $this->toast("Batch {$batch['batch_number']} started.", 'success');
        $this->redirect("/batches/{$id}");
    }

    public function cancel(string $id): void
    {
        if (!$this->checkPermission('batch_tickets', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $batch = $this->getOrFail((int)$id);
        if (in_array($batch['status'], ['CLOSED', 'CANCELLED'])) { $this->toast('Cannot cancel.', 'error'); $this->redirect("/batches/{$id}"); return; }

        // Release reservations
        if ($this->reservationService) {
            $this->reservationService->releaseAllForReference('BATCH', (int)$id);
        }

        $this->db()->prepare("UPDATE batch_tickets SET status='CANCELLED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'batch_tickets', (int)$id, ['status' => $batch['status']], ['status' => 'CANCELLED']);
        $this->toast("Batch {$batch['batch_number']} cancelled.", 'success');
        $this->redirect("/batches/{$id}");
    }

    // ── Clone ───────────────────────────────────────────────────────

    public function cloneBatch(string $id): void
    {
        if (!$this->checkPermission('batch_tickets', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $batch = $this->getOrFail((int)$id);

        $this->db()->beginTransaction();
        try {
            $batchNumber = $this->generateBatchNumber();
            $userId = $this->currentUserId();

            $this->db()->prepare("
                INSERT INTO batch_tickets (batch_number, facility_id, item_id, recipe_version_id, qc_spec_id,
                    target_quantity, priority, scheduled_date, assigned_to, internal_notes, external_notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)
            ")->execute([
                $batchNumber, $batch['facility_id'], $batch['item_id'], $batch['recipe_version_id'],
                $batch['qc_spec_id'], $batch['target_quantity'], $batch['priority'],
                $batch['assigned_to'], $batch['internal_notes'], $batch['external_notes'], $userId,
            ]);
            $newId = (int)$this->db()->lastInsertId();

            // Copy lines
            $this->db()->prepare("
                INSERT INTO batch_ticket_lines (batch_id, item_id, uom_id, theoretical_quantity, sequence)
                SELECT ?, item_id, uom_id, theoretical_quantity, sequence FROM batch_ticket_lines WHERE batch_id = ?
            ")->execute([$newId, (int)$id]);

            // Copy packs
            $this->db()->prepare("
                INSERT INTO batch_ticket_packs (batch_id, pack_extension_id, target_quantity)
                SELECT ?, pack_extension_id, target_quantity FROM batch_ticket_packs WHERE batch_id = ?
            ")->execute([$newId, (int)$id]);

            // Copy equipment
            $this->db()->prepare("
                INSERT INTO batch_equipment (batch_id, equipment_id)
                SELECT ?, equipment_id FROM batch_equipment WHERE batch_id = ?
            ")->execute([$newId, (int)$id]);

            $this->db()->commit();
            $this->auditCreate('batch_tickets', $newId, ['batch_number' => $batchNumber, 'cloned_from' => $batch['batch_number']]);
            $this->toast("Cloned as {$batchNumber}.", 'success');
            $this->redirect("/batches/{$newId}/edit");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/batches/{$id}");
        }
    }

    // ── Split ───────────────────────────────────────────────────────

    public function split(string $id): void
    {
        if (!$this->checkPermission('batch_tickets', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $batch = $this->getOrFail((int)$id);
        if ($batch['status'] !== 'OPEN') { $this->toast('Only OPEN batches can be split.', 'error'); $this->redirect("/batches/{$id}"); return; }

        $keepQty = (float)($_POST['keep_quantity'] ?? 0);
        $newQty = (float)($_POST['new_quantity'] ?? 0);

        if ($keepQty <= 0 || $newQty <= 0) {
            $this->toast('Both quantities must be positive.', 'error');
            $this->redirect("/batches/{$id}");
            return;
        }

        $originalQty = (float)$batch['target_quantity'];
        $keepFactor = $keepQty / $originalQty;
        $newFactor = $newQty / $originalQty;
        $userId = $this->currentUserId();

        $this->db()->beginTransaction();
        try {
            // Update original batch
            $this->db()->prepare("UPDATE batch_tickets SET target_quantity=?, updated_at=NOW() WHERE id=?")->execute([$keepQty, (int)$id]);

            // Scale original lines
            $this->db()->prepare("UPDATE batch_ticket_lines SET theoretical_quantity = theoretical_quantity * ? WHERE batch_id = ?")->execute([$keepFactor, (int)$id]);

            // Create new batch
            $newBatchNumber = $this->generateBatchNumber();
            $this->db()->prepare("
                INSERT INTO batch_tickets (batch_number, facility_id, item_id, recipe_version_id, qc_spec_id,
                    target_quantity, priority, scheduled_date, assigned_to, internal_notes, external_notes,
                    parent_batch_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $newBatchNumber, $batch['facility_id'], $batch['item_id'], $batch['recipe_version_id'],
                $batch['qc_spec_id'], $newQty, $batch['priority'], $batch['scheduled_date'],
                $batch['assigned_to'], $batch['internal_notes'], $batch['external_notes'], (int)$id, $userId,
            ]);
            $newId = (int)$this->db()->lastInsertId();

            // Copy and scale lines for new batch
            $lines = $this->db()->prepare("SELECT item_id, uom_id, theoretical_quantity, sequence FROM batch_ticket_lines WHERE batch_id = ?");
            $lines->execute([(int)$id]);
            $lineInsert = $this->db()->prepare("INSERT INTO batch_ticket_lines (batch_id, item_id, uom_id, theoretical_quantity, sequence) VALUES (?, ?, ?, ?, ?)");
            foreach ($lines->fetchAll() as $l) {
                // Original was already scaled by keepFactor, so new = original_scaled / keepFactor * newFactor
                $newLineQty = round((float)$l['theoretical_quantity'] / $keepFactor * $newFactor, 4);
                $lineInsert->execute([$newId, $l['item_id'], $l['uom_id'], $newLineQty, $l['sequence']]);
            }

            // Copy equipment
            $this->db()->prepare("INSERT INTO batch_equipment (batch_id, equipment_id) SELECT ?, equipment_id FROM batch_equipment WHERE batch_id = ?")->execute([$newId, (int)$id]);

            // Lineage
            $this->db()->prepare("INSERT INTO batch_lineage (parent_batch_id, child_batch_id) VALUES (?, ?)")->execute([(int)$id, $newId]);

            // Re-reserve: release old, reserve new for both
            if ($this->reservationService) {
                $this->reservationService->releaseAllForReference('BATCH', (int)$id);
                // Re-reserve for original (already scaled)
                $origLines = $this->db()->prepare("SELECT item_id, theoretical_quantity FROM batch_ticket_lines WHERE batch_id = ?");
                $origLines->execute([(int)$id]);
                foreach ($origLines->fetchAll() as $ol) {
                    $this->reservationService->reserve((int)$ol['item_id'], (int)$batch['facility_id'], (float)$ol['theoretical_quantity'], 'BATCH', (int)$id);
                }
                // Reserve for new
                $newLines = $this->db()->prepare("SELECT item_id, theoretical_quantity FROM batch_ticket_lines WHERE batch_id = ?");
                $newLines->execute([$newId]);
                foreach ($newLines->fetchAll() as $nl) {
                    $this->reservationService->reserve((int)$nl['item_id'], (int)$batch['facility_id'], (float)$nl['theoretical_quantity'], 'BATCH', $newId);
                }
            }

            $this->db()->commit();
            $this->auditLog('UPDATE', 'batch_tickets', (int)$id, ['target_quantity' => $originalQty], ['target_quantity' => $keepQty, 'split_to' => $newBatchNumber]);
            $this->auditCreate('batch_tickets', $newId, ['batch_number' => $newBatchNumber, 'split_from' => $batch['batch_number']]);
            $this->toast("Split into {$batch['batch_number']} ({$keepQty}) and {$newBatchNumber} ({$newQty}).", 'success');
            $this->redirect("/batches/{$id}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error splitting: ' . $e->getMessage(), 'error');
            $this->redirect("/batches/{$id}");
        }
    }

    // ── Scrap ───────────────────────────────────────────────────────

    public function logScrap(string $id): void
    {
        if (!$this->checkPermission('batch_tickets', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $batch = $this->getOrFail((int)$id);

        $desc = trim($_POST['material_description'] ?? '');
        $qty = (float)($_POST['quantity'] ?? 0);
        $uomId = (int)($_POST['uom_id'] ?? 0);
        $scrapType = $_POST['scrap_type'] ?? 'PROCESS_LOSS';
        $notes = trim($_POST['notes'] ?? '');
        $itemId = (int)($_POST['item_id'] ?? 0) ?: null;

        if (!$desc || $qty <= 0 || !$uomId) { $this->toast('Description, quantity, and UOM required.', 'error'); $this->redirect("/batches/{$id}#scrap"); return; }

        $this->db()->prepare("INSERT INTO batch_scrap (batch_id, material_description, item_id, quantity, uom_id, scrap_type, notes) VALUES (?,?,?,?,?,?,?)")
            ->execute([(int)$id, $desc, $itemId, $qty, $uomId, $scrapType, $notes ?: null]);
        $this->auditLog('CREATE', 'batch_scrap', (int)$this->db()->lastInsertId(), [], ['batch_id' => (int)$id, 'type' => $scrapType]);
        $this->toast('Scrap entry logged.', 'success');
        $this->redirect("/batches/{$id}#scrap");
    }

    // ── Save as Template ────────────────────────────────────────────

    public function saveTemplate(string $id): void
    {
        if (!$this->checkPermission('batch_tickets', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $batch = $this->getOrFail((int)$id);

        $name = trim($_POST['template_name'] ?? '');
        if (!$name) { $this->toast('Template name required.', 'error'); $this->redirect("/batches/{$id}"); return; }

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("INSERT INTO batch_templates (name, item_id, recipe_version_id, target_quantity, internal_notes, created_by) VALUES (?,?,?,?,?,?)")
                ->execute([$name, $batch['item_id'], $batch['recipe_version_id'], $batch['target_quantity'], $batch['internal_notes'], $this->currentUserId()]);
            $tplId = (int)$this->db()->lastInsertId();

            $this->db()->prepare("INSERT INTO batch_template_packs (template_id, pack_extension_id, target_quantity) SELECT ?, pack_extension_id, target_quantity FROM batch_ticket_packs WHERE batch_id = ?")->execute([$tplId, (int)$id]);
            $this->db()->prepare("INSERT INTO batch_template_equipment (template_id, equipment_id) SELECT ?, equipment_id FROM batch_equipment WHERE batch_id = ?")->execute([$tplId, (int)$id]);

            $this->db()->commit();
            $this->auditCreate('batch_templates', $tplId, ['name' => $name]);
            $this->toast("Template '{$name}' saved.", 'success');
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
        }
        $this->redirect("/batches/{$id}");
    }

    // ── Print PDF ───────────────────────────────────────────────────

    public function printPdf(string $id): void
    {
        if (!$this->checkPermission('batch_tickets', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $batch = $this->getOrFail((int)$id);

        $lines = $this->db()->prepare("SELECT bl.*, i.item_code, i.description as item_description, u.abbreviation as uom_abbr FROM batch_ticket_lines bl JOIN items i ON bl.item_id = i.id LEFT JOIN uom u ON bl.uom_id = u.id WHERE bl.batch_id = ? ORDER BY bl.sequence");
        $lines->execute([(int)$id]);

        // Get recipe instructions
        $instrStmt = $this->db()->prepare("SELECT instruction_text, notes FROM recipe_steps WHERE recipe_version_id = ? AND step_type = 'INSTRUCTION' ORDER BY sequence");
        $instrStmt->execute([$batch['recipe_version_id']]);
        $instructions = $instrStmt->fetchAll();

        $packStmt = $this->db()->prepare("SELECT bp.target_quantity, pe.name as pack_name FROM batch_ticket_packs bp JOIN item_pack_extensions pe ON bp.pack_extension_id = pe.id WHERE bp.batch_id = ?");
        $packStmt->execute([(int)$id]);

        $html = $this->buildBatchPdfHtml($batch, $lines->fetchAll(), $instructions, $packStmt->fetchAll());

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $dompdf->stream("batch_{$batch['batch_number']}.pdf", ['Attachment' => false]);
        exit;
    }

    // ── AJAX: Recipe Ingredients ─────────────────────────────────────

    public function recipeIngredients(): void
    {
        $recipeId = (int)($_GET['recipe_version_id'] ?? 0);
        if (!$recipeId) { $this->jsonResponse([]); return; }

        $steps = $this->getRecipeIngredients($recipeId);
        $this->jsonResponse($steps);
    }

    public function templateData(): void
    {
        $tplId = (int)($_GET['template_id'] ?? 0);
        if (!$tplId) { $this->jsonResponse([]); return; }

        $tpl = $this->db()->prepare("SELECT * FROM batch_templates WHERE id = ?")->fetch() ?: null;
        if (!$tplId) { $this->jsonResponse([]); return; }

        $stmt = $this->db()->prepare("SELECT * FROM batch_templates WHERE id = ?");
        $stmt->execute([$tplId]);
        $tpl = $stmt->fetch();
        if (!$tpl) { $this->jsonResponse([]); return; }

        $packs = $this->db()->prepare("SELECT pack_extension_id, target_quantity FROM batch_template_packs WHERE template_id = ?");
        $packs->execute([$tplId]);

        $equip = $this->db()->prepare("SELECT equipment_id FROM batch_template_equipment WHERE template_id = ?");
        $equip->execute([$tplId]);

        $this->jsonResponse([
            'template' => $tpl,
            'packs' => $packs->fetchAll(),
            'equipment' => $equip->fetchAll(\PDO::FETCH_COLUMN),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT b.*, i.item_code, i.description as item_description,
                   rv.version_name, rv.version_number, f.name as facility_name,
                   u.full_name as assigned_name, cu.full_name as created_by_name
            FROM batch_tickets b
            JOIN items i ON b.item_id = i.id
            LEFT JOIN recipe_versions rv ON b.recipe_version_id = rv.id
            LEFT JOIN facilities f ON b.facility_id = f.id
            LEFT JOIN users u ON b.assigned_to = u.id
            LEFT JOIN users cu ON b.created_by = cu.id
            WHERE b.id = ? AND b.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $b = $stmt->fetch();
        if (!$b) { http_response_code(404); echo 'Batch not found.'; exit; }
        return $b;
    }

    private function getRecipeIngredients(int $recipeVersionId): array
    {
        $stmt = $this->db()->prepare("
            SELECT rs.item_id, rs.quantity, rs.uom_id, rs.sequence,
                   i.item_code, i.description as item_description, u.abbreviation as uom_abbr
            FROM recipe_steps rs
            JOIN items i ON rs.item_id = i.id
            LEFT JOIN uom u ON rs.uom_id = u.id
            WHERE rs.recipe_version_id = ? AND rs.step_type = 'INGREDIENT'
            ORDER BY rs.sequence, rs.id
        ");
        $stmt->execute([$recipeVersionId]);
        return $stmt->fetchAll();
    }

    private function extractHeader(): array
    {
        return [
            'item_id' => (int)($_POST['item_id'] ?? 0),
            'recipe_version_id' => (int)($_POST['recipe_version_id'] ?? 0),
            'facility_id' => (int)($_POST['facility_id'] ?? 0),
            'target_quantity' => (float)($_POST['target_quantity'] ?? 0),
            'priority' => $_POST['priority'] ?? 'NORMAL',
            'scheduled_date' => $_POST['scheduled_date'] ?? date('Y-m-d'),
            'assigned_to' => (int)($_POST['assigned_to'] ?? 0),
            'internal_notes' => trim($_POST['internal_notes'] ?? ''),
            'external_notes' => trim($_POST['external_notes'] ?? ''),
        ];
    }

    private function formData(string $mode, ?array $batch = null, ?array $lines = null): array
    {
        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);
        $active = $this->facilityService->getActiveFacility($userId);

        return [
            'mode' => $mode, 'batch' => $batch, 'lines' => $lines ?? [],
            'facilities' => $facilities,
            'activeFacilityId' => (int)($active['id'] ?? 0),
            'users' => $this->db()->query("SELECT id, full_name FROM users WHERE active=1 ORDER BY full_name")->fetchAll(),
            'uoms' => $this->db()->query("SELECT id, abbreviation FROM uom WHERE active=1 ORDER BY abbreviation")->fetchAll(),
            'equipment' => $this->db()->query("SELECT id, name, equipment_type, facility_id, next_maintenance_due FROM equipment WHERE active=1 ORDER BY name")->fetchAll(),
        ];
    }

    private function generateBatchNumber(): string
    {
        $today = date('Y-m-d');
        $this->db()->prepare('INSERT INTO daily_batch_counter (counter_date, counter) VALUES (?, 1) ON DUPLICATE KEY UPDATE counter = counter + 1')
            ->execute([$today]);
        $stmt = $this->db()->prepare('SELECT counter FROM daily_batch_counter WHERE counter_date = ?');
        $stmt->execute([$today]);
        $counter = (int)$stmt->fetchColumn();
        return date('ymd') . str_pad($counter, 3, '0', STR_PAD_LEFT);
    }

    private function buildBatchPdfHtml(array $batch, array $lines, array $instructions, array $packs): string
    {
        $lineRows = '';
        $n = 1;
        foreach ($lines as $l) {
            $lineRows .= '<tr><td>'.$n++.'</td><td style="font-weight:bold;">'.htmlspecialchars($l['item_code']).'</td><td>'.htmlspecialchars($l['item_description']).'</td><td style="text-align:right;">'.number_format((float)$l['theoretical_quantity'], 4).'</td><td>'.htmlspecialchars($l['uom_abbr'] ?? '').'</td><td style="border-bottom:1px solid #999; width:100px;"></td></tr>';
        }
        $instrHtml = '';
        foreach ($instructions as $i => $ins) {
            $instrHtml .= '<div style="margin-bottom:12px;"><strong>Step '.($i+1).':</strong> '.nl2br(htmlspecialchars($ins['instruction_text'])).'</div>';
        }
        $packHtml = '';
        foreach ($packs as $p) {
            $packHtml .= '<li>'.htmlspecialchars($p['pack_name']).': '.number_format((float)$p['target_quantity'], 4).'</li>';
        }

        return '<!DOCTYPE html><html><head><style>
            body{font-family:Arial,sans-serif;font-size:12px;margin:30px;}
            h1{font-size:22px;margin-bottom:4px;}
            table{width:100%;border-collapse:collapse;margin:12px 0;}
            th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;}
            th{background:#f0f0f0;font-size:11px;}
            .rush{color:#dc2626;font-weight:bold;font-size:14px;}
            .meta{color:#555;margin-bottom:16px;}
        </style></head><body>
            <h1>Batch Ticket: '.htmlspecialchars($batch['batch_number']).'</h1>'
            .($batch['priority']==='RUSH'?'<div class="rush">*** RUSH ***</div>':'')
            .'<div class="meta">Item: <strong>'.htmlspecialchars($batch['item_code'].' — '.$batch['item_description']).'</strong> | Recipe: v'.(int)$batch['version_number'].' '.htmlspecialchars($batch['version_name']).'<br>Facility: '.htmlspecialchars($batch['facility_name']).' | Target: '.number_format((float)$batch['target_quantity'],4).' | Date: '.date('M j, Y',strtotime($batch['scheduled_date'])).'</div>'
            .($batch['internal_notes']?'<p><strong>Notes:</strong> '.htmlspecialchars($batch['internal_notes']).'</p>':'')
            .'<h3>Ingredients</h3><table><thead><tr><th>#</th><th>Item Code</th><th>Description</th><th style="text-align:right;">Theoretical</th><th>UOM</th><th>Actual</th></tr></thead><tbody>'.$lineRows.'</tbody></table>'
            .($instrHtml?'<h3>Instructions</h3>'.$instrHtml:'')
            .($packHtml?'<h3>Target Packs</h3><ul>'.$packHtml.'</ul>':'')
            .'</body></html>';
    }
}
