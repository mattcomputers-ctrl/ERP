<?php

namespace PrecisionInk\Controllers;

class TransferController extends BaseController
{
    /**
     * List all transfer orders with filters and pagination.
     */
    public function index(): void
    {
        if (!$this->checkPermission('inter_facility_transfers', 'view')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        // Filters
        $filterStatus = $_GET['status'] ?? '';
        $filterFrom = (int)($_GET['from_facility'] ?? 0);
        $filterTo = (int)($_GET['to_facility'] ?? 0);
        $filterDateFrom = $_GET['date_from'] ?? '';
        $filterDateTo = $_GET['date_to'] ?? '';

        $where = [];
        $params = [];

        if ($filterStatus) {
            $where[] = 'to2.status = ?';
            $params[] = $filterStatus;
        }
        if ($filterFrom) {
            $where[] = 'to2.from_facility_id = ?';
            $params[] = $filterFrom;
        }
        if ($filterTo) {
            $where[] = 'to2.to_facility_id = ?';
            $params[] = $filterTo;
        }
        if ($filterDateFrom) {
            $where[] = 'to2.requested_date >= ?';
            $params[] = $filterDateFrom;
        }
        if ($filterDateTo) {
            $where[] = 'to2.requested_date <= ?';
            $params[] = $filterDateTo;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM transfer_orders to2 $whereClause";
        $stmt = $this->db()->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT to2.*, ff.name as from_facility_name, tf.name as to_facility_name, u.full_name as created_by_name
                FROM transfer_orders to2
                LEFT JOIN facilities ff ON to2.from_facility_id = ff.id
                LEFT JOIN facilities tf ON to2.to_facility_id = tf.id
                LEFT JOIN users u ON to2.created_by = u.id
                $whereClause
                ORDER BY to2.created_at DESC
                LIMIT $perPage OFFSET $offset";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $transfers = $stmt->fetchAll();

        $facilities = $this->facilityService->getAllActiveFacilities();

        $this->renderView('transfers/list', [
            'transfers'  => $transfers,
            'facilities' => $facilities,
            'filters'    => [
                'status'        => $filterStatus,
                'from_facility' => $filterFrom,
                'to_facility'   => $filterTo,
                'date_from'     => $filterDateFrom,
                'date_to'       => $filterDateTo,
            ],
            'page'       => $page,
            'perPage'    => $perPage,
            'total'      => $total,
            'totalPages' => max(1, ceil($total / $perPage)),
        ]);
    }

    /**
     * Show create form.
     */
    public function create(): void
    {
        if (!$this->checkPermission('inter_facility_transfers', 'create')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $userId = $this->currentUserId();
        $userFacilities = $this->facilityService->getUserFacilities($userId);
        $allFacilities = $this->facilityService->getAllActiveFacilities();
        $uoms = $this->db()->query('SELECT * FROM uom WHERE active = 1 ORDER BY name')->fetchAll();

        $this->renderView('transfers/form', [
            'transfer'       => null,
            'lines'          => [],
            'userFacilities' => $userFacilities,
            'allFacilities'  => $allFacilities,
            'uoms'           => $uoms,
            'mode'           => 'create',
        ]);
    }

    /**
     * Save new transfer order.
     */
    public function store(): void
    {
        if (!$this->checkPermission('inter_facility_transfers', 'create')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $userId = $this->currentUserId();
        $fromFacilityId = (int)($_POST['from_facility_id'] ?? 0);
        $toFacilityId = (int)($_POST['to_facility_id'] ?? 0);
        $requestedDate = $_POST['requested_date'] ?? date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '') ?: null;

        // Validate
        $errors = [];
        if (!$fromFacilityId) $errors[] = 'Source facility is required.';
        if (!$toFacilityId) $errors[] = 'Destination facility is required.';
        if ($fromFacilityId && $toFacilityId && $fromFacilityId === $toFacilityId) {
            $errors[] = 'Source and destination facilities must differ.';
        }
        if ($fromFacilityId && !$this->facilityService->canAccessFacility($userId, $fromFacilityId)) {
            $errors[] = 'You do not have access to the source facility.';
        }

        $lineItems = $_POST['lines'] ?? [];
        if (empty($lineItems) || !is_array($lineItems)) {
            $errors[] = 'At least one line item is required.';
        }

        $validLines = [];
        if (is_array($lineItems)) {
            foreach ($lineItems as $i => $line) {
                $itemId = (int)($line['item_id'] ?? 0);
                $qty = (float)($line['quantity'] ?? 0);
                $uomId = (int)($line['uom_id'] ?? 0);
                $packExtId = (int)($line['pack_extension_id'] ?? 0) ?: null;

                if (!$itemId) continue;
                if ($qty <= 0) {
                    $errors[] = "Line " . ($i + 1) . ": Quantity must be > 0.";
                    continue;
                }
                if (!$uomId) {
                    $errors[] = "Line " . ($i + 1) . ": UOM is required.";
                    continue;
                }
                $validLines[] = [
                    'item_id' => $itemId,
                    'pack_extension_id' => $packExtId,
                    'quantity' => $qty,
                    'uom_id' => $uomId,
                ];
            }
        }

        if (empty($validLines) && !in_array('At least one line item is required.', $errors)) {
            $errors[] = 'At least one valid line item is required.';
        }

        if ($errors) {
            $this->toast(implode(' ', $errors), 'error');
            $this->redirect('/transfers/create');
            return;
        }

        $trfNumber = $this->generateDocumentNumber('TRANSFER');

        $this->db()->beginTransaction();
        try {
            $stmt = $this->db()->prepare(
                'INSERT INTO transfer_orders (trf_number, from_facility_id, to_facility_id, requested_date, status, notes, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([$trfNumber, $fromFacilityId, $toFacilityId, $requestedDate, 'DRAFT', $notes, $userId]);
            $transferId = (int)$this->db()->lastInsertId();

            $lineStmt = $this->db()->prepare(
                'INSERT INTO transfer_order_lines (transfer_id, item_id, pack_extension_id, quantity, uom_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
            );
            foreach ($validLines as $line) {
                $lineStmt->execute([
                    $transferId, $line['item_id'], $line['pack_extension_id'],
                    $line['quantity'], $line['uom_id']
                ]);
            }

            $this->db()->commit();
            $this->auditCreate('transfer_orders', $transferId, ['trf_number' => $trfNumber, 'status' => 'DRAFT']);
            $this->toast("Transfer order $trfNumber created.");
            $this->redirect("/transfers/$transferId");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error creating transfer: ' . $e->getMessage(), 'error');
            $this->redirect('/transfers/create');
        }
    }

    /**
     * View a transfer order.
     */
    public function view(string $id): void
    {
        if (!$this->checkPermission('inter_facility_transfers', 'view')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $transfer = $this->getTransferOrFail((int)$id);
        $lines = $this->getTransferLines((int)$id);
        $attachments = $this->attachmentService->getForRecord('transfer_order', (int)$id);

        $this->renderView('transfers/view', [
            'transfer'    => $transfer,
            'lines'       => $lines,
            'attachments' => $attachments,
            'recordType'  => 'transfer_order',
            'recordId'    => (int)$id,
        ]);
    }

    /**
     * Show edit form (DRAFT only).
     */
    public function edit(string $id): void
    {
        if (!$this->checkPermission('inter_facility_transfers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $transfer = $this->getTransferOrFail((int)$id);
        if ($transfer['status'] !== 'DRAFT') {
            $this->toast('Only DRAFT transfers can be edited.', 'error');
            $this->redirect("/transfers/$id");
            return;
        }

        $lines = $this->getTransferLines((int)$id);
        $userId = $this->currentUserId();
        $userFacilities = $this->facilityService->getUserFacilities($userId);
        $allFacilities = $this->facilityService->getAllActiveFacilities();
        $uoms = $this->db()->query('SELECT * FROM uom WHERE active = 1 ORDER BY name')->fetchAll();

        $this->renderView('transfers/form', [
            'transfer'       => $transfer,
            'lines'          => $lines,
            'userFacilities' => $userFacilities,
            'allFacilities'  => $allFacilities,
            'uoms'           => $uoms,
            'mode'           => 'edit',
        ]);
    }

    /**
     * Save edits to a DRAFT transfer order.
     */
    public function update(string $id): void
    {
        if (!$this->checkPermission('inter_facility_transfers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $transferId = (int)$id;
        $transfer = $this->getTransferOrFail($transferId);
        if ($transfer['status'] !== 'DRAFT') {
            $this->toast('Only DRAFT transfers can be edited.', 'error');
            $this->redirect("/transfers/$id");
            return;
        }

        $userId = $this->currentUserId();
        $fromFacilityId = (int)($_POST['from_facility_id'] ?? 0);
        $toFacilityId = (int)($_POST['to_facility_id'] ?? 0);
        $requestedDate = $_POST['requested_date'] ?? date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '') ?: null;

        $errors = [];
        if (!$fromFacilityId) $errors[] = 'Source facility is required.';
        if (!$toFacilityId) $errors[] = 'Destination facility is required.';
        if ($fromFacilityId && $toFacilityId && $fromFacilityId === $toFacilityId) {
            $errors[] = 'Source and destination must differ.';
        }
        if ($fromFacilityId && !$this->facilityService->canAccessFacility($userId, $fromFacilityId)) {
            $errors[] = 'You do not have access to the source facility.';
        }

        $lineItems = $_POST['lines'] ?? [];
        $validLines = [];
        if (is_array($lineItems)) {
            foreach ($lineItems as $i => $line) {
                $itemId = (int)($line['item_id'] ?? 0);
                $qty = (float)($line['quantity'] ?? 0);
                $uomId = (int)($line['uom_id'] ?? 0);
                $packExtId = (int)($line['pack_extension_id'] ?? 0) ?: null;
                if (!$itemId) continue;
                if ($qty <= 0) { $errors[] = "Line " . ($i + 1) . ": Quantity must be > 0."; continue; }
                if (!$uomId) { $errors[] = "Line " . ($i + 1) . ": UOM is required."; continue; }
                $validLines[] = ['item_id' => $itemId, 'pack_extension_id' => $packExtId, 'quantity' => $qty, 'uom_id' => $uomId];
            }
        }
        if (empty($validLines)) $errors[] = 'At least one valid line item is required.';

        if ($errors) {
            $this->toast(implode(' ', $errors), 'error');
            $this->redirect("/transfers/$id/edit");
            return;
        }

        $old = $transfer;
        $this->db()->beginTransaction();
        try {
            $this->db()->prepare(
                'UPDATE transfer_orders SET from_facility_id = ?, to_facility_id = ?, requested_date = ?, notes = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$fromFacilityId, $toFacilityId, $requestedDate, $notes, $transferId]);

            // Replace lines
            $this->db()->prepare('DELETE FROM transfer_order_lines WHERE transfer_id = ?')->execute([$transferId]);
            $lineStmt = $this->db()->prepare(
                'INSERT INTO transfer_order_lines (transfer_id, item_id, pack_extension_id, quantity, uom_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
            );
            foreach ($validLines as $line) {
                $lineStmt->execute([$transferId, $line['item_id'], $line['pack_extension_id'], $line['quantity'], $line['uom_id']]);
            }

            $this->db()->commit();
            $this->auditUpdate('transfer_orders', $transferId, $old, array_merge($old, [
                'from_facility_id' => $fromFacilityId,
                'to_facility_id' => $toFacilityId,
                'requested_date' => $requestedDate,
                'notes' => $notes,
            ]));
            $this->toast('Transfer order updated.');
            $this->redirect("/transfers/$transferId");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error updating transfer: ' . $e->getMessage(), 'error');
            $this->redirect("/transfers/$id/edit");
        }
    }

    /**
     * Show ship confirmation form.
     */
    public function shipForm(string $id): void
    {
        if (!$this->checkPermission('inter_facility_transfers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $transfer = $this->getTransferOrFail((int)$id);
        if ($transfer['status'] !== 'DRAFT') {
            $this->toast('Only DRAFT transfers can be shipped.', 'error');
            $this->redirect("/transfers/$id");
            return;
        }

        $userId = $this->currentUserId();
        if (!$this->facilityService->canAccessFacility($userId, (int)$transfer['from_facility_id'])) {
            $this->toast('You do not have access to the source facility.', 'error');
            $this->redirect("/transfers/$id");
            return;
        }

        $lines = $this->getTransferLines((int)$id);

        // Get available FIFO lots for each line at the from_facility
        foreach ($lines as &$line) {
            $line['available_lots'] = $this->getAvailableLots(
                (int)$line['item_id'],
                (int)$transfer['from_facility_id']
            );
            // Inter-facility availability for informational purposes
            $line['other_facility_stock'] = $this->getOtherFacilityStock(
                (int)$line['item_id'],
                (int)$transfer['from_facility_id']
            );
        }
        unset($line);

        $this->renderView('transfers/ship', [
            'transfer' => $transfer,
            'lines'    => $lines,
        ]);
    }

    /**
     * Execute ship step.
     */
    public function ship(string $id): void
    {
        if (!$this->checkPermission('inter_facility_transfers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $transferId = (int)$id;
        $transfer = $this->getTransferOrFail($transferId);
        if ($transfer['status'] !== 'DRAFT') {
            $this->toast('Only DRAFT transfers can be shipped.', 'error');
            $this->redirect("/transfers/$id");
            return;
        }

        $userId = $this->currentUserId();
        if (!$this->facilityService->canAccessFacility($userId, (int)$transfer['from_facility_id'])) {
            $this->toast('You do not have access to the source facility.', 'error');
            $this->redirect("/transfers/$id");
            return;
        }

        $lotSelections = $_POST['lot_selections'] ?? [];
        $lines = $this->getTransferLines($transferId);

        $this->db()->beginTransaction();
        try {
            foreach ($lines as $line) {
                $lineId = (int)$line['id'];
                $lineLots = $lotSelections[$lineId] ?? [];
                $totalShipped = 0;

                foreach ($lineLots as $lotData) {
                    $lotNumber = $lotData['lot_number'] ?? '';
                    $qty = (float)($lotData['quantity'] ?? 0);
                    $sourceLotId = (int)($lotData['fifo_lot_id'] ?? 0) ?: null;

                    if ($qty <= 0 || !$lotNumber) continue;

                    // TODO: wire to FIFOService::consume() in Session 10
                    // FIFOService::consume($line['item_id'], $transfer['from_facility_id'], $qty, 'TRANSFER', $lineId);

                    $this->db()->prepare(
                        'INSERT INTO transfer_line_lots (transfer_line_id, lot_number, quantity, source_fifo_lot_id, created_at) VALUES (?, ?, ?, ?, NOW())'
                    )->execute([$lineId, $lotNumber, $qty, $sourceLotId]);

                    $totalShipped += $qty;
                }

                $this->db()->prepare(
                    'UPDATE transfer_order_lines SET shipped_quantity = ? WHERE id = ?'
                )->execute([$totalShipped, $lineId]);
            }

            $this->db()->prepare(
                'UPDATE transfer_orders SET status = ?, shipped_by = ?, shipped_at = NOW(), updated_at = NOW() WHERE id = ?'
            )->execute(['SHIPPED', $userId, $transferId]);

            $this->db()->commit();
            $this->auditLog('UPDATE', 'transfer_orders', $transferId,
                ['status' => 'DRAFT'],
                ['status' => 'SHIPPED', 'shipped_by' => $userId]
            );
            $this->toast("Transfer {$transfer['trf_number']} shipped.");
            $this->redirect("/transfers/$transferId");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error shipping transfer: ' . $e->getMessage(), 'error');
            $this->redirect("/transfers/$transferId");
        }
    }

    /**
     * Show receive confirmation form.
     */
    public function receiveForm(string $id): void
    {
        if (!$this->checkPermission('inter_facility_transfers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $transfer = $this->getTransferOrFail((int)$id);
        if ($transfer['status'] !== 'SHIPPED') {
            $this->toast('Only SHIPPED transfers can be received.', 'error');
            $this->redirect("/transfers/$id");
            return;
        }

        $userId = $this->currentUserId();
        if (!$this->facilityService->canAccessFacility($userId, (int)$transfer['to_facility_id'])) {
            $this->toast('You do not have access to the destination facility.', 'error');
            $this->redirect("/transfers/$id");
            return;
        }

        $lines = $this->getTransferLines((int)$id);
        // Get lot details for each line
        foreach ($lines as &$line) {
            $stmt = $this->db()->prepare(
                'SELECT * FROM transfer_line_lots WHERE transfer_line_id = ?'
            );
            $stmt->execute([(int)$line['id']]);
            $line['lots'] = $stmt->fetchAll();
        }
        unset($line);

        $this->renderView('transfers/receive', [
            'transfer' => $transfer,
            'lines'    => $lines,
        ]);
    }

    /**
     * Execute receive step.
     */
    public function receive(string $id): void
    {
        if (!$this->checkPermission('inter_facility_transfers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $transferId = (int)$id;
        $transfer = $this->getTransferOrFail($transferId);
        if ($transfer['status'] !== 'SHIPPED') {
            $this->toast('Only SHIPPED transfers can be received.', 'error');
            $this->redirect("/transfers/$id");
            return;
        }

        $userId = $this->currentUserId();
        if (!$this->facilityService->canAccessFacility($userId, (int)$transfer['to_facility_id'])) {
            $this->toast('You do not have access to the destination facility.', 'error');
            $this->redirect("/transfers/$id");
            return;
        }

        $receivedQtys = $_POST['received_quantities'] ?? [];
        $lines = $this->getTransferLines($transferId);

        $this->db()->beginTransaction();
        try {
            foreach ($lines as $line) {
                $lineId = (int)$line['id'];
                $receivedQty = (float)($receivedQtys[$lineId] ?? $line['shipped_quantity']);

                // Cap at shipped quantity
                if ($receivedQty > (float)$line['shipped_quantity']) {
                    $receivedQty = (float)$line['shipped_quantity'];
                }

                // Get lots for this line
                $lotStmt = $this->db()->prepare('SELECT * FROM transfer_line_lots WHERE transfer_line_id = ?');
                $lotStmt->execute([$lineId]);
                $lots = $lotStmt->fetchAll();

                foreach ($lots as $lot) {
                    // TODO: wire to FIFOService::addLot() in Session 10
                    // FIFOService::addLot($line['item_id'], $transfer['to_facility_id'], $lot['quantity'], $unitCost, $lot['lot_number'], 'TRANSFER', $lineId);
                    // Update dest_fifo_lot_id once FIFOService returns the new lot ID
                }

                $this->db()->prepare(
                    'UPDATE transfer_order_lines SET received_quantity = ? WHERE id = ?'
                )->execute([$receivedQty, $lineId]);
            }

            $this->db()->prepare(
                'UPDATE transfer_orders SET status = ?, received_by = ?, received_at = NOW(), updated_at = NOW() WHERE id = ?'
            )->execute(['RECEIVED', $userId, $transferId]);

            $this->db()->commit();
            $this->auditLog('UPDATE', 'transfer_orders', $transferId,
                ['status' => 'SHIPPED'],
                ['status' => 'RECEIVED', 'received_by' => $userId]
            );
            $this->toast("Transfer {$transfer['trf_number']} received.");
            $this->redirect("/transfers/$transferId");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error receiving transfer: ' . $e->getMessage(), 'error');
            $this->redirect("/transfers/$transferId");
        }
    }

    /**
     * Cancel a transfer order.
     */
    public function cancel(string $id): void
    {
        if (!$this->checkPermission('inter_facility_transfers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $transferId = (int)$id;
        $transfer = $this->getTransferOrFail($transferId);
        if (!in_array($transfer['status'], ['DRAFT', 'SHIPPED'])) {
            $this->toast('This transfer cannot be cancelled.', 'error');
            $this->redirect("/transfers/$id");
            return;
        }

        $userId = $this->currentUserId();
        $oldStatus = $transfer['status'];

        $this->db()->prepare(
            'UPDATE transfer_orders SET status = ?, updated_at = NOW() WHERE id = ?'
        )->execute(['CANCELLED', $transferId]);

        $this->auditLog('UPDATE', 'transfer_orders', $transferId,
            ['status' => $oldStatus],
            ['status' => 'CANCELLED']
        );
        $this->toast("Transfer {$transfer['trf_number']} cancelled.");
        $this->redirect("/transfers/$transferId");
    }

    /**
     * Facility switch handler.
     */
    public function switchFacility(): void
    {
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $userId = $this->currentUserId();

        if ($userId && $this->facilityService->setActiveFacility($userId, $facilityId)) {
            $redirect = $_SERVER['HTTP_REFERER'] ?? '/';
            $this->redirect($redirect);
        } else {
            $this->redirect('/');
        }
    }

    /**
     * AJAX: Search items for typeahead.
     */
    public function searchItems(): void
    {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            $this->jsonResponse([]);
            return;
        }

        $stmt = $this->db()->prepare(
            "SELECT i.id, i.item_code, i.description, i.uom_id, u.abbreviation as uom_abbr
             FROM items i
             LEFT JOIN uom u ON i.uom_id = u.id
             WHERE i.active = 1 AND i.deleted_at IS NULL
               AND (i.item_code LIKE ? OR i.description LIKE ?)
             ORDER BY i.item_code
             LIMIT 20"
        );
        $search = "%$q%";
        $stmt->execute([$search, $search]);
        $this->jsonResponse($stmt->fetchAll());
    }

    /**
     * AJAX: Get pack extensions for an item.
     */
    public function itemPackExtensions(): void
    {
        $itemId = (int)($_GET['item_id'] ?? 0);
        if (!$itemId) {
            $this->jsonResponse([]);
            return;
        }

        $stmt = $this->db()->prepare(
            'SELECT id, description, net_weight, tare_weight FROM item_pack_extensions WHERE item_id = ? ORDER BY description'
        );
        $stmt->execute([$itemId]);
        $this->jsonResponse($stmt->fetchAll());
    }

    // ── Private helpers ───────────────────────────────────────────

    private function getTransferOrFail(int $id): array
    {
        $stmt = $this->db()->prepare(
            'SELECT to2.*, ff.name as from_facility_name, ff.code as from_facility_code,
                    tf.name as to_facility_name, tf.code as to_facility_code,
                    u.full_name as created_by_name,
                    us.full_name as shipped_by_name,
                    ur.full_name as received_by_name
             FROM transfer_orders to2
             LEFT JOIN facilities ff ON to2.from_facility_id = ff.id
             LEFT JOIN facilities tf ON to2.to_facility_id = tf.id
             LEFT JOIN users u ON to2.created_by = u.id
             LEFT JOIN users us ON to2.shipped_by = us.id
             LEFT JOIN users ur ON to2.received_by = ur.id
             WHERE to2.id = ?'
        );
        $stmt->execute([$id]);
        $transfer = $stmt->fetch();
        if (!$transfer) {
            http_response_code(404);
            echo 'Transfer order not found.';
            exit;
        }
        return $transfer;
    }

    private function getTransferLines(int $transferId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT tl.*, i.item_code, i.description as item_description, u.abbreviation as uom_abbr,
                    pe.description as pack_description
             FROM transfer_order_lines tl
             LEFT JOIN items i ON tl.item_id = i.id
             LEFT JOIN uom u ON tl.uom_id = u.id
             LEFT JOIN item_pack_extensions pe ON tl.pack_extension_id = pe.id
             WHERE tl.transfer_id = ?
             ORDER BY tl.id'
        );
        $stmt->execute([$transferId]);
        return $stmt->fetchAll();
    }

    private function getAvailableLots(int $itemId, int $facilityId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT fl.id, fl.lot_number, fl.remaining_quantity, fl.expiration_date,
                    ifl.location
             FROM fifo_lots fl
             LEFT JOIN item_facility_locations ifl ON ifl.item_id = fl.item_id AND ifl.facility_id = fl.facility_id
             WHERE fl.item_id = ? AND fl.facility_id = ? AND fl.remaining_quantity > 0
             ORDER BY fl.expiration_date ASC, fl.created_at ASC"
        );
        $stmt->execute([$itemId, $facilityId]);
        return $stmt->fetchAll();
    }

    private function getOtherFacilityStock(int $itemId, int $excludeFacilityId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT f.name as facility_name, SUM(fl.remaining_quantity) as available_qty
             FROM fifo_lots fl
             JOIN facilities f ON fl.facility_id = f.id
             WHERE fl.item_id = ? AND fl.facility_id != ? AND fl.remaining_quantity > 0 AND f.active = 1
             GROUP BY f.id, f.name
             HAVING available_qty > 0"
        );
        $stmt->execute([$itemId, $excludeFacilityId]);
        return $stmt->fetchAll();
    }
}
