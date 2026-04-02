<?php

namespace PrecisionInk\Controllers;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = $this->currentUser();
        $userId = (int)($user['id'] ?? 0);
        $groupId = (int)($user['group_id'] ?? 0);
        $active = $this->facilityService ? $this->facilityService->getActiveFacility($userId) : null;
        $facilityId = (int)($active['id'] ?? 0);

        // Resolve layout
        $layout = $this->resolveLayout($userId, $groupId);

        // Load card data
        $cardData = [];
        foreach ($layout as $card) {
            if (empty($card['visible'])) continue;
            $cardData[$card['card_key']] = $this->loadCard($card['card_key'], $userId, $facilityId);
        }

        // Announcements
        $announcements = $this->getActiveAnnouncements($userId, $groupId);

        // Facilities for header
        $userFacilities = $this->facilityService ? $this->facilityService->getUserFacilities($userId) : [];

        $this->renderView('dashboard/index', [
            'layout' => $layout,
            'cardData' => $cardData,
            'announcements' => $announcements,
            'currentUser' => $user,
            'activeFacility' => $active,
            'userFacilities' => $userFacilities,
        ]);
    }

    public function saveLayout(): void
    {
        $userId = $this->currentUserId();
        $cards = json_decode($_POST['layout'] ?? '[]', true);
        if (!is_array($cards)) { $this->jsonResponse(['success' => false]); return; }

        foreach ($cards as $i => $card) {
            if (empty($card['key'])) continue;
            $this->db()->prepare('
                INSERT INTO dashboard_user_layouts (user_id, card_key, position, cols, visible)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE position=VALUES(position), cols=VALUES(cols), visible=VALUES(visible)
            ')->execute([$userId, $card['key'], $i, (int)($card['cols'] ?? 4), $card['visible'] ? 1 : 0]);
        }
        $this->jsonResponse(['success' => true]);
    }

    public function resetLayout(): void
    {
        $userId = $this->currentUserId();
        $this->db()->prepare('DELETE FROM dashboard_user_layouts WHERE user_id = ?')->execute([$userId]);
        $this->jsonResponse(['success' => true]);
    }

    public function setTheme(): void
    {
        $theme = ($_POST['theme'] ?? '') === 'light' ? 'light' : 'dark';
        $userId = $this->currentUserId();
        if ($userId) {
            $this->db()->prepare('UPDATE users SET theme = ? WHERE id = ?')->execute([$theme, $userId]);
        }
        setcookie('theme', $theme, time() + 31536000, '/');
        $this->jsonResponse(['success' => true]);
    }

    public function globalSearch(): void
    {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { $this->jsonResponse([]); return; }

        $like = "%{$q}%";
        $results = [];

        $s = $this->db()->prepare("SELECT id, item_code as code, description FROM items WHERE (item_code LIKE ? OR description LIKE ?) AND deleted_at IS NULL LIMIT 3");
        $s->execute([$like, $like]);
        $items = $s->fetchAll();
        if (!empty($items)) $results[] = ['type' => 'items', 'label' => 'Items', 'items' => array_map(fn($r) => ['code' => $r['code'], 'description' => $r['description'], 'url' => '/items/' . $r['id']], $items)];

        $s = $this->db()->prepare("SELECT id, customer_code as code, company_name as description FROM customers WHERE (customer_code LIKE ? OR company_name LIKE ?) AND deleted_at IS NULL LIMIT 3");
        $s->execute([$like, $like]);
        $custs = $s->fetchAll();
        if (!empty($custs)) $results[] = ['type' => 'customers', 'label' => 'Customers', 'items' => array_map(fn($r) => ['code' => $r['code'], 'description' => $r['description'], 'url' => '/customers/' . $r['id']], $custs)];

        $s = $this->db()->prepare("SELECT id, supplier_code as code, company_name as description FROM suppliers WHERE (supplier_code LIKE ? OR company_name LIKE ?) AND deleted_at IS NULL LIMIT 3");
        $s->execute([$like, $like]);
        $supps = $s->fetchAll();
        if (!empty($supps)) $results[] = ['type' => 'suppliers', 'label' => 'Suppliers', 'items' => array_map(fn($r) => ['code' => $r['code'], 'description' => $r['description'], 'url' => '/suppliers/' . $r['id']], $supps)];

        $s = $this->db()->prepare("SELECT id, batch_number as code, status as description FROM batch_tickets WHERE batch_number LIKE ? LIMIT 3");
        $s->execute([$like]);
        $batches = $s->fetchAll();
        if (!empty($batches)) $results[] = ['type' => 'batches', 'label' => 'Batches', 'items' => array_map(fn($r) => ['code' => $r['code'], 'description' => $r['description'], 'url' => '/batches/' . $r['id']], $batches)];

        $s = $this->db()->prepare("SELECT id, so_number as code, status as description FROM sales_orders WHERE so_number LIKE ? AND deleted_at IS NULL LIMIT 3");
        $s->execute([$like]);
        $sos = $s->fetchAll();
        if (!empty($sos)) $results[] = ['type' => 'orders', 'label' => 'Sales Orders', 'items' => array_map(fn($r) => ['code' => $r['code'], 'description' => $r['description'], 'url' => '/orders/' . $r['id']], $sos)];

        $s = $this->db()->prepare("SELECT id, po_number as code, status as description FROM purchase_orders WHERE po_number LIKE ? AND deleted_at IS NULL LIMIT 3");
        $s->execute([$like]);
        $pos = $s->fetchAll();
        if (!empty($pos)) $results[] = ['type' => 'pos', 'label' => 'Purchase Orders', 'items' => array_map(fn($r) => ['code' => $r['code'], 'description' => $r['description'], 'url' => '/purchase-orders/' . $r['id']], $pos)];

        $this->jsonResponse($results);
    }

    // ── Layout Resolution ──────────────────────────────────────────

    private function resolveLayout(int $userId, int $groupId): array
    {
        // User custom layout
        $stmt = $this->db()->prepare('SELECT card_key, position, cols, visible FROM dashboard_user_layouts WHERE user_id = ? ORDER BY position ASC');
        $stmt->execute([$userId]);
        $userLayout = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!empty($userLayout)) return $userLayout;

        // Group default
        $stmt = $this->db()->prepare('SELECT card_key, position, cols, 1 as visible FROM dashboard_group_layouts WHERE group_id = ? AND active = 1 ORDER BY position ASC');
        $stmt->execute([$groupId]);
        $groupLayout = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!empty($groupLayout)) return $groupLayout;

        // System default — all active cards the user has permission for
        $stmt = $this->db()->prepare('
            SELECT d.card_key, d.id as position, d.default_cols as cols, 1 as visible
            FROM dashboard_card_definitions d
            WHERE d.active = 1
              AND (d.required_permission IS NULL
                   OR EXISTS (SELECT 1 FROM group_permissions gp WHERE gp.group_id = ? AND gp.module COLLATE utf8mb4_unicode_ci = d.required_permission COLLATE utf8mb4_unicode_ci AND gp.can_view = 1))
            ORDER BY d.id ASC
        ');
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Card Data Loaders ──────────────────────────────────────────

    private function loadCard(string $key, int $userId, int $facilityId): array
    {
        try {
            return match($key) {
                'orders_to_ship_today' => $this->cardShipToday($facilityId),
                'orders_to_ship_week' => $this->cardShipWeek($facilityId),
                'unfulfillable_orders' => $this->cardUnfulfillable($facilityId),
                'uncovered_demand' => $this->cardUncovered($facilityId),
                'open_batches' => $this->cardOpenBatches($facilityId),
                'rush_batches' => $this->cardRushBatches($facilityId),
                'low_stock' => $this->cardLowStock($facilityId),
                'pending_inspections' => $this->cardPendingInspections(),
                'pending_requisitions' => $this->cardPendingReqs(),
                'overdue_scars' => $this->cardOverdueScars(),
                'my_tasks' => $this->cardMyTasks($userId),
                'ar_summary' => $this->cardArSummary(),
                'sales_mtd' => $this->cardSalesMtd(),
                'backorder_count' => $this->cardBackorders($facilityId),
                'announcements' => $this->cardAnnouncements($userId, (int)($this->currentUser()['group_id'] ?? 0)),
                default => ['error' => 'Unknown card: ' . $key],
            };
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function cardShipToday(int $fid): array
    {
        $s = $this->db()->prepare('SELECT COUNT(*) FROM sales_orders WHERE status IN ("CONFIRMED","PARTIAL") AND promised_ship_date = CURDATE() AND facility_id = ? AND deleted_at IS NULL');
        $s->execute([$fid]);
        return ['count' => (int)$s->fetchColumn(), 'url' => '/orders'];
    }

    private function cardShipWeek(int $fid): array
    {
        $s = $this->db()->prepare('SELECT COUNT(*) FROM sales_orders WHERE status IN ("CONFIRMED","PARTIAL") AND promised_ship_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY) AND facility_id = ? AND deleted_at IS NULL');
        $s->execute([$fid]);
        return ['count' => (int)$s->fetchColumn(), 'url' => '/orders'];
    }

    private function cardUnfulfillable(int $fid): array
    {
        $s = $this->db()->prepare('
            SELECT i.item_code, i.description, SUM(sol.ordered_quantity - sol.shipped_quantity) as demand,
                   COALESCE((SELECT SUM(fl.remaining_quantity) FROM fifo_lots fl WHERE fl.item_id=sol.item_id AND fl.facility_id=? AND fl.status="AVAILABLE"),0) as on_hand,
                   so.so_number, c.company_name
            FROM sales_order_lines sol JOIN sales_orders so ON sol.so_id=so.id JOIN items i ON sol.item_id=i.id JOIN customers c ON so.customer_id=c.id
            WHERE so.status IN ("CONFIRMED","PARTIAL","ON_HOLD") AND (sol.ordered_quantity-sol.shipped_quantity)>0 AND so.facility_id=? AND so.deleted_at IS NULL
            GROUP BY sol.item_id, so.id HAVING demand > on_hand ORDER BY so.promised_ship_date ASC LIMIT 10
        ');
        $s->execute([$fid, $fid]);
        return ['rows' => $s->fetchAll(\PDO::FETCH_ASSOC)];
    }

    private function cardUncovered(int $fid): array
    {
        $s = $this->db()->prepare('
            SELECT i.item_code, i.description, SUM(sol.ordered_quantity-sol.shipped_quantity) as demand,
                   COALESCE((SELECT SUM(fl2.remaining_quantity) FROM fifo_lots fl2 WHERE fl2.item_id=sol.item_id AND fl2.facility_id=? AND fl2.status="AVAILABLE"),0) as on_hand,
                   COALESCE((SELECT SUM(bt.target_quantity) FROM batch_tickets bt WHERE bt.item_id=sol.item_id AND bt.facility_id=? AND bt.status IN ("OPEN","IN_PROGRESS")),0) as batch_supply,
                   COALESCE((SELECT SUM(pol.ordered_quantity-pol.received_quantity) FROM purchase_order_lines pol JOIN purchase_orders po ON pol.po_id=po.id WHERE pol.item_id=sol.item_id AND po.facility_id=? AND po.status="SENT" AND pol.line_status NOT IN ("CANCELLED","RECEIVED")),0) as po_supply
            FROM sales_order_lines sol JOIN sales_orders so ON sol.so_id=so.id JOIN items i ON sol.item_id=i.id
            WHERE so.status IN ("CONFIRMED","PARTIAL","ON_HOLD") AND (sol.ordered_quantity-sol.shipped_quantity)>0 AND so.facility_id=? AND so.deleted_at IS NULL
            GROUP BY sol.item_id HAVING demand > (on_hand+batch_supply+po_supply) ORDER BY demand DESC LIMIT 10
        ');
        $s->execute([$fid, $fid, $fid, $fid]);
        return ['rows' => $s->fetchAll(\PDO::FETCH_ASSOC)];
    }

    private function cardOpenBatches(int $fid): array
    {
        $s = $this->db()->prepare('SELECT COUNT(*) FROM batch_tickets WHERE status IN ("OPEN","IN_PROGRESS") AND facility_id=? AND deleted_at IS NULL');
        $s->execute([$fid]);
        $count = (int)$s->fetchColumn();
        $s2 = $this->db()->prepare('SELECT bt.id, bt.batch_number, i.item_code, bt.status, bt.scheduled_date FROM batch_tickets bt JOIN items i ON bt.item_id=i.id WHERE bt.status IN ("OPEN","IN_PROGRESS") AND bt.facility_id=? AND bt.deleted_at IS NULL ORDER BY bt.priority DESC, bt.scheduled_date ASC LIMIT 5');
        $s2->execute([$fid]);
        return ['count' => $count, 'rows' => $s2->fetchAll(\PDO::FETCH_ASSOC), 'url' => '/batches'];
    }

    private function cardRushBatches(int $fid): array
    {
        $s = $this->db()->prepare('SELECT bt.id, bt.batch_number, i.item_code, bt.scheduled_date FROM batch_tickets bt JOIN items i ON bt.item_id=i.id WHERE bt.priority="RUSH" AND bt.status IN ("OPEN","IN_PROGRESS") AND bt.facility_id=? AND bt.deleted_at IS NULL ORDER BY bt.scheduled_date ASC');
        $s->execute([$fid]);
        $rows = $s->fetchAll(\PDO::FETCH_ASSOC);
        return ['count' => count($rows), 'rows' => $rows, 'url' => '/batches'];
    }

    private function cardLowStock(int $fid): array
    {
        $s = $this->db()->prepare('SELECT i.item_code, i.description, i.reorder_min, COALESCE(SUM(fl.remaining_quantity),0) as on_hand FROM items i LEFT JOIN fifo_lots fl ON fl.item_id=i.id AND fl.facility_id=? AND fl.status="AVAILABLE" WHERE i.reorder_min > 0 AND i.active=1 AND i.deleted_at IS NULL GROUP BY i.id HAVING on_hand < i.reorder_min ORDER BY (i.reorder_min-on_hand) DESC LIMIT 8');
        $s->execute([$fid]);
        $rows = $s->fetchAll(\PDO::FETCH_ASSOC);
        return ['count' => count($rows), 'rows' => $rows, 'url' => '/mrp'];
    }

    private function cardPendingInspections(): array
    {
        return ['count' => (int)$this->db()->query('SELECT COUNT(*) FROM qc_incoming_inspections WHERE status="PENDING"')->fetchColumn(), 'url' => '/qc/inspection'];
    }

    private function cardPendingReqs(): array
    {
        return ['count' => (int)$this->db()->query('SELECT COUNT(*) FROM purchase_requisitions WHERE status="SUBMITTED"')->fetchColumn(), 'url' => '/requisitions'];
    }

    private function cardOverdueScars(): array
    {
        return ['count' => (int)$this->db()->query('SELECT COUNT(*) FROM scars WHERE status IN ("OPEN","RESPONSE_RECEIVED") AND due_date < CURDATE()')->fetchColumn(), 'url' => '/scars'];
    }

    private function cardMyTasks(int $userId): array
    {
        $s = $this->db()->prepare('SELECT t.title, t.due_date, c.company_name, t.priority FROM crm_tasks t JOIN customers c ON t.customer_id=c.id WHERE t.assigned_to=? AND t.status="OPEN" ORDER BY t.due_date ASC LIMIT 6');
        $s->execute([$userId]);
        return ['rows' => $s->fetchAll(\PDO::FETCH_ASSOC), 'url' => '/crm/tasks'];
    }

    private function cardArSummary(): array
    {
        $s = $this->db()->query('SELECT SUM(CASE WHEN DATEDIFF(CURDATE(),due_date)<=0 THEN total_due ELSE 0 END) as current_due, SUM(CASE WHEN DATEDIFF(CURDATE(),due_date) BETWEEN 1 AND 30 THEN total_due ELSE 0 END) as d1_30, SUM(CASE WHEN DATEDIFF(CURDATE(),due_date) BETWEEN 31 AND 60 THEN total_due ELSE 0 END) as d31_60, SUM(CASE WHEN DATEDIFF(CURDATE(),due_date) BETWEEN 61 AND 90 THEN total_due ELSE 0 END) as d61_90, SUM(CASE WHEN DATEDIFF(CURDATE(),due_date)>90 THEN total_due ELSE 0 END) as d90plus, SUM(total_due) as total FROM invoices WHERE status="OPEN"');
        return $s->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function cardSalesMtd(): array
    {
        $s = $this->db()->query('SELECT SUM(CASE WHEN MONTH(invoice_date)=MONTH(CURDATE()) AND YEAR(invoice_date)=YEAR(CURDATE()) THEN total_due ELSE 0 END) as this_month, SUM(CASE WHEN MONTH(invoice_date)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND YEAR(invoice_date)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) THEN total_due ELSE 0 END) as last_month FROM invoices WHERE status != "VOID"');
        return $s->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function cardBackorders(int $fid): array
    {
        $s = $this->db()->prepare('SELECT COUNT(*) FROM sales_order_lines sol JOIN sales_orders so ON sol.so_id=so.id WHERE (sol.ordered_quantity-sol.shipped_quantity)>0 AND so.status IN ("PARTIAL","CONFIRMED") AND so.facility_id=? AND sol.shipped_quantity>0 AND so.deleted_at IS NULL');
        $s->execute([$fid]);
        return ['count' => (int)$s->fetchColumn(), 'url' => '/orders'];
    }

    private function cardAnnouncements(int $userId, int $groupId): array
    {
        return ['rows' => $this->getActiveAnnouncements($userId, $groupId)];
    }

    private function getActiveAnnouncements(int $userId, int $groupId): array
    {
        $stmt = $this->db()->prepare("
            SELECT a.* FROM announcements a
            WHERE a.active = 1 AND a.start_date <= CURDATE() AND (a.end_date IS NULL OR a.end_date >= CURDATE())
              AND a.id NOT IN (SELECT announcement_id FROM announcement_dismissals WHERE user_id = ?)
              AND (a.target_all = 1 OR EXISTS (SELECT 1 FROM announcement_target_groups atg WHERE atg.announcement_id = a.id AND atg.group_id = ?))
            ORDER BY FIELD(a.priority, 'URGENT', 'WARNING', 'INFO'), a.start_date DESC
        ");
        $stmt->execute([$userId, $groupId]);
        return $stmt->fetchAll();
    }
}
