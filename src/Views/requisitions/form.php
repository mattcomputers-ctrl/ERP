<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'edit' ? 'Edit Requisition' : 'New Requisition' ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .line-row { display:flex; gap:8px; align-items:center; margin-bottom:8px; padding:8px; background:#f9fafb; border-radius:4px; flex-wrap:wrap; }
        .line-row .form-input, .line-row select { font-size:13px; padding:4px 8px; }
        .item-search-wrap { position:relative; }
        .item-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none; }
        .item-suggestions div { padding:6px 10px; font-size:13px; cursor:pointer; }
        .item-suggestions div:hover { background:#eff6ff; }
        .supp-search-wrap { position:relative; }
        .supp-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none; }
        .supp-suggestions div { padding:6px 10px; font-size:13px; cursor:pointer; }
        .supp-suggestions div:hover { background:#eff6ff; }
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
            <h1 style="margin:0;">
                <?php if ($mode === 'edit'): ?>
                    Edit Requisition: <?= htmlspecialchars($req['req_number'] ?? '') ?>
                <?php else: ?>
                    New Purchase Requisition
                <?php endif; ?>
            </h1>
            <a href="/purchase-requisitions" class="btn btn-secondary">&larr; Back</a>
        </div>

        <form method="POST" action="<?= $mode === 'edit' ? '/purchase-requisitions/' . $req['id'] . '/edit' : '/purchase-requisitions/create' ?>">
            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Request Date <span style="color:red;">*</span></label>
                        <input type="date" name="request_date" value="<?= htmlspecialchars($req['request_date'] ?? date('Y-m-d')) ?>" required class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Required By Date</label>
                        <input type="date" name="required_by_date" value="<?= htmlspecialchars($req['required_by_date'] ?? '') ?>" class="form-input" style="width:100%;">
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Justification</label>
                    <textarea name="justification" rows="2" class="form-input" style="width:100%;"><?= htmlspecialchars($req['justification'] ?? '') ?></textarea>
                </div>
                <div style="margin-top:8px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Notes</label>
                    <textarea name="notes" rows="2" class="form-input" style="width:100%;"><?= htmlspecialchars($req['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <h3 style="margin-bottom:8px;">Line Items</h3>
            <div id="lines-container">
                <?php if (!empty($lines)): ?>
                    <?php foreach ($lines as $idx => $line): ?>
                    <div class="line-row" data-index="<?= $idx ?>">
                        <div class="item-search-wrap" style="flex:2; min-width:180px;">
                            <input type="hidden" name="lines[<?= $idx ?>][item_id]" value="<?= $line['item_id'] ?>" class="line-item-id">
                            <input type="text" class="form-input item-search" placeholder="Search items..."
                                   value="<?= htmlspecialchars(($line['item_code'] ?? '') . ($line['item_code'] ? ' — ' : '') . ($line['item_description'] ?? '')) ?>"
                                   autocomplete="off" style="width:100%;">
                            <div class="item-suggestions"></div>
                        </div>
                        <div style="width:90px;">
                            <input type="number" step="0.0001" min="0.0001" name="lines[<?= $idx ?>][quantity]"
                                   value="<?= $line['quantity'] ?>" class="form-input" placeholder="Qty" required style="width:100%;">
                        </div>
                        <div style="width:80px;">
                            <select name="lines[<?= $idx ?>][uom_id]" class="form-input uom-select" required style="width:100%;">
                                <?php foreach ($uoms as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($line['uom_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['abbreviation']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="width:100px;">
                            <input type="number" step="0.0001" name="lines[<?= $idx ?>][estimated_unit_cost]"
                                   value="<?= $line['estimated_unit_cost'] ?? '' ?>" class="form-input" placeholder="Est. Cost" style="width:100%;">
                        </div>
                        <div class="supp-search-wrap" style="flex:1; min-width:150px;">
                            <input type="hidden" name="lines[<?= $idx ?>][preferred_supplier_id]" value="<?= $line['preferred_supplier_id'] ?? '' ?>" class="line-supp-id">
                            <input type="text" class="form-input supp-search" placeholder="Supplier..."
                                   value="<?= htmlspecialchars(($line['supplier_code'] ?? '') . ($line['supplier_code'] ? ' — ' : '') . ($line['supplier_name'] ?? '')) ?>"
                                   autocomplete="off" style="width:100%;">
                            <div class="supp-suggestions"></div>
                        </div>
                        <input type="hidden" name="lines[<?= $idx ?>][pack_extension_id]" value="<?= $line['pack_extension_id'] ?? '' ?>">
                        <button type="button" class="btn btn-sm btn-warning" onclick="removeLine(this)" style="flex-shrink:0;">&times;</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-secondary" onclick="addLine()" style="margin-top:4px;">+ Add Line</button>

            <div style="margin-top:24px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $mode === 'edit' ? 'Save Changes' : 'Create Requisition' ?></button>
                <a href="/purchase-requisitions" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
            </div>
        </form>
    </div>

    <script>
    var lineIndex = <?= !empty($lines) ? count($lines) : 0 ?>;
    var uomOptions = '<?php $o=''; foreach ($uoms as $u) { $o .= '<option value="'.$u['id'].'">'.htmlspecialchars($u['abbreviation'], ENT_QUOTES).'</option>'; } echo addslashes($o); ?>';

    var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display='none'; }, 4000);

    function addLine() {
        var c = document.getElementById('lines-container');
        var row = document.createElement('div');
        row.className = 'line-row';
        row.dataset.index = lineIndex;
        row.innerHTML =
            '<div class="item-search-wrap" style="flex:2; min-width:180px;">' +
                '<input type="hidden" name="lines[' + lineIndex + '][item_id]" value="" class="line-item-id">' +
                '<input type="text" class="form-input item-search" placeholder="Search items..." autocomplete="off" style="width:100%;">' +
                '<div class="item-suggestions"></div>' +
            '</div>' +
            '<div style="width:90px;"><input type="number" step="0.0001" min="0.0001" name="lines[' + lineIndex + '][quantity]" class="form-input" placeholder="Qty" required style="width:100%;"></div>' +
            '<div style="width:80px;"><select name="lines[' + lineIndex + '][uom_id]" class="form-input uom-select" required style="width:100%;">' + uomOptions + '</select></div>' +
            '<div style="width:100px;"><input type="number" step="0.0001" name="lines[' + lineIndex + '][estimated_unit_cost]" class="form-input" placeholder="Est. Cost" style="width:100%;"></div>' +
            '<div class="supp-search-wrap" style="flex:1; min-width:150px;">' +
                '<input type="hidden" name="lines[' + lineIndex + '][preferred_supplier_id]" value="" class="line-supp-id">' +
                '<input type="text" class="form-input supp-search" placeholder="Supplier..." autocomplete="off" style="width:100%;">' +
                '<div class="supp-suggestions"></div>' +
            '</div>' +
            '<input type="hidden" name="lines[' + lineIndex + '][pack_extension_id]" value="">' +
            '<button type="button" class="btn btn-sm btn-warning" onclick="removeLine(this)" style="flex-shrink:0;">&times;</button>';
        c.appendChild(row);
        bindItemSearch(row.querySelector('.item-search'));
        bindSuppSearch(row.querySelector('.supp-search'));
        lineIndex++;
    }

    function removeLine(btn) {
        var rows = document.querySelectorAll('.line-row');
        if (rows.length <= 1) { alert('At least one line is required.'); return; }
        btn.closest('.line-row').remove();
    }

    function bindItemSearch(input) {
        var timer;
        input.addEventListener('input', function() {
            var self = this; clearTimeout(timer);
            timer = setTimeout(function() {
                var q = self.value.trim();
                var box = self.parentNode.querySelector('.item-suggestions');
                if (q.length < 1) { box.style.display = 'none'; return; }
                fetch('/items/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r) { return r.json(); })
                    .then(function(items) {
                        box.innerHTML = '';
                        items.forEach(function(item) {
                            var d = document.createElement('div');
                            d.textContent = item.display;
                            d.addEventListener('click', function() {
                                self.value = item.item_code + ' — ' + item.description;
                                self.parentNode.querySelector('.line-item-id').value = item.id;
                                box.style.display = 'none';
                            });
                            box.appendChild(d);
                        });
                        box.style.display = items.length ? 'block' : 'none';
                    });
            }, 300);
        });
    }

    function bindSuppSearch(input) {
        var timer;
        input.addEventListener('input', function() {
            var self = this; clearTimeout(timer);
            timer = setTimeout(function() {
                var q = self.value.trim();
                var box = self.parentNode.querySelector('.supp-suggestions');
                if (q.length < 1) { box.style.display = 'none'; return; }
                fetch('/suppliers/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r) { return r.json(); })
                    .then(function(items) {
                        box.innerHTML = '';
                        items.forEach(function(item) {
                            var d = document.createElement('div');
                            d.textContent = item.display;
                            d.addEventListener('click', function() {
                                self.value = item.display;
                                self.parentNode.querySelector('.line-supp-id').value = item.id;
                                box.style.display = 'none';
                            });
                            box.appendChild(d);
                        });
                        box.style.display = items.length ? 'block' : 'none';
                    });
            }, 300);
        });
    }

    // Close suggestions on outside click
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.item-suggestions, .supp-suggestions').forEach(function(box) {
            if (!box.parentNode.contains(e.target)) box.style.display = 'none';
        });
    });

    // Bind existing lines
    document.querySelectorAll('.item-search').forEach(bindItemSearch);
    document.querySelectorAll('.supp-search').forEach(bindSuppSearch);

    // Auto-add first line if empty
    if (lineIndex === 0) addLine();
    </script>
</body>
</html>
