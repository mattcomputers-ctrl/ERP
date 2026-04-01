<?php
$isEdit = !empty($prototype);
$itemTypes = [
    'RAW_MATERIAL'  => 'Raw Material',
    'FINISHED_GOOD' => 'Finished Good',
    'INTERMEDIATE'  => 'Intermediate',
    'RESALE'        => 'Resale',
    'SERVICE'       => 'Service',
];
?>

<h1><?= $isEdit ? 'Edit Item Prototype' : 'New Item Prototype' ?></h1>

<form method="POST" action="/settings/item-prototypes" class="settings-form" id="protoForm">
    <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'add' ?>">
    <input type="hidden" name="id" value="<?= $prototype['id'] ?? '' ?>">

    <div class="form-section">
        <h2>Basic Information</h2>
        <div class="form-group">
            <label for="proto_name">Name *</label>
            <input type="text" id="proto_name" name="name" required maxlength="100"
                   value="<?= htmlspecialchars($prototype['name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="proto_desc">Description</label>
            <textarea id="proto_desc" name="description" rows="3"><?= htmlspecialchars($prototype['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="proto_item_type">Item Type *</label>
                <select id="proto_item_type" name="item_type" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($itemTypes as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($prototype['item_type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="proto_gl_group">GL Group *</label>
                <select id="proto_gl_group" name="gl_group" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($itemTypes as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($prototype['gl_group'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="proto_uom">Unit of Measure</label>
                <select id="proto_uom" name="uom_id">
                    <option value="">-- None --</option>
                    <?php foreach ($uoms as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($prototype['uom_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['abbreviation']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="proto_shelf">Shelf Life (days)</label>
                <input type="number" id="proto_shelf" name="shelf_life_days" min="0" step="1"
                       value="<?= htmlspecialchars($prototype['shelf_life_days'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="requires_inspection" value="1"
                    <?= !empty($prototype['requires_inspection']) ? 'checked' : '' ?>>
                Requires Inspection
            </label>
        </div>
    </div>

    <!-- Pack Extensions Section -->
    <div class="form-section">
        <h2>Pack Extensions</h2>
        <p class="help-text" style="margin-bottom: 12px;">Define packaging configurations for this prototype.</p>

        <div id="packExtensions">
            <?php if (!empty($extensions)): ?>
                <?php foreach ($extensions as $i => $ext): ?>
                    <div class="pack-row inline-form" style="margin-bottom: 8px;">
                        <input type="hidden" name="pack_id[]" value="<?= $ext['id'] ?>">
                        <div class="inline-field">
                            <label>Name</label>
                            <input type="text" name="pack_name[]" value="<?= htmlspecialchars($ext['name']) ?>" required>
                        </div>
                        <div class="inline-field">
                            <label>Net Weight</label>
                            <input type="number" name="pack_net_weight[]" value="<?= htmlspecialchars($ext['net_weight']) ?>" step="0.0001" min="0" required>
                        </div>
                        <div class="inline-field">
                            <label>Tare Weight</label>
                            <input type="number" name="pack_tare_weight[]" value="<?= htmlspecialchars($ext['tare_weight']) ?>" step="0.0001" min="0" required>
                        </div>
                        <div class="inline-actions">
                            <button type="button" class="btn btn-sm btn-warning" onclick="this.closest('.pack-row').remove()">Remove</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <button type="button" class="btn btn-sm btn-secondary" onclick="addPackRow()" style="margin-top: 8px;">+ Add Pack Extension</button>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Prototype</button>
        <a href="/settings/item-prototypes" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script>
function addPackRow() {
    var container = document.getElementById('packExtensions');
    var row = document.createElement('div');
    row.className = 'pack-row inline-form';
    row.style.marginBottom = '8px';
    row.innerHTML = '<input type="hidden" name="pack_id[]" value="0">' +
        '<div class="inline-field"><label>Name</label><input type="text" name="pack_name[]" required></div>' +
        '<div class="inline-field"><label>Net Weight</label><input type="number" name="pack_net_weight[]" step="0.0001" min="0" required></div>' +
        '<div class="inline-field"><label>Tare Weight</label><input type="number" name="pack_tare_weight[]" step="0.0001" min="0" required></div>' +
        '<div class="inline-actions"><button type="button" class="btn btn-sm btn-warning" onclick="this.closest(\'.pack-row\').remove()">Remove</button></div>';
    container.appendChild(row);
    row.querySelector('input[type="text"]').focus();
}
</script>
