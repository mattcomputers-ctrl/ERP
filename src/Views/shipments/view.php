<?php
/**
 * Placeholder view template — shipment record view.
 * This file will be expanded when the full module is built.
 * For now it provides the custom fields and email history sections.
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

<!-- Email History Section -->
<?php
$emailReferenceType = 'shipment';
$emailReferenceId = $record['id'] ?? 0;
require __DIR__ . '/../partials/email_history_tab.php';
?>
