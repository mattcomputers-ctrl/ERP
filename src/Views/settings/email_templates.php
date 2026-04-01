<h1>Email Templates</h1>

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
?>

<div class="form-section">
    <h2>Templates</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Template Type</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($templates)): ?>
                <tr><td colspan="4" class="empty-state">No email templates found.</td></tr>
            <?php else: ?>
                <?php foreach ($templates as $t): ?>
                    <tr class="<?= $t['active'] ? '' : 'inactive-row' ?>">
                        <td><?= htmlspecialchars($typeLabels[$t['template_type']] ?? ucwords(str_replace('_', ' ', $t['template_type']))) ?></td>
                        <td><?= htmlspecialchars(mb_strimwidth($t['subject'], 0, 60, '...')) ?></td>
                        <td>
                            <span class="badge <?= $t['active'] ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $t['active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <a href="/settings/email-templates/edit/<?= htmlspecialchars($t['template_type']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="form-section" style="margin-top:24px;">
    <h2>Company Email Signature</h2>
    <p class="help-text" style="margin-bottom:12px;">This signature is appended to all outbound emails.</p>
    <form method="POST" action="/settings/email-templates/signature">
        <div class="rich-editor-wrap">
            <div class="rich-toolbar">
                <button type="button" onclick="document.execCommand('bold')" title="Bold"><strong>B</strong></button>
                <button type="button" onclick="document.execCommand('italic')" title="Italic"><em>I</em></button>
                <button type="button" onclick="document.execCommand('insertUnorderedList')" title="Bullet List">&#8226; List</button>
            </div>
            <div class="rich-editor" contenteditable="true" id="signature-editor"><?= $signature ?? '' ?></div>
            <input type="hidden" name="email_signature" id="signature-hidden">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('signature-hidden').value = document.getElementById('signature-editor').innerHTML;">Save Signature</button>
        </div>
    </form>
</div>
