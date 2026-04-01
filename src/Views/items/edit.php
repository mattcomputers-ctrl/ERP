<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'edit' ? 'Edit Item' : 'New Item' ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
</head>
<body>
    <header class="app-header">
        <div class="header-left">
            <a href="/" class="app-logo">Precision Ink ERP</a>
        </div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?>
                <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span>
            <?php endif; ?>
        </div>
    </header>

    <div style="max-width:800px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast">
                <?= htmlspecialchars($_SESSION['toast']['message']) ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">
                <?= $mode === 'edit' ? 'Edit Item: ' . htmlspecialchars($item['item_code'] ?? '') : 'New Item' ?>
            </h1>
            <a href="/items" class="btn btn-secondary">&larr; Back to Items</a>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="toast toast-error" style="margin-bottom:16px;">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= $mode === 'edit' ? '/items/' . $item['id'] . '/edit' : '/items/create' ?>">
            <!-- Prototype Loader -->
            <?php if (!empty($prototypes) && $mode === 'create'): ?>
            <div class="form-section" style="margin-bottom:16px; padding:12px; background:#eff6ff; border-radius:6px;">
                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Load Prototype</label>
                <select id="prototypeSelect" class="form-input" style="font-size:13px; padding:4px 8px;">
                    <option value="">— Select a prototype —</option>
                    <?php foreach ($prototypes as $proto): ?>
                    <option value="<?= $proto['id'] ?>"
                            data-item-type="<?= htmlspecialchars($proto['item_type']) ?>"
                            data-gl-group="<?= htmlspecialchars($proto['gl_group']) ?>"
                            data-uom-id="<?= $proto['uom_id'] ?>"
                            data-shelf-life="<?= $proto['shelf_life_days'] ?? '' ?>"
                            data-inspection="<?= $proto['requires_inspection'] ?>">
                        <?= htmlspecialchars($proto['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">
                            Item Code <span style="color:red;">*</span>
                        </label>
                        <input type="text" name="item_code" value="<?= htmlspecialchars($item['item_code'] ?? '') ?>"
                               required class="form-input" style="width:100%; text-transform:uppercase;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">
                            Description <span style="color:red;">*</span>
                        </label>
                        <input type="text" name="description" value="<?= htmlspecialchars($item['description'] ?? '') ?>"
                               required class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">
                            Item Type <span style="color:red;">*</span>
                        </label>
                        <select name="item_type" id="itemType" required class="form-input" style="width:100%;">
                            <?php foreach (['RAW_MATERIAL' => 'Raw Material', 'FINISHED_GOOD' => 'Finished Good', 'INTERMEDIATE' => 'Intermediate', 'RESALE' => 'Resale', 'SERVICE' => 'Service'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($item['item_type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">
                            GL Group <span style="color:red;">*</span>
                        </label>
                        <select name="gl_group" id="glGroup" required class="form-input" style="width:100%;">
                            <?php foreach (['RAW_MATERIAL' => 'Raw Material', 'FINISHED_GOOD' => 'Finished Good', 'INTERMEDIATE' => 'Intermediate', 'RESALE' => 'Resale', 'SERVICE' => 'Service'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($item['gl_group'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">
                            Unit of Measure <span style="color:red;">*</span>
                        </label>
                        <select name="uom_id" id="uomId" required class="form-input" style="width:100%;">
                            <option value="">— Select —</option>
                            <?php foreach ($uoms as $uom): ?>
                            <option value="<?= $uom['id'] ?>" <?= ($item['uom_id'] ?? 0) == $uom['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($uom['abbreviation']) ?> — <?= htmlspecialchars($uom['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Unit Cost</label>
                        <input type="number" step="0.0001" name="unit_cost" value="<?= htmlspecialchars($item['unit_cost'] ?? '0') ?>"
                               class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Sale Price</label>
                        <input type="number" step="0.0001" name="sale_price" value="<?= htmlspecialchars($item['sale_price'] ?? '0') ?>"
                               class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Reorder Min</label>
                        <input type="number" step="0.0001" name="reorder_min" value="<?= htmlspecialchars($item['reorder_min'] ?? '') ?>"
                               class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Reorder Max</label>
                        <input type="number" step="0.0001" name="reorder_max" value="<?= htmlspecialchars($item['reorder_max'] ?? '') ?>"
                               class="form-input" style="width:100%;" id="reorderMax">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Shelf Life (days)</label>
                        <input type="number" name="shelf_life_days" id="shelfLifeDays" value="<?= htmlspecialchars($item['shelf_life_days'] ?? '') ?>"
                               class="form-input" style="width:100%;">
                    </div>
                </div>

                <div style="margin-top:12px; display:flex; gap:24px; flex-wrap:wrap;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="requires_inspection" id="requiresInspection" value="1"
                               <?= !empty($item['requires_inspection']) ? 'checked' : '' ?> class="form-checkbox">
                        Require incoming inspection for received lots
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;" id="sdsOnFileWrap">
                        <input type="checkbox" name="sds_on_file" value="1"
                               <?= !empty($item['sds_on_file']) ? 'checked' : '' ?> class="form-checkbox">
                        SDS on file
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="active" value="1"
                               <?= ($item === null || !empty($item['active'])) ? 'checked' : '' ?> class="form-checkbox">
                        Active
                    </label>
                </div>

                <div style="margin-top:12px;" id="sdsDateWrap">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">SDS Last Received</label>
                    <input type="date" name="sds_last_received" value="<?= htmlspecialchars($item['sds_last_received'] ?? '') ?>"
                           class="form-input" style="width:200px;">
                </div>

                <div style="margin-top:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Notes</label>
                    <textarea name="notes" rows="3" class="form-input" style="width:100%;"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Custom Fields -->
            <?php
            $cfRecordType = 'items';
            $cfRecordId = $item['id'] ?? 0;
            if (isset($customFieldService)) {
                require __DIR__ . '/../partials/custom_fields_edit.php';
            }
            ?>

            <div style="display:flex; gap:8px; margin-top:16px;">
                <button type="submit" class="btn btn-primary"><?= $mode === 'edit' ? 'Save Changes' : 'Create Item' ?></button>
                <a href="/items" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    // Auto-dismiss toast
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);

    // GL group defaults to item type when changed (only on create)
    var itemType = document.getElementById('itemType');
    var glGroup = document.getElementById('glGroup');
    <?php if ($mode === 'create'): ?>
    itemType.addEventListener('change', function() {
        glGroup.value = this.value;
        toggleSdsFields();
    });
    <?php endif; ?>

    // Show/hide SDS fields based on item type
    function toggleSdsFields() {
        var isRaw = itemType.value === 'RAW_MATERIAL';
        document.getElementById('sdsOnFileWrap').style.display = isRaw ? '' : 'none';
        document.getElementById('sdsDateWrap').style.display = isRaw ? '' : 'none';
    }
    toggleSdsFields();
    itemType.addEventListener('change', toggleSdsFields);

    // Prototype loader
    var protoSelect = document.getElementById('prototypeSelect');
    if (protoSelect) {
        protoSelect.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            if (!opt.value) return;
            itemType.value = opt.dataset.itemType;
            glGroup.value = opt.dataset.glGroup;
            document.getElementById('uomId').value = opt.dataset.uomId;
            var shelf = opt.dataset.shelfLife;
            document.getElementById('shelfLifeDays').value = shelf || '';
            document.getElementById('requiresInspection').checked = opt.dataset.inspection === '1';
            toggleSdsFields();
        });
    }
    </script>
</body>
</html>
