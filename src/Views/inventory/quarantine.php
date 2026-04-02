<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quarantined Lots — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
</head>
<body>
    <header class="app-header">
        <div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?><span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span><?php endif; ?>
        </div>
    </header>
    <div style="max-width:1200px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast"><?= htmlspecialchars($_SESSION['toast']['message']) ?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">Quarantined Lots</h1>
            <a href="/inventory" class="btn btn-secondary">&larr; Back to Stock</a>
        </div>

        <table class="data-table">
            <thead><tr><th>Item</th><th>Lot #</th><th>Facility</th><th style="text-align:right;">Qty</th><th>Reason</th><th>Quarantined By</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($lots)): ?>
                    <tr><td colspan="8" class="empty-state">No quarantined lots.</td></tr>
                <?php else: foreach ($lots as $lot): ?>
                <tr>
                    <td><a href="/items/<?= $lot['item_id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($lot['item_code']) ?></a> <span style="color:#6b7280; font-size:12px;"><?= htmlspecialchars($lot['item_description']) ?></span></td>
                    <td style="font-weight:500;"><?= htmlspecialchars($lot['lot_number']) ?></td>
                    <td><?= htmlspecialchars($lot['facility_name']) ?></td>
                    <td style="text-align:right;"><?= number_format((float)$lot['remaining_quantity'], 4) ?></td>
                    <td><?= htmlspecialchars($lot['quarantine_reason'] ?? '') ?></td>
                    <td><?= htmlspecialchars($lot['quarantined_by_name'] ?? '') ?></td>
                    <td><?= $lot['quarantined_at'] ? date('M j, Y g:ia', strtotime($lot['quarantined_at'])) : '' ?></td>
                    <td class="actions-cell">
                        <form method="POST" action="/inventory/release/<?= $lot['id'] ?>" style="display:inline;">
                            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Release this lot from quarantine?')">Release</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display='none'; }, 4000);</script>
</body>
</html>
