<?php

namespace PrecisionInk\Controllers;

class CrmController extends BaseController
{
    // ── Dashboard ────────────────────────────────────────────────────

    public function landing(): void
    {
        $this->redirect('/crm/dashboard');
    }

    public function dashboard(): void
    {
        if (!$this->checkPermission('crm', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $userId = $this->currentUserId();

        // My customers
        $customers = $this->db()->prepare("
            SELECT c.id, c.customer_code, c.company_name, c.account_hold,
                   MAX(ca.created_at) as last_activity_date,
                   (SELECT MAX(so.order_date) FROM sales_orders so WHERE so.customer_id = c.id AND so.deleted_at IS NULL) as last_order_date,
                   crm.next_contact_date,
                   (SELECT COUNT(*) FROM crm_tasks t WHERE t.customer_id = c.id AND t.assigned_to = ? AND t.status = 'OPEN') as open_tasks
            FROM customers c
            LEFT JOIN customer_activities ca ON ca.customer_id = c.id
            LEFT JOIN customer_crm_profiles crm ON crm.customer_id = c.id
            WHERE c.sales_rep_id = ? AND c.deleted_at IS NULL AND c.active = 1
            GROUP BY c.id
            ORDER BY CASE WHEN crm.next_contact_date IS NULL THEN 1 ELSE 0 END, crm.next_contact_date ASC, c.company_name ASC
        ");
        $customers->execute([$userId, $userId]);
        $myCustomers = $customers->fetchAll();

        // My open tasks
        $tasks = $this->db()->prepare("
            SELECT t.*, c.company_name, c.id as cust_id
            FROM crm_tasks t
            JOIN customers c ON t.customer_id = c.id
            WHERE t.assigned_to = ? AND t.status = 'OPEN'
            ORDER BY t.due_date ASC
        ");
        $tasks->execute([$userId]);
        $myTasks = $tasks->fetchAll();

        // My open quotes (graceful if no data)
        $myQuotes = [];
        try {
            $qStmt = $this->db()->prepare("
                SELECT q.id, q.quote_number, c.company_name, q.expiration_date,
                       COALESCE(SUM(ql.quantity * ql.unit_price), 0) as total_value
                FROM quotes q
                JOIN customers c ON q.customer_id = c.id
                LEFT JOIN quote_lines ql ON ql.quote_id = q.id
                WHERE c.sales_rep_id = ? AND q.status IN ('DRAFT','SENT') AND q.deleted_at IS NULL
                GROUP BY q.id
                ORDER BY q.expiration_date ASC
            ");
            $qStmt->execute([$userId]);
            $myQuotes = $qStmt->fetchAll();
        } catch (\Throwable $e) {}

        // My open orders
        $myOrders = [];
        try {
            $oStmt = $this->db()->prepare("
                SELECT so.id, so.so_number, c.company_name, so.promised_ship_date, so.status,
                       COALESCE(SUM(sol.ordered_quantity * sol.unit_price), 0) as value
                FROM sales_orders so
                JOIN customers c ON so.customer_id = c.id
                LEFT JOIN sales_order_lines sol ON sol.so_id = so.id
                WHERE c.sales_rep_id = ? AND so.status IN ('CONFIRMED','PARTIAL') AND so.deleted_at IS NULL
                GROUP BY so.id
                ORDER BY so.promised_ship_date ASC
            ");
            $oStmt->execute([$userId]);
            $myOrders = $oStmt->fetchAll();
        } catch (\Throwable $e) {}

        // Recent activity
        $recentActivity = $this->db()->prepare("
            SELECT ca.*, c.company_name, c.id as cust_id, u.full_name as created_by_name
            FROM customer_activities ca
            JOIN customers c ON ca.customer_id = c.id
            LEFT JOIN users u ON ca.created_by = u.id
            WHERE c.sales_rep_id = ?
            ORDER BY ca.created_at DESC LIMIT 15
        ");
        $recentActivity->execute([$userId]);

        $this->renderView('crm/dashboard', [
            'myCustomers' => $myCustomers,
            'myTasks' => $myTasks,
            'myQuotes' => $myQuotes,
            'myOrders' => $myOrders,
            'recentActivity' => $recentActivity->fetchAll(),
        ]);
    }

    // ── Global Task List ────────────────────────────────────────────

    public function taskList(): void
    {
        if (!$this->checkPermission('crm', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $userId = $this->currentUserId();
        $filterAssigned = (int)($_GET['assigned_to'] ?? $userId);
        $filterCustomer = trim($_GET['customer'] ?? '');
        $filterPriority = $_GET['priority'] ?? '';
        $filterStatus = $_GET['status'] ?? 'OPEN';
        $filterOverdue = isset($_GET['overdue']);
        $filterDateFrom = $_GET['date_from'] ?? '';
        $filterDateTo = $_GET['date_to'] ?? '';

        $where = [];
        $params = [];

        if ($filterAssigned) { $where[] = 't.assigned_to = ?'; $params[] = $filterAssigned; }
        if ($filterCustomer) { $where[] = '(c.customer_code LIKE ? OR c.company_name LIKE ?)'; $params[] = "%{$filterCustomer}%"; $params[] = "%{$filterCustomer}%"; }
        if ($filterPriority) { $where[] = 't.priority = ?'; $params[] = $filterPriority; }
        if ($filterStatus && $filterStatus !== 'ALL') { $where[] = 't.status = ?'; $params[] = $filterStatus; }
        if ($filterOverdue) { $where[] = "t.due_date < CURDATE() AND t.status = 'OPEN'"; }
        if ($filterDateFrom) { $where[] = 't.due_date >= ?'; $params[] = $filterDateFrom; }
        if ($filterDateTo) { $where[] = 't.due_date <= ?'; $params[] = $filterDateTo; }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM crm_tasks t JOIN customers c ON t.customer_id = c.id {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT t.*, c.company_name, c.customer_code, u.full_name as assigned_name
            FROM crm_tasks t
            JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.assigned_to = u.id
            {$whereClause}
            ORDER BY FIELD(t.status, 'OPEN', 'COMPLETED', 'CANCELLED'), t.due_date ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $users = $this->db()->query("SELECT id, full_name FROM users WHERE active = 1 ORDER BY full_name")->fetchAll();

        $this->renderView('crm/tasks', [
            'tasks' => $stmt->fetchAll(),
            'users' => $users,
            'filters' => [
                'assigned_to' => $filterAssigned, 'customer' => $filterCustomer,
                'priority' => $filterPriority, 'status' => $filterStatus,
                'overdue' => $filterOverdue, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo,
            ],
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    // ── Task Actions ────────────────────────────────────────────────

    public function completeTask(string $id): void
    {
        $this->db()->prepare("UPDATE crm_tasks SET status='COMPLETED', completed_at=NOW(), updated_at=NOW() WHERE id=? AND status='OPEN'")
            ->execute([(int)$id]);
        $this->auditLog('UPDATE', 'crm_tasks', (int)$id, ['status' => 'OPEN'], ['status' => 'COMPLETED']);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            $this->jsonResponse(['success' => true]);
            return;
        }
        $this->toast('Task completed.', 'success');
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/crm/tasks');
    }

    public function cancelTask(string $id): void
    {
        $this->db()->prepare("UPDATE crm_tasks SET status='CANCELLED', updated_at=NOW() WHERE id=? AND status='OPEN'")
            ->execute([(int)$id]);
        $this->auditLog('UPDATE', 'crm_tasks', (int)$id, ['status' => 'OPEN'], ['status' => 'CANCELLED']);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            $this->jsonResponse(['success' => true]);
            return;
        }
        $this->toast('Task cancelled.', 'success');
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/crm/tasks');
    }

    public function bulkTasks(): void
    {
        if (!$this->checkPermission('crm', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $action = $_POST['action'] ?? '';
        $taskIds = $_POST['task_ids'] ?? [];

        if (empty($taskIds)) { $this->toast('No tasks selected.', 'error'); $this->redirect('/crm/tasks'); return; }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $ids = array_map('intval', $taskIds);

        if ($action === 'complete') {
            $this->db()->prepare("UPDATE crm_tasks SET status='COMPLETED', completed_at=NOW(), updated_at=NOW() WHERE id IN ({$placeholders}) AND status='OPEN'")
                ->execute($ids);
            $this->toast(count($ids) . ' task(s) completed.', 'success');
        } elseif ($action === 'reassign') {
            $assignTo = (int)($_POST['assigned_to'] ?? 0);
            if ($assignTo) {
                $this->db()->prepare("UPDATE crm_tasks SET assigned_to=?, updated_at=NOW() WHERE id IN ({$placeholders})")
                    ->execute(array_merge([$assignTo], $ids));
                $this->toast(count($ids) . ' task(s) reassigned.', 'success');
            }
        } elseif ($action === 'due_date') {
            $dueDate = $_POST['due_date'] ?? '';
            if ($dueDate) {
                $this->db()->prepare("UPDATE crm_tasks SET due_date=?, updated_at=NOW() WHERE id IN ({$placeholders})")
                    ->execute(array_merge([$dueDate], $ids));
                $this->toast(count($ids) . ' task(s) rescheduled.', 'success');
            }
        }

        $this->redirect('/crm/tasks');
    }

    // ── Quick Add Activity ──────────────────────────────────────────

    public function activityCreateForm(): void
    {
        if (!$this->checkPermission('crm', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->jsonResponse(['html' => 'Use slide-over panel']); // Handled client-side
    }

    public function activityCreate(): void
    {
        if (!$this->checkPermission('crm', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $customerId = (int)($_POST['customer_id'] ?? 0);
        $type = $_POST['activity_type'] ?? 'GENERAL';
        $subject = trim($_POST['subject'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!$customerId || !$subject) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->jsonResponse(['success' => false, 'message' => 'Customer and subject required.'], 400);
            } else {
                $this->toast('Customer and subject required.', 'error');
                $this->redirect('/crm/dashboard');
            }
            return;
        }

        $this->db()->prepare("INSERT INTO customer_activities (customer_id, activity_type, subject, notes, created_by) VALUES (?,?,?,?,?)")
            ->execute([$customerId, $type, $subject, $notes ?: null, $this->currentUserId()]);
        $actId = (int)$this->db()->lastInsertId();
        $this->auditLog('CREATE', 'customer_activities', $actId, [], ['subject' => $subject]);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->jsonResponse(['success' => true, 'id' => $actId]);
        } else {
            $this->toast('Activity logged.', 'success');
            $this->redirect('/crm/dashboard');
        }
    }

    public function taskCreate(): void
    {
        if (!$this->checkPermission('crm', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $customerId = (int)($_POST['customer_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $dueDate = $_POST['due_date'] ?? '';
        $priority = $_POST['priority'] ?? 'NORMAL';
        $assignedTo = (int)($_POST['assigned_to'] ?? $this->currentUserId());

        if (!$customerId || !$title || !$dueDate) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->jsonResponse(['success' => false, 'message' => 'Customer, title, and due date required.'], 400);
            } else {
                $this->toast('Customer, title, and due date required.', 'error');
                $this->redirect('/crm/dashboard');
            }
            return;
        }

        $this->db()->prepare("INSERT INTO crm_tasks (customer_id, title, due_date, priority, assigned_to, created_by) VALUES (?,?,?,?,?,?)")
            ->execute([$customerId, $title, $dueDate, $priority, $assignedTo, $this->currentUserId()]);
        $taskId = (int)$this->db()->lastInsertId();
        $this->auditLog('CREATE', 'crm_tasks', $taskId, [], ['title' => $title]);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->jsonResponse(['success' => true, 'id' => $taskId]);
        } else {
            $this->toast('Task created.', 'success');
            $this->redirect('/crm/dashboard');
        }
    }
}
