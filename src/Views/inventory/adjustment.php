<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Adjustment — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .item-search-wrap { position:relative; }
        .item-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none; box-shadow:0 4px 6px rgba(0,0,0,.1); }
        .item-suggestions div { padding:6px 10px; font-size:13px; cursor:pointer; }
        .item-suggestions div:hover { background:#eff6ff; }
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
    <div style="max-width:600px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast"><?= htmlspecialchars($_SESSION['toast']['message']) ?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">Manual Inventory Adjustment</h1>
            <a href="/inventory" class="btn btn-secondary">&larr; Back</a>
        </div>

        <form method="POST" action="/inventory/adjustments">
            <div class="form-section">
                <div class="item-search-wrap" style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Item <span style="color:red;">*</span></label>
                    <input type="text" id="adjItemSearch" placeholder="Search items..." class="form-input" style="width:100%;" autocomplete="off">
                    <input type="hidden" name="item_id" id="adjItemId" required>
                    <div class="item-suggestions" id="adjSuggestions"></div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Facility <span style="color:red;">*</span></label>
                    <select name="facility_id" class="form-input" style="width:100%;" required>
                        <?php foreach ($facilities as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= $activeFacilityId == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Quantity <span style="color:red;">*</span></label>
                    <input type="number" step="0.0001" name="quantity" class="form-input" style="width:100%;" required placeholder="Positive = add, negative = reduce">
                    <p style="font-size:11px; color:#6b7280; margin-top:2px;">Positive values add inventory, negative values consume from oldest FIFO lots.</p>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Lot Number (optional)</label>
                    <input type="text" name="lot_number" class="form-input" style="width:100%;" placeholder="Auto-generated if blank">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Reason Code</label>
                    <select name="reason_code_id" class="form-input" style="width:100%;">
                        <option value="">— Select —</option>
                        <?php foreach ($reasonCodes as $rc): ?>
                        <option value="<?= $rc['id'] ?>"><?= htmlspecialchars($rc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Notes</label>
                    <textarea name="notes" rows="3" class="form-input" style="width:100%;"></textarea>
                </div>
            </div>
            <div style="margin-top:16px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Save Adjustment</button>
                <a href="/inventory" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>
    var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display='none'; }, 4000);
    // Item search
    (function() {
        var input = document.getElementById('adjItemSearch');
        var sugs = document.getElementById('adjSuggestions');
        var hidden = document.getElementById('adjItemId');
        var timer;
        input.addEventListener('input', function() {
            clearTimeout(timer); var q = this.value.trim();
            if (q.length < 1) { sugs.style.display = 'none'; return; }
            timer = setTimeout(function() {
                fetch('/items/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        sugs.innerHTML = '';
                        data.forEach(function(item) {
                            var d = document.createElement('div');
                            d.textContent = item.display;
                            d.addEventListener('click', function() { input.value = item.display; hidden.value = item.id; sugs.style.display = 'none'; });
                            sugs.appendChild(d);
                        });
                        sugs.style.display = data.length ? 'block' : 'none';
                    });
            }, 300);
        });
        document.addEventListener('click', function(e) { if (!input.contains(e.target) && !sugs.contains(e.target)) sugs.style.display = 'none'; });
    })();
    </script>
</body>
</html>
