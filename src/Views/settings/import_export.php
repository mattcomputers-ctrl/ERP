<h1>Import / Export</h1>

<style>
.ie-tabs { display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:20px; }
.ie-tab { padding:10px 20px; cursor:pointer; font-weight:600; font-size:13px; border-bottom:2px solid transparent; margin-bottom:-2px; color:#6b7280; background:none; border-top:none; border-left:none; border-right:none; }
.ie-tab.active { color:#2563eb; border-bottom-color:#2563eb; }
.ie-panel { display:none; }
.ie-panel.active { display:block; }
.sub-tabs { display:flex; gap:0; margin-bottom:16px; background:#f3f4f6; border-radius:6px; padding:4px; }
.sub-tab { padding:8px 16px; cursor:pointer; font-size:12px; font-weight:600; border:none; background:none; border-radius:4px; color:#6b7280; }
.sub-tab.active { background:#fff; color:#1a1a2e; box-shadow:0 1px 2px rgba(0,0,0,0.1); }
.import-entity { display:none; }
.import-entity.active { display:block; }
.dry-run-table { width:100%; border-collapse:collapse; margin-top:12px; font-size:13px; }
.dry-run-table th, .dry-run-table td { padding:6px 10px; border:1px solid #e5e7eb; text-align:left; }
.dry-run-table .row-valid { background:#f0fdf4; }
.dry-run-table .row-warning { background:#fffbeb; }
.dry-run-table .row-error { background:#fef2f2; }
.export-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:12px; }
.export-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px; display:flex; justify-content:space-between; align-items:center; }
.export-card h4 { margin:0; font-size:14px; }
</style>

<!-- Main Tabs -->
<div class="ie-tabs">
    <button class="ie-tab active" onclick="switchMainTab('import', this)">Import</button>
    <button class="ie-tab" onclick="switchMainTab('export', this)">Export</button>
</div>

<!-- Import Panel -->
<div class="ie-panel active" id="panel-import">
    <div class="sub-tabs" id="import-sub-tabs">
        <button class="sub-tab active" onclick="switchImportTab('items', this)">Items</button>
        <button class="sub-tab" onclick="switchImportTab('customers', this)">Customers</button>
        <button class="sub-tab" onclick="switchImportTab('suppliers', this)">Suppliers</button>
        <button class="sub-tab" onclick="switchImportTab('opening-inventory', this)">Opening Inventory</button>
        <button class="sub-tab" onclick="switchImportTab('customer-pricing', this)">Customer Pricing</button>
        <button class="sub-tab" onclick="switchImportTab('approved-vendors', this)">Approved Vendor List</button>
    </div>

    <?php
    $entities = [
        'items'             => 'Items',
        'customers'         => 'Customers',
        'suppliers'         => 'Suppliers',
        'opening-inventory' => 'Opening Inventory',
        'customer-pricing'  => 'Customer Pricing',
        'approved-vendors'  => 'Approved Vendor List',
    ];
    $first = true;
    foreach ($entities as $key => $label):
    ?>
    <div class="import-entity <?= $first ? 'active' : '' ?>" id="import-<?= $key ?>">
        <h3 style="margin-bottom:12px;">Import <?= htmlspecialchars($label) ?></h3>

        <div style="margin-bottom:16px;">
            <a href="/settings/import/template/<?= $key ?>" class="btn btn-secondary">Download CSV Template</a>
        </div>

        <div style="margin-bottom:12px;">
            <label style="font-weight:600; display:block; margin-bottom:4px;">Upload CSV File</label>
            <input type="file" accept=".csv" id="file-<?= $key ?>" onchange="fileSelected('<?= $key ?>')">
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px;">
            <button class="btn btn-secondary" id="dryrun-btn-<?= $key ?>" onclick="runDryRun('<?= $key ?>')" disabled>Dry Run</button>
            <button class="btn btn-primary" id="import-btn-<?= $key ?>" onclick="runImport('<?= $key ?>')" disabled style="display:none;">Import</button>
        </div>

        <div id="dryrun-summary-<?= $key ?>" style="display:none; margin-bottom:8px; font-weight:600;"></div>
        <div id="dryrun-results-<?= $key ?>" style="display:none; max-height:400px; overflow-y:auto;"></div>
        <div id="import-result-<?= $key ?>" style="display:none; margin-top:12px;"></div>
    </div>
    <?php $first = false; endforeach; ?>
</div>

<!-- Export Panel -->
<div class="ie-panel" id="panel-export">
    <h3 style="margin-bottom:16px;">Export Data</h3>
    <div class="export-grid">
        <div class="export-card">
            <h4>Items</h4>
            <a href="/settings/export/items" class="btn btn-primary btn-sm">Download CSV</a>
        </div>
        <div class="export-card">
            <h4>Customers</h4>
            <a href="/settings/export/customers" class="btn btn-primary btn-sm">Download CSV</a>
        </div>
        <div class="export-card">
            <h4>Suppliers</h4>
            <a href="/settings/export/suppliers" class="btn btn-primary btn-sm">Download CSV</a>
        </div>
        <div class="export-card">
            <h4>Inventory (On-Hand)</h4>
            <a href="/settings/export/inventory" class="btn btn-primary btn-sm">Download CSV</a>
        </div>
        <div class="export-card">
            <h4>Open Sales Orders</h4>
            <a href="/settings/export/open-sales" class="btn btn-primary btn-sm">Download CSV</a>
        </div>
        <div class="export-card">
            <h4>Open Purchase Orders</h4>
            <a href="/settings/export/open-purchases" class="btn btn-primary btn-sm">Download CSV</a>
        </div>
    </div>
</div>

<script>
// Track state per entity
var importState = {};

function switchMainTab(tab, el) {
    document.querySelectorAll('.ie-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.ie-panel').forEach(function(p) { p.classList.remove('active'); });
    el.classList.add('active');
    document.getElementById('panel-' + tab).classList.add('active');
}

function switchImportTab(entity, el) {
    document.querySelectorAll('#import-sub-tabs .sub-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.import-entity').forEach(function(p) { p.classList.remove('active'); });
    el.classList.add('active');
    document.getElementById('import-' + entity).classList.add('active');
}

function fileSelected(entity) {
    var file = document.getElementById('file-' + entity).files[0];
    importState[entity] = { file: file, dryRunDone: false, errorCount: 0 };
    document.getElementById('dryrun-btn-' + entity).disabled = !file;
    document.getElementById('import-btn-' + entity).style.display = 'none';
    document.getElementById('dryrun-results-' + entity).style.display = 'none';
    document.getElementById('dryrun-summary-' + entity).style.display = 'none';
    document.getElementById('import-result-' + entity).style.display = 'none';
}

function runDryRun(entity) {
    var state = importState[entity];
    if (!state || !state.file) return;

    var btn = document.getElementById('dryrun-btn-' + entity);
    btn.disabled = true;
    btn.textContent = 'Validating...';

    var form = new FormData();
    form.append('csv', state.file);

    fetch('/settings/import/dry-run/' + entity, { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(results) {
            var valid = 0, warn = 0, err = 0;
            var html = '<table class="dry-run-table"><thead><tr><th>Row</th><th>Status</th><th>Message</th></tr></thead><tbody>';
            results.forEach(function(r) {
                if (r.status === 'valid') valid++;
                else if (r.status === 'warning') warn++;
                else err++;
                html += '<tr class="row-' + r.status + '"><td>' + r.row + '</td><td>' + r.status.toUpperCase() + '</td><td>' + r.message + '</td></tr>';
            });
            html += '</tbody></table>';

            state.dryRunDone = true;
            state.errorCount = err;

            var summary = 'Validated: ' + valid + ' valid, ' + warn + ' warnings, ' + err + ' errors';
            document.getElementById('dryrun-summary-' + entity).textContent = summary;
            document.getElementById('dryrun-summary-' + entity).style.display = 'block';
            document.getElementById('dryrun-results-' + entity).innerHTML = html;
            document.getElementById('dryrun-results-' + entity).style.display = 'block';

            var importBtn = document.getElementById('import-btn-' + entity);
            if (err === 0) {
                importBtn.style.display = 'inline-block';
                importBtn.disabled = false;
                importBtn.textContent = 'Import (' + (valid + warn) + ' rows)';
            } else {
                importBtn.style.display = 'inline-block';
                importBtn.disabled = true;
                importBtn.textContent = 'Import (fix errors first)';
            }
        })
        .catch(function(e) { alert('Dry run failed: ' + e.message); })
        .finally(function() { btn.disabled = false; btn.textContent = 'Dry Run'; });
}

function runImport(entity) {
    var state = importState[entity];
    if (!state || !state.file || state.errorCount > 0) return;

    if (!confirm('Import data for ' + entity + '? This will write to the database.')) return;

    var btn = document.getElementById('import-btn-' + entity);
    btn.disabled = true;
    btn.textContent = 'Importing...';

    var form = new FormData();
    form.append('csv', state.file);

    fetch('/settings/import/commit/' + entity, { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            var html = '<div class="toast toast-success" style="margin-bottom:8px;">Import complete: ' + result.imported + ' imported, ' + result.skipped + ' skipped.</div>';
            if (result.errors && result.errors.length > 0) {
                html += '<table class="dry-run-table"><thead><tr><th>Row</th><th>Error</th></tr></thead><tbody>';
                result.errors.forEach(function(e) {
                    html += '<tr class="row-error"><td>' + e.row + '</td><td>' + e.message + '</td></tr>';
                });
                html += '</tbody></table>';
            }
            document.getElementById('import-result-' + entity).innerHTML = html;
            document.getElementById('import-result-' + entity).style.display = 'block';
        })
        .catch(function(e) { alert('Import failed: ' + e.message); })
        .finally(function() { btn.disabled = false; btn.textContent = 'Import'; });
}
</script>
