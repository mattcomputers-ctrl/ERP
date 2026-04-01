<?php

namespace PrecisionInk\Controllers;

abstract class BaseController
{
    protected static ?\PDO $pdo = null;

    /**
     * Get a shared PDO database connection.
     */
    protected function db(): \PDO
    {
        if (self::$pdo === null) {
            $config = require __DIR__ . '/../../config/config.php';
            self::$pdo = new \PDO(
                "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4",
                $config['DB_USER'],
                $config['DB_PASS'],
                [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE  => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES    => false,
                ]
            );
        }
        return self::$pdo;
    }

    /**
     * Verify the current user has permission for the given module/action.
     * System admins bypass all checks.
     */
    protected function checkPermission(string $module, string $action): bool
    {
        $user = $this->currentUser();
        if (!$user) {
            return false;
        }

        // System admins have all permissions
        if (!empty($user['is_system_admin'])) {
            return true;
        }

        $groupId = $user['group_id'] ?? null;
        if (!$groupId) {
            return false;
        }

        // Check if the group is a system admin group
        $stmt = $this->db()->prepare('SELECT is_system_admin FROM `groups` WHERE id = ? AND active = 1');
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        if ($group && $group['is_system_admin']) {
            return true;
        }

        // Map action to column
        $columnMap = [
            'view'   => 'can_view',
            'create' => 'can_create',
            'edit'   => 'can_edit',
            'delete' => 'can_delete',
        ];

        $column = $columnMap[$action] ?? null;
        if (!$column) {
            return false;
        }

        $stmt = $this->db()->prepare(
            "SELECT {$column} FROM group_permissions WHERE group_id = ? AND module = ?"
        );
        $stmt->execute([$groupId, $module]);
        $row = $stmt->fetch();

        return $row && $row[$column];
    }

    /**
     * Require admin permission — redirect with 403 if denied.
     */
    protected function requireAdmin(): void
    {
        if (!$this->checkPermission('settings', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }
    }

    /**
     * Return the facility the current session is scoped to.
     */
    protected function getActiveFacility(): ?int
    {
        return $_SESSION['active_facility_id'] ?? null;
    }

    /**
     * Write an entry to the audit-log table.
     */
    protected function auditLog(
        string $action,
        string $module,
        int    $recordId,
        mixed  $old = null,
        mixed  $new = null,
    ): void {
        $user = $this->currentUser();
        $changes = null;

        if ($old !== null || $new !== null) {
            $fieldChanges = [];
            $allKeys = array_unique(array_merge(
                array_keys($old ?? []),
                array_keys($new ?? [])
            ));
            foreach ($allKeys as $key) {
                $oldVal = $old[$key] ?? null;
                $newVal = $new[$key] ?? null;
                if ($oldVal !== $newVal) {
                    $fieldChanges[$key] = ['old' => $oldVal, 'new' => $newVal];
                }
            }
            if ($fieldChanges) {
                $changes = json_encode($fieldChanges);
            }
        }

        $stmt = $this->db()->prepare(
            'INSERT INTO audit_log (user_id, action_type, module, record_id, field_changes, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'] ?? null,
            $action,
            $module,
            $recordId,
            $changes,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    /**
     * Send a JSON response and exit.
     */
    protected function jsonResponse(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Render a view template with the supplied data.
     */
    protected function renderView(string $template, array $data = []): void
    {
        extract($data);
        require __DIR__ . '/../Views/' . $template . '.php';
    }

    /**
     * Return the currently authenticated user record.
     */
    protected function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Set a flash/toast message in the session.
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        $_SESSION['toast'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Redirect to a URL and exit.
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }
}
