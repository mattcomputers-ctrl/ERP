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
