<h1>Batch Ticket Templates</h1>

<?php if (empty($items)): ?>
    <div class="form-section">
        <p style="text-align: center; color: #6b7280; padding: 24px;">
            No templates yet &mdash; templates are created from batch ticket records.
        </p>
    </div>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Item</th>
                <th>Target Qty</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr class="<?= $item['active'] ? '' : 'inactive-row' ?>" id="tpl-row-<?= $item['id'] ?>">
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= htmlspecialchars($item['item_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars(number_format((float)$item['target_quantity'], 2)) ?></td>
                    <td>
                        <span class="badge <?= $item['active'] ? 'badge-active' : 'badge-inactive' ?>">
                            <?= $item['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="actions-cell">
                        <button class="btn btn-sm btn-secondary" onclick="editTemplate(<?= $item['id'] ?>)">Edit</button>
                        <form method="POST" action="/settings/batch-templates" class="inline-toggle"
                              onsubmit="return confirm('Delete this template?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-warning">Delete</button>
                        </form>
                    </td>
                </tr>
                <!-- Edit Row -->
                <tr id="tpl-edit-<?= $item['id'] ?>" style="display:none;">
                    <td colspan="5">
                        <form method="POST" action="/settings/batch-templates" class="inline-form">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <div class="inline-field" style="flex:2;">
                                <label>Name</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required>
                            </div>
                            <div class="inline-field" style="flex:3;">
                                <label>Notes</label>
                                <input type="text" name="notes" value="<?= htmlspecialchars($item['notes'] ?? '') ?>">
                            </div>
                            <div class="inline-actions">
                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="cancelTemplateEdit(<?= $item['id'] ?>)">Cancel</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="help-text" style="margin-top: 12px;">
        Templates are created from batch ticket records. You can only edit the name and notes here.
    </p>
<?php endif; ?>

<script>
function editTemplate(id) {
    document.querySelectorAll('[id^="tpl-edit-"]').forEach(r => r.style.display = 'none');
    document.querySelectorAll('[id^="tpl-row-"]').forEach(r => r.style.display = '');
    document.getElementById('tpl-row-' + id).style.display = 'none';
    document.getElementById('tpl-edit-' + id).style.display = '';
}

function cancelTemplateEdit(id) {
    document.getElementById('tpl-row-' + id).style.display = '';
    document.getElementById('tpl-edit-' + id).style.display = 'none';
}
</script>
