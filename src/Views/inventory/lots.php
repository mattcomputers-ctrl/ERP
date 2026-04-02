<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIFO Lots — <?= htmlspecialchars($item['item_code']) ?> — Precision Ink ERP</title>
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
            <h1 style="margin:0;">
                FIFO Lots: <?= htmlspecialchars($item['item_code']) ?>
                <span style="font-size:14px; font-weight:400; color:#6b7280;"> — <?= htmlspecialchars($item['description']) ?></span>
            </h1>
            <a href="/inventory" class="btn btn-secondary">&larr; Back to Stock</a>
        </div>

        <table class="data-table">
            <thead>
                <tr><th>Lot Number</th><th>Source</th><th>Created</th><th>Expiration</th><th>Location</th><th>Status</th><th style="text-align:right;">Remaining</th><th style="text-align:right;">Unit Cost</th><th style="text-align:right;">Total Value</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (empty($lots)): ?>
                    <tr><td colspan="10" class="empty-state">No FIFO lots for this item at this facility.</td></tr>
                <?php else: foreach ($lots as $lot):
                    $expired = $lot['expiration_date'] && $lot['expiration_date'] < date('Y-m-d');
                    $nearExpiry = $lot['expiration_date'] && !$expired && $lot['expiration_date'] <= date('Y-m-d', strtotime('+30 days'));
                    $totalVal = (float)$lot['remaining_quantity'] * (float)$lot['unit_cost'];
                ?>
                <tr class="<?= $lot['status'] === 'QUARANTINED' ? 'inactive-row' : '' ?>">
                    <td style="font-weight:500;"><?= htmlspecialchars($lot['lot_number']) ?></td>
                    <td><span class="badge"><?= $lot['source_type'] ?></span></td>
                    <td><?= date('M j, Y', strtotime($lot['created_at'])) ?></td>
                    <td style="<?= $expired ? 'color:#dc2626; font-weight:600;' : ($nearExpiry ? 'color:#f59e0b; font-weight:600;' : '') ?>">
                        <?= $lot['expiration_date'] ? date('M j, Y', strtotime($lot['expiration_date'])) : '—' ?>
                        <?= $expired ? ' (EXPIRED)' : ($nearExpiry ? ' (EXPIRING)' : '') ?>
                    </td>
                    <td><?= htmlspecialchars($lot['location'] ?? '') ?></td>
                    <td><?php
                        $sb = match($lot['status']) { 'AVAILABLE'=>'badge-active','PENDING_INSPECTION'=>'badge-warning','QUARANTINED'=>'badge-danger','EXPIRED'=>'badge-inactive', default=>'' };
                    ?><span class="badge <?= $sb ?>"><?= $lot['status'] ?></span></td>
                    <td style="text-align:right;"><?= number_format((float)$lot['remaining_quantity'], 4) ?></td>
                    <td style="text-align:right;">$<?= number_format((float)$lot['unit_cost'], 4) ?></td>
                    <td style="text-align:right;">$<?= number_format($totalVal, 2) ?></td>
                    <td class="actions-cell">
                        <?php if ($lot['status'] === 'AVAILABLE' && (float)$lot['remaining_quantity'] > 0): ?>
                        <form method="POST" action="/inventory/quarantine/<?= $lot['id'] ?>" style="display:inline;" onsubmit="var r=prompt('Quarantine reason:'); if(!r){return false;} this.querySelector('[name=reason]').value=r;">
                            <input type="hidden" name="reason" value="">
                            <button type="submit" class="btn btn-sm btn-warning">Quarantine</button>
                        </form>
                        <?php elseif ($lot['status'] === 'QUARANTINED'): ?>
                        <form method="POST" action="/inventory/release/<?= $lot['id'] ?>" style="display:inline;">
                            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Release from quarantine?')">Release</button>
                        </form>
                        <?php endif; ?>
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
