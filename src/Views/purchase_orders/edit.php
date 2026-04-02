<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'edit' ? 'Edit PO' : 'New Purchase Order' ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .line-row { display:flex; gap:8px; align-items:center; margin-bottom:8px; padding:8px; background:#f9fafb; border-radius:4px; flex-wrap:wrap; }
        .line-row .form-input, .line-row select { font-size:13px; padding:4px 8px; }
        .search-wrap { position:relative; }
        .search-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none; }
        .search-suggestions div { padding:6px 10px; font-size:13px; cursor:pointer; }
        .search-suggestions div:hover { background:#eff6ff; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?><span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span><?php endif; ?>
        </div>
    </header>
    <div style="max-width:960px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast"><?= htmlspecialchars($_SESSION['toast']['message']) ?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;"><?= $mode === 'edit' ? 'Edit PO: ' . htmlspecialchars($po['po_number'] ?? '') : 'New Purchase Order' ?></h1>
            <a href="/purchase-orders" class="btn btn-secondary">&larr; Back</a>
        </div>

        <form method="POST" action="<?= $mode === 'edit' ? '/purchase-orders/' . $po['id'] . '/edit' : '/purchase-orders/create' ?>" id="poForm">
            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">PO Type</label>
                        <select name="po_type" id="poType" class="form-input" style="width:100%;">
                            <option value="STANDARD" <?= ($po['po_type'] ?? '') === 'STANDARD' ? 'selected' : '' ?>>Standard</option>
                            <option value="BLANKET" <?= ($po['po_type'] ?? '') === 'BLANKET' ? 'selected' : '' ?>>Blanket</option>
                        </select>
                    </div>
                    <div class="search-wrap">
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Supplier <span style="color:red;">*</span></label>
                        <input type="hidden" name="supplier_id" id="supplierId" value="<?= $po['supplier_id'] ?? '' ?>">
                        <input type="text" id="supplierSearch" placeholder="Search suppliers..." class="form-input" style="width:100%;" autocomplete="off"
                               value="<?= isset($po['supplier_code']) ? htmlspecialchars($po['supplier_code'] . ' — ' . $po['supplier_name']) : '' ?>">
                        <div class="search-suggestions" id="supplierSuggestions"></div>
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Facility <span style="color:red;">*</span></label>
                        <select name="facility_id" class="form-input" style="width:100%;" required>
                            <?php foreach ($facilities as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= ($po['facility_id'] ?? $activeFacilityId) == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Order Date</label>
                        <input type="date" name="order_date" value="<?= htmlspecialchars($po['order_date'] ?? date('Y-m-d')) ?>" class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Expected Delivery</label>
                        <input type="date" name="expected_delivery_date" value="<?= htmlspecialchars($po['expected_delivery_date'] ?? '') ?>" class="form-input" style="width:100%;">
                    </div>
                </div>

                <!-- Blanket PO fields -->
                <div id="blanketFields" style="display:<?= ($po['po_type'] ?? '') === 'BLANKET' ? 'block' : 'none' ?>; margin-top:12px; padding:12px; background:#eff6ff; border-radius:6px;">
                    <h4 style="margin:0 0 8px; font-size:13px; color:#1d4ed8;">Blanket Contract Details</h4>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px;">
                        <div><label style="font-size:12px; display:block; margin-bottom:2px;">Start Date</label><input type="date" name="contract_start_date" value="<?= htmlspecialchars($po['contract_start_date'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                        <div><label style="font-size:12px; display:block; margin-bottom:2px;">End Date</label><input type="date" name="contract_end_date" value="<?= htmlspecialchars($po['contract_end_date'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                        <div><label style="font-size:12px; display:block; margin-bottom:2px;">Contracted Qty</label><input type="number" step="0.0001" name="contracted_quantity" value="<?= htmlspecialchars($po['contracted_quantity'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                        <div><label style="font-size:12px; display:block; margin-bottom:2px;">Contracted Value</label><input type="number" step="0.01" name="contracted_value" value="<?= htmlspecialchars($po['contracted_value'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Notes</label><textarea name="notes" rows="2" class="form-input" style="width:100%;"><?= htmlspecialchars($po['notes'] ?? '') ?></textarea></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Shipping Instructions</label><textarea name="shipping_instructions" rows="2" class="form-input" style="width:100%;"><?= htmlspecialchars($po['shipping_instructions'] ?? '') ?></textarea></div>
                </div>
            </div>

            <!-- Lines -->
            <h3 style="margin-bottom:8px;">Line Items</h3>
            <div id="lines-container">
                <?php if (!empty($lines)): foreach ($lines as $idx => $line): if (($line['line_status'] ?? 'OPEN') === 'CANCELLED') continue; ?>
                <div class="line-row" data-index="<?= $idx ?>">
                    <div class="search-wrap" style="flex:2; min-width:180px;">
                        <input type="hidden" name="lines[<?= $idx ?>][item_id]" value="<?= $line['item_id'] ?>" class="line-item-id">
                        <input type="text" class="form-input item-search" placeholder="Search items..." autocomplete="off" style="width:100%;"
                               value="<?= htmlspecialchars(($line['item_code'] ?? '') . ($line['item_code'] ? ' — ' : '') . ($line['item_description'] ?? '')) ?>">
                        <div class="search-suggestions item-suggestions"></div>
                    </div>
                    <div style="width:90px;"><input type="number" step="0.0001" min="0.0001" name="lines[<?= $idx ?>][quantity]" value="<?= $line['ordered_quantity'] ?? '' ?>" class="form-input line-qty" placeholder="Qty" required style="width:100%;"></div>
                    <div style="width:80px;">
                        <select name="lines[<?= $idx ?>][uom_id]" class="form-input" required style="width:100%;">
                            <?php foreach ($uoms as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($line['uom_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['abbreviation']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="width:100px;"><input type="number" step="0.0001" name="lines[<?= $idx ?>][unit_cost]" value="<?= $line['unit_cost'] ?? '0' ?>" class="form-input line-cost" placeholder="Unit Cost" style="width:100%;"></div>
                    <div style="width:70px; text-align:right; font-size:12px; color:#6b7280;" class="line-total">—</div>
                    <input type="hidden" name="lines[<?= $idx ?>][pack_extension_id]" value="<?= $line['pack_extension_id'] ?? '' ?>">
                    <input type="hidden" name="lines[<?= $idx ?>][notes]" value="<?= htmlspecialchars($line['notes'] ?? '') ?>">
                    <button type="button" class="btn btn-sm btn-warning" onclick="removeLine(this)">&times;</button>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
                <button type="button" class="btn btn-secondary" onclick="addLine()">+ Add Line</button>
                <div style="font-size:14px; font-weight:600;">Total: $<span id="poTotal">0.00</span></div>
            </div>

            <?php $cfRecordType = 'purchase_orders'; $cfRecordId = $po['id'] ?? 0; if (isset($customFieldService)) require __DIR__ . '/../partials/custom_fields_edit.php'; ?>

            <div style="margin-top:24px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $mode === 'edit' ? 'Save Changes' : 'Create PO' ?></button>
                <a href="/purchase-orders" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
    var lineIndex = <?= !empty($lines) ? count($lines) : 0 ?>;
    var uomOpts = '<?php $o=''; foreach ($uoms as $u) { $o.='<option value="'.$u['id'].'">'.htmlspecialchars($u['abbreviation'],ENT_QUOTES).'</option>'; } echo addslashes($o); ?>';
    var supplierId = document.getElementById('supplierId');

    var toast = document.getElementById('toast'); if (toast) setTimeout(function(){toast.style.display='none';}, 4000);

    // PO type toggle
    document.getElementById('poType').addEventListener('change', function() {
        document.getElementById('blanketFields').style.display = this.value === 'BLANKET' ? 'block' : 'none';
    });

    // Supplier search
    (function() {
        var input = document.getElementById('supplierSearch');
        var sugs = document.getElementById('supplierSuggestions');
        var timer;
        input.addEventListener('input', function() {
            clearTimeout(timer); var q = this.value.trim();
            if (q.length < 1) { sugs.style.display = 'none'; return; }
            timer = setTimeout(function() {
                fetch('/suppliers/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r){return r.json();})
                    .then(function(data) {
                        sugs.innerHTML = '';
                        data.forEach(function(s) {
                            var d = document.createElement('div');
                            d.textContent = s.display;
                            d.addEventListener('click', function() {
                                input.value = s.display;
                                supplierId.value = s.id;
                                sugs.style.display = 'none';
                            });
                            sugs.appendChild(d);
                        });
                        sugs.style.display = data.length ? 'block' : 'none';
                    });
            }, 300);
        });
    })();

    function addLine() {
        var c = document.getElementById('lines-container');
        var row = document.createElement('div');
        row.className = 'line-row'; row.dataset.index = lineIndex;
        row.innerHTML =
            '<div class="search-wrap" style="flex:2; min-width:180px;">' +
                '<input type="hidden" name="lines['+lineIndex+'][item_id]" value="" class="line-item-id">' +
                '<input type="text" class="form-input item-search" placeholder="Search items..." autocomplete="off" style="width:100%;">' +
                '<div class="search-suggestions item-suggestions"></div>' +
            '</div>' +
            '<div style="width:90px;"><input type="number" step="0.0001" min="0.0001" name="lines['+lineIndex+'][quantity]" class="form-input line-qty" placeholder="Qty" required style="width:100%;"></div>' +
            '<div style="width:80px;"><select name="lines['+lineIndex+'][uom_id]" class="form-input" required style="width:100%;">'+uomOpts+'</select></div>' +
            '<div style="width:100px;"><input type="number" step="0.0001" name="lines['+lineIndex+'][unit_cost]" class="form-input line-cost" placeholder="Cost" style="width:100%;"></div>' +
            '<div style="width:70px; text-align:right; font-size:12px; color:#6b7280;" class="line-total">—</div>' +
            '<input type="hidden" name="lines['+lineIndex+'][pack_extension_id]" value="">' +
            '<input type="hidden" name="lines['+lineIndex+'][notes]" value="">' +
            '<button type="button" class="btn btn-sm btn-warning" onclick="removeLine(this)">&times;</button>';
        c.appendChild(row);
        bindItemSearch(row.querySelector('.item-search'));
        bindLineCalc(row);
        lineIndex++;
    }

    function removeLine(btn) {
        if (document.querySelectorAll('.line-row').length <= 1) { alert('At least one line required.'); return; }
        btn.closest('.line-row').remove(); updateTotal();
    }

    function bindItemSearch(input) {
        var timer;
        input.addEventListener('input', function() {
            var self = this; clearTimeout(timer);
            timer = setTimeout(function() {
                var q = self.value.trim();
                var box = self.parentNode.querySelector('.item-suggestions');
                if (q.length < 1) { box.style.display = 'none'; return; }
                fetch('/items/search?q='+encodeURIComponent(q)+'&limit=10')
                    .then(function(r){return r.json();})
                    .then(function(items) {
                        box.innerHTML = '';
                        items.forEach(function(item) {
                            var d = document.createElement('div');
                            d.textContent = item.display;
                            d.addEventListener('click', function() {
                                self.value = item.item_code + ' — ' + item.description;
                                self.parentNode.querySelector('.line-item-id').value = item.id;
                                box.style.display = 'none';
                                // Fetch AVL cost
                                var sid = supplierId.value;
                                if (sid) {
                                    fetch('/purchase-orders/line-cost?item_id='+item.id+'&supplier_id='+sid)
                                        .then(function(r){return r.json();})
                                        .then(function(d) {
                                            if (d.unit_cost !== null) {
                                                var row = self.closest('.line-row');
                                                row.querySelector('.line-cost').value = d.unit_cost;
                                                updateTotal();
                                            }
                                        });
                                }
                            });
                            box.appendChild(d);
                        });
                        box.style.display = items.length ? 'block' : 'none';
                    });
            }, 300);
        });
    }

    function bindLineCalc(row) {
        var qty = row.querySelector('.line-qty');
        var cost = row.querySelector('.line-cost');
        if (qty) qty.addEventListener('input', updateTotal);
        if (cost) cost.addEventListener('input', updateTotal);
    }

    function updateTotal() {
        var total = 0;
        document.querySelectorAll('.line-row').forEach(function(row) {
            var q = parseFloat(row.querySelector('.line-qty')?.value) || 0;
            var c = parseFloat(row.querySelector('.line-cost')?.value) || 0;
            var lt = row.querySelector('.line-total');
            var lineVal = q * c;
            if (lt) lt.textContent = lineVal > 0 ? '$' + lineVal.toFixed(2) : '—';
            total += lineVal;
        });
        document.getElementById('poTotal').textContent = total.toFixed(2);
    }

    // Close suggestions on outside click
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.search-suggestions').forEach(function(b) {
            if (!b.parentNode.contains(e.target)) b.style.display = 'none';
        });
    });

    // Init existing lines
    document.querySelectorAll('.item-search').forEach(bindItemSearch);
    document.querySelectorAll('.line-row').forEach(bindLineCalc);
    if (lineIndex === 0) addLine();
    updateTotal();
    </script>
</body>
</html>
