<?php

namespace PrecisionInk\Controllers;

class ReportController extends BaseController
{
    public function crmRepActivity(): void
    {
        if (!$this->checkPermission('crm', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        $repId = (int)($_GET['rep_id'] ?? 0);

        $where = ['ca.created_at >= ?', 'ca.created_at <= ?'];
        $params = [$dateFrom, $dateTo . ' 23:59:59'];
        if ($repId) { $where[] = 'ca.created_by = ?'; $params[] = $repId; }
        $wc = implode(' AND ', $where);

        $rows = $this->db()->prepare("
            SELECT u.full_name as rep_name, ca.activity_type, COUNT(*) as cnt
            FROM customer_activities ca
            JOIN users u ON ca.created_by = u.id
            WHERE {$wc}
            GROUP BY u.id, ca.activity_type
            ORDER BY u.full_name, ca.activity_type
        ");
        $rows->execute($params);

        $users = $this->db()->query("SELECT id, full_name FROM users WHERE active = 1 ORDER BY full_name")->fetchAll();

        $this->renderView('reports/crm_rep_activity', [
            'rows' => $rows->fetchAll(), 'users' => $users,
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'rep_id' => $repId],
        ]);
    }

    public function crmContactFrequency(): void
    {
        if (!$this->checkPermission('crm', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $threshold = max(1, (int)($_GET['days'] ?? 30));

        $rows = $this->db()->prepare("
            SELECT c.id, c.customer_code, c.company_name, u.full_name as rep_name,
                   MAX(ca.created_at) as last_activity,
                   DATEDIFF(CURDATE(), MAX(ca.created_at)) as days_since,
                   crm.next_contact_date
            FROM customers c
            LEFT JOIN customer_activities ca ON ca.customer_id = c.id
            LEFT JOIN users u ON c.sales_rep_id = u.id
            LEFT JOIN customer_crm_profiles crm ON crm.customer_id = c.id
            WHERE c.active = 1 AND c.deleted_at IS NULL AND c.sales_rep_id IS NOT NULL
            GROUP BY c.id
            HAVING last_activity IS NULL OR days_since > ?
            ORDER BY days_since DESC
        ");
        $rows->execute([$threshold]);

        $this->renderView('reports/crm_contact_frequency', [
            'rows' => $rows->fetchAll(), 'threshold' => $threshold,
        ]);
    }

    public function crmTaskReport(): void
    {
        if (!$this->checkPermission('crm', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        $repId = (int)($_GET['rep_id'] ?? 0);

        $repFilter = $repId ? 'AND t.assigned_to = ?' : '';
        $params = $repId ? [$repId] : [];

        $rows = $this->db()->prepare("
            SELECT u.full_name as rep_name,
                   SUM(CASE WHEN t.status = 'OPEN' THEN 1 ELSE 0 END) as open_tasks,
                   SUM(CASE WHEN t.status = 'COMPLETED' AND t.completed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as completed,
                   SUM(CASE WHEN t.status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled,
                   SUM(CASE WHEN t.status = 'OPEN' AND t.due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
            FROM crm_tasks t
            JOIN users u ON t.assigned_to = u.id
            WHERE 1=1 {$repFilter}
            GROUP BY u.id
            ORDER BY u.full_name
        ");
        $rows->execute(array_merge([$dateFrom, $dateTo . ' 23:59:59'], $params));

        $users = $this->db()->query("SELECT id, full_name FROM users WHERE active = 1 ORDER BY full_name")->fetchAll();

        $this->renderView('reports/crm_tasks', [
            'rows' => $rows->fetchAll(), 'users' => $users,
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'rep_id' => $repId],
        ]);
    }

    public function crmRepCustomers(): void
    {
        if (!$this->checkPermission('crm', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $repId = (int)($_GET['rep_id'] ?? $this->currentUserId());
        $users = $this->db()->query("SELECT id, full_name FROM users WHERE active = 1 ORDER BY full_name")->fetchAll();

        $rows = $this->db()->prepare("
            SELECT c.id, c.customer_code, c.company_name, c.credit_limit, c.ar_balance, c.account_hold,
                   (SELECT MAX(so.order_date) FROM sales_orders so WHERE so.customer_id = c.id AND so.deleted_at IS NULL) as last_order,
                   (SELECT MAX(ca.created_at) FROM customer_activities ca WHERE ca.customer_id = c.id) as last_contact,
                   (SELECT COUNT(*) FROM quotes q WHERE q.customer_id = c.id AND q.status IN ('DRAFT','SENT') AND q.deleted_at IS NULL) as open_quotes,
                   (SELECT COUNT(*) FROM sales_orders so WHERE so.customer_id = c.id AND so.status IN ('CONFIRMED','PARTIAL') AND so.deleted_at IS NULL) as open_orders
            FROM customers c
            WHERE c.sales_rep_id = ? AND c.active = 1 AND c.deleted_at IS NULL
            ORDER BY c.company_name
        ");
        $rows->execute([$repId]);

        $this->renderView('reports/crm_rep_customers', [
            'rows' => $rows->fetchAll(), 'users' => $users, 'repId' => $repId,
        ]);
    }

    public function crmNextContact(): void
    {
        if (!$this->checkPermission('crm', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $daysAhead = max(1, (int)($_GET['days'] ?? 7));

        $rows = $this->db()->prepare("
            SELECT c.id, c.customer_code, c.company_name,
                   u.full_name as rep_name, crm.next_contact_date, crm.profile_notes,
                   (SELECT MAX(ca.created_at) FROM customer_activities ca WHERE ca.customer_id = c.id) as last_activity
            FROM customers c
            JOIN customer_crm_profiles crm ON crm.customer_id = c.id
            LEFT JOIN users u ON c.sales_rep_id = u.id
            WHERE crm.next_contact_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
              AND c.active = 1 AND c.deleted_at IS NULL
            ORDER BY crm.next_contact_date ASC
        ");
        $rows->execute([$daysAhead]);

        $this->renderView('reports/crm_next_contact', [
            'rows' => $rows->fetchAll(), 'daysAhead' => $daysAhead,
        ]);
    }
}
