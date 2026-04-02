<?php

namespace PrecisionInk\Controllers;

use App\Services\AuditService;
use App\Services\CustomFieldService;
use App\Services\EmailService;
use App\Services\FacilityService;
use App\Services\AttachmentService;
use App\Services\SupplierService;
use App\Services\CreditService;
use App\Services\CustomerResolutionService;
use App\Services\FIFOService;
use App\Services\ReservationService;

abstract class BaseController
{
    protected static ?\PDO $pdo = null;
    protected ?AuditService $auditService = null;
    protected ?EmailService $emailService = null;
    protected ?FacilityService $facilityService = null;
    protected ?AttachmentService $attachmentService = null;
    protected ?CustomFieldService $customFieldService = null;
    protected ?SupplierService $supplierService = null;
    protected ?CreditService $creditService = null;
    protected ?CustomerResolutionService $customerResolutionService = null;
    protected ?FIFOService $fifoService = null;
    protected ?ReservationService $reservationService = null;

    /** @var array Static service container, set once at bootstrap time. */
    private static array $container = [];

    /**
     * Register the service container (called once in public/index.php).
     */
    public static function setContainer(array $container): void
    {
        self::$container = $container;
        if (isset($container['db'])) {
            self::$pdo = $container['db'];
        }
    }

    /**
     * Constructor — pulls services from the static container.
     * The Bramus router instantiates controllers with no args,
     * so we use the static container instead of constructor injection.
     */
    public function __construct()
    {
        if (isset(self::$container['audit'])) {
            $this->auditService = self::$container['audit'];
        }
        if (isset(self::$container['email'])) {
            $this->emailService = self::$container['email'];
        }
        if (isset(self::$container['facility'])) {
            $this->facilityService = self::$container['facility'];
        }
        if (isset(self::$container['attachments'])) {
            $this->attachmentService = self::$container['attachments'];
        }
        if (isset(self::$container['custom_fields'])) {
            $this->customFieldService = self::$container['custom_fields'];
        }
        if (isset(self::$container['supplier'])) {
            $this->supplierService = self::$container['supplier'];
        }
        if (isset(self::$container['credit'])) {
            $this->creditService = self::$container['credit'];
        }
        if (isset(self::$container['customer_res'])) {
            $this->customerResolutionService = self::$container['customer_res'];
        }
        if (isset(self::$container['fifo'])) {
            $this->fifoService = self::$container['fifo'];
        }
        if (isset(self::$container['reservation'])) {
            $this->reservationService = self::$container['reservation'];
        }
    }

    /**
     * Get the shared PDO instance (for use in view partials).
     */
    public static function getSharedDb(): ?\PDO
    {
        return self::$pdo;
    }

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
     * Check if the current user has a special permission key.
     */
    protected function hasSpecialPermission(string $permissionKey): bool
    {
        $user = $this->currentUser();
        if (!$user) return false;
        if (!empty($user['is_system_admin'])) return true;

        $groupId = $user['group_id'] ?? null;
        if (!$groupId) return false;

        // Check group is system admin
        $stmt = $this->db()->prepare('SELECT is_system_admin FROM `groups` WHERE id = ? AND active = 1');
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        if ($group && $group['is_system_admin']) return true;

        $stmt = $this->db()->prepare('SELECT id FROM group_special_permissions WHERE group_id = ? AND permission_key = ?');
        $stmt->execute([$groupId, $permissionKey]);
        return (bool)$stmt->fetch();
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
     * Get current user ID from session.
     */
    protected function currentUserId(): ?int
    {
        $user = $this->currentUser();
        return $user ? (int)($user['id'] ?? 0) : null;
    }

    /**
     * Write an entry to the audit-log table.
     * Uses AuditService if available, falls back to direct insert.
     */
    protected function auditLog(
        string $action,
        string $module,
        int    $recordId,
        mixed  $old = null,
        mixed  $new = null,
    ): void {
        if ($this->auditService) {
            $this->auditService->log(
                $this->currentUserId(),
                $action,
                $module,
                $recordId,
                $old ?? [],
                $new ?? []
            );
            return;
        }

        // Fallback: direct insert (backward compatibility)
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
     * Convenience: log a CREATE event.
     */
    protected function auditCreate(string $module, int $recordId, array $data = []): void
    {
        $this->auditLog('CREATE', $module, $recordId, null, $data);
    }

    /**
     * Convenience: log an UPDATE event with field-level diffs.
     */
    protected function auditUpdate(string $module, int $recordId, array $oldData, array $newData): void
    {
        $this->auditLog('UPDATE', $module, $recordId, $oldData, $newData);
    }

    /**
     * Convenience: log a DELETE event.
     */
    protected function auditDelete(string $module, int $recordId): void
    {
        $this->auditLog('DELETE', $module, $recordId);
    }

    /**
     * Send an email via EmailService.
     */
    protected function sendEmail(
        string $templateType,
        array $toAddresses,
        array $mergeData = [],
        ?string $attachmentPath = null,
        ?string $attachmentName = null,
        string $referenceType = '',
        int $referenceId = 0
    ): bool {
        if (!$this->emailService) {
            return false;
        }
        return $this->emailService->send(
            $templateType, $toAddresses, $mergeData,
            $attachmentPath, $attachmentName, $referenceType, $referenceId
        );
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
        if ($this->customFieldService && !isset($data['customFieldService'])) {
            $data['customFieldService'] = $this->customFieldService;
        }
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

    /**
     * Generate the next document number for a given sequence key.
     * Atomically increments the counter. Returns e.g. "TRF00001".
     */
    protected function generateDocumentNumber(string $sequenceKey): string
    {
        $this->db()->beginTransaction();
        try {
            $stmt = $this->db()->prepare(
                'SELECT * FROM document_numbering_sequences WHERE sequence_key = ? FOR UPDATE'
            );
            $stmt->execute([$sequenceKey]);
            $seq = $stmt->fetch();

            if (!$seq) {
                throw new \RuntimeException("Document numbering sequence not found: $sequenceKey");
            }

            $number = $seq['prefix'] . str_pad($seq['next_number'], 5, '0', STR_PAD_LEFT);

            $this->db()->prepare(
                'UPDATE document_numbering_sequences SET next_number = next_number + 1 WHERE id = ?'
            )->execute([$seq['id']]);

            $this->db()->commit();
            return $number;
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            throw $e;
        }
    }
}
