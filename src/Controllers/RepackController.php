<?php

namespace PrecisionInk\Controllers;

class RepackController extends BaseController
{
    public function index(): void
    {
        if (!$this->checkPermission('repack', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterStatus = $_GET['status'] ?? '';
        $filterFacility = (int)($_GET['facility_id'] ?? 0);
        $filterSearch = trim($_GET['q'] ?? '');

        $where = [];
        $params = [];
        if ($filterStatus) { $where[] = 'r.status = ?'; $params[] = $filterStatus; }
        if ($filterFacility) { $where[] = 'r.facility_id = ?'; $params[] = $filterFacility; }
        if ($filterSearch) { $where[] = '(r.rpk_number LIKE ? OR i.item_code LIKE ?)'; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM repack_tickets r LEFT JOIN items i ON r.item_id = i.id {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT r.*, i.item_code, i.description as item_description, f.name as facility_name,
                   sp.name as source_pack_name, dp.name as dest_pack_name
            FROM repack_tickets r
            JOIN items i ON r.item_id = i.id
            LEFT JOIN facilities f ON r.facility_id = f.id
            LEFT JOIN item_pack_extensions sp ON r.source_pack_id = sp.id
            LEFT JOIN item_pack_extensions dp ON r.destination_pack_id = dp.id
            {$whereClause}
            ORDER BY r.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);

        $this->renderView('repack/list', [
            'repacks' => $stmt->fetchAll(), 'facilities' => $facilities,
            'filters' => ['status' => $filterStatus, 'facility_id' => $filterFacility, 'q' => $filterSearch],
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    public function create(): void
    {
        if (!$this->checkPermission('repack', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->renderView('repack/edit', $this->formData('create'));
    }

    public function store(): void
    {
        if (!$this->checkPermission('repack', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $data = $this->extractData();
        $errors = $this->validate($data);
        if (!empty($errors)) {
            $this->toast(implode(' ', $errors), 'error');
            $this->renderView('repack/edit', $this->formData('create', $data));
            return;
        }

        $rpkNumber = $this->generateDocumentNumber('REPACK_TICKET');

        $this->db()->prepare("
            INSERT INTO repack_tickets (rpk_number, facility_id, item_id, source_pack_id, source_quantity, destination_pack_id, destination_quantity, reason, repack_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'OPEN')
        ")->execute([
            $rpkNumber, $data['facility_id'], $data['item_id'],
            $data['source_pack_id'] ?: null, $data['source_quantity'],
            $data['destination_pack_id'] ?: null, $data['destination_quantity'],
            $data['reason'] ?: null, $data['repack_date'],
        ]);
        $id = (int)$this->db()->lastInsertId();

        $this->auditCreate('repack_tickets', $id, ['rpk_number' => $rpkNumber]);
        $this->toast("Repack ticket {$rpkNumber} created.", 'success');
        $this->redirect("/repack/{$id}");
    }

    public function show(string $id): void
    {
        if (!$this->checkPermission('repack', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $repack = $this->getOrFail((int)$id);

        // Get available source inventory
        $available = 0;
        if ($this->fifoService) {
            $available = $this->fifoService->getAvailable((int)$repack['item_id'], (int)$repack['facility_id']);
        }

        $this->renderView('repack/view', ['repack' => $repack, 'available' => $available]);
    }

    public function editForm(string $id): void
    {
        if (!$this->checkPermission('repack', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $repack = $this->getOrFail((int)$id);
        if ($repack['status'] !== 'OPEN') { $this->toast('Only OPEN repacks can be edited.', 'error'); $this->redirect("/repack/{$id}"); return; }
        $this->renderView('repack/edit', $this->formData('edit', $repack));
    }

    public function update(string $id): void
    {
        if (!$this->checkPermission('repack', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $repack = $this->getOrFail((int)$id);
        if ($repack['status'] !== 'OPEN') { $this->toast('Only OPEN repacks can be edited.', 'error'); $this->redirect("/repack/{$id}"); return; }

        $data = $this->extractData();

        $this->db()->prepare("
            UPDATE repack_tickets SET facility_id=?, item_id=?, source_pack_id=?, source_quantity=?,
                destination_pack_id=?, destination_quantity=?, reason=?, repack_date=?, updated_at=NOW() WHERE id=?
        ")->execute([
            $data['facility_id'], $data['item_id'],
            $data['source_pack_id'] ?: null, $data['source_quantity'],
            $data['destination_pack_id'] ?: null, $data['destination_quantity'],
            $data['reason'] ?: null, $data['repack_date'], (int)$id,
        ]);

        $this->auditUpdate('repack_tickets', (int)$id, $repack, $data);
        $this->toast('Repack ticket updated.', 'success');
        $this->redirect("/repack/{$id}");
    }

    public function close(string $id): void
    {
        if (!$this->checkPermission('repack', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $repack = $this->getOrFail((int)$id);
        if ($repack['status'] !== 'OPEN') { $this->toast('Only OPEN repacks can be closed.', 'error'); $this->redirect("/repack/{$id}"); return; }

        $userId = $this->currentUserId();

        $this->db()->beginTransaction();
        try {
            // 1. Consume source
            $consumed = $this->fifoService->consume(
                (int)$repack['item_id'], (int)$repack['facility_id'],
                (float)$repack['source_quantity'], 'REPACK', (int)$id, $userId
            );

            // 2. Calculate weighted average cost
            $totalCost = 0;
            foreach ($consumed as $c) {
                $totalCost += $c['quantity_consumed'] * $c['unit_cost'];
            }
            $weightedAvgCost = (float)$repack['source_quantity'] > 0 ? $totalCost / (float)$repack['source_quantity'] : 0;

            // 3. Create destination lot (inherit primary source lot number)
            $lotNumber = !empty($consumed) ? $consumed[0]['lot_number'] : 'RPK-' . $repack['rpk_number'];

            $this->fifoService->addLot(
                (int)$repack['item_id'], (int)$repack['facility_id'],
                (float)$repack['destination_quantity'], round($weightedAvgCost, 4),
                $lotNumber, 'REPACK', (int)$id, null, 'AVAILABLE', $userId,
                $repack['destination_pack_id'] ? (int)$repack['destination_pack_id'] : null
            );

            // 4. Update status
            $this->db()->prepare("UPDATE repack_tickets SET status='CLOSED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);

            $this->db()->commit();
            $this->auditLog('UPDATE', 'repack_tickets', (int)$id, ['status' => 'OPEN'], ['status' => 'CLOSED', 'cost' => $totalCost]);
            $this->toast("Repack {$repack['rpk_number']} closed. Source consumed, destination lot created.", 'success');
            $this->redirect("/repack/{$id}");
        } catch (\App\Exceptions\NegativeInventoryException $e) {
            $this->db()->rollBack();
            $this->toast('Inventory shortfall: ' . $e->getMessage(), 'error');
            $this->redirect("/repack/{$id}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect("/repack/{$id}");
        }
    }

    public function cancel(string $id): void
    {
        if (!$this->checkPermission('repack', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $repack = $this->getOrFail((int)$id);
        if ($repack['status'] !== 'OPEN') { $this->toast('Only OPEN repacks can be cancelled.', 'error'); $this->redirect("/repack/{$id}"); return; }

        $this->db()->prepare("UPDATE repack_tickets SET status='CANCELLED', updated_at=NOW() WHERE id=?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'repack_tickets', (int)$id, ['status' => 'OPEN'], ['status' => 'CANCELLED']);
        $this->toast("Repack {$repack['rpk_number']} cancelled.", 'success');
        $this->redirect("/repack/{$id}");
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT r.*, i.item_code, i.description as item_description, f.name as facility_name,
                   sp.name as source_pack_name, dp.name as dest_pack_name
            FROM repack_tickets r
            JOIN items i ON r.item_id = i.id
            LEFT JOIN facilities f ON r.facility_id = f.id
            LEFT JOIN item_pack_extensions sp ON r.source_pack_id = sp.id
            LEFT JOIN item_pack_extensions dp ON r.destination_pack_id = dp.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if (!$r) { http_response_code(404); echo 'Repack ticket not found.'; exit; }
        return $r;
    }

    private function extractData(): array
    {
        return [
            'facility_id' => (int)($_POST['facility_id'] ?? 0),
            'item_id' => (int)($_POST['item_id'] ?? 0),
            'source_pack_id' => (int)($_POST['source_pack_id'] ?? 0),
            'source_quantity' => (float)($_POST['source_quantity'] ?? 0),
            'destination_pack_id' => (int)($_POST['destination_pack_id'] ?? 0),
            'destination_quantity' => (float)($_POST['destination_quantity'] ?? 0),
            'reason' => trim($_POST['reason'] ?? ''),
            'repack_date' => $_POST['repack_date'] ?? date('Y-m-d'),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (!$data['item_id']) $errors[] = 'Item is required.';
        if (!$data['facility_id']) $errors[] = 'Facility is required.';
        if ($data['source_quantity'] <= 0) $errors[] = 'Source quantity must be positive.';
        if ($data['destination_quantity'] <= 0) $errors[] = 'Destination quantity must be positive.';
        if ($data['source_pack_id'] === $data['destination_pack_id']) $errors[] = 'Source and destination packs must be different.';
        return $errors;
    }

    private function formData(string $mode, ?array $repack = null): array
    {
        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);
        $active = $this->facilityService->getActiveFacility($userId);
        return [
            'mode' => $mode, 'repack' => $repack,
            'facilities' => $facilities, 'activeFacilityId' => (int)($active['id'] ?? 0),
        ];
    }
}
