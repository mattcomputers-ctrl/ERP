<?php

namespace App\Services;

class NotificationService
{
    private \PDO $db;
    private EmailService $email;

    public function __construct(\PDO $db, EmailService $email)
    {
        $this->db = $db;
        $this->email = $email;
    }

    public function sendAlert(string $alertType, array $mergeData, string $referenceType = '', int $referenceId = 0): void
    {
        $config = $this->getConfig($alertType);
        if (!$config || !$config['enabled']) return;

        $recipients = $this->parseRecipients($config['recipients'] ?? '');
        if (empty($recipients)) return;

        $subject = $mergeData['subject'] ?? ucwords(str_replace('_', ' ', $alertType));
        $body = $mergeData['body'] ?? 'Alert: ' . $subject;

        $success = $this->email->send(
            'notification', $recipients,
            array_merge($mergeData, ['subject' => $subject, 'body' => $body]),
            null, null, $referenceType, $referenceId
        );

        $this->logNotification($alertType, $recipients, $referenceType, $referenceId, $success);
    }

    public function runScheduledChecks(): void
    {
        $this->checkLowStock();
        $this->checkBatchOverdue();
        $this->checkOrdersApproachingShipDate();
        $this->checkOrdersOpenTooLong();
        $this->checkQuotesExpiring();
        $this->checkTasksOverdue();
    }

    private function checkLowStock(): void
    {
        $config = $this->getConfig('low_stock');
        if (!$config || !$config['enabled']) return;

        $stmt = $this->db->query('
            SELECT i.item_code, i.description, i.reorder_min, f.name as facility_name,
                   COALESCE(SUM(fl.remaining_quantity), 0) as on_hand
            FROM items i
            CROSS JOIN facilities f
            LEFT JOIN fifo_lots fl ON fl.item_id = i.id AND fl.facility_id = f.id AND fl.status = "AVAILABLE"
            WHERE i.reorder_min IS NOT NULL AND i.reorder_min > 0 AND i.active = 1 AND f.active = 1
            GROUP BY i.id, f.id
            HAVING on_hand < i.reorder_min
        ');
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($items)) return;

        $list = implode("\n", array_map(fn($r) => "- {$r['item_code']} ({$r['description']}) at {$r['facility_name']}: {$r['on_hand']} on hand, min {$r['reorder_min']}", $items));
        $this->sendAlert('low_stock', ['subject' => count($items) . ' items below reorder minimum', 'body' => "Items below reorder minimum:\n\n" . $list]);
    }

    private function checkBatchOverdue(): void
    {
        $config = $this->getConfig('batch_overdue');
        if (!$config || !$config['enabled']) return;

        $stmt = $this->db->query('
            SELECT bt.batch_number, i.description, bt.scheduled_date, f.name as facility
            FROM batch_tickets bt JOIN items i ON bt.item_id = i.id JOIN facilities f ON bt.facility_id = f.id
            WHERE bt.status IN ("OPEN","IN_PROGRESS") AND bt.scheduled_date < CURDATE()
        ');
        $batches = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($batches)) return;

        $list = implode("\n", array_map(fn($b) => "- {$b['batch_number']} ({$b['description']}) scheduled {$b['scheduled_date']} at {$b['facility']}", $batches));
        $this->sendAlert('batch_overdue', ['subject' => count($batches) . ' overdue batch tickets', 'body' => "Overdue batches:\n\n" . $list]);
    }

    private function checkOrdersApproachingShipDate(): void
    {
        $config = $this->getConfig('orders_approaching_ship_date');
        if (!$config || !$config['enabled']) return;
        $days = (int)($config['threshold_value'] ?? 3);

        $stmt = $this->db->prepare('
            SELECT so.so_number, c.company_name, so.promised_ship_date
            FROM sales_orders so JOIN customers c ON so.customer_id = c.id
            WHERE so.status IN ("CONFIRMED","PARTIAL") AND so.promised_ship_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ');
        $stmt->execute([$days]);
        $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($orders)) return;

        $list = implode("\n", array_map(fn($o) => "- {$o['so_number']} ({$o['company_name']}) ships {$o['promised_ship_date']}", $orders));
        $this->sendAlert('orders_approaching_ship_date', ['subject' => count($orders) . ' orders shipping within ' . $days . ' days', 'body' => "Orders approaching ship date:\n\n" . $list]);
    }

