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

    // ── SMTP Email Settings ──────────────────────────────────────────

    public function smtp(): void
    {
        $this->requireAdmin();

        $keys = [
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
            'smtp_from_address', 'smtp_from_name', 'smtp_encryption',
        ];
        $settings = $this->getSettings($keys);
        // Mask password — just indicate if set
        $settings['smtp_password_set'] = !empty($settings['smtp_password']);
        unset($settings['smtp_password']);

        $this->renderView('settings/_layout', [
            'title'    => 'SMTP Email Settings',
            'section'  => 'smtp',
            'content'  => 'settings/smtp',
            'settings' => $settings,
        ]);
    }

    public function saveSmtp(): void
    {
        $this->requireAdmin();

        $keys = [
            'smtp_host', 'smtp_port', 'smtp_username',
            'smtp_from_address', 'smtp_from_name', 'smtp_encryption',
        ];

        $old = $this->getSettings(array_merge($keys, ['smtp_password']));
        $new = [];

        foreach ($keys as $key) {
            $new[$key] = trim($_POST[$key] ?? '');
        }

        // Validate encryption value
        if (!in_array($new['smtp_encryption'], ['tls', 'ssl'], true)) {
            $new['smtp_encryption'] = 'tls';
        }

        // Handle password: only update if "change password" checkbox is checked
        if (!empty($_POST['change_password'])) {
            $plainPassword = $_POST['smtp_password'] ?? '';
            if ($plainPassword !== '') {
                $new['smtp_password'] = $this->encryptSmtpPassword($plainPassword);
            } else {
                $new['smtp_password'] = '';
            }
        }

        $this->saveSettings($new);
        $this->auditLog('UPDATE', 'settings_smtp', 0, $old, array_merge($old, $new));
        $this->toast('SMTP settings saved successfully.');
        $this->redirect('/settings/smtp');
    }

    public function testSmtp(): void
    {
        $this->requireAdmin();

        $keys = [
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
            'smtp_from_address', 'smtp_from_name', 'smtp_encryption',
        ];
        $settings = $this->getSettings($keys);

        $user = $this->currentUser();
        $toEmail = $user['email'] ?? '';

        if (empty($toEmail)) {
            $this->jsonResponse(['success' => false, 'error' => 'Your user account has no email address configured.']);
            return;
        }

        if (empty($settings['smtp_host'])) {
            $this->jsonResponse(['success' => false, 'error' => 'SMTP host is not configured.']);
            return;
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $settings['smtp_host'];
            $mail->Port       = (int) ($settings['smtp_port'] ?: 587);
            $mail->SMTPAuth   = !empty($settings['smtp_username']);
            $mail->Username   = $settings['smtp_username'];
            $mail->Password   = $this->decryptSmtpPassword($settings['smtp_password']);
            $mail->SMTPSecure = $settings['smtp_encryption'] === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

            $mail->setFrom(
                $settings['smtp_from_address'] ?: 'noreply@example.com',
                $settings['smtp_from_name'] ?: 'Precision Ink ERP'
            );
            $mail->addAddress($toEmail);
            $mail->Subject = 'Precision Ink ERP — SMTP Test';
            $mail->Body    = 'This is a test email from Precision Ink ERP. If you received this, your SMTP settings are configured correctly.';
            $mail->isHTML(false);
            $mail->send();

            $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function encryptSmtpPassword(string $plain): string
    {
        $config = require __DIR__ . '/../../config/config.php';
        $key = hash('sha256', $config['APP_KEY'] ?? 'default-key', true);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plain, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    private function decryptSmtpPassword(string $encrypted): string
    {
        if (empty($encrypted)) {
            return '';
        }
        $config = require __DIR__ . '/../../config/config.php';
        $key = hash('sha256', $config['APP_KEY'] ?? 'default-key', true);
        $decoded = base64_decode($encrypted);
        $parts = explode('::', $decoded, 2);
        if (count($parts) !== 2) {
            return '';
        }
        [$iv, $cipherText] = $parts;
        $decrypted = openssl_decrypt($cipherText, 'aes-256-cbc', $key, 0, $iv);
        return $decrypted !== false ? $decrypted : '';
    }

    // ── Email Templates ───────────────────────────────────────────────

    private function getMergeFields(): array
    {
        return [
            'invoice' => ['{customer_name}', '{invoice_number}', '{order_number}', '{amount_due}', '{due_date}', '{rep_name}'],
            'order_acknowledgment' => ['{customer_name}', '{order_number}', '{promised_ship_date}', '{rep_name}'],
            'quote' => ['{customer_name}', '{quote_number}', '{expiration_date}', '{rep_name}'],
            'coa' => ['{customer_name}', '{batch_number}', '{item_description}', '{rep_name}'],
            'purchase_order' => ['{supplier_name}', '{po_number}', '{expected_delivery}', '{rep_name}'],
            'scar' => ['{supplier_name}', '{scar_number}', '{issue_description}', '{due_date}'],
            'credit_memo' => ['{customer_name}', '{rma_number}', '{credit_amount}'],
        ];
    }

    public function emailTemplates(): void
    {
        $this->requireAdmin();

        $this->ensureDefaultEmailTemplates();

        $templates = $this->db()->query(
            'SELECT id, template_type, subject, active FROM email_templates ORDER BY template_type ASC'
        )->fetchAll();

        $signature = $this->getSettings(['email_signature']);

        $this->renderView('settings/_layout', [
            'title'     => 'Email Templates',
            'section'   => 'email-templates',
            'content'   => 'settings/email_templates',
            'templates' => $templates,
            'signature' => $signature['email_signature'] ?? '',
        ]);
    }

    public function emailTemplateEdit(string $type): void
    {
        $this->requireAdmin();

        $this->ensureDefaultEmailTemplates();

        $stmt = $this->db()->prepare('SELECT * FROM email_templates WHERE template_type = ?');
        $stmt->execute([$type]);
        $template = $stmt->fetch();

        if (!$template) {
            $this->toast('Template not found.', 'error');
            $this->redirect('/settings/email-templates');
            return;
        }

        $mergeFields = $this->getMergeFields();

        $this->renderView('settings/_layout', [
            'title'       => 'Edit Email Template',
            'section'     => 'email-templates',
            'content'     => 'settings/email_template_edit',
            'template'    => $template,
            'mergeFields' => $mergeFields[$type] ?? [],
        ]);
    }

    public function saveEmailTemplate(string $type): void
    {
        $this->requireAdmin();

        $stmt = $this->db()->prepare('SELECT * FROM email_templates WHERE template_type = ?');
        $stmt->execute([$type]);
        $old = $stmt->fetch();

        if (!$old) {
            $this->toast('Template not found.', 'error');
            $this->redirect('/settings/email-templates');
            return;
        }

        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body'] ?? '';
        $active = !empty($_POST['active']) ? 1 : 0;

        $this->db()->prepare(
            'UPDATE email_templates SET subject = ?, body = ?, active = ? WHERE template_type = ?'
        )->execute([$subject, $body, $active, $type]);

        $this->auditLog('UPDATE', 'email_templates', $old['id'],
            ['subject' => $old['subject'], 'active' => $old['active']],
            ['subject' => $subject, 'active' => $active]
        );
        $this->toast('Email template saved successfully.');
        $this->redirect('/settings/email-templates');
    }

    public function saveEmailSignature(): void
    {
        $this->requireAdmin();

        $old = $this->getSettings(['email_signature']);
        $signature = $_POST['email_signature'] ?? '';

        $this->saveSettings(['email_signature' => $signature]);
        $this->auditLog('UPDATE', 'settings_email_signature', 0, $old, ['email_signature' => $signature]);
        $this->toast('Email signature saved successfully.');
        $this->redirect('/settings/email-templates');
    }

    private function ensureDefaultEmailTemplates(): void
    {
        $count = (int) $this->db()->query('SELECT COUNT(*) FROM email_templates')->fetchColumn();
        if ($count === 0) {
            // Insert defaults — they should already exist from migration, but ensure
            $defaults = [
                ['invoice', 'Invoice {invoice_number} from Precision Ink', '<p>Dear {customer_name},</p><p>Please find attached invoice <strong>{invoice_number}</strong> for order {order_number}.</p><p>Amount due: <strong>{amount_due}</strong><br>Due date: {due_date}</p><p>Thank you for your business.</p><p>Best regards,<br>{rep_name}</p>'],
                ['order_acknowledgment', 'Order Acknowledgment — {order_number}', '<p>Dear {customer_name},</p><p>Thank you for your order <strong>{order_number}</strong>.</p><p>Promised ship date: <strong>{promised_ship_date}</strong></p><p>Best regards,<br>{rep_name}</p>'],
                ['quote', 'Quote {quote_number} from Precision Ink', '<p>Dear {customer_name},</p><p>Please find attached quote <strong>{quote_number}</strong>.</p><p>This quote is valid until <strong>{expiration_date}</strong>.</p><p>Best regards,<br>{rep_name}</p>'],
                ['coa', 'Certificate of Analysis — {batch_number}', '<p>Dear {customer_name},</p><p>Please find attached the Certificate of Analysis for batch <strong>{batch_number}</strong> — {item_description}.</p><p>Best regards,<br>{rep_name}</p>'],
                ['purchase_order', 'Purchase Order {po_number} — Precision Ink', '<p>Dear {supplier_name},</p><p>Please find attached purchase order <strong>{po_number}</strong>.</p><p>Expected delivery: <strong>{expected_delivery}</strong></p><p>Best regards,<br>{rep_name}</p>'],
                ['scar', 'Supplier Corrective Action Request — {scar_number}', '<p>Dear {supplier_name},</p><p>SCAR <strong>{scar_number}</strong> has been issued regarding:</p><p>{issue_description}</p><p>Please respond by <strong>{due_date}</strong>.</p>'],
                ['credit_memo', 'Credit Memo for RMA {rma_number}', '<p>Dear {customer_name},</p><p>A credit memo has been issued for RMA <strong>{rma_number}</strong>.</p><p>Credit amount: <strong>{credit_amount}</strong></p>'],
            ];
            $stmt = $this->db()->prepare('INSERT IGNORE INTO email_templates (template_type, subject, body) VALUES (?, ?, ?)');
            foreach ($defaults as $d) {
                $stmt->execute($d);
            }
        }
    }

    // ── Password Policy ───────────────────────────────────────────────

    public function passwordPolicy(): void
    {
        $this->requireAdmin();

        $keys = [
            'password_min_length', 'password_require_uppercase',
            'password_require_number', 'password_require_special',
            'password_expiry_days', 'password_history_count',
        ];
        $settings = $this->getSettings($keys);

        // Apply defaults
        if ($settings['password_min_length'] === '') $settings['password_min_length'] = '10';
        if ($settings['password_require_uppercase'] === '') $settings['password_require_uppercase'] = '1';
        if ($settings['password_require_number'] === '') $settings['password_require_number'] = '1';
        if ($settings['password_require_special'] === '') $settings['password_require_special'] = '1';
        if ($settings['password_expiry_days'] === '') $settings['password_expiry_days'] = '0';
        if ($settings['password_history_count'] === '') $settings['password_history_count'] = '5';

        $this->renderView('settings/_layout', [
            'title'    => 'Password Policy',
            'section'  => 'password-policy',
            'content'  => 'settings/password_policy',
            'settings' => $settings,
        ]);
    }

    public function savePasswordPolicy(): void
    {
        $this->requireAdmin();

        $keys = [
            'password_min_length', 'password_require_uppercase',
            'password_require_number', 'password_require_special',
            'password_expiry_days', 'password_history_count',
        ];

        $old = $this->getSettings($keys);
        $new = [];
        $errors = [];

        $minLength = (int) ($_POST['password_min_length'] ?? 10);
        if ($minLength < 6 || $minLength > 128) {
            $errors[] = 'Minimum length must be between 6 and 128.';
        }
        $new['password_min_length'] = (string) $minLength;

        $new['password_require_uppercase'] = !empty($_POST['password_require_uppercase']) ? '1' : '0';
        $new['password_require_number'] = !empty($_POST['password_require_number']) ? '1' : '0';
        $new['password_require_special'] = !empty($_POST['password_require_special']) ? '1' : '0';

        $expiryDays = (int) ($_POST['password_expiry_days'] ?? 0);
        if ($expiryDays < 0) $expiryDays = 0;
        $new['password_expiry_days'] = (string) $expiryDays;

        $historyCount = (int) ($_POST['password_history_count'] ?? 5);
        if ($historyCount < 0) $historyCount = 0;
        $new['password_history_count'] = (string) $historyCount;

        if ($errors) {
            $this->toast(implode(' ', $errors), 'error');
            $this->redirect('/settings/password-policy');
            return;
        }

        $this->saveSettings($new);
        $this->auditLog('UPDATE', 'settings_password_policy', 0, $old, $new);
        $this->toast('Password policy saved successfully.');
        $this->redirect('/settings/password-policy');
    }

    // ── Notification Settings ─────────────────────────────────────────

    private function getAlertTypeLabels(): array
    {
        return [
            'low_stock'                   => 'Low Stock Alert',
            'batch_overdue'               => 'Batch Overdue',
            'credit_limit_warning'        => 'Credit Limit Warning',
            'credit_override_used'        => 'Credit Override Used',
            'cost_change'                 => 'Cost Change Alert',
            'quote_expiring'              => 'Quote Expiring Soon',
            'orders_open_too_long'        => 'Orders Open Too Long',
            'orders_approaching_ship_date'=> 'Orders Approaching Ship Date',
            'task_overdue'                => 'Task Overdue',
            'batch_cost_variance'         => 'Batch Cost Variance',
        ];
    }

    public function notifications(): void
    {
        $this->requireAdmin();

        $this->ensureDefaultNotifications();

        $alerts = $this->db()->query(
            'SELECT * FROM notifications_config ORDER BY id ASC'
        )->fetchAll();

        $labels = $this->getAlertTypeLabels();

        $this->renderView('settings/_layout', [
            'title'  => 'Notification Settings',
            'section'=> 'notifications',
            'content'=> 'settings/notifications',
            'alerts' => $alerts,
            'labels' => $labels,
        ]);
    }

    public function saveNotifications(): void
    {
        $this->requireAdmin();

        $alerts = $this->db()->query('SELECT * FROM notifications_config ORDER BY id ASC')->fetchAll();
        $oldData = [];
        $newData = [];

        foreach ($alerts as $alert) {
            $id = $alert['id'];
            $type = $alert['alert_type'];
            $oldData[$type] = $alert;

            $enabled = !empty($_POST["enabled_{$id}"]) ? 1 : 0;
            $recipients = trim($_POST["recipients_{$id}"] ?? '');
            $threshold = $_POST["threshold_{$id}"] ?? null;
            if ($threshold !== null && $threshold !== '') {
                $threshold = (float) $threshold;
            } else {
                $threshold = $alert['threshold_value'];
            }

            $this->db()->prepare(
                'UPDATE notifications_config SET enabled = ?, recipients = ?, threshold_value = ? WHERE id = ?'
            )->execute([$enabled, $recipients ?: null, $threshold, $id]);

            $newData[$type] = [
                'enabled' => $enabled,
                'recipients' => $recipients,
                'threshold_value' => $threshold,
            ];
        }

        $this->auditLog('UPDATE', 'notifications_config', 0, $oldData, $newData);
        $this->toast('Notification settings saved successfully.');
        $this->redirect('/settings/notifications');
    }

    private function ensureDefaultNotifications(): void
    {
        $count = (int) $this->db()->query('SELECT COUNT(*) FROM notifications_config')->fetchColumn();
        if ($count === 0) {
            $defaults = [
                ['low_stock', 1, null, null, null],
                ['batch_overdue', 1, null, null, null],
                ['credit_limit_warning', 1, null, null, null],
                ['credit_override_used', 1, null, null, null],
                ['cost_change', 1, null, 5.00, 'percent'],
                ['quote_expiring', 1, null, 3.00, 'days'],
                ['orders_open_too_long', 1, null, 14.00, 'days'],
                ['orders_approaching_ship_date', 1, null, 3.00, 'days'],
                ['task_overdue', 1, null, null, null],
                ['batch_cost_variance', 1, null, 10.00, 'percent'],
            ];
            $stmt = $this->db()->prepare(
                'INSERT IGNORE INTO notifications_config (alert_type, enabled, recipients, threshold_value, threshold_unit) VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($defaults as $d) {
                $stmt->execute($d);
            }
        }
    }

    // ── Scheduled Report Delivery ─────────────────────────────────────

    public function scheduledReports(): void
    {
        $this->requireAdmin();

        $reports = $this->db()->query(
            'SELECT * FROM scheduled_reports ORDER BY report_name ASC'
        )->fetchAll();

        $this->renderView('settings/_layout', [
            'title'   => 'Scheduled Reports',
            'section' => 'scheduled-reports',
            'content' => 'settings/scheduled_reports',
            'reports' => $reports,
        ]);
    }

    public function saveScheduledReport(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $reportName = trim($_POST['report_name'] ?? '');
        $scheduleType = $_POST['schedule_type'] ?? 'DAILY';
        $scheduleDay = ($_POST['schedule_day'] ?? '') !== '' ? (int) $_POST['schedule_day'] : null;
        $scheduleTime = trim($_POST['schedule_time'] ?? '06:00');
        $outputFormat = $_POST['output_format'] ?? 'CSV';
        $recipients = trim($_POST['recipients'] ?? '');
        $active = !empty($_POST['active']) ? 1 : 0;

        $errors = [];
        if ($reportName === '') $errors[] = 'Report name is required.';
        if ($recipients === '') $errors[] = 'Recipients are required.';
        if (!in_array($scheduleType, ['DAILY', 'WEEKLY', 'MONTHLY'], true)) $errors[] = 'Invalid schedule type.';
        if (!in_array($outputFormat, ['CSV', 'PDF'], true)) $errors[] = 'Invalid output format.';
        if (!preg_match('/^\d{2}:\d{2}$/', $scheduleTime)) $errors[] = 'Invalid time format (HH:MM).';

        if ($errors) {
            $this->toast(implode(' ', $errors), 'error');
            $this->redirect('/settings/scheduled-reports');
            return;
        }

        if ($id > 0) {
            // Update
            $stmt = $this->db()->prepare('SELECT * FROM scheduled_reports WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();

            $this->db()->prepare(
                'UPDATE scheduled_reports SET report_name=?, schedule_type=?, schedule_day=?, schedule_time=?, output_format=?, recipients=?, active=? WHERE id=?'
            )->execute([$reportName, $scheduleType, $scheduleDay, $scheduleTime, $outputFormat, $recipients, $active, $id]);

            $this->auditLog('UPDATE', 'scheduled_reports', $id, $old, [
                'report_name' => $reportName, 'schedule_type' => $scheduleType,
                'schedule_day' => $scheduleDay, 'schedule_time' => $scheduleTime,
                'output_format' => $outputFormat, 'recipients' => $recipients, 'active' => $active,
            ]);
            $this->toast('Scheduled report updated.');
        } else {
            // Insert
            $this->db()->prepare(
                'INSERT INTO scheduled_reports (report_name, schedule_type, schedule_day, schedule_time, output_format, recipients, active) VALUES (?,?,?,?,?,?,?)'
            )->execute([$reportName, $scheduleType, $scheduleDay, $scheduleTime, $outputFormat, $recipients, $active]);
            $newId = (int) $this->db()->lastInsertId();
            $this->auditLog('CREATE', 'scheduled_reports', $newId);
            $this->toast('Scheduled report added.');
        }

        $this->redirect('/settings/scheduled-reports');
    }

    public function deleteScheduledReport(string $id): void
    {
        $this->requireAdmin();
        $id = (int) $id;

        $stmt = $this->db()->prepare('SELECT * FROM scheduled_reports WHERE id = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetch();

        if ($old) {
            $this->db()->prepare('DELETE FROM scheduled_reports WHERE id = ?')->execute([$id]);
            $this->auditLog('DELETE', 'scheduled_reports', $id, $old);
            $this->toast('Scheduled report deleted.');
        }

        $this->redirect('/settings/scheduled-reports');
    }

    // ── Notification Log ──────────────────────────────────────────

    private function getAlertTypeLabelMap(): array
    {
        return [
            'low_stock'                    => 'Low Stock Alert',
            'batch_overdue'                => 'Batch Overdue',
            'credit_limit_warning'         => 'Credit Limit Warning',
            'credit_override_used'         => 'Credit Override Used',
            'cost_change'                  => 'Cost Change Alert',
            'quote_expiring'               => 'Quote Expiring Soon',
            'orders_open_too_long'         => 'Orders Open Too Long',
            'orders_approaching_ship_date' => 'Orders Approaching Ship Date',
            'task_overdue'                 => 'Task Overdue',
            'batch_cost_variance'          => 'Batch Cost Variance',
        ];
    }

    public function notificationLog(): void
    {
        $this->requireAdmin();

        $where = [];
        $params = [];

        $alertType  = trim($_GET['alert_type'] ?? '');
        $dateFrom   = trim($_GET['date_from'] ?? '');
        $dateTo     = trim($_GET['date_to'] ?? '');
        $status     = trim($_GET['status'] ?? '');

        if ($alertType !== '') {
            $where[] = 'n.alert_type = ?';
            $params[] = $alertType;
        }
        if ($dateFrom !== '') {
            $where[] = 'n.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = 'n.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($status !== '') {
            $where[] = 'n.status = ?';
            $params[] = $status;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM notification_log n {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = 50;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db()->prepare(
            "SELECT n.* FROM notification_log n {$whereClause} ORDER BY n.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Get distinct alert types for dropdown
        $alertTypes = $this->db()->query('SELECT DISTINCT alert_type FROM notification_log ORDER BY alert_type')->fetchAll(\PDO::FETCH_COLUMN);

        $this->renderView('settings/_layout', [
            'title'       => 'Notification Log',
            'section'     => 'notification-log',
            'content'     => 'settings/notification_log',
            'rows'        => $rows,
            'alertTypes'  => $alertTypes,
            'labelMap'    => $this->getAlertTypeLabelMap(),
            'filters'     => compact('alertType', 'dateFrom', 'dateTo', 'status'),
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
        ]);
    }

    // ── Audit Log ─────────────────────────────────────────────────

    public function auditLog(): void
    {
        $this->requireAdmin();

        $where = [];
        $params = [];

        $userId     = trim($_GET['user_id'] ?? '');
        $dateFrom   = trim($_GET['date_from'] ?? '');
        $dateTo     = trim($_GET['date_to'] ?? '');
        $module     = trim($_GET['module'] ?? '');
        $actionType = trim($_GET['action_type'] ?? '');
        $recordId   = trim($_GET['record_id'] ?? '');

        if ($userId !== '') {
            $where[] = 'a.user_id = ?';
            $params[] = (int) $userId;
        }
        if ($dateFrom !== '') {
            $where[] = 'a.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = 'a.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($module !== '') {
            $where[] = 'a.module = ?';
            $params[] = $module;
        }
        if ($actionType !== '') {
            $where[] = 'a.action_type = ?';
            $params[] = $actionType;
        }
        if ($recordId !== '') {
            $where[] = 'a.record_id = ?';
            $params[] = (int) $recordId;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM audit_log a {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = 50;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db()->prepare(
            "SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON a.user_id = u.id {$whereClause} ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $users = $this->db()->query('SELECT id, username FROM users ORDER BY username')->fetchAll();
        $modules = $this->db()->query('SELECT DISTINCT module FROM audit_log ORDER BY module')->fetchAll(\PDO::FETCH_COLUMN);

        $this->renderView('settings/_layout', [
            'title'       => 'Audit Log',
            'section'     => 'audit-log',
            'content'     => 'settings/audit_log',
            'rows'        => $rows,
            'users'       => $users,
            'modules'     => $modules,
            'filters'     => compact('userId', 'dateFrom', 'dateTo', 'module', 'actionType', 'recordId'),
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
        ]);
    }

    public function auditLogExport(): void
    {
        $this->requireAdmin();

        $where = [];
        $params = [];

        $userId     = trim($_GET['user_id'] ?? '');
        $dateFrom   = trim($_GET['date_from'] ?? '');
        $dateTo     = trim($_GET['date_to'] ?? '');
        $module     = trim($_GET['module'] ?? '');
        $actionType = trim($_GET['action_type'] ?? '');
        $recordId   = trim($_GET['record_id'] ?? '');

        if ($userId !== '') {
            $where[] = 'a.user_id = ?';
            $params[] = (int) $userId;
        }
        if ($dateFrom !== '') {
            $where[] = 'a.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = 'a.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($module !== '') {
            $where[] = 'a.module = ?';
            $params[] = $module;
        }
        if ($actionType !== '') {
            $where[] = 'a.action_type = ?';
            $params[] = $actionType;
        }
        if ($recordId !== '') {
            $where[] = 'a.record_id = ?';
            $params[] = (int) $recordId;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db()->prepare(
            "SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON a.user_id = u.id {$whereClause} ORDER BY a.created_at DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['timestamp', 'username', 'action_type', 'module', 'record_id', 'field_changes', 'ip_address']);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['created_at'],
                $row['username'] ?? 'System',
                $row['action_type'],
                $row['module'],
                $row['record_id'],
                $row['field_changes'] ?? '',
                $row['ip_address'],
            ]);
        }

        fclose($out);
        $this->auditLog('CREATE', 'audit_log_export', 0, null, ['exported_rows' => count($rows)]);
        exit;
    }

    // ── Outbound Email Log ────────────────────────────────────────

    public function emailLog(): void
    {
        $this->requireAdmin();

        $where = [];
        $params = [];

        $recipient    = trim($_GET['recipient'] ?? '');
        $documentType = trim($_GET['document_type'] ?? '');
        $dateFrom     = trim($_GET['date_from'] ?? '');
        $dateTo       = trim($_GET['date_to'] ?? '');
        $status       = trim($_GET['status'] ?? '');

        if ($recipient !== '') {
            $where[] = 'e.recipients LIKE ?';
            $params[] = '%' . $recipient . '%';
        }
        if ($documentType !== '') {
            $where[] = 'e.document_type = ?';
            $params[] = $documentType;
        }
        if ($dateFrom !== '') {
            $where[] = 'e.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = 'e.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($status !== '') {
            $where[] = 'e.status = ?';
            $params[] = $status;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM outbound_email_log e {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = 50;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db()->prepare(
            "SELECT e.*, u.username AS sent_by_name FROM outbound_email_log e LEFT JOIN users u ON e.sent_by = u.id {$whereClause} ORDER BY e.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $documentTypes = $this->db()->query('SELECT DISTINCT document_type FROM outbound_email_log ORDER BY document_type')->fetchAll(\PDO::FETCH_COLUMN);

        $this->renderView('settings/_layout', [
            'title'         => 'Outbound Email Log',
            'section'       => 'email-log',
            'content'       => 'settings/email_log',
            'rows'          => $rows,
            'documentTypes' => $documentTypes,
            'filters'       => compact('recipient', 'documentType', 'dateFrom', 'dateTo', 'status'),
            'page'          => $page,
            'totalPages'    => $totalPages,
            'total'         => $total,
        ]);
    }

    public function resendEmail(string $id): void
    {
        $this->requireAdmin();
        $id = (int) $id;

        $stmt = $this->db()->prepare('SELECT * FROM outbound_email_log WHERE id = ?');
        $stmt->execute([$id]);
        $original = $stmt->fetch();

        if (!$original) {
            $this->jsonResponse(['success' => false, 'error' => 'Email record not found.'], 404);
            return;
        }

        $recipients = trim($_POST['recipients'] ?? '');
        if ($recipients === '') {
            $this->jsonResponse(['success' => false, 'error' => 'Recipients are required.']);
            return;
        }

        $user = $this->currentUser();
        $newStatus = 'FAILED';
        $failureReason = null;

        try {
            // Attempt to send via EmailService
            $smtpSettings = $this->getSettings([
                'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                'smtp_from_address', 'smtp_from_name', 'smtp_encryption',
            ]);

            if (!empty($smtpSettings['smtp_host'])) {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $smtpSettings['smtp_host'];
                $mail->Port       = (int) ($smtpSettings['smtp_port'] ?: 587);
                $mail->SMTPAuth   = !empty($smtpSettings['smtp_username']);
                $mail->Username   = $smtpSettings['smtp_username'];
                $mail->Password   = $this->decryptSmtpPassword($smtpSettings['smtp_password']);
                $mail->SMTPSecure = $smtpSettings['smtp_encryption'] === 'ssl'
                    ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

                $mail->setFrom(
                    $smtpSettings['smtp_from_address'] ?: 'noreply@example.com',
                    $smtpSettings['smtp_from_name'] ?: 'Precision Ink ERP'
                );

                foreach (preg_split('/[\s,;]+/', $recipients) as $addr) {
                    $addr = trim($addr);
                    if ($addr !== '') {
                        $mail->addAddress($addr);
                    }
                }

                $mail->Subject = $original['subject'];
                $mail->Body    = 'Resent: ' . $original['subject'];
                $mail->isHTML(false);
                $mail->send();
                $newStatus = 'SENT';
            } else {
                $failureReason = 'SMTP not configured.';
            }
        } catch (\Exception $e) {
            $failureReason = $e->getMessage();
        }

        // Log as new row
        $this->db()->prepare(
            'INSERT INTO outbound_email_log (sent_by, document_type, reference_type, reference_id, recipients, subject, status, failure_reason, attachment_filename) VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $user['id'] ?? null,
            $original['document_type'],
            $original['reference_type'],
            $original['reference_id'],
            $recipients,
            $original['subject'],
            $newStatus,
            $failureReason,
            $original['attachment_filename'],
        ]);

        $this->jsonResponse([
            'success' => $newStatus === 'SENT',
            'error'   => $failureReason,
        ]);
    }

    // ── User Activity Dashboard ──────────────────────────────────

    public function userActivity(): void
    {
        $this->requireAdmin();

        $timeout = (int) ($this->getSettings(['session_timeout_minutes'])['session_timeout_minutes'] ?: 30);

        $stmt = $this->db()->prepare(
            'SELECT s.*, u.username, u.full_name
             FROM sessions s
             JOIN users u ON s.user_id = u.id
             WHERE s.last_activity > DATE_SUB(NOW(), INTERVAL ? MINUTE)
             ORDER BY s.last_activity DESC'
        );
        $stmt->execute([$timeout]);
        $activeSessions = $stmt->fetchAll();

        $loginHistory = $this->db()->query(
            'SELECT * FROM login_attempts ORDER BY attempted_at DESC LIMIT 20'
        )->fetchAll();

        $this->renderView('settings/_layout', [
            'title'          => 'User Activity',
            'section'        => 'user-activity',
            'content'        => 'settings/user_activity',
            'activeSessions' => $activeSessions,
            'loginHistory'   => $loginHistory,
            'timeout'        => $timeout,
        ]);
    }

    public function forceLogout(string $sessionId): void
    {
        $this->requireAdmin();
        $sessionId = (int) $sessionId;

        // Get session info before deleting
        $stmt = $this->db()->prepare(
            'SELECT s.*, u.username FROM sessions s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?'
        );
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            $this->toast('Session not found or already expired.', 'error');
            $this->redirect('/settings/user-activity');
            return;
        }

        $this->db()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$sessionId]);

        $currentUser = $this->currentUser();
        $this->auditLog('DELETE', 'sessions', $sessionId,
            ['user' => $session['username'], 'forced_logout_by' => $currentUser['username'] ?? 'admin'],
            null
        );

        $this->toast('User "' . ($session['username'] ?? 'unknown') . '" has been logged out.');
        $this->redirect('/settings/user-activity');
    }

    public function activeSessionsJson(): void
    {
        $this->requireAdmin();

        $timeout = (int) ($this->getSettings(['session_timeout_minutes'])['session_timeout_minutes'] ?: 30);

        $stmt = $this->db()->prepare(
            'SELECT s.*, u.username, u.full_name
             FROM sessions s
             JOIN users u ON s.user_id = u.id
             WHERE s.last_activity > DATE_SUB(NOW(), INTERVAL ? MINUTE)
             ORDER BY s.last_activity DESC'
        );
        $stmt->execute([$timeout]);
        $sessions = $stmt->fetchAll();

        $html = '';
        if (empty($sessions)) {
            $html = '<tr><td colspan="6" style="text-align:center;color:#999;">No active sessions.</td></tr>';
        } else {
            foreach ($sessions as $s) {
                $lastAct = strtotime($s['last_activity']);
                $diff = max(0, time() - $lastAct);
                if ($diff < 60) {
                    $ago = 'Just now';
                } elseif ($diff < 3600) {
                    $ago = (int)($diff / 60) . ' min ago';
                } else {
                    $ago = (int)($diff / 3600) . ' hr ago';
                }
                $username = htmlspecialchars($s['username']);
                $fullName = htmlspecialchars($s['full_name']);
                $started = htmlspecialchars($s['started_at']);
                $lastPage = htmlspecialchars($s['last_page'] ?? '—');
                $id = (int)$s['id'];
                $html .= "<tr>"
                    . "<td>{$username}</td>"
                    . "<td>{$fullName}</td>"
                    . "<td>{$started}</td>"
                    . "<td>{$ago}</td>"
                    . "<td>{$lastPage}</td>"
                    . "<td><form method=\"POST\" action=\"/settings/user-activity/force-logout/{$id}\" style=\"display:inline;\" onsubmit=\"return confirm('Force logout {$username}?');\">"
                    . "<button type=\"submit\" class=\"btn btn-danger btn-sm\">Force Logout</button></form></td>"
                    . "</tr>";
            }
        }

        $this->jsonResponse(['html' => $html]);
    }

    // ── Announcements ─────────────────────────────────────────────

    public function announcements(): void
    {
        $this->requireAdmin();

        $rows = $this->db()->query(
            'SELECT a.*, u.username AS created_by_name
             FROM announcements a
             LEFT JOIN users u ON a.created_by = u.id
             ORDER BY a.start_date DESC, a.id DESC'
        )->fetchAll();

        // Load target group names and IDs for non-target_all announcements
        foreach ($rows as &$row) {
            $row['target_groups'] = [];
            $row['_group_ids'] = [];
            if (!$row['target_all']) {
                $stmt = $this->db()->prepare(
                    'SELECT atg.group_id, g.name FROM announcement_target_groups atg
                     JOIN `groups` g ON atg.group_id = g.id
                     WHERE atg.announcement_id = ?
                     ORDER BY g.name'
                );
                $stmt->execute([$row['id']]);
                $grps = $stmt->fetchAll();
                $row['target_groups'] = array_column($grps, 'name');
                $row['_group_ids'] = array_map('intval', array_column($grps, 'group_id'));
            }
        }
        unset($row);

        $groups = $this->db()->query('SELECT id, name FROM `groups` WHERE active = 1 ORDER BY name')->fetchAll();

        $this->renderView('settings/_layout', [
            'title'  => 'Announcements',
            'section'=> 'announcements',
            'content'=> 'settings/announcements',
            'rows'   => $rows,
            'groups' => $groups,
        ]);
    }

    public function saveAnnouncement(): void
    {
        $this->requireAdmin();

        $id        = (int) ($_POST['id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $message   = $_POST['message'] ?? '';
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate   = trim($_POST['end_date'] ?? '') ?: null;
        $targetAll = !empty($_POST['target_all']) ? 1 : 0;
        $priority  = $_POST['priority'] ?? 'INFO';
        $active    = !empty($_POST['active']) ? 1 : 0;
        $groupIds  = $_POST['group_ids'] ?? [];

        if (!in_array($priority, ['INFO', 'WARNING', 'URGENT'], true)) {
            $priority = 'INFO';
        }

        $errors = [];
        if ($title === '') $errors[] = 'Title is required.';
        if ($startDate === '') $errors[] = 'Start date is required.';

        if ($errors) {
            $this->jsonResponse(['success' => false, 'error' => implode(' ', $errors)]);
            return;
        }

        $user = $this->currentUser();

        if ($id > 0) {
            // Update
            $stmt = $this->db()->prepare('SELECT * FROM announcements WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();

            $this->db()->prepare(
                'UPDATE announcements SET title=?, message=?, start_date=?, end_date=?, target_all=?, priority=?, active=? WHERE id=?'
            )->execute([$title, $message, $startDate, $endDate, $targetAll, $priority, $active, $id]);

            $this->auditLog('UPDATE', 'announcements', $id, $old, [
                'title' => $title, 'priority' => $priority, 'active' => $active,
            ]);
        } else {
            // Insert
            $this->db()->prepare(
                'INSERT INTO announcements (title, message, start_date, end_date, target_all, priority, active, created_by) VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$title, $message, $startDate, $endDate, $targetAll, $priority, $active, $user['id'] ?? null]);
            $id = (int) $this->db()->lastInsertId();
            $this->auditLog('CREATE', 'announcements', $id);
        }

        // Update target groups
        $this->db()->prepare('DELETE FROM announcement_target_groups WHERE announcement_id = ?')->execute([$id]);
        if (!$targetAll && !empty($groupIds)) {
            $stmt = $this->db()->prepare('INSERT INTO announcement_target_groups (announcement_id, group_id) VALUES (?, ?)');
            foreach ($groupIds as $gid) {
                $gid = (int) $gid;
                if ($gid > 0) {
                    $stmt->execute([$id, $gid]);
                }
            }
        }

        $this->jsonResponse(['success' => true, 'id' => $id]);
    }

    public function deleteAnnouncement(string $id): void
    {
        $this->requireAdmin();
        $id = (int) $id;

        $stmt = $this->db()->prepare('SELECT * FROM announcements WHERE id = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetch();

        if (!$old) {
            $this->jsonResponse(['success' => false, 'error' => 'Announcement not found.'], 404);
            return;
        }

        // Check if dismissals exist
        $dismissCount = (int) $this->db()->prepare('SELECT COUNT(*) FROM announcement_dismissals WHERE announcement_id = ?');
        $dismissCount->execute([$id]);
        $hasDismissals = (int) $dismissCount->fetchColumn() > 0;

        if ($hasDismissals) {
            // Soft delete
            $this->db()->prepare('UPDATE announcements SET active = 0 WHERE id = ?')->execute([$id]);
            $this->auditLog('UPDATE', 'announcements', $id, ['active' => 1], ['active' => 0]);
        } else {
            // Hard delete
            $this->db()->prepare('DELETE FROM announcement_target_groups WHERE announcement_id = ?')->execute([$id]);
            $this->db()->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
            $this->auditLog('DELETE', 'announcements', $id, $old);
        }

        $this->jsonResponse(['success' => true]);
    }

    public function dismissAnnouncement(string $id): void
    {
        $id = (int) $id;
        $user = $this->currentUser();
        if (!$user) {
            $this->jsonResponse(['success' => false, 'error' => 'Not authenticated.'], 401);
            return;
        }

        $this->db()->prepare(
            'INSERT IGNORE INTO announcement_dismissals (announcement_id, user_id) VALUES (?, ?)'
        )->execute([$id, $user['id']]);

        $this->jsonResponse(['success' => true]);
    }

    // ── Custom Fields Management ──────────────────────────────────

    private function getRecordTypes(): array
    {
        return [
            'items'           => 'Items',
            'customers'       => 'Customers',
            'suppliers'       => 'Suppliers',
            'contacts'        => 'Contacts',
            'sales_orders'    => 'Sales Orders',
            'purchase_orders' => 'Purchase Orders',
            'batch_tickets'   => 'Batch Tickets',
            'shipments'       => 'Shipments',
        ];
    }

    public function customFields(string $recordType = 'items'): void
    {
        $this->requireAdmin();

        $recordTypes = $this->getRecordTypes();
        if (!isset($recordTypes[$recordType])) {
            $recordType = 'items';
        }

        $stmt = $this->db()->prepare(
            'SELECT * FROM custom_field_definitions WHERE record_type = ? ORDER BY display_sequence ASC, id ASC'
        );
        $stmt->execute([$recordType]);
        $fields = $stmt->fetchAll();

        foreach ($fields as &$f) {
            if ($f['options']) {
                $f['options_raw'] = $f['options'];
                $f['options'] = json_decode($f['options'], true);
            } else {
                $f['options_raw'] = null;
                $f['options'] = [];
            }
        }
        unset($f);

        $this->renderView('settings/_layout', [
            'title'       => 'Custom Fields',
            'section'     => 'custom-fields',
            'content'     => 'settings/custom_fields',
            'fields'      => $fields,
            'recordType'  => $recordType,
            'recordTypes' => $recordTypes,
        ]);
    }

    public function saveCustomField(): void
    {
        $this->requireAdmin();

        $id              = (int) ($_POST['id'] ?? 0);
        $recordType      = trim($_POST['record_type'] ?? '');
        $label           = trim($_POST['label'] ?? '');
        $fieldType       = trim($_POST['field_type'] ?? 'TEXT');
        $optionsRaw      = trim($_POST['options'] ?? '');
        $isRequired      = !empty($_POST['is_required']) ? 1 : 0;
        $displaySequence = (int) ($_POST['display_sequence'] ?? 0);
        $helpText        = trim($_POST['help_text'] ?? '') ?: null;

        $validTypes = ['TEXT', 'NUMBER', 'DATE', 'YES_NO', 'DROPDOWN', 'MULTI_SELECT'];
        if (!in_array($fieldType, $validTypes, true)) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid field type.']);
            return;
        }

        $recordTypes = $this->getRecordTypes();
        if (!isset($recordTypes[$recordType])) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid record type.']);
            return;
        }

        if ($label === '') {
            $this->jsonResponse(['success' => false, 'error' => 'Label is required.']);
            return;
        }

        // Check label uniqueness within record type
        $uniqueStmt = $this->db()->prepare(
            'SELECT id FROM custom_field_definitions WHERE record_type = ? AND label = ? AND id != ?'
        );
        $uniqueStmt->execute([$recordType, $label, $id]);
        if ($uniqueStmt->fetch()) {
            $this->jsonResponse(['success' => false, 'error' => 'A field with this label already exists for this record type.']);
            return;
        }

        // Parse options for DROPDOWN / MULTI_SELECT
        $options = null;
        if (in_array($fieldType, ['DROPDOWN', 'MULTI_SELECT'], true) && $optionsRaw !== '') {
            $optionsList = array_filter(array_map('trim', preg_split('/[\r\n]+/', $optionsRaw)));
            $options = json_encode(array_values($optionsList));
        }

        if ($id > 0) {
            // Check if field_type is being changed and values exist
            $oldStmt = $this->db()->prepare('SELECT * FROM custom_field_definitions WHERE id = ?');
            $oldStmt->execute([$id]);
            $old = $oldStmt->fetch();

            if ($old && $old['field_type'] !== $fieldType) {
                $valCount = $this->db()->prepare(
                    'SELECT COUNT(*) FROM custom_field_values WHERE field_definition_id = ?'
                );
                $valCount->execute([$id]);
                if ((int) $valCount->fetchColumn() > 0) {
                    $this->jsonResponse([
                        'success' => false,
                        'error' => 'Cannot change field type — existing values are stored for this field.',
                    ]);
                    return;
                }
            }

            $this->db()->prepare(
                'UPDATE custom_field_definitions SET label=?, field_type=?, options=?, is_required=?, display_sequence=?, help_text=? WHERE id=?'
            )->execute([$label, $fieldType, $options, $isRequired, $displaySequence, $helpText, $id]);

            $this->auditLog('UPDATE', 'custom_field_definitions', $id, $old ?? [], [
                'label' => $label, 'field_type' => $fieldType, 'is_required' => $isRequired,
            ]);
        } else {
            $this->db()->prepare(
                'INSERT INTO custom_field_definitions (record_type, label, field_type, options, is_required, display_sequence, help_text) VALUES (?,?,?,?,?,?,?)'
            )->execute([$recordType, $label, $fieldType, $options, $isRequired, $displaySequence, $helpText]);
            $id = (int) $this->db()->lastInsertId();
            $this->auditLog('CREATE', 'custom_field_definitions', $id);
        }

        $this->jsonResponse(['success' => true, 'id' => $id]);
    }

    public function deactivateCustomField(string $id): void
    {
        $this->requireAdmin();
        $id = (int) $id;

        $stmt = $this->db()->prepare('SELECT * FROM custom_field_definitions WHERE id = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetch();

        if (!$old) {
            $this->jsonResponse(['success' => false, 'error' => 'Field not found.'], 404);
            return;
        }

        $newActive = $old['active'] ? 0 : 1;
        $this->db()->prepare('UPDATE custom_field_definitions SET active = ? WHERE id = ?')->execute([$newActive, $id]);
        $this->auditLog('UPDATE', 'custom_field_definitions', $id,
            ['active' => $old['active']],
            ['active' => $newActive]
        );

        $this->jsonResponse(['success' => true, 'active' => $newActive]);
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
