<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= htmlspecialchars($list['name']) ?> — Price List — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1400px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div style="margin-bottom:16px;"><a href="/price-lists" style="color:#2563eb;">&larr; Back to Price Lists</a></div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <div>
                <h1 style="margin:0;"><?= htmlspecialchars($list['name']) ?></h1>
                <div style="display:flex;gap:12px;margin-top:4px;font-size:13px;color:#6b7280;">
                    <span class="badge <?= $list['list_type']==='CUSTOMER'?'badge-info':'badge-warning' ?>"><?= $list['list_type'] ?></span>
                    <span>Priority: <?= (int)$list['default_priority'] ?></span>
                    <span>Effective: <?= date('M j, Y', strtotime($list['effective_date'])) ?></span>
                    <?php if ($list['expiration_date']): ?><span>Expires: <?= date('M j, Y', strtotime($list['expiration_date'])) ?></span><?php endif; ?>
                    <span class="badge <?= $list['active']?'badge-active':'badge-inactive' ?>"><?= $list['active']?'Active':'Inactive' ?></span>
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="/price-lists/<?= $list['id'] ?>/edit" class="btn btn-secondary">Edit Header</a>
                <form method="POST" action="/price-lists/<?= $list['id'] ?>/clone" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <button type="submit" class="btn btn-secondary">Clone List</button>
                </form>
            </div>
        </div>
        <?php if ($list['notes']): ?><p style="color:#6b7280;font-size:13px;margin:4px 0 16px;"><?= htmlspecialchars($list['notes']) ?></p><?php endif; ?>

        <!-- Add Line Form -->
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:16px;margin-bottom:16px;" id="addLineForm">
            <h3 style="margin:0 0 12px;font-size:14px;">Add Line</h3>
            <form method="POST" action="/price-lists/<?= $list['id'] ?>/lines" id="lineForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:8px;">
                    <div style="flex:2;min-width:200px;" class="item-search-wrap">
                        <label style="font-size:12px;display:block;margin-bottom:2px;">Item *</label>
                        <input type="text" id="lineItemSearch" placeholder="Search items..." style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;" autocomplete="off">
                        <input type="hidden" name="item_id" id="lineItemId" required>
                        <div class="item-suggestions" id="lineSuggestions" style="position:absolute;z-index:10;background:#fff;border:1px solid #d1d5db;border-radius:4px;max-height:200px;overflow-y:auto;display:none;width:300px;"></div>
                    </div>
                    <div style="flex:1;min-width:120px;">
                        <label style="font-size:12px;display:block;margin-bottom:2px;">External Code</label>
                        <input type="text" name="external_code" placeholder="Your code" style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;">
                    </div>
                    <div style="flex:1;min-width:120px;">
                        <label style="font-size:12px;display:block;margin-bottom:2px;">Package Type</label>
                        <select name="package_type_id" id="linePackageType" onchange="toggleQtyPerPkg()" style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;">
                            <option value="">None (base UOM)</option>
                            <?php foreach ($packageTypes as $pt): ?>
                            <option value="<?= $pt['id'] ?>"><?= htmlspecialchars($pt['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:0 0 100px;" id="qtyPerPkgWrap" style="display:none;">
                        <label style="font-size:12px;display:block;margin-bottom:2px;">Qty/Pkg</label>
                        <input type="number" step="any" name="qty_per_package" id="lineQtyPerPkg" style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;">
                    </div>
                </div>

                <!-- Price breaks -->
                <div style="margin-bottom:8px;">
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Price Breaks</label>
                    <div id="priceBreaks">
                        <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;" class="break-row">
                            <span style="font-size:12px;color:#6b7280;width:14px;">1.</span>
                            <label style="font-size:12px;">Qty &ge;</label>
                            <input type="number" step="any" name="break_qty_1" value="0" style="width:90px;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;">
                            <label style="font-size:12px;">lbs &rarr; $</label>
                            <input type="number" step="any" name="break_price_1" value="0" style="width:90px;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;" required>
                            <span style="font-size:12px;color:#6b7280;">/lb</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="addBreak()" id="addBreakBtn" style="font-size:11px;">+ Add Price Break</button>
                </div>

                <div style="margin-bottom:8px;">
                    <label style="font-size:12px;display:block;margin-bottom:2px;">Notes</label>
                    <input type="text" name="notes" style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Add Line</button>
            </form>
        </div>

        <!-- Lines Table -->
        <table class="data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Ext. Code</th>
                    <th>Package</th>
                    <th>Qty/Pkg</th>
                    <th>Break 1</th>
                    <th>Break 2</th>
                    <th>Break 3</th>
                    <th>Break 4</th>
                    <th>Break 5</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($lines)): ?>
                <tr><td colspan="11" style="text-align:center;color:#9ca3af;">No lines yet. Add items above.</td></tr>
            <?php else: foreach ($lines as $ln): ?>
                <tr class="<?= !$ln['active'] ? 'inactive-row' : '' ?>" id="line-row-<?= $ln['id'] ?>">
                    <td>
                        <a href="/items/<?= $ln['item_id'] ?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?= htmlspecialchars($ln['item_code']) ?></a>
                        <br><small style="color:#6b7280;"><?= htmlspecialchars($ln['item_description']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($ln['external_code'] ?? '') ?></td>
                    <td><?= htmlspecialchars($ln['package_type_name'] ?? '—') ?></td>
                    <td><?= $ln['qty_per_package'] ? number_format((float)$ln['qty_per_package'], 2) : '—' ?></td>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <td>
                        <?php if ($ln["break_qty_{$i}"] !== null): ?>
                            <span style="color:#6b7280;">&ge;<?= number_format((float)$ln["break_qty_{$i}"], 0) ?></span>
                            $<?= number_format((float)$ln["break_price_{$i}"], 4) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <?php endfor; ?>
                    <td><span class="badge <?= $ln['active']?'badge-active':'badge-inactive' ?>"><?= $ln['active']?'Active':'Inactive' ?></span></td>
                    <td>
                        <?php if ($ln['active']): ?>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="editLine(<?= htmlspecialchars(json_encode($ln)) ?>)">Edit</button>
                        <form method="POST" action="/price-lists/<?= $list['id'] ?>/lines/<?= $ln['id'] ?>/deactivate" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Deactivate this line?')">Remove</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Edit form row (hidden) -->
                <tr id="line-edit-<?= $ln['id'] ?>" style="display:none;background:#f9fafb;">
                    <td colspan="11">
                        <form method="POST" action="/price-lists/<?= $list['id'] ?>/lines/<?= $ln['id'] ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;padding:8px 0;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <div><label style="font-size:11px;">Ext. Code</label><input type="text" name="external_code" value="<?= htmlspecialchars($ln['external_code'] ?? '') ?>" style="width:100px;padding:4px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;"></div>
                            <div>
                                <label style="font-size:11px;">Package</label>
                                <select name="package_type_id" style="padding:4px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">
                                    <option value="">None</option>
                                    <?php foreach ($packageTypes as $pt): ?>
                                    <option value="<?= $pt['id'] ?>" <?= ($ln['package_type_id'] ?? 0) == $pt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pt['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label style="font-size:11px;">Qty/Pkg</label><input type="number" step="any" name="qty_per_package" value="<?= $ln['qty_per_package'] ?? '' ?>" style="width:70px;padding:4px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;"></div>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div>
                                <label style="font-size:11px;">Brk<?= $i ?> Qty</label>
                                <input type="number" step="any" name="break_qty_<?= $i ?>" value="<?= $ln["break_qty_{$i}"] ?? '' ?>" style="width:70px;padding:4px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">
                            </div>
                            <div>
                                <label style="font-size:11px;">Price</label>
                                <input type="number" step="any" name="break_price_<?= $i ?>" value="<?= $ln["break_price_{$i}"] ?? '' ?>" style="width:70px;padding:4px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">
                            </div>
                            <?php endfor; ?>
                            <div><label style="font-size:11px;">Notes</label><input type="text" name="notes" value="<?= htmlspecialchars($ln['notes'] ?? '') ?>" style="width:120px;padding:4px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;"></div>
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="cancelEdit(<?= $ln['id'] ?>)">Cancel</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    var toast=document.getElementById('toast');if(toast)setTimeout(function(){toast.style.display='none';},4000);

    var breakCount = 1;
    function addBreak() {
        if (breakCount >= 5) return;
        breakCount++;
        var div = document.createElement('div');
        div.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:4px;';
        div.className = 'break-row';
        div.innerHTML = '<span style="font-size:12px;color:#6b7280;width:14px;">' + breakCount + '.</span>' +
            '<label style="font-size:12px;">Qty &ge;</label>' +
            '<input type="number" step="any" name="break_qty_' + breakCount + '" style="width:90px;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;">' +
            '<label style="font-size:12px;">lbs &rarr; $</label>' +
            '<input type="number" step="any" name="break_price_' + breakCount + '" style="width:90px;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;">' +
            '<span style="font-size:12px;color:#6b7280;">/lb</span>';
        document.getElementById('priceBreaks').appendChild(div);
        if (breakCount >= 5) document.getElementById('addBreakBtn').style.display = 'none';
    }

    function toggleQtyPerPkg() {
        var sel = document.getElementById('linePackageType');
        document.getElementById('qtyPerPkgWrap').style.display = sel.value ? '' : 'none';
    }
    toggleQtyPerPkg();

    function editLine(line) {
        document.getElementById('line-edit-' + line.id).style.display = '';
    }
    function cancelEdit(id) {
        document.getElementById('line-edit-' + id).style.display = 'none';
    }

    // Item search typeahead
    var searchInput = document.getElementById('lineItemSearch');
    var sugDiv = document.getElementById('lineSuggestions');
    var itemIdInput = document.getElementById('lineItemId');
    var debounce;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(debounce);
            var q = this.value.trim();
            if (q.length < 2) { sugDiv.style.display = 'none'; return; }
            debounce = setTimeout(function() {
                fetch('/items/search?q=' + encodeURIComponent(q))
                    .then(function(r) { return r.json(); })
                    .then(function(items) {
                        sugDiv.innerHTML = '';
                        items.forEach(function(item) {
                            var d = document.createElement('div');
                            d.style.cssText = 'padding:6px 8px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:13px;';
                            d.textContent = item.item_code + ' — ' + item.description;
                            d.addEventListener('click', function() {
                                itemIdInput.value = item.id;
                                searchInput.value = item.item_code + ' — ' + item.description;
                                sugDiv.style.display = 'none';
                            });
                            d.addEventListener('mouseenter', function() { this.style.background = '#f0f4ff'; });
                            d.addEventListener('mouseleave', function() { this.style.background = ''; });
                            sugDiv.appendChild(d);
                        });
                        sugDiv.style.display = items.length ? '' : 'none';
                    });
            }, 250);
        });
        document.addEventListener('click', function(e) { if (!searchInput.contains(e.target) && !sugDiv.contains(e.target)) sugDiv.style.display = 'none'; });
    }
    </script>
</body>
</html>