    private function checkOrdersOpenTooLong(): void
    {
        $config = $this->getConfig('orders_open_too_long');
        if (!$config || !$config['enabled']) return;
        $days = (int)($config['threshold_value'] ?? 14);

        $stmt = $this->db->prepare('
            SELECT so.so_number, c.company_name, so.order_date FROM sales_orders so JOIN customers c ON so.customer_id = c.id
            WHERE so.status IN ("CONFIRMED","PARTIAL") AND so.order_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ');
        $stmt->execute([$days]);
        $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($orders)) return;

        $list = implode("\n", array_map(fn($o) => "- {$o['so_number']} ({$o['company_name']}) ordered {$o['order_date']}", $orders));
        $this->sendAlert('orders_open_too_long', ['subject' => count($orders) . ' orders open more than ' . $days . ' days', 'body' => "Stale orders:\n\n" . $list]);
    }

    private function checkQuotesExpiring(): void
    {
        $config = $this->getConfig('quote_expiring');
        if (!$config || !$config['enabled']) return;
        $days = (int)($config['threshold_value'] ?? 3);

        $stmt = $this->db->prepare('
            SELECT q.quote_number, c.company_name, q.expiration_date FROM quotes q JOIN customers c ON q.customer_id = c.id
            WHERE q.status IN ("DRAFT","SENT") AND q.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ');
        $stmt->execute([$days]);
        $quotes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($quotes)) return;

        $list = implode("\n", array_map(fn($q) => "- {$q['quote_number']} ({$q['company_name']}) expires {$q['expiration_date']}", $quotes));
        $this->sendAlert('quote_expiring', ['subject' => count($quotes) . ' quotes expiring soon', 'body' => "Quotes expiring within {$days} days:\n\n" . $list]);
    }

    private function checkTasksOverdue(): void
    {
        $stmt = $this->db->query('
            SELECT t.assigned_to, u.email, u.full_name, t.title, t.due_date, c.company_name
            FROM crm_tasks t JOIN users u ON t.assigned_to = u.id JOIN customers c ON t.customer_id = c.id
            WHERE t.status = "OPEN" AND t.due_date < CURDATE()
            ORDER BY t.assigned_to, t.due_date
        ');
        $tasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($tasks)) return;

        $byRep = [];
        foreach ($tasks as $t) {
            $byRep[$t['assigned_to']] = $byRep[$t['assigned_to']] ?? ['email' => $t['email'], 'name' => $t['full_name'], 'tasks' => []];
            $byRep[$t['assigned_to']]['tasks'][] = $t;
        }

        foreach ($byRep as $rep) {
            if (!$rep['email']) continue;
            $list = implode("\n", array_map(fn($t) => "- {$t['title']} ({$t['company_name']}) due {$t['due_date']}", $rep['tasks']));
            $this->email->send('notification', [$rep['email']], [
                'subject' => count($rep['tasks']) . ' overdue tasks',
                'body' => "Hi {$rep['name']},\n\nOverdue CRM tasks:\n\n" . $list
            ]);
        }
    }

    private function getConfig(string $alertType): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM notifications_config WHERE alert_type = ?');
        $stmt->execute([$alertType]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function parseRecipients(string $str): array
    {
        return array_filter(array_map('trim', explode(',', $str)));
    }

    private function logNotification(string $type, array $recipients, string $refType, int $refId, bool $success): void
    {
        $this->db->prepare('INSERT INTO notification_log (alert_type, recipients, reference_type, reference_id, status, created_at) VALUES (?,?,?,?,?,NOW())')
            ->execute([$type, implode(', ', $recipients), $refType ?: null, $refId ?: null, $success ? 'SENT' : 'FAILED']);
    }
}
