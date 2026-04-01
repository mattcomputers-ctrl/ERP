<?php

namespace PrecisionInk\Controllers;

class SettingsController extends BaseController
{
    // ── Settings Dashboard ─────────────────────────────────────────

    public function index(): void
    {
        $this->requireAdmin();
        $this->renderView('settings/_layout', [
            'title'   => 'Settings',
            'section' => 'index',
            'content' => 'settings/index',
        ]);
    }

    // ── Company Settings ───────────────────────────────────────────

    public function company(): void
    {
        $this->requireAdmin();

        $keys = [
            'company_name', 'company_street', 'company_city', 'company_state',
            'company_zip', 'company_country', 'company_phone', 'company_fax',
            'company_email', 'company_logo_path',
        ];
        $settings = $this->getSettings($keys);

        $this->renderView('settings/_layout', [
            'title'    => 'Company Settings',
            'section'  => 'company',
            'content'  => 'settings/company',
            'settings' => $settings,
        ]);
    }

    public function saveCompany(): void
    {
        $this->requireAdmin();

        $keys = [
            'company_name', 'company_street', 'company_city', 'company_state',
            'company_zip', 'company_country', 'company_phone', 'company_fax',
            'company_email',
        ];

        $old = $this->getSettings(array_merge($keys, ['company_logo_path']));
        $new = [];

        foreach ($keys as $key) {
            $new[$key] = trim($_POST[$key] ?? '');
        }

        // Handle logo removal
        if (!empty($_POST['remove_logo'])) {
            $logoPath = $old['company_logo_path'] ?? '';
            if ($logoPath) {
                $fullPath = __DIR__ . '/../../storage/' . $logoPath;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
            $new['company_logo_path'] = '';
        }

        // Handle logo upload
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['company_logo'];

            // Validate type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            if (!in_array($mimeType, $allowedTypes, true)) {
                $this->toast('Invalid file type. Only JPG, PNG, and GIF are allowed.', 'error');
                $this->redirect('/settings/company');
                return;
            }

            // Validate size (2MB max)
            if ($file['size'] > 2 * 1024 * 1024) {
                $this->toast('Logo file must be under 2MB.', 'error');
                $this->redirect('/settings/company');
                return;
            }

            // Remove old logo if replacing
            $oldLogo = $old['company_logo_path'] ?? '';
            if ($oldLogo) {
                $oldFullPath = __DIR__ . '/../../storage/' . $oldLogo;
                if (file_exists($oldFullPath)) {
                    unlink($oldFullPath);
                }
            }

            // Save new logo
            $ext = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
            };
            $filename = 'company_logo_' . time() . '.' . $ext;
            $destDir = __DIR__ . '/../../storage/attachments/logo';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $destPath = $destDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $new['company_logo_path'] = 'attachments/logo/' . $filename;
            }
        }

        $this->saveSettings($new);
        $this->auditLog('UPDATE', 'settings', 0, $old, array_merge($old, $new));
        $this->toast('Company settings saved successfully.');
        $this->redirect('/settings/company');
    }

    // ── Thresholds & Defaults ──────────────────────────────────────

    public function thresholds(): void
    {
        $this->requireAdmin();

        $keys = [
            'default_lead_time_days', 'quote_expiration_days',
            'receiving_discrepancy_threshold', 'cost_change_alert_threshold',
            'session_timeout_minutes', 'session_warning_minutes',
            'max_login_attempts', 'lockout_duration_minutes',
        ];
        $settings = $this->getSettings($keys);

        $this->renderView('settings/_layout', [
            'title'    => 'Thresholds & Defaults',
            'section'  => 'thresholds',
            'content'  => 'settings/thresholds',
            'settings' => $settings,
        ]);
    }

    public function saveThresholds(): void
    {
        $this->requireAdmin();

        $keys = [
            'default_lead_time_days', 'quote_expiration_days',
            'receiving_discrepancy_threshold', 'cost_change_alert_threshold',
            'session_timeout_minutes', 'session_warning_minutes',
            'max_login_attempts', 'lockout_duration_minutes',
        ];

        $old = $this->getSettings($keys);
        $new = [];
        $errors = [];

        foreach ($keys as $key) {
            $val = trim($_POST[$key] ?? '');
            if ($val === '' || !is_numeric($val) || (float) $val <= 0) {
                $label = ucwords(str_replace('_', ' ', $key));
                $errors[] = "{$label} must be a positive number.";
            } else {
                $new[$key] = $val;
            }
        }

        if ($errors) {
            $this->toast(implode(' ', $errors), 'error');
            $this->redirect('/settings/thresholds');
            return;
        }

        $this->saveSettings($new);
        $this->auditLog('UPDATE', 'settings', 0, $old, $new);
        $this->toast('Thresholds saved successfully.');
        $this->redirect('/settings/thresholds');
    }

    // ── Generic Dropdown List Methods ──────────────────────────────

    // UOM
    public function uom(): void { $this->showDropdownList('uom', 'Units of Measure'); }
    public function saveUom(): void { $this->saveDropdownItem('uom', '/settings/uom'); }

    // Ship Via
    public function shipVia(): void { $this->showDropdownList('ship_via', 'Ship Via'); }
    public function saveShipVia(): void { $this->saveDropdownItem('ship_via', '/settings/ship-via'); }

    // Payment Terms
    public function paymentTerms(): void { $this->showDropdownList('payment_terms', 'Payment Terms'); }
    public function savePaymentTerms(): void { $this->saveDropdownItem('payment_terms', '/settings/payment-terms'); }

    // Surcharge Types
    public function surchargeTypes(): void { $this->showDropdownList('surcharge_types', 'Surcharge Types'); }
    public function saveSurchargeTypes(): void { $this->saveDropdownItem('surcharge_types', '/settings/surcharge-types'); }

    // RMA Return Reasons
    public function rmaReasons(): void { $this->showDropdownList('rma_return_reasons', 'RMA Return Reasons'); }
    public function saveRmaReasons(): void { $this->saveDropdownItem('rma_return_reasons', '/settings/rma-reasons'); }

    // Lost Quote Reasons
    public function lostQuoteReasons(): void { $this->showDropdownList('lost_quote_reasons', 'Lost Quote Reasons'); }
    public function saveLostQuoteReasons(): void { $this->saveDropdownItem('lost_quote_reasons', '/settings/lost-quote-reasons'); }

    // Landed Cost Types
    public function landedCostTypes(): void { $this->showDropdownList('landed_cost_types', 'Landed Cost Types'); }
    public function saveLandedCostTypes(): void { $this->saveDropdownItem('landed_cost_types', '/settings/landed-cost-types'); }

    // Reason Codes
    public function reasonCodes(): void { $this->showDropdownList('reason_codes', 'Reason Codes'); }
    public function saveReasonCodes(): void { $this->saveDropdownItem('reason_codes', '/settings/reason-codes'); }

    // Industry Segments
    public function industrySegments(): void { $this->showDropdownList('industry_segments', 'Industry Segments'); }
    public function saveIndustrySegments(): void { $this->saveDropdownItem('industry_segments', '/settings/industry-segments'); }

    // ── Dropdown List Helpers ──────────────────────────────────────

    /**
     * Column definitions for each dropdown table.
     * 'fields' are editable columns beyond 'name'.
     */
    private function getDropdownConfig(string $table): array
    {
        return match ($table) {
            'uom' => [
                'fields' => [
                    'name'         => ['label' => 'Name', 'type' => 'text', 'required' => true],
                    'abbreviation' => ['label' => 'Abbreviation', 'type' => 'text', 'required' => true],
                ],
            ],
            'ship_via' => [
                'fields' => [
                    'name'         => ['label' => 'Name', 'type' => 'text', 'required' => true],
                    'transit_days' => ['label' => 'Transit Days', 'type' => 'number', 'required' => false,
                        'help' => 'Used to auto-calculate promised delivery date on orders'],
                ],
            ],
            'payment_terms' => [
                'fields' => [
                    'name'     => ['label' => 'Name', 'type' => 'text', 'required' => true],
                    'net_days' => ['label' => 'Net Days', 'type' => 'number', 'required' => true,
                        'help' => 'Used for invoice due date calculation: invoice_date + net_days'],
                ],
            ],
            'surcharge_types' => [
                'fields' => [
                    'name'             => ['label' => 'Name', 'type' => 'text', 'required' => true],
                    'calculation_type' => ['label' => 'Calculation Type', 'type' => 'select', 'required' => true,
                        'options' => ['FIXED' => 'Fixed Amount', 'PERCENTAGE' => 'Percentage']],
                ],
            ],
            default => [
                'fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                ],
            ],
        };
    }

    /**
     * Route name used in sub-nav based on table name.
     */
    private function getDropdownRoute(string $table): string
    {
        return match ($table) {
            'uom'                => 'uom',
            'ship_via'           => 'ship-via',
            'payment_terms'      => 'payment-terms',
            'surcharge_types'    => 'surcharge-types',
            'rma_return_reasons' => 'rma-reasons',
            'lost_quote_reasons' => 'lost-quote-reasons',
            'landed_cost_types'  => 'landed-cost-types',
            'reason_codes'       => 'reason-codes',
            'industry_segments'  => 'industry-segments',
            default              => $table,
        };
    }

    private function showDropdownList(string $table, string $title): void
    {
        $this->requireAdmin();

        $config = $this->getDropdownConfig($table);
        $fieldNames = array_keys($config['fields']);

        $cols = implode(', ', array_merge(['id'], $fieldNames, ['active', 'created_at', 'updated_at']));
        $items = $this->db()->query("SELECT {$cols} FROM `{$table}` ORDER BY name ASC")->fetchAll();

        $this->renderView('settings/_layout', [
            'title'        => $title,
            'section'      => $this->getDropdownRoute($table),
            'content'      => 'settings/dropdown_list',
            'items'        => $items,
            'table'        => $table,
            'dropdownTitle' => $title,
            'config'       => $config,
            'route'        => '/settings/' . $this->getDropdownRoute($table),
        ]);
    }

    private function saveDropdownItem(string $table, string $redirect): void
    {
        $this->requireAdmin();

        $config = $this->getDropdownConfig($table);
        $fieldNames = array_keys($config['fields']);
        $action = $_POST['action'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'toggle' && $id > 0) {
            // Toggle active status
            $stmt = $this->db()->prepare("SELECT active FROM `{$table}` WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                $newActive = $row['active'] ? 0 : 1;
                $this->db()->prepare("UPDATE `{$table}` SET active = ? WHERE id = ?")->execute([$newActive, $id]);
                $status = $newActive ? 'activated' : 'deactivated';
                $this->auditLog('UPDATE', $table, $id, ['active' => $row['active']], ['active' => $newActive]);
                $this->toast("Item {$status} successfully.");
            }
            $this->redirect($redirect);
            return;
        }

        // Collect field values
        $values = [];
        $errors = [];
        foreach ($config['fields'] as $field => $def) {
            $val = trim($_POST[$field] ?? '');
            if ($def['required'] && $val === '') {
                $errors[] = "{$def['label']} is required.";
            }
            $values[$field] = $val;
        }

        if ($errors) {
            $this->toast(implode(' ', $errors), 'error');
            $this->redirect($redirect);
            return;
        }

        if ($action === 'edit' && $id > 0) {
            // Update existing
            $setClauses = [];
            $params = [];
            foreach ($values as $field => $val) {
                $setClauses[] = "`{$field}` = ?";
                $params[] = $val === '' ? null : $val;
            }
            $params[] = $id;
            $sql = "UPDATE `{$table}` SET " . implode(', ', $setClauses) . " WHERE id = ?";
            $this->db()->prepare($sql)->execute($params);
            $this->auditLog('UPDATE', $table, $id);
            $this->toast('Item updated successfully.');
        } else {
            // Insert new
            $columns = array_keys($values);
            $placeholders = array_fill(0, count($columns), '?');
            $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
            $params = array_map(fn($v) => $v === '' ? null : $v, array_values($values));
            $this->db()->prepare($sql)->execute($params);
            $newId = (int) $this->db()->lastInsertId();
            $this->auditLog('CREATE', $table, $newId);
            $this->toast('Item added successfully.');
        }

        $this->redirect($redirect);
    }

    // ── Facilities ─────────────────────────────────────────────────

    public function facilities(): void
    {
        $this->requireAdmin();
        $items = $this->db()->query(
            'SELECT * FROM facilities ORDER BY name ASC'
        )->fetchAll();

        $this->renderView('settings/_layout', [
            'title'   => 'Facilities',
            'section' => 'facilities',
            'content' => 'settings/facilities',
            'items'   => $items,
        ]);
    }

    public function saveFacility(): void
    {
        $this->requireAdmin();
        $action = $_POST['action'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'deactivate' && $id > 0) {
            // Cannot deactivate last active facility
            $count = (int) $this->db()->query('SELECT COUNT(*) FROM facilities WHERE active = 1')->fetchColumn();
            if ($count <= 1) {
                $this->toast('Cannot deactivate the last active facility.', 'error');
                $this->redirect('/settings/facilities');
                return;
            }
            $stmt = $this->db()->prepare('SELECT * FROM facilities WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $this->db()->prepare('UPDATE facilities SET active = 0 WHERE id = ?')->execute([$id]);
            $this->auditLog('UPDATE', 'facilities', $id, $old, array_merge($old, ['active' => 0]));
            $this->toast('Facility deactivated.');
            $this->redirect('/settings/facilities');
            return;
        }

        if ($action === 'activate' && $id > 0) {
            $stmt = $this->db()->prepare('SELECT * FROM facilities WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $this->db()->prepare('UPDATE facilities SET active = 1 WHERE id = ?')->execute([$id]);
            $this->auditLog('UPDATE', 'facilities', $id, $old, array_merge($old, ['active' => 1]));
            $this->toast('Facility activated.');
            $this->redirect('/settings/facilities');
            return;
        }

        // Collect fields
        $fields = ['code', 'name', 'street', 'city', 'state', 'zip', 'country', 'phone', 'facility_type'];
        $values = [];
        $errors = [];
        foreach ($fields as $f) {
            $values[$f] = trim($_POST[$f] ?? '');
        }
        $values['is_default'] = !empty($_POST['is_default']) ? 1 : 0;

        if ($values['code'] === '') $errors[] = 'Code is required.';
        if ($values['name'] === '') $errors[] = 'Name is required.';
        if (!in_array($values['facility_type'], ['MANUFACTURING', 'WAREHOUSE', 'DISTRIBUTION'], true)) {
            $errors[] = 'Invalid facility type.';
        }

        // Check unique code
        $codeCheck = $this->db()->prepare('SELECT id FROM facilities WHERE code = ? AND id != ?');
        $codeCheck->execute([$values['code'], $id]);
        if ($codeCheck->fetch()) {
            $errors[] = 'Facility code already exists.';
        }

        if ($errors) {
            $this->toast(implode(' ', $errors), 'error');
            $this->redirect('/settings/facilities');
            return;
        }

        if ($values['is_default']) {
            $this->db()->exec('UPDATE facilities SET is_default = 0');
        }

        if ($action === 'edit' && $id > 0) {
            $stmt = $this->db()->prepare('SELECT * FROM facilities WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();

            $sql = 'UPDATE facilities SET code=?, name=?, street=?, city=?, state=?, zip=?, country=?, phone=?, facility_type=?, is_default=? WHERE id=?';
            $this->db()->prepare($sql)->execute([
                $values['code'], $values['name'], $values['street'], $values['city'],
                $values['state'], $values['zip'], $values['country'], $values['phone'],
                $values['facility_type'], $values['is_default'], $id,
            ]);
            $this->auditLog('UPDATE', 'facilities', $id, $old, array_merge($old, $values));
            $this->toast('Facility updated.');
        } else {
            $sql = 'INSERT INTO facilities (code, name, street, city, state, zip, country, phone, facility_type, is_default) VALUES (?,?,?,?,?,?,?,?,?,?)';
            $this->db()->prepare($sql)->execute([
                $values['code'], $values['name'], $values['street'], $values['city'],
                $values['state'], $values['zip'], $values['country'], $values['phone'],
                $values['facility_type'], $values['is_default'],
            ]);
            $newId = (int) $this->db()->lastInsertId();
            $this->auditLog('CREATE', 'facilities', $newId);
            $this->toast('Facility added.');
        }

        $this->redirect('/settings/facilities');
    }

    // ── Equipment ──────────────────────────────────────────────────

    public function equipment(): void
    {
        $this->requireAdmin();
        $items = $this->db()->query(
            'SELECT e.*, f.name AS facility_name
             FROM equipment e
             LEFT JOIN facilities f ON e.facility_id = f.id
             ORDER BY e.name ASC'
        )->fetchAll();

        $facilities = $this->db()->query('SELECT id, name FROM facilities WHERE active = 1 ORDER BY name')->fetchAll();

        $this->renderView('settings/_layout', [
            'title'      => 'Equipment',
            'section'    => 'equipment',
            'content'    => 'settings/equipment',
            'items'      => $items,
            'facilities' => $facilities,
        ]);
    }

    public function saveEquipment(): void
    {
        $this->requireAdmin();
        $action = $_POST['action'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'deactivate' && $id > 0) {
            $stmt = $this->db()->prepare('SELECT * FROM equipment WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $this->db()->prepare('UPDATE equipment SET active = 0 WHERE id = ?')->execute([$id]);
            $this->auditLog('UPDATE', 'equipment', $id, $old, array_merge($old, ['active' => 0]));
            $this->toast('Equipment deactivated.');
            $this->redirect('/settings/equipment');
            return;
        }

        if ($action === 'activate' && $id > 0) {
            $stmt = $this->db()->prepare('SELECT * FROM equipment WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $this->db()->prepare('UPDATE equipment SET active = 1 WHERE id = ?')->execute([$id]);
            $this->auditLog('UPDATE', 'equipment', $id, $old, array_merge($old, ['active' => 1]));
            $this->toast('Equipment activated.');
            $this->redirect('/settings/equipment');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $equipmentType = trim($_POST['equipment_type'] ?? '');
        $facilityId = ($_POST['facility_id'] ?? '') !== '' ? (int) $_POST['facility_id'] : null;
        $notes = trim($_POST['notes'] ?? '');
        $nextMaintenanceDue = trim($_POST['next_maintenance_due'] ?? '') ?: null;

        $errors = [];
        if ($name === '') $errors[] = 'Name is required.';
        if ($equipmentType === '') $errors[] = 'Equipment type is required.';

        if ($errors) {
            $this->toast(implode(' ', $errors), 'error');
            $this->redirect('/settings/equipment');
            return;
        }

        if ($action === 'edit' && $id > 0) {
            $stmt = $this->db()->prepare('SELECT * FROM equipment WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();

            $sql = 'UPDATE equipment SET name=?, equipment_type=?, facility_id=?, notes=?, next_maintenance_due=? WHERE id=?';
            $this->db()->prepare($sql)->execute([$name, $equipmentType, $facilityId, $notes ?: null, $nextMaintenanceDue, $id]);
            $new = ['name' => $name, 'equipment_type' => $equipmentType, 'facility_id' => $facilityId, 'notes' => $notes, 'next_maintenance_due' => $nextMaintenanceDue];
            $this->auditLog('UPDATE', 'equipment', $id, $old, array_merge($old, $new));
            $this->toast('Equipment updated.');
        } else {
            $sql = 'INSERT INTO equipment (name, equipment_type, facility_id, notes, next_maintenance_due) VALUES (?,?,?,?,?)';
            $this->db()->prepare($sql)->execute([$name, $equipmentType, $facilityId, $notes ?: null, $nextMaintenanceDue]);
            $newId = (int) $this->db()->lastInsertId();
            $this->auditLog('CREATE', 'equipment', $newId);
            $this->toast('Equipment added.');
        }

        $this->redirect('/settings/equipment');
    }

    // ── Item Prototypes ────────────────────────────────────────────

    public function itemPrototypes(): void
    {
        $this->requireAdmin();
        $items = $this->db()->query(
            'SELECT p.*, u.name AS uom_name
             FROM item_prototypes p
             LEFT JOIN uom u ON p.uom_id = u.id
             ORDER BY p.name ASC'
        )->fetchAll();

        $uoms = $this->db()->query('SELECT id, name, abbreviation FROM uom WHERE active = 1 ORDER BY name')->fetchAll();

        $this->renderView('settings/_layout', [
            'title'   => 'Item Prototypes',
            'section' => 'item-prototypes',
            'content' => 'settings/item_prototypes',
            'items'   => $items,
            'uoms'    => $uoms,
        ]);
    }

    public function saveItemPrototype(): void
    {
        $this->requireAdmin();
        $action = $_POST['action'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'deactivate' && $id > 0) {
            $stmt = $this->db()->prepare('SELECT * FROM item_prototypes WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $this->db()->prepare('UPDATE item_prototypes SET active = 0 WHERE id = ?')->execute([$id]);
            $this->auditLog('UPDATE', 'item_prototypes', $id, $old, array_merge($old, ['active' => 0]));
            $this->toast('Item prototype deactivated.');
            $this->redirect('/settings/item-prototypes');
            return;
        }

        if ($action === 'activate' && $id > 0) {
            $stmt = $this->db()->prepare('SELECT * FROM item_prototypes WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $this->db()->prepare('UPDATE item_prototypes SET active = 1 WHERE id = ?')->execute([$id]);
            $this->auditLog('UPDATE', 'item_prototypes', $id, $old, array_merge($old, ['active' => 1]));
            $this->toast('Item prototype activated.');
            $this->redirect('/settings/item-prototypes');
            return;
        }

        $itemTypes = ['RAW_MATERIAL', 'FINISHED_GOOD', 'INTERMEDIATE', 'RESALE', 'SERVICE'];

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $itemType = $_POST['item_type'] ?? '';
        $glGroup = $_POST['gl_group'] ?? '';
        $uomId = ($_POST['uom_id'] ?? '') !== '' ? (int) $_POST['uom_id'] : null;
        $shelfLifeDays = ($_POST['shelf_life_days'] ?? '') !== '' ? (int) $_POST['shelf_life_days'] : null;
        $requiresInspection = !empty($_POST['requires_inspection']) ? 1 : 0;

        $errors = [];
        if ($name === '') $errors[] = 'Name is required.';
        if (!in_array($itemType, $itemTypes, true)) $errors[] = 'Invalid item type.';
        if (!in_array($glGroup, $itemTypes, true)) $errors[] = 'Invalid GL group.';

        if ($errors) {
            $this->toast(implode(' ', $errors), 'error');
            $this->redirect('/settings/item-prototypes');
            return;
        }

        if ($action === 'edit' && $id > 0) {
            $stmt = $this->db()->prepare('SELECT * FROM item_prototypes WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();

            $sql = 'UPDATE item_prototypes SET name=?, description=?, item_type=?, gl_group=?, uom_id=?, shelf_life_days=?, requires_inspection=? WHERE id=?';
            $this->db()->prepare($sql)->execute([$name, $description ?: null, $itemType, $glGroup, $uomId, $shelfLifeDays, $requiresInspection, $id]);
            $this->auditLog('UPDATE', 'item_prototypes', $id, $old, [
                'name' => $name, 'description' => $description, 'item_type' => $itemType,
                'gl_group' => $glGroup, 'uom_id' => $uomId, 'shelf_life_days' => $shelfLifeDays,
                'requires_inspection' => $requiresInspection,
            ]);
            $this->toast('Item prototype updated.');
        } else {
            $sql = 'INSERT INTO item_prototypes (name, description, item_type, gl_group, uom_id, shelf_life_days, requires_inspection) VALUES (?,?,?,?,?,?,?)';
            $this->db()->prepare($sql)->execute([$name, $description ?: null, $itemType, $glGroup, $uomId, $shelfLifeDays, $requiresInspection]);
            $newId = (int) $this->db()->lastInsertId();
            $this->auditLog('CREATE', 'item_prototypes', $newId);
            $this->toast('Item prototype added.');
            $id = $newId;
        }

        // Handle pack extensions
        $this->savePackExtensions($id);

        $this->redirect('/settings/item-prototypes');
    }

    private function savePackExtensions(int $prototypeId): void
    {
        $packNames = $_POST['pack_name'] ?? [];
        $packNetWeights = $_POST['pack_net_weight'] ?? [];
        $packTareWeights = $_POST['pack_tare_weight'] ?? [];
        $packIds = $_POST['pack_id'] ?? [];

        // Delete removed extensions
        $keepIds = array_filter(array_map('intval', $packIds));
        if ($keepIds) {
            $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
            $this->db()->prepare(
                "DELETE FROM item_prototype_pack_extensions WHERE prototype_id = ? AND id NOT IN ({$placeholders})"
            )->execute(array_merge([$prototypeId], $keepIds));
        } else {
            $this->db()->prepare('DELETE FROM item_prototype_pack_extensions WHERE prototype_id = ?')->execute([$prototypeId]);
        }

        // Upsert pack extensions
        for ($i = 0; $i < count($packNames); $i++) {
            $pName = trim($packNames[$i] ?? '');
            $pNet = (float) ($packNetWeights[$i] ?? 0);
            $pTare = (float) ($packTareWeights[$i] ?? 0);
            $pId = (int) ($packIds[$i] ?? 0);

            if ($pName === '') continue;

            if ($pId > 0) {
                $this->db()->prepare(
                    'UPDATE item_prototype_pack_extensions SET name=?, net_weight=?, tare_weight=? WHERE id=? AND prototype_id=?'
                )->execute([$pName, $pNet, $pTare, $pId, $prototypeId]);
            } else {
                $this->db()->prepare(
                    'INSERT INTO item_prototype_pack_extensions (prototype_id, name, net_weight, tare_weight) VALUES (?,?,?,?)'
                )->execute([$prototypeId, $pName, $pNet, $pTare]);
            }
        }

        $this->auditLog('UPDATE', 'item_prototype_pack_extensions', $prototypeId);
    }

    public function itemPrototypeForm(): void
    {
        $this->requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $prototype = null;
        $extensions = [];

        if ($id > 0) {
            $stmt = $this->db()->prepare('SELECT * FROM item_prototypes WHERE id = ?');
            $stmt->execute([$id]);
            $prototype = $stmt->fetch();

            $stmt = $this->db()->prepare('SELECT * FROM item_prototype_pack_extensions WHERE prototype_id = ? ORDER BY id');
            $stmt->execute([$id]);
            $extensions = $stmt->fetchAll();
        }

        $uoms = $this->db()->query('SELECT id, name, abbreviation FROM uom WHERE active = 1 ORDER BY name')->fetchAll();

        $this->renderView('settings/_layout', [
            'title'      => $prototype ? 'Edit Item Prototype' : 'New Item Prototype',
            'section'    => 'item-prototypes',
            'content'    => 'settings/item_prototype_form',
            'prototype'  => $prototype,
            'extensions' => $extensions,
            'uoms'       => $uoms,
        ]);
    }

    // ── Batch Templates ────────────────────────────────────────────

    public function batchTemplates(): void
    {
        $this->requireAdmin();
        $items = $this->db()->query(
            'SELECT bt.*, i.description AS item_name
             FROM batch_templates bt
             LEFT JOIN items i ON bt.item_id = i.id
             ORDER BY bt.name ASC'
        )->fetchAll();

        $this->renderView('settings/_layout', [
            'title'   => 'Batch Ticket Templates',
            'section' => 'batch-templates',
            'content' => 'settings/batch_templates',
            'items'   => $items,
        ]);
    }

    public function saveBatchTemplate(): void
    {
        $this->requireAdmin();
        $action = $_POST['action'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete' && $id > 0) {
            // Check if any batch tickets reference this template
            $stmt = $this->db()->prepare('SELECT COUNT(*) FROM batch_tickets WHERE template_id = ?');
            $stmt->execute([$id]);
            $refCount = (int) $stmt->fetchColumn();
            if ($refCount > 0) {
                // Soft delete
                $this->db()->prepare('UPDATE batch_templates SET active = 0 WHERE id = ?')->execute([$id]);
                $this->auditLog('UPDATE', 'batch_templates', $id, ['active' => 1], ['active' => 0]);
                $this->toast('Template deactivated (referenced by batch tickets).');
            } else {
                $this->db()->prepare('DELETE FROM batch_templates WHERE id = ?')->execute([$id]);
                $this->auditLog('DELETE', 'batch_templates', $id);
                $this->toast('Template deleted.');
            }
            $this->redirect('/settings/batch-templates');
            return;
        }

        if ($action === 'edit' && $id > 0) {
            $stmt = $this->db()->prepare('SELECT * FROM batch_templates WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();

            $name = trim($_POST['name'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($name === '') {
                $this->toast('Name is required.', 'error');
                $this->redirect('/settings/batch-templates');
                return;
            }

            $this->db()->prepare('UPDATE batch_templates SET name = ?, notes = ? WHERE id = ?')
                ->execute([$name, $notes ?: null, $id]);
            $this->auditLog('UPDATE', 'batch_templates', $id, $old, array_merge($old, ['name' => $name, 'notes' => $notes]));
            $this->toast('Template updated.');
        }

        $this->redirect('/settings/batch-templates');
    }

    // ── Document Numbering ─────────────────────────────────────────

    public function documentNumbering(): void
    {
        $this->requireAdmin();
        $sequences = $this->db()->query(
            'SELECT * FROM document_numbering_sequences ORDER BY sequence_key ASC'
        )->fetchAll();

        $labels = [
            'PURCHASE_ORDER' => 'Purchase Orders',
            'SALES_ORDER'    => 'Sales Orders',
            'REPACK_TICKET'  => 'Repack Tickets',
            'RMA'            => 'RMA Orders',
            'QUOTE'          => 'Quotes',
            'CONSIGNMENT'    => 'Consignment',
            'TRANSFER'       => 'Transfers',
            'PICK_LIST'      => 'Pick Lists',
            'SCAR'           => 'SCARs',
            'REQUISITION'    => 'Requisitions',
        ];

        $this->renderView('settings/_layout', [
            'title'     => 'Document Numbering',
            'section'   => 'document-numbering',
            'content'   => 'settings/document_numbering',
            'sequences' => $sequences,
            'labels'    => $labels,
        ]);
    }

    public function saveDocumentNumbering(): void
    {
        $this->requireAdmin();

        $sequences = $this->db()->query('SELECT * FROM document_numbering_sequences')->fetchAll();
        $errors = [];
        $updates = [];

        foreach ($sequences as $seq) {
            $key = $seq['sequence_key'];
            $newNext = trim($_POST["next_{$key}"] ?? '');
            $newPrefix = trim($_POST["prefix_{$key}"] ?? $seq['prefix']);

            if ($newNext === '' || !ctype_digit($newNext) || (int) $newNext < 1) {
                $errors[] = "{$key}: Next number must be a positive integer.";
                continue;
            }

            $updates[] = [
                'id'          => $seq['id'],
                'key'         => $key,
                'prefix'      => $newPrefix,
                'next_number' => (int) $newNext,
                'old_prefix'  => $seq['prefix'],
                'old_next'    => $seq['next_number'],
            ];
        }

        if ($errors) {
            $this->toast(implode(' ', $errors), 'error');
            $this->redirect('/settings/document-numbering');
            return;
        }

        $warnings = [];
        foreach ($updates as $u) {
            if ($u['next_number'] < $u['old_next']) {
                $warnings[] = "{$u['key']}: lowered from {$u['old_next']} to {$u['next_number']}";
            }
            $this->db()->prepare('UPDATE document_numbering_sequences SET prefix = ?, next_number = ? WHERE id = ?')
                ->execute([$u['prefix'], $u['next_number'], $u['id']]);

            if ($u['next_number'] !== (int) $u['old_next'] || $u['prefix'] !== $u['old_prefix']) {
                $this->auditLog('UPDATE', 'document_numbering_sequences', $u['id'],
                    ['prefix' => $u['old_prefix'], 'next_number' => $u['old_next']],
                    ['prefix' => $u['prefix'], 'next_number' => $u['next_number']]
                );
            }
        }

        $msg = 'Document numbering saved.';
        if ($warnings) {
            $msg .= ' Warning — numbers were lowered: ' . implode('; ', $warnings) . '. This may cause duplicates.';
            $this->toast($msg, 'warning');
        } else {
            $this->toast($msg);
        }

        $this->redirect('/settings/document-numbering');
    }

    // ── API Keys ───────────────────────────────────────────────────

    public function apiKeys(): void
    {
        $this->requireAdmin();
        $keys = $this->db()->query(
            'SELECT id, key_prefix, rate_limit_per_minute, active, created_at, last_used_at FROM api_keys ORDER BY created_at DESC'
        )->fetchAll();

        // Check if a newly generated key needs to be shown
        $newKey = $_SESSION['new_api_key'] ?? null;
        unset($_SESSION['new_api_key']);

        $this->renderView('settings/_layout', [
            'title'   => 'API Keys',
            'section' => 'api-keys',
            'content' => 'settings/api_keys',
            'keys'    => $keys,
            'newKey'  => $newKey,
        ]);
    }

    public function saveApiKey(): void
    {
        $this->requireAdmin();
        $action = $_POST['action'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'generate') {
            $key = 'prec_' . bin2hex(random_bytes(24));
            $hash = hash('sha256', $key);
            $prefix = substr($key, 0, 12);

            $this->db()->prepare(
                'INSERT INTO api_keys (key_hash, key_prefix, rate_limit_per_minute) VALUES (?, ?, ?)'
            )->execute([$hash, $prefix, 60]);
            $newId = (int) $this->db()->lastInsertId();

            $this->auditLog('CREATE', 'api_keys', $newId, null, ['key_prefix' => $prefix]);
            $_SESSION['new_api_key'] = $key;
            $this->toast('API key generated. Copy it now — it will not be shown again.');
            $this->redirect('/settings/api-keys');
            return;
        }

        if ($action === 'rotate' && $id > 0) {
            $stmt = $this->db()->prepare('SELECT key_prefix FROM api_keys WHERE id = ?');
            $stmt->execute([$id]);
            $oldRow = $stmt->fetch();

            // Generate new key
            $key = 'prec_' . bin2hex(random_bytes(24));
            $hash = hash('sha256', $key);
            $prefix = substr($key, 0, 12);

            $this->db()->beginTransaction();
            // Deactivate old
            $this->db()->prepare('UPDATE api_keys SET active = 0 WHERE id = ?')->execute([$id]);
            // Insert new
            $this->db()->prepare(
                'INSERT INTO api_keys (key_hash, key_prefix, rate_limit_per_minute) VALUES (?, ?, ?)'
            )->execute([$hash, $prefix, 60]);
            $newId = (int) $this->db()->lastInsertId();
            $this->db()->commit();

            $this->auditLog('UPDATE', 'api_keys', $id, ['active' => 1, 'key_prefix' => $oldRow['key_prefix'] ?? ''], ['active' => 0]);
            $this->auditLog('CREATE', 'api_keys', $newId, null, ['key_prefix' => $prefix, 'rotated_from' => $id]);

            $_SESSION['new_api_key'] = $key;
            $this->toast('API key rotated. Copy the new key now — it will not be shown again.');
            $this->redirect('/settings/api-keys');
            return;
        }

        if ($action === 'revoke' && $id > 0) {
            $stmt = $this->db()->prepare('SELECT key_prefix FROM api_keys WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $this->db()->prepare('UPDATE api_keys SET active = 0 WHERE id = ?')->execute([$id]);
            $this->auditLog('UPDATE', 'api_keys', $id, ['active' => 1, 'key_prefix' => $row['key_prefix'] ?? ''], ['active' => 0]);
            $this->toast('API key revoked.');
            $this->redirect('/settings/api-keys');
            return;
        }

        if ($action === 'update_rate' && $id > 0) {
            $rate = (int) ($_POST['rate_limit_per_minute'] ?? 60);
            if ($rate < 1) $rate = 1;
            $this->db()->prepare('UPDATE api_keys SET rate_limit_per_minute = ? WHERE id = ?')->execute([$rate, $id]);
            $this->auditLog('UPDATE', 'api_keys', $id, null, ['rate_limit_per_minute' => $rate]);
            $this->toast('Rate limit updated.');
            $this->redirect('/settings/api-keys');
            return;
        }

        $this->redirect('/settings/api-keys');
    }

    // ── System Settings Helpers ────────────────────────────────────

    /**
     * Get multiple settings as key => value array.
     */
    private function getSettings(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->db()->prepare(
            "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ({$placeholders})"
        );
        $stmt->execute($keys);
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = '';
        }
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['setting_key']] = $row['setting_value'] ?? '';
        }
        return $result;
    }

    /**
     * Save multiple settings as key => value pairs (upsert).
     */
    private function saveSettings(array $settings): void
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO system_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    }
}
