<?php

namespace PrecisionInk\Controllers;

class UserController extends BaseController
{
    // ══════════════════════════════════════════════════════════════════
    // USERS
    // ══════════════════════════════════════════════════════════════════

    public function index(): void
    {
        $this->requireAdmin();

        $filterActive = $_GET['active'] ?? '1';
        $filterGroup = (int)($_GET['group_id'] ?? 0);

        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if ($filterActive !== '') { $where[] = 'u.active = ?'; $params[] = (int)$filterActive; }
        if ($filterGroup) { $where[] = 'u.group_id = ?'; $params[] = $filterGroup; }
        $wc = implode(' AND ', $where);

        $stmt = $this->db()->prepare("
            SELECT u.*, g.name as group_name, g.is_system_admin as group_is_admin
            FROM users u LEFT JOIN `groups` g ON u.group_id = g.id
            WHERE {$wc} ORDER BY u.username
        ");
        $stmt->execute($params);

        $groups = $this->db()->query("SELECT id, name FROM `groups` WHERE active = 1 ORDER BY name")->fetchAll();

        $this->renderView('settings/_layout', [
            'content' => 'settings/users_list', 'section' => 'users', 'title' => 'Users',
            'users' => $stmt->fetchAll(), 'groups' => $groups,
            'filters' => ['active' => $filterActive, 'group_id' => $filterGroup],
        ]);
    }

    public function createForm(): void
    {
        $this->requireAdmin();
        $groups = $this->db()->query("SELECT id, name FROM `groups` WHERE active = 1 ORDER BY name")->fetchAll();
        $facilities = $this->db()->query("SELECT id, name FROM facilities WHERE active = 1 ORDER BY name")->fetchAll();
        $this->renderView('settings/_layout', [
            'content' => 'settings/user_form', 'section' => 'users', 'title' => 'New User',
            'mode' => 'create', 'editUser' => null, 'groups' => $groups,
            'facilities' => $facilities, 'userFacilities' => [],
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();
        $data = $this->extractUserData();
        $errors = $this->validateUser($data);

        if (!empty($errors)) {
            $this->toast(implode(' ', $errors), 'error');
            $this->redirect('/users/create');
            return;
        }

        $hash = password_hash($data['password'], PASSWORD_BCRYPT);

        $this->db()->prepare("
            INSERT INTO users (username, full_name, email, password_hash, group_id, active)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$data['username'], $data['full_name'], $data['email'], $hash, $data['group_id'] ?: null, $data['active']]);
        $userId = (int)$this->db()->lastInsertId();

        $this->saveFacilityRestrictions($userId);
        $this->auditCreate('users', $userId, ['username' => $data['username']]);
        $this->toast("User '{$data['username']}' created.", 'success');
        $this->redirect('/users');
    }

    public function editForm(string $id): void
    {
        $this->requireAdmin();
        $user = $this->getUserOrFail((int)$id);
        $groups = $this->db()->query("SELECT id, name FROM `groups` WHERE active = 1 ORDER BY name")->fetchAll();
        $facilities = $this->db()->query("SELECT id, name FROM facilities WHERE active = 1 ORDER BY name")->fetchAll();

        $ufStmt = $this->db()->prepare("SELECT facility_id FROM user_facility_restrictions WHERE user_id = ?");
        $ufStmt->execute([(int)$id]);
        $userFacilities = $ufStmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->renderView('settings/_layout', [
            'content' => 'settings/user_form', 'section' => 'users', 'title' => 'Edit User: ' . $user['username'],
            'mode' => 'edit', 'editUser' => $user, 'groups' => $groups,
            'facilities' => $facilities, 'userFacilities' => $userFacilities,
        ]);
    }

    public function update(string $id): void
    {
        $this->requireAdmin();
        $user = $this->getUserOrFail((int)$id);
        $data = $this->extractUserData();

        // Validate
        $errors = [];
        if (empty($data['username'])) $errors[] = 'Username required.';
        if (empty($data['full_name'])) $errors[] = 'Full name required.';
        if (empty($data['email'])) $errors[] = 'Email required.';

        // Check uniqueness excluding this user
        $dup = $this->db()->prepare("SELECT id FROM users WHERE username = ? AND id != ? AND deleted_at IS NULL");
        $dup->execute([$data['username'], (int)$id]);
        if ($dup->fetch()) $errors[] = 'Username already taken.';

        $dup = $this->db()->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL");
        $dup->execute([$data['email'], (int)$id]);
        if ($dup->fetch()) $errors[] = 'Email already taken.';

        if (!empty($errors)) {
            $this->toast(implode(' ', $errors), 'error');
            $this->redirect("/users/{$id}/edit");
            return;
        }

        $this->db()->prepare("UPDATE users SET username=?, full_name=?, email=?, group_id=?, active=?, updated_at=NOW() WHERE id=?")
            ->execute([$data['username'], $data['full_name'], $data['email'], $data['group_id'] ?: null, $data['active'], (int)$id]);

        // Update password if provided
        if (!empty($data['password'])) {
            $hash = password_hash($data['password'], PASSWORD_BCRYPT);
            $this->db()->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?")->execute([$hash, (int)$id]);
        }

        $this->saveFacilityRestrictions((int)$id);
        $this->auditUpdate('users', (int)$id, $user, $data);
        $this->toast('User updated.', 'success');
        $this->redirect('/users');
    }

    public function deactivate(string $id): void
    {
        $this->requireAdmin();
        $this->db()->prepare("UPDATE users SET active = 0, updated_at = NOW() WHERE id = ?")->execute([(int)$id]);
        $this->auditLog('UPDATE', 'users', (int)$id, ['active' => 1], ['active' => 0]);
        $this->toast('User deactivated.', 'success');
        $this->redirect('/users');
    }

    public function resetPassword(string $id): void
    {
        $this->requireAdmin();
        $user = $this->getUserOrFail((int)$id);

        $tempPass = 'Temp' . rand(1000, 9999) . '!';
        $hash = password_hash($tempPass, PASSWORD_BCRYPT);
        $this->db()->prepare("UPDATE users SET password_hash = ?, password_changed_at = NULL WHERE id = ?")->execute([$hash, (int)$id]);

        if ($this->emailService && $user['email']) {
            $this->emailService->send('notification', [$user['email']], [
                'subject' => 'Password Reset — Precision Ink ERP',
                'body' => "Your password has been reset.\n\nTemporary password: {$tempPass}\n\nPlease change it after logging in.",
            ]);
        }

        $this->auditLog('UPDATE', 'users', (int)$id, [], ['password_reset' => true]);
        $this->toast("Password reset. Temporary: {$tempPass}", 'warning');
        $this->redirect('/users');
    }

    // ══════════════════════════════════════════════════════════════════
    // GROUPS
    // ══════════════════════════════════════════════════════════════════

    public function groupIndex(): void
    {
        $this->requireAdmin();
        $groups = $this->db()->query("
            SELECT g.*, (SELECT COUNT(*) FROM users WHERE group_id = g.id AND deleted_at IS NULL) as user_count
            FROM `groups` g ORDER BY g.name
        ")->fetchAll();

        $this->renderView('settings/_layout', [
            'content' => 'settings/groups_list', 'section' => 'groups', 'title' => 'Groups & Permissions',
            'groups' => $groups,
        ]);
    }

    public function groupCreateForm(): void
    {
        $this->requireAdmin();
        $this->renderView('settings/_layout', [
            'content' => 'settings/group_form', 'section' => 'groups', 'title' => 'New Group',
            'mode' => 'create', 'group' => null,
            'permissions' => [], 'specialPerms' => [],
            'modules' => $this->getModuleList(), 'specialPermKeys' => $this->getSpecialPermKeys(),
        ]);
    }

    public function groupStore(): void
    {
        $this->requireAdmin();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isAdmin = isset($_POST['is_system_admin']) ? 1 : 0;

        if (!$name) { $this->toast('Group name required.', 'error'); $this->redirect('/groups/create'); return; }

        $this->db()->prepare("INSERT INTO `groups` (name, description, is_system_admin, active) VALUES (?, ?, ?, 1)")
            ->execute([$name, $description ?: null, $isAdmin]);
        $groupId = (int)$this->db()->lastInsertId();

        $this->saveGroupPermissions($groupId);
        $this->saveSpecialPermissions($groupId);
        $this->auditCreate('groups', $groupId, ['name' => $name]);
        $this->toast("Group '{$name}' created.", 'success');
        $this->redirect('/groups');
    }

    public function groupEditForm(string $id): void
    {
        $this->requireAdmin();
        $group = $this->db()->prepare("SELECT * FROM `groups` WHERE id = ?")->execute([(int)$id]) ? null : null;
        $gStmt = $this->db()->prepare("SELECT * FROM `groups` WHERE id = ?");
        $gStmt->execute([(int)$id]);
        $group = $gStmt->fetch();
        if (!$group) { http_response_code(404); echo 'Group not found.'; exit; }

        $permStmt = $this->db()->prepare("SELECT module, can_view, can_create, can_edit, can_delete FROM group_permissions WHERE group_id = ?");
        $permStmt->execute([(int)$id]);
        $permissions = [];
        foreach ($permStmt->fetchAll() as $p) $permissions[$p['module']] = $p;

        $spStmt = $this->db()->prepare("SELECT permission_key FROM group_special_permissions WHERE group_id = ?");
        $spStmt->execute([(int)$id]);
        $specialPerms = $spStmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->renderView('settings/_layout', [
            'content' => 'settings/group_form', 'section' => 'groups', 'title' => 'Edit Group: ' . $group['name'],
            'mode' => 'edit', 'group' => $group,
            'permissions' => $permissions, 'specialPerms' => $specialPerms,
            'modules' => $this->getModuleList(), 'specialPermKeys' => $this->getSpecialPermKeys(),
        ]);
    }

    public function groupUpdate(string $id): void
    {
        $this->requireAdmin();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isAdmin = isset($_POST['is_system_admin']) ? 1 : 0;

        $this->db()->prepare("UPDATE `groups` SET name=?, description=?, is_system_admin=?, updated_at=NOW() WHERE id=?")
            ->execute([$name, $description ?: null, $isAdmin, (int)$id]);

        $this->saveGroupPermissions((int)$id);
        $this->saveSpecialPermissions((int)$id);
        $this->auditLog('UPDATE', 'groups', (int)$id, [], ['name' => $name]);
        $this->toast('Group updated.', 'success');
        $this->redirect('/groups');
    }

    public function groupDelete(string $id): void
    {
        $this->requireAdmin();
        $userCount = $this->db()->prepare("SELECT COUNT(*) FROM users WHERE group_id = ? AND deleted_at IS NULL");
        $userCount->execute([(int)$id]);
        if ((int)$userCount->fetchColumn() > 0) {
            $this->toast('Cannot delete group with assigned users.', 'error');
            $this->redirect('/groups');
            return;
        }

        $this->db()->prepare("DELETE FROM group_permissions WHERE group_id = ?")->execute([(int)$id]);
        $this->db()->prepare("DELETE FROM group_special_permissions WHERE group_id = ?")->execute([(int)$id]);
        $this->db()->prepare("DELETE FROM `groups` WHERE id = ?")->execute([(int)$id]);
        $this->auditDelete('groups', (int)$id);
        $this->toast('Group deleted.', 'success');
        $this->redirect('/groups');
    }

    // ══════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════

    private function getUserOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("SELECT u.*, g.name as group_name FROM users u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.id = ? AND u.deleted_at IS NULL");
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if (!$u) { http_response_code(404); echo 'User not found.'; exit; }
        return $u;
    }

    private function extractUserData(): array
    {
        return [
            'username' => trim($_POST['username'] ?? ''),
            'full_name' => trim($_POST['full_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'group_id' => (int)($_POST['group_id'] ?? 0),
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
    }

    private function validateUser(array $data): array
    {
        $errors = [];
        if (empty($data['username'])) $errors[] = 'Username required.';
        if (empty($data['full_name'])) $errors[] = 'Full name required.';
        if (empty($data['email'])) $errors[] = 'Email required.';
        if (empty($data['password'])) $errors[] = 'Password required.';
        if (strlen($data['password']) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($data['password'] !== ($_POST['password_confirm'] ?? '')) $errors[] = 'Passwords do not match.';

        $dup = $this->db()->prepare("SELECT id FROM users WHERE username = ? AND deleted_at IS NULL");
        $dup->execute([$data['username']]);
        if ($dup->fetch()) $errors[] = 'Username already exists.';

        $dup = $this->db()->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
        $dup->execute([$data['email']]);
        if ($dup->fetch()) $errors[] = 'Email already exists.';

        return $errors;
    }

    private function saveFacilityRestrictions(int $userId): void
    {
        $this->db()->prepare("DELETE FROM user_facility_restrictions WHERE user_id = ?")->execute([$userId]);
        $facilityIds = $_POST['facilities'] ?? [];
        $stmt = $this->db()->prepare("INSERT INTO user_facility_restrictions (user_id, facility_id) VALUES (?, ?)");
        foreach ($facilityIds as $fid) {
            if ((int)$fid) $stmt->execute([$userId, (int)$fid]);
        }
    }

    private function saveGroupPermissions(int $groupId): void
    {
        $this->db()->prepare("DELETE FROM group_permissions WHERE group_id = ?")->execute([$groupId]);
        $perms = $_POST['perms'] ?? [];
        $stmt = $this->db()->prepare("INSERT INTO group_permissions (group_id, module, can_view, can_create, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($perms as $module => $actions) {
            $stmt->execute([
                $groupId, $module,
                isset($actions['view']) ? 1 : 0,
                isset($actions['create']) ? 1 : 0,
                isset($actions['edit']) ? 1 : 0,
                isset($actions['delete']) ? 1 : 0,
            ]);
        }
    }

    private function saveSpecialPermissions(int $groupId): void
    {
        $this->db()->prepare("DELETE FROM group_special_permissions WHERE group_id = ?")->execute([$groupId]);
        $specials = $_POST['special'] ?? [];
        $stmt = $this->db()->prepare("INSERT INTO group_special_permissions (group_id, permission_key) VALUES (?, ?)");
        foreach ($specials as $key) {
            $stmt->execute([$groupId, $key]);
        }
    }

    private function getModuleList(): array
    {
        return [
            'Inventory' => ['items', 'recipes', 'inventory', 'cycle_counts', 'batch_tickets', 'repack', 'consignment'],
            'Purchasing' => ['suppliers', 'purchase_requisitions', 'purchase_orders', 'receiving', 'landed_costs'],
            'Sales' => ['customers', 'quotes', 'sales_orders', 'pick_lists', 'shipments', 'invoices', 'rma'],
            'Quality' => ['qc', 'scars', 'equipment_maintenance'],
            'CRM' => ['crm'],
            'Production' => ['batch_templates', 'production_calendar', 'mrp'],
            'Other' => ['transfers', 'traceability', 'reports', 'settings'],
        ];
    }

    private function getSpecialPermKeys(): array
    {
        return [
            'approve_purchase_requisitions' => 'Approve Purchase Requisitions',
            'override_inventory_warning' => 'Override Inventory Warning',
            'override_moq' => 'Override MOQ',
            'release_order_hold' => 'Release Order Hold',
            'cancel_shipped_orders' => 'Cancel Shipped Orders',
            'credit_override' => 'Credit Override',
            'release_quarantine' => 'Release Quarantine',
            'void_invoice' => 'Void Invoice',
            'edit_invoice_header' => 'Edit Invoice Header',
            'edit_invoice_lines' => 'Edit Invoice Lines',
            'override_expired_lot' => 'Override Expired Lot',
        ];
    }
}
