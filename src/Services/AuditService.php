<?php

namespace App\Services;

class AuditService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Log any write event to the audit trail.
     *
     * @param int|null $userId  Current user ID (null for system-triggered events)
     * @param string $actionType  CREATE | UPDATE | DELETE | LOGIN | LOGOUT | PERMISSION_OVERRIDE
     * @param string $module  e.g. 'items', 'customers', 'batch_tickets'
     * @param int|null $recordId  Primary key of the affected record
     * @param array $oldData  For UPDATE: the record's data BEFORE the change
     * @param array $newData  For UPDATE: the record's data AFTER the change
     */
    public function log(
        ?int $userId,
        string $actionType,
        string $module,
        ?int $recordId = null,
        array $oldData = [],
        array $newData = []
    ): void {
        $fieldChanges = null;

        if ($actionType === 'UPDATE' && !empty($oldData) && !empty($newData)) {
            $changes = [];
            foreach ($newData as $field => $newValue) {
                $oldValue = $oldData[$field] ?? null;
                if ((string)$oldValue !== (string)$newValue) {
                    $changes[] = [
                        'field' => $field,
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                    ];
                }
            }
            if (!empty($changes)) {
                $fieldChanges = json_encode($changes);
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $this->db->prepare(
            'INSERT INTO audit_log (user_id, action_type, module, record_id, field_changes, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $actionType, $module, $recordId, $fieldChanges, $ip]);
    }

    /**
     * Retrieve audit trail entries with optional filtering.
     */
    public function getAuditTrail(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }
        if (!empty($filters['module'])) {
            $where[] = 'a.module = ?';
            $params[] = $filters['module'];
        }
        if (!empty($filters['action_type'])) {
            $where[] = 'a.action_type = ?';
            $params[] = $filters['action_type'];
        }
        if (!empty($filters['record_id'])) {
            $where[] = 'a.record_id = ?';
            $params[] = (int)$filters['record_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare(
            "SELECT a.*, u.username FROM audit_log a
             LEFT JOIN users u ON a.user_id = u.id
             {$whereClause}
             ORDER BY a.created_at DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get audit history for a specific entity.
     */
    public function getEntityHistory(string $module, int $recordId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, u.username FROM audit_log a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.module = ? AND a.record_id = ?
             ORDER BY a.created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$module, $recordId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get recent activity across all modules.
     */
    public function getRecentActivity(int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, u.username FROM audit_log a
             LEFT JOIN users u ON a.user_id = u.id
             ORDER BY a.created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
