<h1>Custom Fields</h1>

<!-- Record Type Tabs -->
<div class="tabs" style="display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:20px;">
    <?php foreach ($recordTypes as $key => $label): ?>
        <a href="/settings/custom-fields/<?= $key ?>"
           class="tab-link" style="padding:10px 18px; text-decoration:none; font-size:13px; font-weight:600; border-bottom:2px solid transparent; margin-bottom:-2px; color:<?= $recordType === $key ? '#2563eb' : '#6b7280' ?>; border-color:<?= $recordType === $key ? '#2563eb' : 'transparent' ?>;">
            <?= htmlspecialchars($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<div style="margin-bottom:16px;">
    <button type="button" class="btn btn-primary" onclick="openFieldModal()">+ Add Custom Field</button>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th style="width:60px;">Order</th>
            <th>Label</th>
            <th>Type</th>
            <th>Required</th>
            <th>Active</th>
            <th style="width:140px;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($fields)): ?>
            <tr><td colspan="6" style="text-align:center; color:#999;">No custom fields defined for <?= htmlspecialchars($recordTypes[$recordType]) ?>.</td></tr>
        <?php else: ?>
            <?php foreach ($fields as $f): ?>
                <?php
                $typeBadges = [
                    'TEXT' => 'info', 'NUMBER' => 'primary', 'DATE' => 'secondary',
                    'YES_NO' => 'success', 'DROPDOWN' => 'warning', 'MULTI_SELECT' => 'warning',
                ];
                $typeLabels = [
                    'TEXT' => 'Text', 'NUMBER' => 'Number', 'DATE' => 'Date',
                    'YES_NO' => 'Yes/No', 'DROPDOWN' => 'Dropdown', 'MULTI_SELECT' => 'Multi-select',
                ];
                ?>
                <tr>
                    <td><?= (int)$f['display_sequence'] ?></td>
                    <td>
                        <?= htmlspecialchars($f['label']) ?>
                        <?php if ($f['help_text']): ?>
                            <br><small style="color:#999;"><?= htmlspecialchars($f['help_text']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= $typeBadges[$f['field_type']] ?? 'secondary' ?>"><?= $typeLabels[$f['field_type']] ?? $f['field_type'] ?></span></td>
                    <td><?= $f['is_required'] ? '&#10003;' : '—' ?></td>
                    <td>
                        <?php if ($f['active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-secondary btn-sm"
                                onclick='openFieldModal(<?= htmlspecialchars(json_encode($f)) ?>)'>Edit</button>
                        <button type="button" class="btn <?= $f['active'] ? 'btn-danger' : 'btn-primary' ?> btn-sm"
                                onclick="toggleField(<?= $f['id'] ?>, <?= $f['active'] ? 'true' : 'false' ?>)"><?= $f['active'] ? 'Deactivate' : 'Activate' ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- Add/Edit Modal -->
<div id="cf-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:#fff; border-radius:8px; padding:24px; max-width:550px; width:95%; margin:5vh auto; box-shadow:0 4px 20px rgba(0,0,0,0.2); max-height:85vh; overflow-y:auto;">
        <h2 id="cf-modal-title" style="margin-top:0;">New Custom Field</h2>
        <form id="cf-form" onsubmit="saveField(event)">
            <input type="hidden" name="id" id="cf-id" value="0">
            <input type="hidden" name="record_type" value="<?= htmlspecialchars($recordType) ?>">

            <div class="form-group" style="margin-bottom:12px;">
                <label>Label <span style="color:red;">*</span></label>
                <input type="text" name="label" id="cf-label" class="form-control" required maxlength="100" style="width:100%;">
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label>Field Type</label>
                <select name="field_type" id="cf-field-type" class="form-control" onchange="toggleOptions()">
                    <option value="TEXT">Text</option>
                    <option value="NUMBER">Number</option>
                    <option value="DATE">Date</option>
                    <option value="YES_NO">Yes / No</option>
                    <option value="DROPDOWN">Dropdown</option>
                    <option value="MULTI_SELECT">Multi-select</option>
                </select>
            </div>

            <div class="form-group" id="cf-options-wrap" style="margin-bottom:12px; display:none;">
                <label>Options <small style="color:#999;">(one per line)</small></label>
                <textarea name="options" id="cf-options" class="form-control" rows="4" style="width:100%;"></textarea>
            </div>

            <div style="display:flex; gap:12px; margin-bottom:12px;">
                <div class="form-group" style="flex:1;">
                    <label>Display Order</label>
                    <input type="number" name="display_sequence" id="cf-sequence" class="form-control" value="0" min="0">
                </div>
                <div class="form-group" style="flex:1; display:flex; align-items:end;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" name="is_required" id="cf-required" value="1">
                        Required
                    </label>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label>Help Text <small style="color:#999;">(optional)</small></label>
                <input type="text" name="help_text" id="cf-help" class="form-control" maxlength="255" style="width:100%;">
            </div>

            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeFieldModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="cf-save-btn">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openFieldModal(data) {
    document.getElementById('cf-id').value = data ? data.id : 0;
    document.getElementById('cf-label').value = data ? data.label : '';
    document.getElementById('cf-field-type').value = data ? data.field_type : 'TEXT';
    document.getElementById('cf-required').checked = data ? !!parseInt(data.is_required) : false;
    document.getElementById('cf-sequence').value = data ? data.display_sequence : 0;
    document.getElementById('cf-help').value = data ? (data.help_text || '') : '';
    document.getElementById('cf-modal-title').textContent = data ? 'Edit Custom Field' : 'New Custom Field';

    // Options: convert array back to newline-separated
    var opts = '';
    if (data && data.options && Array.isArray(data.options)) {
        opts = data.options.join('\n');
    }
    document.getElementById('cf-options').value = opts;

    toggleOptions();
    document.getElementById('cf-modal').style.display = 'block';
}

function closeFieldModal() {
    document.getElementById('cf-modal').style.display = 'none';
}

function toggleOptions() {
    var type = document.getElementById('cf-field-type').value;
    document.getElementById('cf-options-wrap').style.display =
        (type === 'DROPDOWN' || type === 'MULTI_SELECT') ? 'block' : 'none';
}

function saveField(e) {
    e.preventDefault();
    var btn = document.getElementById('cf-save-btn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    var form = new FormData(document.getElementById('cf-form'));

    fetch('/settings/custom-fields/save', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(function(err) { alert('Request failed: ' + err.message); })
        .finally(function() { btn.disabled = false; btn.textContent = 'Save'; });
}

function toggleField(id, currentlyActive) {
    var action = currentlyActive ? 'Deactivate' : 'Activate';
    if (!confirm(action + ' this custom field?')) return;

    fetch('/settings/custom-fields/deactivate/' + id, { method: 'POST' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(function(err) { alert('Request failed: ' + err.message); });
}

document.getElementById('cf-modal').addEventListener('click', function(e) {
    if (e.target === this) closeFieldModal();
});
</script>
