<?php

namespace PrecisionInk\Controllers;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = $this->currentUser();
        $userId = (int)($user['id'] ?? 0);
        $groupId = $user['group_id'] ?? null;
        $active = $this->facilityService ? $this->facilityService->getActiveFacility($userId) : null;
        $facilityId = (int)($active['id'] ?? 0);

        $data = ['title' => 'Dashboard', 'user' => $user];

        // Announcements — always
        $data['announcements'] = $this->getActiveAnnouncements($userId, $groupId ? (int)$groupId : null);

        // My overdue tasks
        if ($this->checkPermission('crm', 'view')) {
            $s = $this->db()->prepare("SELECT t.title, t.due_date, c.company_name FROM crm_tasks t JOIN customers c ON t.customer_id = c.id WHERE t.assigned_to = ? AND t.status = 'OPEN' AND t.due_date < CURDATE() ORDER BY t.due_date LIMIT 5");
            $s->execute([$userId]);
            $data['overdue_tasks'] = $s->fetchAll();
        }

        // Pending requisitions
        if ($this->hasSpecialPermission('approve_purchase_requisitions')) {
            $data['pending_reqs'] = (int)$this->db()->query("SELECT COUNT(*) FROM purchase_requisitions WHERE status = 'SUBMITTED'")->fetchColumn();
        }

        // Batch stats
        if ($this->checkPermission('batch_tickets', 'view')) {
            $s = $this->db()->prepare("SELECT status, COUNT(*) as cnt FROM batch_tickets WHERE facility_id = ? AND status IN ('OPEN','IN_PROGRESS') AND deleted_at IS NULL GROUP BY status");
            $s->execute([$facilityId]);
            $batchStats = ['OPEN' => 0, 'IN_PROGRESS' => 0, 'rush' => 0];
            foreach ($s->fetchAll() as $r) $batchStats[$r['status']] = (int)$r['cnt'];
            $batchStats['rush'] = (int)$this->db()->prepare("SELECT COUNT(*) FROM batch_tickets WHERE facility_id = ? AND priority = 'RUSH' AND status IN ('OPEN','IN_PROGRESS')")->execute([$facilityId]) ? $this->db()->prepare("SELECT COUNT(*) FROM batch_tickets WHERE facility_id = ? AND priority = 'RUSH' AND status IN ('OPEN','IN_PROGRESS')")->fetchColumn() : 0;
            $rushStmt = $this->db()->prepare("SELECT COUNT(*) FROM batch_tickets WHERE facility_id = ? AND priority = 'RUSH' AND status IN ('OPEN','IN_PROGRESS') AND deleted_at IS NULL");
            $rushStmt->execute([$facilityId]);
            $batchStats['rush'] = (int)$rushStmt->fetchColumn();
            $data['batch_stats'] = $batchStats;
        }

        // Orders due
        if ($this->checkPermission('sales_orders', 'view')) {
            $today = date('Y-m-d');
            $weekEnd = date('Y-m-d', strtotime('+7 days'));
            $s = $this->db()->prepare("SELECT COUNT(*) FROM sales_orders WHERE promised_ship_date = ? AND status IN ('CONFIRMED','PARTIAL') AND deleted_at IS NULL AND facility_id = ?");
            $s->execute([$today, $facilityId]);
            $data['orders_due_today'] = (int)$s->fetchColumn();

            $s = $this->db()->prepare("SELECT COUNT(*) FROM sales_orders WHERE promised_ship_date BETWEEN ? AND ? AND status IN ('CONFIRMED','PARTIAL') AND deleted_at IS NULL AND facility_id = ?");
            $s->execute([$today, $weekEnd, $facilityId]);
            $data['orders_due_week'] = (int)$s->fetchColumn();

            $data['backorder_count'] = (int)$this->db()->query("SELECT COUNT(*) FROM sales_order_lines sol JOIN sales_orders so ON sol.so_id = so.id WHERE sol.backordered_quantity > 0 AND so.deleted_at IS NULL")->fetchColumn();
        }

        // Inventory alerts
        if ($this->checkPermission('inventory', 'view')) {
            $s = $this->db()->prepare("SELECT i.item_code, i.description, i.reorder_min, COALESCE(SUM(fl.remaining_quantity),0) as on_hand FROM items i LEFT JOIN fifo_lots fl ON fl.item_id=i.id AND fl.facility_id=? AND fl.status='AVAILABLE' WHERE i.reorder_min IS NOT NULL AND i.reorder_min > 0 AND i.active=1 GROUP BY i.id HAVING on_hand < i.reorder_min ORDER BY (i.reorder_min - on_hand) DESC LIMIT 5");
            $s->execute([$facilityId]);
            $data['low_stock'] = $s->fetchAll();
        }

        // Pending inspections
        if ($this->checkPermission('qc', 'view')) {
            $data['pending_inspections'] = (int)$this->db()->query("SELECT COUNT(*) FROM qc_incoming_inspections WHERE status = 'PENDING'")->fetchColumn();
        }

        // Sales stats
        if ($this->checkPermission('invoices', 'view')) {
            $s = $this->db()->query("SELECT SUM(CASE WHEN MONTH(invoice_date)=MONTH(CURDATE()) AND YEAR(invoice_date)=YEAR(CURDATE()) THEN total_due ELSE 0 END) as this_month, SUM(CASE WHEN MONTH(invoice_date)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND YEAR(invoice_date)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) THEN total_due ELSE 0 END) as last_month FROM invoices WHERE status != 'VOID'");
            $data['sales_stats'] = $s->fetch();

            $data['ar_total'] = (float)$this->db()->query("SELECT COALESCE(SUM(total_due),0) FROM invoices WHERE status = 'OPEN'")->fetchColumn();
        }

        // Overdue SCARs
        if ($this->checkPermission('qc', 'view')) {
            $data['overdue_scars'] = (int)$this->db()->query("SELECT COUNT(*) FROM scars WHERE status IN ('OPEN','RESPONSE_RECEIVED') AND due_date < CURDATE()")->fetchColumn();
        }

        // Credit holds
        if ($this->checkPermission('customers', 'view')) {
            $data['credit_holds'] = (int)$this->db()->query("SELECT COUNT(*) FROM customers WHERE account_hold = 1 AND active = 1 AND deleted_at IS NULL")->fetchColumn();
        }

        $this->renderView('dashboard', $data);
    }

    public function globalSearch(): void
    {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { $this->jsonResponse([]); return; }

        $like = "%{$q}%";
        $results = [];

        // Items
        $s = $this->db()->prepare("SELECT id, item_code as code, description FROM items WHERE (item_code LIKE ? OR description LIKE ?) AND deleted_at IS NULL LIMIT 3");
        $s->execute([$like, $like]);
        $items = $s->fetchAll();
        if (!empty($items)) $results[] = ['type' => 'items', 'label' => 'Items', 'items' => array_map(fn($r) => ['code' => $r['code'], 'description' => $r['description'], 'url' => '/items/' . $r['id']], $items)];

        // Customers
        $s = $this->db()->prepare("SELECT id, customer_code as code, company_name as description FROM customers WHERE (customer_code LIKE ? OR company_name LIKE ?) AND deleted_at IS NULL LIMIT 3");
        $s->execute([$like, $like]);
        $custs = $s->fetchAll();
        if (!empty($custs)) $results[] = ['type' => 'customers', 'label' => 'Customers', 'items' => array_map(fn($r) => ['code' => $r['code'], 'description' => $r['description'], 'url' => '/customers/' . $r['id']], $custs)];

        // Suppliers
        $s = $this->db()->prepare("SELECT id, supplier_code as code, company_name as description FROM suppliers WHERE (supplier_code LIKE ? OR company_name LIKE ?) AND deleted_at IS NULL LIMIT 3");
        $s->execute([$like, $like]);
        $supps = $s->fetchAll();
        if (!empty($supps)) $results[] = ['type' => 'suppliers', 'label' => 'Suppliers', 'items' => array_map(fn($r) => ['code' => $r['code'], 'description' => $r['description'], 'url' => '/suppliers/' . $r['id']], $supps)];

        // Batches
        $s = $this->db()->prepare("SELECT id, batch_number as code, status as description FROM batch_tickets WHERE batch_number LIKE ? LIMIT 3");
        $s->execute([$like]);
        $batches = $s->fetchAll();
        if (!empty($batches)) $results[] = ['type' => 'batches', 'label' => 'Batches', 'items' => array_map(fn($r) => ['code' => $r['code'], 'description' => $r['description'], 'url' => '/batches/' . $r['id']], $batches)];

        // Sales Orders
        $s = $this->db()->prepare("SELECT id, so_number as code, status as description FROM sales_orders WHERE so_number LIKE ? AND deleted_at IS NULL LIMIT 3");
        $s->execute([$like]);
        $sos = $s->fetchAll();
        if (!empty($sos)) $results[] = ['type' => 'orders', 'label' => 'Sales Orders', 'items' => array_map(fn($r) => ['code' => $r['code'], 'description' => $r['description'], 'url' => '/orders/' . $r['id']], $sos)];

        // Purchase Orders
        $s = $this->db()->prepare("SELECT id, po_number as code, status as description FROM purchase_orders WHERE po_number LIKE ? AND deleted_at IS NULL LIMIT 3");
        $s->execute([$like]);
        $pos = $s->fetchAll();
        if (!empty($pos)) $results[] = ['type' => 'pos', 'label' => 'Purchase Orders', 'items' => array_map(fn($r) => ['code' => $r['code'], 'description' => $r['description'], 'url' => '/purchase-orders/' . $r['id']], $pos)];

        $this->jsonResponse($results);
    }

    private function getActiveAnnouncements(int $userId, ?int $groupId): array
    {
        $stmt = $this->db()->prepare("
            SELECT a.* FROM announcements a
            WHERE a.active = 1 AND a.start_date <= CURDATE() AND (a.end_date IS NULL OR a.end_date >= CURDATE())
              AND a.id NOT IN (SELECT announcement_id FROM announcement_dismissals WHERE user_id = ?)
              AND (a.target_all = 1 OR EXISTS (SELECT 1 FROM announcement_target_groups atg WHERE atg.announcement_id = a.id AND atg.group_id = ?))
            ORDER BY FIELD(a.priority, 'URGENT', 'WARNING', 'INFO'), a.start_date DESC
        ");
        $stmt->execute([$userId, $groupId ?? 0]);
        return $stmt->fetchAll();
    }
}
