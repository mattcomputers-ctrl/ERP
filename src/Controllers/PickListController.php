<?php

namespace PrecisionInk\Controllers;

class PickListController extends BaseController
{
    // ── List ────────────────────────────────────────────────────────

    public function index(): void
    {
        if (!$this->checkPermission('pick_lists', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterStatus = $_GET['status'] ?? '';
        $filterFacility = (int)($_GET['facility_id'] ?? 0);

        $where = [];
        $params = [];
        if ($filterStatus) { $where[] = 'pl.status = ?'; $params[] = $filterStatus; }
        if ($filterFacility) { $where[] = 'pl.facility_id = ?'; $params[] = $filterFacility; }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM pick_lists pl {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT pl.*, f.name as facility_name, u.full_name as created_by_name,
                   (SELECT COUNT(*) FROM pick_list_orders WHERE pick_list_id = pl.id) as so_count,
                   (SELECT COUNT(*) FROM pick_list_lines WHERE pick_list_id = pl.id) as line_count,
                   (SELECT COUNT(*) FROM pick_list_lines WHERE pick_list_id = pl.id AND status IN ('PICKED','PARTIAL')) as picked_count
            FROM pick_lists pl
            LEFT JOIN facilities f ON pl.facility_id = f.id
            LEFT JOIN users u ON pl.created_by = u.id
            {$whereClause}
            ORDER BY pl.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);

        $this->renderView('pick_lists/list', [
            'pickLists' => $stmt->fetchAll(), 'facilities' => $facilities,
            'filters' => ['status' => $filterStatus, 'facility_id' => $filterFacility],
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    // ── Create ──────────────────────────────────────────────────────

    public function createForm(): void
    {
        if (!$this->checkPermission('pick_lists', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $userId = $this->currentUserId();
        $active = $this->facilityService->getActiveFacility($userId);
        $facilityId = (int)($active['id'] ?? 0);

        // Get confirmed SOs at this facility with unshipped lines
        $stmt = $this->db()->prepare("
            SELECT so.id, so.so_number, c.company_name as customer_name,
                   st.location_name as ship_to_name, so.promised_ship_date,
                   (SELECT COUNT(*) FROM sales_order_lines sol WHERE sol.so_id = so.id AND sol.ordered_quantity > sol.shipped_quantity) as unshipped_lines
            FROM sales_orders so
            JOIN customers c ON so.customer_id = c.id
            LEFT JOIN ship_to_locations st ON so.ship_to_id = st.id
            WHERE so.facility_id = ? AND so.status = 'CONFIRMED' AND so.deleted_at IS NULL
            HAVING unshipped_lines > 0
            ORDER BY so.promised_ship_date ASC, so.so_number
        ");
        $stmt->execute([$facilityId]);

        $this->renderView('pick_lists/create', [
            'orders' => $stmt->fetchAll(),
            'facilityId' => $facilityId,
            'facilityName' => $active['name'] ?? '',
        ]);
    }

    public function store(): void
    {
        if (!$this->checkPermission('pick_lists', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $soIds = $_POST['so_ids'] ?? [];
        if (empty($soIds)) {
            $this->toast('Select at least one sales order.', 'error');
            $this->redirect('/pick-lists/create');
            return;
        }

        $userId = $this->currentUserId();
        $active = $this->facilityService->getActiveFacility($userId);
        $facilityId = (int)($active['id'] ?? 0);

        $this->db()->beginTransaction();
        try {
            $pklNumber = $this->generateDocumentNumber('PICK_LIST');

            $this->db()->prepare("INSERT INTO pick_lists (pkl_number, facility_id, status, created_by) VALUES (?, ?, 'OPEN', ?)")
                ->execute([$pklNumber, $facilityId, $userId]);
            $pklId = (int)$this->db()->lastInsertId();

            $orderInsert = $this->db()->prepare("INSERT INTO pick_list_orders (pick_list_id, so_id) VALUES (?, ?)");
            $lineInsert = $this->db()->prepare("
                INSERT INTO pick_list_lines (pick_list_id, so_line_id, quantity_to_pick, lot_number, status)
                VALUES (?, ?, ?, ?, 'PENDING')
            ");

            foreach ($soIds as $soId) {
                $soId = (int)$soId;
                $orderInsert->execute([$pklId, $soId]);

                // Get unshipped lines
                $lineStmt = $this->db()->prepare("
                    SELECT sol.id, sol.item_id, sol.ordered_quantity, sol.shipped_quantity
                    FROM sales_order_lines sol
                    WHERE sol.so_id = ? AND sol.ordered_quantity > sol.shipped_quantity
                ");
                $lineStmt->execute([$soId]);

                foreach ($lineStmt->fetchAll() as $sol) {
                    $qtyToPick = (float)$sol['ordered_quantity'] - (float)$sol['shipped_quantity'];

                    // Pre-fill lot from FIFO
                    $suggestedLot = null;
                    if ($this->fifoService) {
                        $lots = $this->fifoService->getLotsForItem((int)$sol['item_id'], $facilityId, true);
                        if (!empty($lots)) {
                            $suggestedLot = $lots[0]['lot_number'];
                        }
                    }

                    $lineInsert->execute([$pklId, $sol['id'], $qtyToPick, $suggestedLot]);
                }
            }

            $this->db()->commit();
            $this->auditCreate('pick_lists', $pklId, ['pkl_number' => $pklNumber, 'so_count' => count($soIds)]);
            $this->toast("Pick list {$pklNumber} created.", 'success');
            $this->redirect("/pick-lists/{$pklId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect('/pick-lists/create');
        }
    }

    // ── View / Digital Confirmation ─────────────────────────────────

    public function show(string $id): void
    {
        if (!$this->checkPermission('pick_lists', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $pkl = $this->getOrFail((int)$id);

        // Get SOs on this pick list with combined notes
        $soStmt = $this->db()->prepare("
            SELECT plo.so_id, so.so_number, c.company_name as customer_name,
                   st.location_name as ship_to_name, st.street as ship_to_street,
                   st.city as ship_to_city, st.state as ship_to_state, st.zip as ship_to_zip,
                   so.internal_notes as so_notes,
                   c.default_internal_notes as customer_notes,
                   st.default_internal_notes as ship_to_notes
            FROM pick_list_orders plo
            JOIN sales_orders so ON plo.so_id = so.id
            JOIN customers c ON so.customer_id = c.id
            LEFT JOIN ship_to_locations st ON so.ship_to_id = st.id
            WHERE plo.pick_list_id = ?
        ");
        $soStmt->execute([(int)$id]);
        $pickOrders = $soStmt->fetchAll();

        // Combine notes per SO
        foreach ($pickOrders as &$po) {
            $po['combined_notes'] = implode("\n\n", array_filter([
                trim($po['customer_notes'] ?? ''),
                trim($po['ship_to_notes'] ?? ''),
                trim($po['so_notes'] ?? ''),
            ], fn($s) => $s !== ''));
        }
        unset($po);

        // Get all lines with item, location, SO info
        $lineStmt = $this->db()->prepare("
            SELECT pll.*, sol.so_id, sol.item_id, sol.pack_extension_id, sol.uom_id,
                   so.so_number, c.company_name as customer_name,
                   i.item_code, i.description as item_description,
                   pe.name as pack_name, u.abbreviation as uom_abbr,
                   COALESCE(ifl.location, '') as location
            FROM pick_list_lines pll
            JOIN sales_order_lines sol ON pll.so_line_id = sol.id
            JOIN sales_orders so ON sol.so_id = so.id
            JOIN customers c ON so.customer_id = c.id
            JOIN items i ON sol.item_id = i.id
            LEFT JOIN item_pack_extensions pe ON sol.pack_extension_id = pe.id
            LEFT JOIN uom u ON sol.uom_id = u.id
            LEFT JOIN item_facility_locations ifl ON ifl.item_id = sol.item_id AND ifl.facility_id = ?
            WHERE pll.pick_list_id = ?
            ORDER BY so.so_number, pll.id
        ");
        $lineStmt->execute([(int)$pkl['facility_id'], (int)$id]);
        $lines = $lineStmt->fetchAll();

        $this->renderView('pick_lists/view', [
            'pkl' => $pkl, 'pickOrders' => $pickOrders, 'lines' => $lines,
        ]);
    }

    // ── Confirm Line (AJAX) ─────────────────────────────────────────

    public function confirmLine(string $id): void
    {
        if (!$this->checkPermission('pick_lists', 'edit')) { http_response_code(403); $this->jsonResponse(['error' => 'Denied'], 403); return; }

        $lineId = (int)($_POST['line_id'] ?? 0);
        $qtyPicked = (float)($_POST['quantity_picked'] ?? 0);
        $lotNumber = trim($_POST['lot_number'] ?? '');
        $status = $_POST['status'] ?? 'PICKED';
        $unableReason = trim($_POST['unable_reason'] ?? '');

        if (!$lineId) { $this->jsonResponse(['error' => 'Line ID required'], 400); return; }
        if ($status === 'UNABLE' && !$unableReason) { $this->jsonResponse(['error' => 'Reason required'], 400); return; }

        $this->db()->prepare("
            UPDATE pick_list_lines SET quantity_picked = ?, lot_number = ?, status = ?, unable_reason = ?, updated_at = NOW()
            WHERE id = ? AND pick_list_id = ?
        ")->execute([
            $status === 'UNABLE' ? 0 : $qtyPicked,
            $lotNumber ?: null,
            $status,
            $status === 'UNABLE' ? $unableReason : null,
            $lineId, (int)$id,
        ]);

        // Check if all lines done
        $remaining = $this->db()->prepare("SELECT COUNT(*) FROM pick_list_lines WHERE pick_list_id = ? AND status = 'PENDING'");
        $remaining->execute([(int)$id]);
        $pendingCount = (int)$remaining->fetchColumn();

        $this->jsonResponse(['success' => true, 'status' => $status, 'pending_count' => $pendingCount]);
    }

    // ── Complete ────────────────────────────────────────────────────

    public function complete(string $id): void
    {
        if (!$this->checkPermission('pick_lists', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $this->db()->prepare("UPDATE pick_lists SET status = 'COMPLETE', updated_at = NOW() WHERE id = ?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'pick_lists', (int)$id, ['status' => 'OPEN'], ['status' => 'COMPLETE']);
        $this->toast('Pick list completed.', 'success');
        $this->redirect("/pick-lists/{$id}");
    }

    // ── Print PDF ───────────────────────────────────────────────────

    public function printPdf(string $id): void
    {
        if (!$this->checkPermission('pick_lists', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $pkl = $this->getOrFail((int)$id);

        // Get SOs and lines
        $soStmt = $this->db()->prepare("
            SELECT plo.so_id, so.so_number, c.company_name as customer_name,
                   st.location_name as ship_to_name, st.street as ship_to_street,
                   st.city as ship_to_city, st.state as ship_to_state, st.zip as ship_to_zip,
                   so.internal_notes as so_notes, c.default_internal_notes as customer_notes,
                   st.default_internal_notes as ship_to_notes
            FROM pick_list_orders plo
            JOIN sales_orders so ON plo.so_id = so.id
            JOIN customers c ON so.customer_id = c.id
            LEFT JOIN ship_to_locations st ON so.ship_to_id = st.id
            WHERE plo.pick_list_id = ?
        ");
        $soStmt->execute([(int)$id]);
        $pickOrders = $soStmt->fetchAll();

        $lineStmt = $this->db()->prepare("
            SELECT pll.*, sol.so_id, i.item_code, i.description as item_description,
                   pe.name as pack_name, COALESCE(ifl.location, '') as location
            FROM pick_list_lines pll
            JOIN sales_order_lines sol ON pll.so_line_id = sol.id
            JOIN items i ON sol.item_id = i.id
            LEFT JOIN item_pack_extensions pe ON sol.pack_extension_id = pe.id
            LEFT JOIN item_facility_locations ifl ON ifl.item_id = sol.item_id AND ifl.facility_id = ?
            WHERE pll.pick_list_id = ?
            ORDER BY COALESCE(ifl.location, ''), i.item_code
        ");
        $lineStmt->execute([(int)$pkl['facility_id'], (int)$id]);
        $lines = $lineStmt->fetchAll();

        // Group lines by SO
        $linesBySo = [];
        foreach ($lines as $l) {
            $linesBySo[$l['so_id']][] = $l;
        }

        $html = $this->buildPickListPdf($pkl, $pickOrders, $linesBySo);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $dompdf->stream("PKL_{$pkl['pkl_number']}.pdf", ['Attachment' => false]);
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT pl.*, f.name as facility_name, u.full_name as created_by_name
            FROM pick_lists pl
            LEFT JOIN facilities f ON pl.facility_id = f.id
            LEFT JOIN users u ON pl.created_by = u.id
            WHERE pl.id = ?
        ");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if (!$p) { http_response_code(404); echo 'Pick list not found.'; exit; }
        return $p;
    }

    private function buildPickListPdf(array $pkl, array $pickOrders, array $linesBySo): string
    {
        $soSections = '';
        foreach ($pickOrders as $po) {
            $notes = implode("\n", array_filter([trim($po['customer_notes'] ?? ''), trim($po['ship_to_notes'] ?? ''), trim($po['so_notes'] ?? '')], fn($s) => $s !== ''));
            $addr = implode(', ', array_filter([$po['ship_to_street'] ?? '', $po['ship_to_city'] ?? '', $po['ship_to_state'] ?? '', $po['ship_to_zip'] ?? '']));

            $soSections .= '<div style="margin-top:20px; page-break-inside:avoid;">';
            $soSections .= '<div style="background:#e5e7eb; padding:8px 12px; border-radius:4px; margin-bottom:8px;">';
            $soSections .= '<strong style="font-size:14px;">' . htmlspecialchars($po['so_number']) . ' — ' . htmlspecialchars($po['customer_name']) . '</strong>';
            if ($po['ship_to_name']) $soSections .= '<br><span style="font-size:11px;">Ship To: ' . htmlspecialchars($po['ship_to_name']) . ($addr ? ' — ' . htmlspecialchars($addr) : '') . '</span>';
            $soSections .= '</div>';

            if ($notes) {
                $soSections .= '<div style="background:#fef9c3; border:1px solid #fde68a; padding:6px 10px; border-radius:4px; margin-bottom:8px; font-size:11px;"><strong>NOTES:</strong> ' . nl2br(htmlspecialchars($notes)) . '</div>';
            }

            $soSections .= '<table><thead><tr><th style="width:90px;">Location</th><th>Item Code</th><th>Description</th><th>Pack</th><th>Lot</th><th style="text-align:right;">Qty</th><th style="width:40px;">&#10003;</th></tr></thead><tbody>';
            foreach ($linesBySo[$po['so_id']] ?? [] as $l) {
                $soSections .= '<tr style="height:28px;">';
                $soSections .= '<td style="font-weight:bold; font-size:12px;">' . htmlspecialchars($l['location']) . '</td>';
                $soSections .= '<td style="font-weight:bold;">' . htmlspecialchars($l['item_code']) . '</td>';
                $soSections .= '<td>' . htmlspecialchars($l['item_description']) . '</td>';
                $soSections .= '<td>' . htmlspecialchars($l['pack_name'] ?? '') . '</td>';
                $soSections .= '<td style="font-size:11px;">' . htmlspecialchars($l['lot_number'] ?? '') . '</td>';
                $soSections .= '<td style="text-align:right; font-weight:bold;">' . number_format((float)$l['quantity_to_pick'], 4) . '</td>';
                $soSections .= '<td></td>';
                $soSections .= '</tr>';
            }
            $soSections .= '</tbody></table></div>';
        }

        return '<!DOCTYPE html><html><head><style>
            body { font-family: Arial, sans-serif; font-size: 11px; margin: 25px; }
            h1 { font-size: 22px; margin-bottom: 4px; }
            .meta { color: #555; margin-bottom: 12px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
            th { background: #f0f0f0; font-size: 10px; }
            tr { page-break-inside: avoid; }
        </style></head><body>
            <h1>PICK LIST</h1>
            <div style="font-size:18px; font-weight:bold; margin-bottom:8px;">' . htmlspecialchars($pkl['pkl_number']) . '</div>
            <div class="meta">Facility: ' . htmlspecialchars($pkl['facility_name']) . ' | Date: ' . date('M j, Y') . ' | Created by: ' . htmlspecialchars($pkl['created_by_name'] ?? '') . '</div>'
            . $soSections
            . '</body></html>';
    }
}
