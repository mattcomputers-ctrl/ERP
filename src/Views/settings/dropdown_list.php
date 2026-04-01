<h1><?= htmlspecialchars($dropdownTitle) ?></h1>

<?php
$fields = $config['fields'];
$fieldNames = array_keys($fields);
?>

<!-- Add New Item Form -->
<div class="dropdown-add-form" id="addForm" style="display:none;">
    <form method="POST" action="<?= htmlspecialchars($route) ?>" class="inline-form">
        <input type="hidden" name="action" value="add">
        <?php foreach ($fields as $fname => $fdef): ?>
            <div class="inline-field">
                <label for="add_<?= $fname ?>"><?= htmlspecialchars($fdef['label']) ?></label>
                <?php if (($fdef['type'] ?? 'text') === 'select'): ?>
                    <select id="add_<?= $fname ?>" name="<?= $fname ?>" <?= ($fdef['required'] ?? false) ? 'required' : '' ?>>
                        <option value="">— Select —</option>
                        <?php foreach ($fdef['options'] as $optVal => $optLabel): ?>
                            <option value="<?= htmlspecialchars($optVal) ?>"><?= htmlspecialchars($optLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="<?= $fdef['type'] ?? 'text' ?>" id="add_<?= $fname ?>" name="<?= $fname ?>"
                           <?= ($fdef['required'] ?? false) ? 'required' : '' ?>
                           <?= ($fdef['type'] ?? 'text') === 'number' ? 'min="0" step="1"' : '' ?>>
                <?php endif; ?>
                <?php if (!empty($fdef['help'])): ?>
                    <span class="help-text"><?= htmlspecialchars($fdef['help']) ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="inline-actions">
            <button type="submit" class="btn btn-primary btn-sm">Add</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAddForm()">Cancel</button>
        </div>
    </form>
</div>

<div class="table-toolbar">
    <button class="btn btn-primary" id="addBtn" onclick="toggleAddForm()">+ Add New</button>
</div>

<!-- Items Table -->
<table class="data-table">
    <thead>
        <tr>
            <?php foreach ($fields as $fname => $fdef): ?>
                <th><?= htmlspecialchars($fdef['label']) ?></th>
            <?php endforeach; ?>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="<?= count($fields) + 2 ?>" class="empty-state">No items yet. Click "Add New" to create one.</td></tr>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <tr class="<?= $item['active'] ? '' : 'inactive-row' ?>" id="row-<?= $item['id'] ?>">
                    <!-- Display Mode -->
                    <td class="display-cell" data-row="<?= $item['id'] ?>">
                        <?php $first = true; foreach ($fields as $fname => $fdef): ?>
                            <?php if (!$first): ?></td><td class="display-cell" data-row="<?= $item['id'] ?>"><?php endif; ?>
                            <?php
                            $val = $item[$fname] ?? '';
                            if (($fdef['type'] ?? '') === 'select' && isset($fdef['options'][$val])) {
                                echo htmlspecialchars($fdef['options'][$val]);
                            } else {
                                echo htmlspecialchars($val);
                            }
                            ?>
                            <?php $first = false; ?>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <span class="badge <?= $item['active'] ? 'badge-active' : 'badge-inactive' ?>">
                            <?= $item['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="actions-cell">
                        <button class="btn btn-sm btn-secondary" onclick="startEdit(<?= $item['id'] ?>)">Edit</button>
                        <form method="POST" action="<?= htmlspecialchars($route) ?>" class="inline-toggle">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $item['active'] ? 'btn-warning' : 'btn-success' ?>">
                                <?= $item['active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <!-- Edit Mode (hidden by default) -->
                <tr class="edit-row" id="edit-<?= $item['id'] ?>" style="display:none;">
                    <td colspan="<?= count($fields) + 2 ?>">
                        <form method="POST" action="<?= htmlspecialchars($route) ?>" class="inline-form">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <?php foreach ($fields as $fname => $fdef): ?>
                                <div class="inline-field">
                                    <label><?= htmlspecialchars($fdef['label']) ?></label>
                                    <?php if (($fdef['type'] ?? 'text') === 'select'): ?>
                                        <select name="<?= $fname ?>" <?= ($fdef['required'] ?? false) ? 'required' : '' ?>>
                                            <?php foreach ($fdef['options'] as $optVal => $optLabel): ?>
                                                <option value="<?= htmlspecialchars($optVal) ?>"
                                                    <?= ($item[$fname] ?? '') === $optVal ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($optLabel) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="<?= $fdef['type'] ?? 'text' ?>" name="<?= $fname ?>"
                                               value="<?= htmlspecialchars($item[$fname] ?? '') ?>"
                                               <?= ($fdef['required'] ?? false) ? 'required' : '' ?>
                                               <?= ($fdef['type'] ?? 'text') === 'number' ? 'min="0" step="1"' : '' ?>>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <div class="inline-actions">
                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEdit(<?= $item['id'] ?>)">Cancel</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<script>
function toggleAddForm() {
    const form = document.getElementById('addForm');
    const btn = document.getElementById('addBtn');
    if (form.style.display === 'none') {
        form.style.display = 'block';
        btn.style.display = 'none';
        form.querySelector('input[type="text"], select')?.focus();
    } else {
        form.style.display = 'none';
        btn.style.display = '';
    }
}

function startEdit(id) {
    // Hide all other edit rows first
    document.querySelectorAll('.edit-row').forEach(r => r.style.display = 'none');
    document.querySelectorAll('tr[id^="row-"]').forEach(r => r.style.display = '');

    document.getElementById('row-' + id).style.display = 'none';
    document.getElementById('edit-' + id).style.display = '';
    document.getElementById('edit-' + id).querySelector('input[type="text"], select')?.focus();
}

function cancelEdit(id) {
    document.getElementById('row-' + id).style.display = '';
    document.getElementById('edit-' + id).style.display = 'none';
}
</script>
