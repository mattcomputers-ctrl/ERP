<div style="margin-bottom:16px;">
    <a href="/settings/qc-tests" style="color:#2563eb;">&larr; Back to QC Tests</a>
</div>

<h2 style="margin-bottom:16px;"><?= $test ? 'Edit QC Test' : 'New QC Test' ?></h2>

<form method="POST" action="/settings/qc-tests/save" style="max-width:600px;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <input type="hidden" name="id" value="<?= $test['id'] ?? 0 ?>">
    <input type="hidden" name="action" value="save">

    <div class="form-group" style="margin-bottom:12px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">Test Name <span style="color:red;">*</span></label>
        <input type="text" name="test_name" value="<?= htmlspecialchars($test['test_name'] ?? '') ?>"
               required style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;" placeholder="e.g. Viscosity, pH, Tack, Color">
    </div>

    <div class="form-group" style="margin-bottom:12px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">Test Type <span style="color:red;">*</span></label>
        <?php $typeDisabled = ($test && $assignedCount > 0); ?>
        <select name="test_type" id="testType" <?= $typeDisabled ? 'disabled' : '' ?>
                onchange="toggleRangeFields()"
                style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
            <option value="PASS_FAIL" <?= ($test['test_type'] ?? '') === 'PASS_FAIL' ? 'selected' : '' ?>>Pass / Fail</option>
            <option value="NUMERIC_RANGE" <?= ($test['test_type'] ?? '') === 'NUMERIC_RANGE' ? 'selected' : '' ?>>Numeric Range</option>
        </select>
        <?php if ($typeDisabled): ?>
            <input type="hidden" name="test_type" value="<?= htmlspecialchars($test['test_type']) ?>">
            <small style="color:#6b7280;">Type cannot be changed — <?= $assignedCount ?> item(s) use this test.</small>
        <?php endif; ?>
    </div>

    <div id="rangeFields" style="<?= ($test['test_type'] ?? '') !== 'NUMERIC_RANGE' ? 'display:none;' : '' ?>margin-bottom:12px;">
        <div style="display:flex;gap:12px;">
            <div style="flex:1;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Default Min Value</label>
                <input type="number" step="any" name="default_min_value"
                       value="<?= $test['default_min_value'] ?? '' ?>"
                       style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
            </div>
            <div style="flex:1;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Default Max Value</label>
                <input type="number" step="any" name="default_max_value"
                       value="<?= $test['default_max_value'] ?? '' ?>"
                       style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
            </div>
        </div>
    </div>

    <div class="form-group" style="margin-bottom:12px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">UOM</label>
        <input type="text" name="uom" value="<?= htmlspecialchars($test['uom'] ?? '') ?>"
               style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;" placeholder="e.g. cP, pH, %, Krebs Units">
        <small style="color:#6b7280;">Unit of measure displayed on QC entry forms. Leave blank for unitless.</small>
    </div>

    <div class="form-group" style="margin-bottom:12px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">Description</label>
        <textarea name="description" rows="3" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;"
                  placeholder="Help text shown on QC entry forms"><?= htmlspecialchars($test['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group" style="margin-bottom:12px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">Display Sequence</label>
        <input type="number" name="display_sequence" value="<?= (int)($test['display_sequence'] ?? 0) ?>"
               style="width:120px;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
    </div>

    <div class="form-group" style="margin-bottom:16px;">
        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
            <input type="checkbox" name="active" value="1"
                   <?= (!$test || $test['active']) ? 'checked' : '' ?>>
            Active
        </label>
    </div>

    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><?= $test ? 'Save Changes' : 'Create Test' ?></button>
        <a href="/settings/qc-tests" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script>
function toggleRangeFields() {
    var sel = document.getElementById('testType');
    document.getElementById('rangeFields').style.display = sel.value === 'NUMERIC_RANGE' ? '' : 'none';
}
</script>
