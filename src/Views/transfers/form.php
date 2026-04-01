<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'edit' ? 'Edit' : 'New' ?> Transfer Order — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .line-row { display:flex; gap:8px; align-items:center; margin-bottom:8px; padding:8px; background:#f9fafb; border-radius:4px; }
        .line-row .form-input, .line-row select { font-size:13px; padding:4px 8px; }
        .item-search-wrap { position:relative; }
        .item-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none; }
        .item-suggestions div { padding:6px 10px; font-size:13px; cursor:pointer; }
        .item-suggestions div:hover { background:#eff6ff; }
    </style>
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

    <div style="max-width:900px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast">
                <?= htmlspecialchars($_SESSION['toast']['message']) ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">
                <?php if ($mode === 'edit'): ?>
                    Edit Transfer: <?= htmlspecialchars($transfer['trf_number']) ?>
                <?php else: ?>
                    New Transfer Order
                <?php endif; ?>
            </h1>
            <a href="/transfers" class="btn btn-secondary">&larr; Back to List</a>
        </div>

        <form method="POST" action="<?= $mode === 'edit' ? '/transfers/' . $transfer['id'] . '/edit' : '/transfers/create' ?>">
            <div class="form-section" style="margin-bottom:16px;">
                <div class="form-row" style="display:flex; gap:16px; flex-wrap:wrap;">
                    <div class="form-group" style="flex:1; min-width:200px;">
                        <label for="from_facility_id">From Facility *</label>
                        <select name="from_facility_id" id="from_facility_id" required class="form-input">
                            <option value="">-- Select Source --</option>
                            <?php foreach ($userFacilities as $f): ?>
                            <option value="<?= $f['id'] ?>"
                                    <?= ($transfer['from_facility_id'] ?? '') == $f['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1; min-width:200px;">
                        <label for="to_facility_id">To Facility *</label>
                        <select name="to_facility_id" id="to_facility_id" required class="form-input">
                            <option value="">-- Select Destination --</option>
                            <?php foreach ($allFacilities as $f): ?>
                            <option value="<?= $f['id'] ?>"
                                    <?= ($transfer['to_facility_id'] ?? '') == $f['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="min-width:160px;">
                        <label for="requested_date">Requested Date *</label>
                        <input type="date" name="requested_date" id="requested_date" required class="form-input"
                               value="<?= htmlspecialchars($transfer['requested_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-top:8px;">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes" rows="2" class="form-input" style="width:100%;"><?= htmlspecialchars($transfer['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Line Items -->
            <h3 style="margin-bottom:8px;">Line Items</h3>
            <div id="lines-container">
                <?php if (!empty($lines)): ?>
                    <?php foreach ($lines as $idx => $line): ?>
                    <div class="line-row" data-index="<?= $idx ?>">
                        <div class="item-search-wrap" style="flex:2;">
                            <input type="hidden" name="lines[<?= $idx ?>][item_id]" value="<?= $line['item_id'] ?>" class="line-item-id">
                            <input type="text" class="form-input item-search" placeholder="Search items..."
                                   value="<?= htmlspecialchars(($line['item_code'] ?? '') . ' - ' . ($line['item_description'] ?? '')) ?>"
                                   autocomplete="off" style="width:100%;">
                            <div class="item-suggestions"></div>
                        </div>
                        <div style="flex:1;">
                            <select name="lines[<?= $idx ?>][pack_extension_id]" class="form-input pack-select" style="width:100%;">
                                <option value="">Bulk / No Pack</option>
                            </select>
                        </div>
                        <div style="width:100px;">
                            <input type="number" name="lines[<?= $idx ?>][quantity]" step="0.0001" min="0.0001"
                                   value="<?= $line['quantity'] ?>" class="form-input" placeholder="Qty" required style="width:100%;">
                        </div>
                        <div style="width:120px;">
                            <select name="lines[<?= $idx ?>][uom_id]" class="form-input uom-select" required style="width:100%;">
                                <?php foreach ($uoms as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($line['uom_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['abbreviation']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn btn-sm btn-warning" onclick="removeLine(this)" style="flex-shrink:0;">&times;</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-secondary" onclick="addLine()" style="margin-top:4px;">+ Add Line</button>

            <div style="margin-top:24px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $mode === 'edit' ? 'Save Changes' : 'Create Transfer' ?></button>
                <a href="/transfers" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
            </div>
        </form>

        <?php if ($mode === 'edit' && $transfer): ?>
        <?php
            $recordType = 'transfer_order';
            $recordId = $transfer['id'];
            $attachments = $this->attachmentService ? $this->attachmentService->getForRecord($recordType, $recordId) : [];
            require __DIR__ . '/../partials/attachments.php';
        ?>
        <?php endif; ?>
    </div>

    <script>
    var lineIndex = <?= !empty($lines) ? count($lines) : 0 ?>;
    var uomOptions = '<?php
        $opts = '';
        foreach ($uoms as $u) {
            $opts .= '<option value="' . $u['id'] . '">' . htmlspecialchars($u['abbreviation']) . '</option>';
        }
        echo addslashes($opts);
    ?>';

    function addLine() {
        var container = document.getElementById('lines-container');
        var row = document.createElement('div');
        row.className = 'line-row';
        row.dataset.index = lineIndex;
        row.innerHTML = '<div class="item-search-wrap" style="flex:2;">' +
            '<input type="hidden" name="lines[' + lineIndex + '][item_id]" value="" class="line-item-id">' +
            '<input type="text" class="form-input item-search" placeholder="Search items..." autocomplete="off" style="width:100%;">' +
            '<div class="item-suggestions"></div>' +
            '</div>' +
            '<div style="flex:1;">' +
            '<select name="lines[' + lineIndex + '][pack_extension_id]" class="form-input pack-select" style="width:100%;">' +
            '<option value="">Bulk / No Pack</option>' +
            '</select></div>' +
            '<div style="width:100px;">' +
            '<input type="number" name="lines[' + lineIndex + '][quantity]" step="0.0001" min="0.0001" class="form-input" placeholder="Qty" required style="width:100%;"></div>' +
            '<div style="width:120px;">' +
            '<select name="lines[' + lineIndex + '][uom_id]" class="form-input uom-select" required style="width:100%;">' +
            uomOptions + '</select></div>' +
            '<button type="button" class="btn btn-sm btn-warning" onclick="removeLine(this)" style="flex-shrink:0;">&times;</button>';
        container.appendChild(row);
        bindItemSearch(row.querySelector('.item-search'));
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
            var self = this;
            clearTimeout(timer);
            timer = setTimeout(function() {
                var q = self.value.trim();
                var sugBox = self.parentNode.querySelector('.item-suggestions');
                if (q.length < 2) { sugBox.style.display = 'none'; return; }
                fetch('/transfers/search-items?q=' + encodeURIComponent(q))
                    .then(function(r) { return r.json(); })
                    .then(function(items) {
                        sugBox.innerHTML = '';
                        items.forEach(function(item) {
                            var d = document.createElement('div');
                            d.textContent = item.item_code + ' — ' + item.description;
                            d.addEventListener('click', function() {
                                self.value = item.item_code + ' - ' + item.description;
                                self.parentNode.querySelector('.line-item-id').value = item.id;
                                sugBox.style.display = 'none';
                                // Set default UOM
                                var uomSel = self.closest('.line-row').querySelector('.uom-select');
                                if (uomSel && item.uom_id) uomSel.value = item.uom_id;
                                // Load pack extensions
                                loadPackExtensions(self.closest('.line-row'), item.id);
                            });
                            sugBox.appendChild(d);
                        });
                        sugBox.style.display = items.length ? 'block' : 'none';
                    });
            }, 250);
        });
        // Close suggestions on blur
        input.addEventListener('blur', function() {
            var sugBox = this.parentNode.querySelector('.item-suggestions');
            setTimeout(function() { sugBox.style.display = 'none'; }, 200);
        });
    }

    function loadPackExtensions(row, itemId) {
        var select = row.querySelector('.pack-select');
        fetch('/transfers/item-packs?item_id=' + itemId)
            .then(function(r) { return r.json(); })
            .then(function(packs) {
                select.innerHTML = '<option value="">Bulk / No Pack</option>';
                packs.forEach(function(p) {
                    var opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.description;
                    select.appendChild(opt);
                });
            });
    }

    // Filter destination facility options to exclude selected source
    document.getElementById('from_facility_id').addEventListener('change', function() {
        var fromId = this.value;
        var toSelect = document.getElementById('to_facility_id');
        var opts = toSelect.querySelectorAll('option');
        opts.forEach(function(o) {
            o.disabled = (o.value && o.value === fromId);
        });
        if (toSelect.value === fromId) toSelect.value = '';
    });

    // Bind search to existing lines
    document.querySelectorAll('.item-search').forEach(bindItemSearch);

    // If no lines exist, add one
    if (document.querySelectorAll('.line-row').length === 0) addLine();

    // Toast auto-dismiss
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);
    </script>
</body>
</html>
