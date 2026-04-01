<?php
/**
 * Custom Fields section for edit forms.
 * Expects: $customFieldService (CustomFieldService), $cfRecordType (string), $cfRecordId (int)
 */
$customFields = $customFieldService->getValues($cfRecordType, $cfRecordId ?? 0);
if (!empty($customFields)):
?>
<div style="margin-top:24px; border-top:1px solid #e5e7eb; padding-top:16px;">
    <h3 style="font-size:13px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px;">Custom Fields</h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:12px;">
        <?php foreach ($customFields as $cf): ?>
            <div class="form-group">
                <label>
                    <?= htmlspecialchars($cf['definition']['label']) ?>
                    <?php if ($cf['definition']['is_required']): ?><span style="color:red;">*</span><?php endif; ?>
                </label>
                <?= $customFieldService->renderFieldHtml($cf['definition'], $cf['value']) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
