<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($recipe['version_name']) ?> — <?= htmlspecialchars($item['item_code']) ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .step-badge { display:inline-block; font-size:10px; font-weight:700; padding:2px 6px; border-radius:3px; text-transform:uppercase; letter-spacing:0.5px; }
        .step-badge-ing { background:#dcfce7; color:#166534; }
        .step-badge-ins { background:#dbeafe; color:#1e40af; }
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center; }
        .modal-overlay.active { display:flex; }
        .modal-box { background:#fff; border-radius:8px; padding:24px; max-width:500px; width:90%; box-shadow:0 10px 25px rgba(0,0,0,0.15); }
        .item-search-wrap { position:relative; }
        .item-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none; box-shadow:0 4px 6px rgba(0,0,0,0.1); }
        .item-suggestions div { padding:6px 10px; font-size:13px; cursor:pointer; }
        .item-suggestions div:hover { background:#eff6ff; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?>
                <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span>
            <?php endif; ?>
        </div>
    </header>

    <div style="max-width:960px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast">
                <?= htmlspecialchars($_SESSION['toast']['message']) ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">
                <?= htmlspecialchars($recipe['version_name']) ?>
                <span style="font-size:14px; color:#6b7280;">v<?= (int)$recipe['version_number'] ?></span>
                <?php if ($recipe['is_default']): ?>
                    <span style="color:#f59e0b; font-size:18px;" title="Default version">&#9733;</span>
                <?php endif; ?>
                <span class="badge <?= $recipe['is_active'] ? 'badge-active' : 'badge-inactive' ?>" style="font-size:12px; vertical-align:middle;">
                    <?= $recipe['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
            </h1>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="/items/<?= $item['id'] ?>/recipes" class="btn btn-secondary">&larr; Back to Recipes</a>
                <?php if ($recipe['is_active']): ?>
                    <a href="/items/<?= $item['id'] ?>/recipes/<?= $recipe['id'] ?>/edit" class="btn btn-secondary">Edit</a>
                    <?php if (!$recipe['is_default']): ?>
                    <form method="POST" action="/items/<?= $item['id'] ?>/recipes/<?= $recipe['id'] ?>/set-default" style="display:inline;">
                        <button type="submit" class="btn btn-secondary">Set as Default</button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" action="/items/<?= $item['id'] ?>/recipes/<?= $recipe['id'] ?>/deactivate" style="display:inline;">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Deactivate this version?')">Deactivate</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="/items/<?= $item['id'] ?>/recipes/<?= $recipe['id'] ?>/activate" style="display:inline;">
                        <button type="submit" class="btn btn-primary">Reactivate</button>
                    </form>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="document.getElementById('cloneModal').classList.add('active')">Clone</button>
            </div>
        </div>

        <!-- Header Info -->
        <div class="form-section" style="margin-bottom:16px;">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                <div>
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Item</strong>
                    <p style="margin:2px 0;">
                        <a href="/items/<?= $item['id'] ?>" style="color:#2563eb; text-decoration:none;">
                            <?= htmlspecialchars($item['item_code']) ?>
                        </a>
                        — <?= htmlspecialchars($item['description']) ?>
                    </p>
                </div>
                <div>
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Yield</strong>
                    <p style="margin:2px 0;"><?= number_format((float)$recipe['yield_percentage'], 2) ?>%</p>
                </div>
                <div>
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Created By</strong>
                    <p style="margin:2px 0;"><?= htmlspecialchars($recipe['created_by_name'] ?? '') ?> on <?= date('M j, Y', strtotime($recipe['created_at'])) ?></p>
                </div>
            </div>
            <?php if (!$recipe['is_active'] && $recipe['deactivated_by_name']): ?>
            <div style="margin-top:8px; padding:8px 12px; background:#fef2f2; border-radius:4px; font-size:13px; color:#991b1b;">
                Deactivated by <?= htmlspecialchars($recipe['deactivated_by_name']) ?> on <?= date('M j, Y g:ia', strtotime($recipe['deactivated_at'])) ?>
            </div>
            <?php endif; ?>
            <?php if ($recipe['notes']): ?>
            <div style="margin-top:8px;">
                <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Notes</strong>
                <p style="margin:2px 0;"><?= nl2br(htmlspecialchars($recipe['notes'])) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Steps -->
        <h3 style="margin-bottom:8px;">Formula Steps</h3>
        <?php
        // Calculate total for weight%
        $totalQty = 0;
        foreach ($steps as $s) {
            if ($s['step_type'] === 'INGREDIENT') $totalQty += (float)$s['quantity'];
        }
        ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th style="width:50px;">Type</th>
                    <th>Content</th>
                    <th style="width:100px; text-align:right;">Quantity</th>
                    <th style="width:60px;">UOM</th>
                    <th style="width:80px; text-align:right;">Weight %</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($steps)): ?>
                    <tr><td colspan="6" class="empty-state">No steps defined.</td></tr>
                <?php else: ?>
                    <?php $n = 1; foreach ($steps as $step): ?>
                    <tr>
                        <td style="color:#9ca3af;"><?= $n++ ?></td>
                        <td>
                            <span class="step-badge <?= $step['step_type'] === 'INGREDIENT' ? 'step-badge-ing' : 'step-badge-ins' ?>">
                                <?= $step['step_type'] === 'INGREDIENT' ? 'ING' : 'INS' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($step['step_type'] === 'INGREDIENT'): ?>
                                <a href="/items/<?= $step['item_id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;">
                                    <?= htmlspecialchars($step['item_code'] ?? '') ?>
                                </a>
                                <span style="color:#6b7280;"> — <?= htmlspecialchars($step['item_description'] ?? '') ?></span>
                            <?php else: ?>
                                <?= nl2br(htmlspecialchars($step['instruction_text'] ?? '')) ?>
                            <?php endif; ?>
                            <?php if ($step['notes']): ?>
                                <br><span style="font-size:11px; color:#9ca3af;">Note: <?= htmlspecialchars($step['notes']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <?= $step['step_type'] === 'INGREDIENT' ? number_format((float)$step['quantity'], 4) : '' ?>
                        </td>
                        <td><?= htmlspecialchars($step['uom_abbr'] ?? '') ?></td>
                        <td style="text-align:right;">
                            <?php if ($step['step_type'] === 'INGREDIENT' && $totalQty > 0): ?>
                                <?= number_format((float)$step['quantity'] / $totalQty * 100, 2) ?>%
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($totalQty > 0): ?>
                    <tr style="font-weight:600; border-top:2px solid #d1d5db;">
                        <td colspan="3" style="text-align:right;">Total</td>
                        <td style="text-align:right;"><?= number_format($totalQty, 4) ?></td>
                        <td></td>
                        <td style="text-align:right;">100.00%</td>
                    </tr>
                    <?php endif; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top:12px; font-size:11px; color:#9ca3af;">
            Updated <?= date('M j, Y g:ia', strtotime($recipe['updated_at'])) ?>
        </div>
    </div>

    <!-- Clone Modal -->
    <div id="cloneModal" class="modal-overlay" onclick="if(event.target===this) this.classList.remove('active')">
        <div class="modal-box">
            <h3 style="margin:0 0 16px;">Clone Recipe Version</h3>

            <!-- Option 1: Same item (GET — opens unsaved form) -->
            <div style="margin-bottom:16px;">
                <p style="font-size:14px; margin:0 0 8px;">Clone as new version on <strong><?= htmlspecialchars($item['item_code']) ?></strong></p>
                <a href="/items/<?= $item['id'] ?>/recipes/<?= $recipe['id'] ?>/clone" class="btn btn-primary">Clone to Same Item</a>
            </div>

            <hr style="border:none; border-top:1px solid #e5e7eb; margin:16px 0;">

            <!-- Option 2: Different item -->
            <form method="POST" action="/items/<?= $item['id'] ?>/recipes/<?= $recipe['id'] ?>/clone">
                <p style="font-size:14px; margin:0 0 8px;">Clone to a different item:</p>
                <div class="item-search-wrap" style="margin-bottom:8px;">
                    <input type="text" id="cloneItemSearch" placeholder="Search items..."
                           class="form-input" style="width:100%;" autocomplete="off">
                    <input type="hidden" name="target_item_id" id="cloneTargetId" value="">
                    <div class="item-suggestions" id="cloneSuggestions"></div>
                </div>
                <button type="submit" class="btn btn-primary" id="cloneToDiffBtn" disabled>Clone to Selected Item</button>
            </form>

            <div style="margin-top:16px; text-align:right;">
                <button class="btn btn-secondary" onclick="document.getElementById('cloneModal').classList.remove('active')">Cancel</button>
            </div>
        </div>
    </div>

    <script>
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);

    // Clone modal item search
    (function() {
        var input = document.getElementById('cloneItemSearch');
        var suggestions = document.getElementById('cloneSuggestions');
        var hidden = document.getElementById('cloneTargetId');
        var btn = document.getElementById('cloneToDiffBtn');
        var timer;

        if (!input) return;

        input.addEventListener('input', function() {
            clearTimeout(timer);
            var q = this.value.trim();
            hidden.value = '';
            btn.disabled = true;
            if (q.length < 1) { suggestions.style.display = 'none'; return; }
            timer = setTimeout(function() {
                fetch('/items/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        suggestions.innerHTML = '';
                        data.forEach(function(item) {
                            var d = document.createElement('div');
                            d.textContent = item.display;
                            d.addEventListener('click', function() {
                                input.value = item.display;
                                hidden.value = item.id;
                                btn.disabled = false;
                                suggestions.style.display = 'none';
                            });
                            suggestions.appendChild(d);
                        });
                        suggestions.style.display = data.length ? 'block' : 'none';
                    });
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                suggestions.style.display = 'none';
            }
        });
    })();
    </script>
</body>
</html>
