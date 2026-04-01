<?php
/**
 * Placeholder view template — custom fields section.
 * This file will be expanded when the full module is built.
 * For now it provides the custom fields section that renders
 * when fields are defined for this record type.
 */
?>
<!-- Custom Fields Section -->
<?php
$cfRecordType = 'shipments';
$cfRecordId = $record['id'] ?? 0;
if (isset($customFieldService)) {
    require __DIR__ . '/../partials/custom_fields_view.php';
}
?>
