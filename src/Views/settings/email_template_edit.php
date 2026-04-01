<?php
$typeLabels = [
    'invoice' => 'Invoice',
    'order_acknowledgment' => 'Order Acknowledgment',
    'quote' => 'Quote',
    'coa' => 'Certificate of Analysis',
    'purchase_order' => 'Purchase Order',
    'scar' => 'Supplier Corrective Action',
    'credit_memo' => 'Credit Memo',
];
$typeLabel = $typeLabels[$template['template_type']] ?? ucwords(str_replace('_', ' ', $template['template_type']));
?>

<h1>Edit Template: <?= htmlspecialchars($typeLabel) ?></h1>

<form method="POST" action="/settings/email-templates/save/<?= htmlspecialchars($template['template_type']) ?>" class="settings-form" id="template-form">

    <div class="form-section">
        <h2>Subject</h2>
        <div class="form-group">
            <input type="text" id="template-subject" name="subject"
                   value="<?= htmlspecialchars($template['subject'] ?? '') ?>" maxlength="255">
        </div>
        <?php if (!empty($mergeFields)): ?>
            <div class="merge-chips" data-target="template-subject">
                <span class="merge-label">Insert merge field:</span>
                <?php foreach ($mergeFields as $field): ?>
                    <button type="button" class="merge-chip" data-field="<?= htmlspecialchars($field) ?>"><?= htmlspecialchars($field) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-section">
        <h2>Body</h2>
        <div class="rich-editor-wrap">
            <div class="rich-toolbar">
                <button type="button" onclick="document.execCommand('bold')" title="Bold"><strong>B</strong></button>
                <button type="button" onclick="document.execCommand('italic')" title="Italic"><em>I</em></button>
                <button type="button" onclick="document.execCommand('insertUnorderedList')" title="Bullet List">&#8226; List</button>
            </div>
            <div class="rich-editor" contenteditable="true" id="template-body"><?= $template['body'] ?? '' ?></div>
            <input type="hidden" name="body" id="template-body-hidden">
        </div>
        <?php if (!empty($mergeFields)): ?>
            <div class="merge-chips" data-target="template-body">
                <span class="merge-label">Insert merge field:</span>
                <?php foreach ($mergeFields as $field): ?>
                    <button type="button" class="merge-chip" data-field="<?= htmlspecialchars($field) ?>"><?= htmlspecialchars($field) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-section">
        <h2>Status</h2>
        <label class="checkbox-label">
            <input type="checkbox" name="active" value="1" <?= $template['active'] ? 'checked' : '' ?>>
            Active — this template will be used for outbound emails
        </label>
    </div>

    <div class="form-actions" style="display:flex; gap:12px;">
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('template-body-hidden').value = document.getElementById('template-body').innerHTML;">Save Template</button>
        <a href="/settings/email-templates" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script>
// Merge field chip insertion
document.querySelectorAll('.merge-chips').forEach(function(container) {
    var targetId = container.getAttribute('data-target');
    container.querySelectorAll('.merge-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            var field = this.getAttribute('data-field');
            var target = document.getElementById(targetId);
            if (target.tagName === 'INPUT') {
                // Insert at cursor in text input
                var start = target.selectionStart || target.value.length;
                var end = target.selectionEnd || target.value.length;
                target.value = target.value.substring(0, start) + field + target.value.substring(end);
                target.focus();
                target.setSelectionRange(start + field.length, start + field.length);
            } else if (target.getAttribute('contenteditable') === 'true') {
                // Insert at cursor in contenteditable
                target.focus();
                var sel = window.getSelection();
                if (sel.rangeCount) {
                    var range = sel.getRangeAt(0);
                    range.deleteContents();
                    range.insertNode(document.createTextNode(field));
                    range.collapse(false);
                } else {
                    target.innerHTML += field;
                }
            }
        });
    });
});

// Sync contenteditable to hidden input on form submit
document.getElementById('template-form').addEventListener('submit', function() {
    document.getElementById('template-body-hidden').value = document.getElementById('template-body').innerHTML;
});
</script>
