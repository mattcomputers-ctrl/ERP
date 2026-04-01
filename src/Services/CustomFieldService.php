<?php

namespace App\Services;

/**
 * Manages user-defined custom fields and their values.
 */
class CustomFieldService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get all active field definitions for a record type, ordered by display_sequence.
     */
    public function getDefinitions(string $recordType): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM custom_field_definitions
             WHERE record_type = ? AND active = 1
             ORDER BY display_sequence ASC, id ASC'
        );
        $stmt->execute([$recordType]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if ($row['options']) {
                $row['options'] = json_decode($row['options'], true);
            }
        }
        return $rows;
    }

    /**
     * Get current values for a specific record, returns [{definition, value}] pairs.
     */
    public function getValues(string $recordType, int $recordId): array
    {
        $definitions = $this->getDefinitions($recordType);
        if (empty($definitions)) {
            return [];
        }

        $defIds = array_column($definitions, 'id');
        $placeholders = implode(',', array_fill(0, count($defIds), '?'));

        $stmt = $this->db->prepare(
            "SELECT field_definition_id, value FROM custom_field_values
             WHERE record_type = ? AND record_id = ? AND field_definition_id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$recordType, $recordId], $defIds));
        $values = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $values[$row['field_definition_id']] = $row['value'];
        }

        $result = [];
        foreach ($definitions as $def) {
            $result[] = [
                'definition' => $def,
                'value' => $values[$def['id']] ?? null,
            ];
        }
        return $result;
    }

    /**
     * Validate and save custom field values from a form POST.
     * $postData should contain custom_fields[field_id] => value
     * Returns array of error messages (empty = success).
     */
    public function saveValues(string $recordType, int $recordId, array $postData): array
    {
        $errors = [];
        $fieldData = $postData['custom_fields'] ?? [];
        $definitions = $this->getDefinitions($recordType);

        foreach ($definitions as $def) {
            $value = $fieldData[$def['id']] ?? null;

            // For MULTI_SELECT, value comes as array — encode as JSON
            if ($def['field_type'] === 'MULTI_SELECT' && is_array($value)) {
                $value = json_encode(array_filter($value));
            }

            $fieldErrors = $this->validate($def, $value);
            foreach ($fieldErrors as $error) {
                $errors[] = $def['label'] . ': ' . $error;
            }

            if (empty($fieldErrors)) {
                $stmt = $this->db->prepare(
                    'INSERT INTO custom_field_values (record_type, record_id, field_definition_id, value, updated_at)
                     VALUES (?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
                );
                $stmt->execute([$recordType, $recordId, $def['id'], $value]);
            }
        }

        return $errors;
    }

    /**
     * Validate a single value against its field definition.
     * Returns array of error strings (empty = valid).
     */
    public function validate(array $definition, mixed $value): array
    {
        $errors = [];
        $isEmpty = ($value === null || $value === '' || $value === '[]');

        if ($definition['is_required'] && $isEmpty) {
            $errors[] = 'This field is required.';
            return $errors;
        }

        if ($isEmpty) {
            return $errors;
        }

        switch ($definition['field_type']) {
            case 'NUMBER':
                if (!is_numeric($value)) {
                    $errors[] = 'Must be a number.';
                }
                break;
            case 'DATE':
                if (!\DateTime::createFromFormat('Y-m-d', $value)) {
                    $errors[] = 'Must be a valid date (YYYY-MM-DD).';
                }
                break;
            case 'YES_NO':
                if (!in_array($value, ['0', '1', 'true', 'false', true, false], true)) {
                    $errors[] = 'Must be yes or no.';
                }
                break;
        }

        return $errors;
    }

    /**
     * Returns HTML string for the field input — used in edit forms.
     */
    public function renderFieldHtml(array $definition, mixed $currentValue): string
    {
        $id = 'cf_' . $definition['id'];
        $name = 'custom_fields[' . $definition['id'] . ']';
        $required = $definition['is_required'] ? 'required' : '';
        $helpText = $definition['help_text']
            ? '<small style="color:#666; display:block; margin-top:2px;">' . htmlspecialchars($definition['help_text']) . '</small>'
            : '';

        switch ($definition['field_type']) {
            case 'TEXT':
                $val = htmlspecialchars((string)($currentValue ?? ''));
                return "<input type=\"text\" id=\"$id\" name=\"$name\" value=\"$val\" $required class=\"form-control\">$helpText";

            case 'NUMBER':
                $val = htmlspecialchars((string)($currentValue ?? ''));
                return "<input type=\"number\" step=\"any\" id=\"$id\" name=\"$name\" value=\"$val\" $required class=\"form-control\">$helpText";

            case 'DATE':
                $val = htmlspecialchars((string)($currentValue ?? ''));
                return "<input type=\"date\" id=\"$id\" name=\"$name\" value=\"$val\" $required class=\"form-control\">$helpText";

            case 'YES_NO':
                $checked = $currentValue ? 'checked' : '';
                return "<label style=\"display:flex; align-items:center; gap:6px;\"><input type=\"hidden\" name=\"$name\" value=\"0\">"
                    . "<input type=\"checkbox\" id=\"$id\" name=\"$name\" value=\"1\" $checked> Yes</label>$helpText";

            case 'DROPDOWN':
                $options = $definition['options'] ?? [];
                $html = "<select id=\"$id\" name=\"$name\" $required class=\"form-control\">";
                $html .= '<option value="">— Select —</option>';
                foreach ($options as $opt) {
                    $sel = ((string)$currentValue === (string)$opt) ? 'selected' : '';
                    $escaped = htmlspecialchars($opt);
                    $html .= "<option value=\"$escaped\" $sel>$escaped</option>";
                }
                $html .= "</select>$helpText";
                return $html;

            case 'MULTI_SELECT':
                $options = $definition['options'] ?? [];
                $selected = $currentValue ? json_decode($currentValue, true) : [];
                $selected = is_array($selected) ? $selected : [];
                $html = "<select id=\"$id\" name=\"{$name}[]\" multiple $required class=\"form-control\" size=\"4\">";
                foreach ($options as $opt) {
                    $sel = in_array($opt, $selected) ? 'selected' : '';
                    $escaped = htmlspecialchars($opt);
                    $html .= "<option value=\"$escaped\" $sel>$escaped</option>";
                }
                $html .= "</select>$helpText";
                return $html;

            default:
                return '';
        }
    }

    /**
     * Returns HTML for displaying a value in read-only mode.
     */
    public function renderFieldValueHtml(array $definition, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '<span style="color:#999;">—</span>';
        }
        switch ($definition['field_type']) {
            case 'YES_NO':
                return $value ? '<span style="color:#166534;">Yes</span>' : '<span style="color:#991b1b;">No</span>';
            case 'MULTI_SELECT':
                $values = json_decode($value, true);
                if (!is_array($values)) {
                    return htmlspecialchars($value);
                }
                return implode(', ', array_map('htmlspecialchars', $values));
            default:
                return htmlspecialchars((string)$value);
        }
    }

    /**
     * Returns CSV column headers for custom fields of a record type.
     */
    public function getExportColumns(string $recordType): array
    {
        $definitions = $this->getDefinitions($recordType);
        return array_map(fn($d) => 'cf_' . $d['label'], $definitions);
    }

    /**
     * Returns CSV values for one record's custom fields.
     */
    public function getExportValues(string $recordType, int $recordId): array
    {
        $fieldValues = $this->getValues($recordType, $recordId);
        return array_map(function ($item) {
            $value = $item['value'];
            if ($item['definition']['field_type'] === 'MULTI_SELECT' && $value) {
                $arr = json_decode($value, true);
                return is_array($arr) ? implode('; ', $arr) : $value;
            }
            return $value ?? '';
        }, $fieldValues);
    }
}
