<?php
/**
 * Custom Fields section for view (read-only) forms.
 * Expects: $customFieldService (CustomFieldService), $cfRecordType (string), $cfRecordId (int)
 */
$customFields = $customFieldService->getValues($cfRecordType, $cfRecordId ?? 0);
if (!empty($customFields)):
?>
<div style="margin-top:24px; border-top:1px solid #e5e7eb; padding-top:16px;">
    <h3 style="font-size:13px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px;">Custom Fields</h3>
    <dl style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:12px;">
        <?php foreach ($customFields as $cf): ?>
            <div>
                <dt style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($cf['definition']['label']) ?></dt>
                <dd style="font-size:14px; font-weight:500;"><?= $customFieldService->renderFieldValueHtml($cf['definition'], $cf['value']) ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</div>
<?php endif; ?>
