<?php

namespace PrecisionInk\Controllers;

use App\Exceptions\NegativeInventoryException;

class InventoryController extends BaseController
{
    // ── Stock On Hand ───────────────────────────────────────────────

    public function index(): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $userId = $this->currentUserId();
        $facilityId = (int)($_GET['facility_id'] ?? 0);
        $facilities = $this->facilityService->getUserFacilities($userId);

        if (!$facilityId && $this->facilityService) {
            $active = $this->facilityService->getActiveFacility($userId);
            $facilityId = (int)($active['id'] ?? 0);
        }

        $filterType = $_GET['item_type'] ?? '';
        $filterSearch = trim($_GET['q'] ?? '');
        $filterBelowMin = isset($_GET['below_min']);

        $where = ['i.deleted_at IS NULL', 'i.active = 1'];
        $params = [];

        if ($filterType) { $where[] = 'i.item_type = ?'; $params[] = $filterType; }
        if ($filterSearch) {
            $where[] = '(i.item_code LIKE ? OR i.description LIKE ?)';
            $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%";
        }

        $whereClause = implode(' AND ', $where);

        // Get all items with inventory data
        if ($facilityId) {
            $sql = "
                SELECT i.id, i.item_code, i.description, i.item_type, i.reorder_min,
                       COALESCE(ifl.location, '') as location,
                       COALESCE(oh.on_hand, 0) as on_hand,
                       COALESCE(br.batch_reserved, 0) as batch_reserved,
                       COALESCE(qr.quote_reserved, 0) as quote_reserved,
                       COALESCE(it.in_transit, 0) as in_transit
                FROM items i
                LEFT JOIN item_facility_locations ifl ON ifl.item_id = i.id AND ifl.facility_id = ?
                LEFT JOIN (SELECT item_id, SUM(remaining_quantity) as on_hand FROM fifo_lots WHERE facility_id = ? AND status = 'AVAILABLE' GROUP BY item_id) oh ON oh.item_id = i.id
                LEFT JOIN (SELECT item_id, SUM(quantity) as batch_reserved FROM inventory_reservations WHERE facility_id = ? AND reservation_type = 'BATCH' GROUP BY item_id) br ON br.item_id = i.id
                LEFT JOIN (SELECT item_id, SUM(quantity) as quote_reserved FROM inventory_reservations WHERE facility_id = ? AND reservation_type = 'QUOTE' GROUP BY item_id) qr ON qr.item_id = i.id
                LEFT JOIN (SELECT tol.item_id, SUM(tol.shipped_quantity - COALESCE(tol.received_quantity, 0)) as in_transit
                           FROM transfer_order_lines tol
                           JOIN transfer_orders t ON tol.transfer_id = t.id AND t.status = 'SHIPPED' AND t.to_facility_id = ?
                           GROUP BY tol.item_id) it ON it.item_id = i.id
                WHERE {$whereClause}
                HAVING on_hand > 0 OR batch_reserved > 0 OR quote_reserved > 0 OR in_transit > 0
                ORDER BY i.item_code
            ";
            $facilityParams = [$facilityId, $facilityId, $facilityId, $facilityId, $facilityId];
        } else {
            // All facilities consolidated
            $sql = "
                SELECT i.id, i.item_code, i.description, i.item_type, i.reorder_min,
                       '' as location,
                       COALESCE(oh.on_hand, 0) as on_hand,
                       COALESCE(br.batch_reserved, 0) as batch_reserved,
                       COALESCE(qr.quote_reserved, 0) as quote_reserved,
                       COALESCE(it.in_transit, 0) as in_transit
                FROM items i
                LEFT JOIN (SELECT item_id, SUM(remaining_quantity) as on_hand FROM fifo_lots WHERE status = 'AVAILABLE' GROUP BY item_id) oh ON oh.item_id = i.id
                LEFT JOIN (SELECT item_id, SUM(quantity) as batch_reserved FROM inventory_reservations WHERE reservation_type = 'BATCH' GROUP BY item_id) br ON br.item_id = i.id
                LEFT JOIN (SELECT item_id, SUM(quantity) as quote_reserved FROM inventory_reservations WHERE reservation_type = 'QUOTE' GROUP BY item_id) qr ON qr.item_id = i.id
                LEFT JOIN (SELECT tol.item_id, SUM(tol.shipped_quantity - COALESCE(tol.received_quantity, 0)) as in_transit
                           FROM transfer_order_lines tol
                           JOIN transfer_orders t ON tol.transfer_id = t.id AND t.status = 'SHIPPED'
                           GROUP BY tol.item_id) it ON it.item_id = i.id
                WHERE {$whereClause}
                HAVING on_hand > 0 OR batch_reserved > 0 OR quote_reserved > 0 OR in_transit > 0
                ORDER BY i.item_code
            ";
            $facilityParams = [];
        }

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($facilityParams, $params));
        $items = $stmt->fetchAll();

        // Calculate available for each
        foreach ($items as &$item) {
            $item['available'] = max(0, (float)$item['on_hand'] - (float)$item['batch_reserved'] - (float)$item['quote_reserved']);
        }
        unset($item);

        if ($filterBelowMin) {
            $items = array_filter($items, fn($i) => $i['reorder_min'] !== null && (float)$i['on_hand'] < (float)$i['reorder_min']);
            $items = array_values($items);
        }

        $this->renderView('inventory/stock', [
            'items' => $items,
            'facilities' => $facilities,
            'facilityId' => $facilityId,
            'filters' => ['item_type' => $filterType, 'q' => $filterSearch, 'below_min' => $filterBelowMin],
        ]);
    }

    // ── FIFO Lot Detail ─────────────────────────────────────────────

    public function lots(string $itemId): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $userId = $this->currentUserId();
        $active = $this->facilityService->getActiveFacility($userId);
        $facilityId = (int)($active['id'] ?? 0);

        $item = $this->db()->prepare("SELECT * FROM items WHERE id = ? AND deleted_at IS NULL");
        $item->execute([(int)$itemId]);
        $item = $item->fetch();
        if (!$item) { http_response_code(404); echo 'Item not found.'; exit; }

        $lots = $this->fifoService->getLotsForItem((int)$itemId, $facilityId);

        $this->renderView('inventory/lots', [
            'item' => $item,
            'lots' => $lots,
            'facilityId' => $facilityId,
        ]);
    }

    // ── Manual Adjustments ──────────────────────────────────────────

    public function adjustmentForm(): void
    {
        if (!$this->checkPermission('inventory', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $userId = $this->currentUserId();
        $active = $this->facilityService->getActiveFacility($userId);
        $facilities = $this->facilityService->getUserFacilities($userId);
        $reasonCodes = $this->db()->query("SELECT id, name FROM reason_codes WHERE active = 1 ORDER BY name")->fetchAll();

        $this->renderView('inventory/adjustment', [
            'facilities' => $facilities,
            'activeFacilityId' => (int)($active['id'] ?? 0),
            'reasonCodes' => $reasonCodes,
        ]);
    }

    public function saveAdjustment(): void
    {
        if (!$this->checkPermission('inventory', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $itemId = (int)($_POST['item_id'] ?? 0);
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $quantity = (float)($_POST['quantity'] ?? 0);
        $reasonCodeId = (int)($_POST['reason_code_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $lotNumber = trim($_POST['lot_number'] ?? '') ?: 'ADJ-' . date('Ymd-His');

        if (!$itemId || !$facilityId || $quantity == 0) {
            $this->toast('Item, facility, and non-zero quantity are required.', 'error');
            $this->redirect('/inventory/adjustments');
            return;
        }

        $userId = $this->currentUserId();

        $this->db()->beginTransaction();
        try {
            if ($quantity > 0) {
                $this->fifoService->addLot(
                    $itemId, $facilityId, $quantity, 0, $lotNumber,
                    'ADJUSTMENT', $reasonCodeId, null, 'AVAILABLE', $userId
                );
            } else {
                $this->fifoService->consume(
                    $itemId, $facilityId, abs($quantity),
                    'ADJUSTMENT', $reasonCodeId, $userId
                );
            }
            $this->db()->commit();

            $this->auditLog('CREATE', 'inventory_adjustment', 0, [], [
                'item_id' => $itemId, 'facility_id' => $facilityId,
                'quantity' => $quantity, 'reason_code_id' => $reasonCodeId, 'notes' => $notes,
            ]);
            $this->toast('Inventory adjustment saved.', 'success');
        } catch (NegativeInventoryException $e) {
            $this->db()->rollBack();
            $this->toast($e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
        }

        $this->redirect('/inventory/adjustments');
    }

    // ── Quarantine ──────────────────────────────────────────────────

    public function quarantineList(): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $lots = $this->db()->query("
            SELECT fl.*, i.item_code, i.description as item_description,
                   f.name as facility_name, u.full_name as quarantined_by_name
            FROM fifo_lots fl
            JOIN items i ON fl.item_id = i.id
            JOIN facilities f ON fl.facility_id = f.id
            LEFT JOIN users u ON fl.quarantined_by = u.id
            WHERE fl.status = 'QUARANTINED'
            ORDER BY fl.quarantined_at DESC
        ")->fetchAll();

        $this->renderView('inventory/quarantine', ['lots' => $lots]);
    }

    public function quarantineLot(string $lotId): void
    {
        if (!$this->checkPermission('inventory', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $reason = trim($_POST['reason'] ?? '');
        if (!$reason) { $this->toast('Quarantine reason is required.', 'error'); $this->redirect($_SERVER['HTTP_REFERER'] ?? '/inventory'); return; }

        $this->fifoService->quarantine((int)$lotId, $reason, $this->currentUserId());
        $this->auditLog('UPDATE', 'fifo_lots', (int)$lotId, ['status' => 'AVAILABLE'], ['status' => 'QUARANTINED', 'reason' => $reason]);
        $this->toast('Lot quarantined.', 'success');
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/inventory/quarantine');
    }

    public function releaseLot(string $lotId): void
    {
        if (!$this->checkPermission('inventory', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $this->fifoService->releaseQuarantine((int)$lotId, $this->currentUserId());
        $this->auditLog('UPDATE', 'fifo_lots', (int)$lotId, ['status' => 'QUARANTINED'], ['status' => 'AVAILABLE']);
        $this->toast('Lot released from quarantine.', 'success');
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/inventory/quarantine');
    }

    // ── Transaction History ─────────────────────────────────────────

    public function transactions(): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->db()->query("SELECT COUNT(*) FROM inventory_transactions");
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $txns = $this->db()->prepare("
            SELECT t.*, i.item_code, i.description as item_description,
                   f.name as facility_name, u.full_name as user_name,
                   fl.lot_number
            FROM inventory_transactions t
            JOIN items i ON t.item_id = i.id
            JOIN facilities f ON t.facility_id = f.id
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN fifo_lots fl ON t.fifo_lot_id = fl.id
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $txns->execute([$perPage, $offset]);

        $this->renderView('inventory/transactions', [
            'txns' => $txns->fetchAll(),
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    // ── Cycle Counts ────────────────────────────────────────────────

    public function countSessions(): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $sessions = $this->db()->query("
            SELECT cs.*, f.name as facility_name, u.full_name as created_by_name,
                   pu.full_name as posted_by_name,
                   (SELECT COUNT(*) FROM cycle_count_lines WHERE session_id = cs.id) as line_count,
                   (SELECT COUNT(*) FROM cycle_count_lines WHERE session_id = cs.id AND counted_quantity IS NOT NULL) as counted_count
            FROM cycle_count_sessions cs
            JOIN facilities f ON cs.facility_id = f.id
            JOIN users u ON cs.created_by = u.id
            LEFT JOIN users pu ON cs.posted_by = pu.id
            ORDER BY cs.created_at DESC
        ")->fetchAll();

        $this->renderView('inventory/counts_list', ['sessions' => $sessions]);
    }

    public function createCountForm(): void
    {
        if (!$this->checkPermission('inventory', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);
        $active = $this->facilityService->getActiveFacility($userId);

        $this->renderView('inventory/count_create', [
            'facilities' => $facilities,
            'activeFacilityId' => (int)($active['id'] ?? 0),
        ]);
    }

    public function createCount(): void
    {
        if (!$this->checkPermission('inventory', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $isBlind = isset($_POST['is_blind']) ? 1 : 0;
        $itemSelection = $_POST['item_selection'] ?? 'all';
        $itemType = $_POST['item_type'] ?? '';
        $customItems = $_POST['custom_items'] ?? [];

        if (!$facilityId) { $this->toast('Facility is required.', 'error'); $this->redirect('/inventory/counts/create'); return; }

        $userId = $this->currentUserId();

        // Get items to count
        if ($itemSelection === 'by_type' && $itemType) {
            $itemStmt = $this->db()->prepare("SELECT id FROM items WHERE item_type = ? AND active = 1 AND deleted_at IS NULL");
            $itemStmt->execute([$itemType]);
        } elseif ($itemSelection === 'custom' && !empty($customItems)) {
            $placeholders = implode(',', array_fill(0, count($customItems), '?'));
            $itemStmt = $this->db()->prepare("SELECT id FROM items WHERE id IN ({$placeholders}) AND active = 1 AND deleted_at IS NULL");
            $itemStmt->execute($customItems);
        } else {
            $itemStmt = $this->db()->query("SELECT id FROM items WHERE active = 1 AND deleted_at IS NULL");
        }
        $itemIds = $itemStmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($itemIds)) { $this->toast('No items to count.', 'error'); $this->redirect('/inventory/counts/create'); return; }

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("INSERT INTO cycle_count_sessions (facility_id, is_blind, created_by) VALUES (?, ?, ?)")
                ->execute([$facilityId, $isBlind, $userId]);
            $sessionId = (int)$this->db()->lastInsertId();

            $insertLine = $this->db()->prepare("INSERT INTO cycle_count_lines (session_id, item_id, book_quantity) VALUES (?, ?, ?)");
            foreach ($itemIds as $iId) {
                $bookQty = $this->fifoService->getOnHand((int)$iId, $facilityId);
                $insertLine->execute([$sessionId, (int)$iId, $bookQty]);
            }

            $this->db()->commit();
            $this->auditCreate('cycle_count_sessions', $sessionId, ['facility_id' => $facilityId, 'items' => count($itemIds)]);
            $this->toast('Cycle count session created with ' . count($itemIds) . ' items.', 'success');
            $this->redirect("/inventory/counts/{$sessionId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect('/inventory/counts/create');
        }
    }

    public function countSession(string $id): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $session = $this->getCountSessionOrFail((int)$id);
        $lines = $this->db()->prepare("
            SELECT cl.*, i.item_code, i.description as item_description,
                   COALESCE(ifl.location, '') as location
            FROM cycle_count_lines cl
            JOIN items i ON cl.item_id = i.id
            LEFT JOIN item_facility_locations ifl ON ifl.item_id = cl.item_id AND ifl.facility_id = ?
            WHERE cl.session_id = ?
            ORDER BY COALESCE(ifl.location, ''), i.item_code
        ");
        $lines->execute([(int)$session['facility_id'], (int)$id]);

        $this->renderView('inventory/count_entry', [
            'session' => $session,
            'lines' => $lines->fetchAll(),
        ]);
    }

    public function saveCountEntry(string $id): void
    {
        if (!$this->checkPermission('inventory', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $session = $this->getCountSessionOrFail((int)$id);
        if ($session['status'] !== 'OPEN') { $this->toast('Session is not open for entry.', 'error'); $this->redirect("/inventory/counts/{$id}"); return; }

        $counts = $_POST['counts'] ?? [];
        $markComplete = isset($_POST['mark_complete']);

        foreach ($counts as $lineId => $counted) {
            if ($counted === '' || $counted === null) continue;
            $countedQty = (float)$counted;
            $this->db()->prepare("
                UPDATE cycle_count_lines SET counted_quantity = ?, variance = ? - book_quantity, updated_at = NOW()
                WHERE id = ? AND session_id = ?
            ")->execute([$countedQty, $countedQty, (int)$lineId, (int)$id]);
        }

        if ($markComplete) {
            $this->db()->prepare("UPDATE cycle_count_sessions SET status = 'IN_REVIEW', updated_at = NOW() WHERE id = ?")->execute([(int)$id]);
            $this->toast('Count session marked for review.', 'success');
            $this->redirect("/inventory/counts/{$id}/review");
        } else {
            $this->toast('Counts saved.', 'success');
            $this->redirect("/inventory/counts/{$id}");
        }
    }

    public function reviewCount(string $id): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $session = $this->getCountSessionOrFail((int)$id);
        $lines = $this->db()->prepare("
            SELECT cl.*, i.item_code, i.description as item_description,
                   COALESCE(fl_cost.avg_cost, 0) as avg_unit_cost
            FROM cycle_count_lines cl
            JOIN items i ON cl.item_id = i.id
            LEFT JOIN (
                SELECT item_id, facility_id, AVG(unit_cost) as avg_cost
                FROM fifo_lots WHERE status = 'AVAILABLE' AND remaining_quantity > 0
                GROUP BY item_id, facility_id
            ) fl_cost ON fl_cost.item_id = cl.item_id AND fl_cost.facility_id = ?
            WHERE cl.session_id = ? AND cl.counted_quantity IS NOT NULL
            ORDER BY i.item_code
        ");
        $lines->execute([(int)$session['facility_id'], (int)$id]);

        $this->renderView('inventory/count_review', [
            'session' => $session,
            'lines' => $lines->fetchAll(),
        ]);
    }

    public function postCount(string $id): void
    {
        if (!$this->checkPermission('inventory', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $session = $this->getCountSessionOrFail((int)$id);
        if (!in_array($session['status'], ['OPEN', 'IN_REVIEW'])) {
            $this->toast('Session cannot be posted.', 'error');
            $this->redirect("/inventory/counts/{$id}");
            return;
        }

        $approvedLines = $_POST['approved'] ?? [];
        $userId = $this->currentUserId();
        $posted = 0;
        $errors = [];

        $this->db()->beginTransaction();
        try {
            // Mark approved lines
            if (!empty($approvedLines)) {
                $placeholders = implode(',', array_fill(0, count($approvedLines), '?'));
                $this->db()->prepare("UPDATE cycle_count_lines SET approved = 1 WHERE id IN ({$placeholders}) AND session_id = ?")
                    ->execute(array_merge($approvedLines, [(int)$id]));
            }

            // Process approved lines with variance
            $lines = $this->db()->prepare("
                SELECT cl.*, i.item_code FROM cycle_count_lines cl
                JOIN items i ON cl.item_id = i.id
                WHERE cl.session_id = ? AND cl.approved = 1 AND cl.variance IS NOT NULL AND cl.variance != 0
            ");
            $lines->execute([(int)$id]);

            foreach ($lines->fetchAll() as $line) {
                $variance = (float)$line['variance'];
                $lotNumber = 'CC-' . date('Ymd') . '-' . $id;

                try {
                    if ($variance > 0) {
                        $this->fifoService->addLot(
                            (int)$line['item_id'], (int)$session['facility_id'],
                            $variance, 0, $lotNumber, 'ADJUSTMENT', (int)$id,
                            null, 'AVAILABLE', $userId
                        );
                    } else {
                        $this->fifoService->consume(
                            (int)$line['item_id'], (int)$session['facility_id'],
                            abs($variance), 'ADJUSTMENT', (int)$id, $userId
                        );
                    }
                    $posted++;
                } catch (NegativeInventoryException $e) {
                    $errors[] = "{$line['item_code']}: {$e->getMessage()}";
                }
            }

            $this->db()->prepare("UPDATE cycle_count_sessions SET status = 'POSTED', posted_by = ?, posted_at = NOW(), updated_at = NOW() WHERE id = ?")
                ->execute([$userId, (int)$id]);

            $this->db()->commit();
            $this->auditLog('UPDATE', 'cycle_count_sessions', (int)$id, ['status' => $session['status']], ['status' => 'POSTED']);

            $msg = "Posted {$posted} adjustment(s).";
            if (!empty($errors)) $msg .= ' Errors: ' . implode('; ', $errors);
            $this->toast($msg, empty($errors) ? 'success' : 'warning');
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error posting: ' . $e->getMessage(), 'error');
        }

        $this->redirect("/inventory/counts/{$id}/review");
    }

    public function cancelCount(string $id): void
    {
        if (!$this->checkPermission('inventory', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $session = $this->getCountSessionOrFail((int)$id);
        if ($session['status'] === 'POSTED') { $this->toast('Cannot cancel a posted session.', 'error'); $this->redirect("/inventory/counts/{$id}"); return; }

        $this->db()->prepare("UPDATE cycle_count_sessions SET status = 'CANCELLED', updated_at = NOW() WHERE id = ?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'cycle_count_sessions', (int)$id, ['status' => $session['status']], ['status' => 'CANCELLED']);
        $this->toast('Count session cancelled.', 'success');
        $this->redirect('/inventory/counts');
    }

    public function countSheet(string $id): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $session = $this->getCountSessionOrFail((int)$id);
        $lines = $this->db()->prepare("
            SELECT cl.*, i.item_code, i.description as item_description,
                   COALESCE(ifl.location, '') as location,
                   pe.name as pack_name
            FROM cycle_count_lines cl
            JOIN items i ON cl.item_id = i.id
            LEFT JOIN item_facility_locations ifl ON ifl.item_id = cl.item_id AND ifl.facility_id = ?
            LEFT JOIN item_pack_extensions pe ON cl.pack_extension_id = pe.id
            WHERE cl.session_id = ?
            ORDER BY COALESCE(ifl.location, ''), i.item_code
        ");
        $lines->execute([(int)$session['facility_id'], (int)$id]);
        $lines = $lines->fetchAll();

        $html = '<html><head><style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            h1 { font-size: 18px; margin-bottom: 4px; }
            .meta { color: #666; margin-bottom: 12px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
            th { background: #f0f0f0; font-size: 11px; }
            .count-field { width: 80px; height: 20px; border: 1px solid #999; }
            .notes-field { width: 100%; height: 20px; }
        </style></head><body>';
        $html .= '<h1>Cycle Count Sheet</h1>';
        $html .= '<div class="meta">Facility: ' . htmlspecialchars($session['facility_name']) . ' | Date: ' . date('M j, Y') . ' | Session #' . $id . ' | ' . ($session['is_blind'] ? 'BLIND' : 'NON-BLIND') . '</div>';
        $html .= '<table><thead><tr><th>Location</th><th>Item Code</th><th>Description</th><th>Pack</th>';
        if (!$session['is_blind']) $html .= '<th>Book Qty</th>';
        $html .= '<th>Counted Qty</th><th>Notes</th></tr></thead><tbody>';
        foreach ($lines as $l) {
            $html .= '<tr><td>' . htmlspecialchars($l['location']) . '</td>';
            $html .= '<td>' . htmlspecialchars($l['item_code']) . '</td>';
            $html .= '<td>' . htmlspecialchars($l['item_description']) . '</td>';
            $html .= '<td>' . htmlspecialchars($l['pack_name'] ?? '') . '</td>';
            if (!$session['is_blind']) $html .= '<td>' . number_format((float)$l['book_quantity'], 4) . '</td>';
            $html .= '<td><div class="count-field"></div></td>';
            $html .= '<td><div class="notes-field"></div></td></tr>';
        }
        $html .= '</tbody></table></body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();
        $dompdf->stream("count_sheet_{$id}.pdf", ['Attachment' => false]);
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getCountSessionOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT cs.*, f.name as facility_name, u.full_name as created_by_name
            FROM cycle_count_sessions cs
            JOIN facilities f ON cs.facility_id = f.id
            JOIN users u ON cs.created_by = u.id
            WHERE cs.id = ?
        ");
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        if (!$s) { http_response_code(404); echo 'Count session not found.'; exit; }
        return $s;
    }
}
