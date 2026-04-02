<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Count Entry — Session #<?= $session['id'] ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
    <header class="app-header">
        <div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?><span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span><?php endif; ?>
        </div>
    </header>
    <div style="max-width:1000px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast"><?= htmlspecialchars($_SESSION['toast']['message']) ?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">
                Count Entry — Session #<?= $session['id'] ?>
                <span class="badge <?= $session['is_blind'] ? 'badge-warning' : 'badge-info' ?>" style="font-size:12px; vertical-align:middle;"><?= $session['is_blind'] ? 'BLIND' : 'NON-BLIND' ?></span>
                <span class="badge badge-info" style="font-size:12px; vertical-align:middle;"><?= $session['status'] ?></span>
            </h1>
            <div style="display:flex; gap:8px;">
                <a href="/inventory/counts/<?= $session['id'] ?>/sheet" class="btn btn-secondary" target="_blank">Print Sheet</a>
                <a href="/inventory/counts" class="btn btn-secondary">&larr; Back</a>
            </div>
        </div>
        <p style="font-size:13px; color:#6b7280; margin-bottom:16px;">Facility: <?= htmlspecialchars($session['facility_name']) ?> | Created: <?= date('M j, Y', strtotime($session['created_at'])) ?> by <?= htmlspecialchars($session['created_by_name']) ?></p>

        <?php if ($session['status'] !== 'OPEN'): ?>
            <div class="toast toast-warning" style="margin-bottom:16px;">This session is <?= $session['status'] ?> — counts are read-only.</div>
        <?php endif; ?>

        <form method="POST" action="/inventory/counts/<?= $session['id'] ?>/entry">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Location</th><th>Item Code</th><th>Description</th>
                        <?php if (!$session['is_blind']): ?><th style="text-align:right;">Book Qty</th><?php endif; ?>
                        <th style="text-align:right;">Counted Qty</th>
                        <?php if (!$session['is_blind']): ?><th style="text-align:right;">Variance</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $l): ?>
                    <tr x-data="{ counted: '<?= $l['counted_quantity'] !== null ? htmlspecialchars($l['counted_quantity']) : '' ?>', book: <?= (float)$l['book_quantity'] ?> }">
                        <td><?= htmlspecialchars($l['location']) ?></td>
                        <td style="font-weight:500;"><?= htmlspecialchars($l['item_code']) ?></td>
                        <td><?= htmlspecialchars($l['item_description']) ?></td>
                        <?php if (!$session['is_blind']): ?>
                        <td style="text-align:right;"><?= number_format((float)$l['book_quantity'], 4) ?></td>
                        <?php endif; ?>
                        <td style="text-align:right;">
                            <?php if ($session['status'] === 'OPEN'): ?>
                            <input type="number" step="0.0001" min="0" name="counts[<?= $l['id'] ?>]"
                                   x-model="counted" class="form-input" style="width:120px; text-align:right; font-size:13px; padding:4px 8px;"
                                   value="<?= $l['counted_quantity'] !== null ? htmlspecialchars($l['counted_quantity']) : '' ?>">
                            <?php else: ?>
                            <?= $l['counted_quantity'] !== null ? number_format((float)$l['counted_quantity'], 4) : '<span style="color:#9ca3af;">—</span>' ?>
                            <?php endif; ?>
                        </td>
                        <?php if (!$session['is_blind']): ?>
                        <td style="text-align:right;">
                            <span x-text="counted !== '' ? (parseFloat(counted) - book).toFixed(4) : '—'"
                                  :style="counted !== '' && (parseFloat(counted) - book) !== 0 ? 'color:#dc2626; font-weight:600;' : 'color:#6b7280;'"></span>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($session['status'] === 'OPEN'): ?>
            <div style="margin-top:16px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-secondary">Save Counts</button>
                <button type="submit" name="mark_complete" value="1" class="btn btn-primary" onclick="return confirm('Mark this session for review? You can still edit counts until posting.')">Mark Complete &amp; Review</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display='none'; }, 4000);</script>
</body>
</html>
