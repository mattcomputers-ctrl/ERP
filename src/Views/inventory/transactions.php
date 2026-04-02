<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Transactions — Precision Ink ERP</title>
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
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">Inventory Transactions</h1>
            <a href="/inventory" class="btn btn-secondary">&larr; Back to Stock</a>
        </div>

        <table class="data-table">
            <thead><tr><th>Date</th><th>Item</th><th>Facility</th><th>Lot #</th><th>Type</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Unit Cost</th><th>Reference</th><th>User</th></tr></thead>
            <tbody>
                <?php if (empty($txns)): ?>
                    <tr><td colspan="9" class="empty-state">No transactions.</td></tr>
                <?php else: foreach ($txns as $t): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= date('M j, Y g:ia', strtotime($t['created_at'])) ?></td>
                    <td><a href="/items/<?= $t['item_id'] ?>" style="color:#2563eb; text-decoration:none;"><?= htmlspecialchars($t['item_code']) ?></a></td>
                    <td><?= htmlspecialchars($t['facility_name']) ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($t['lot_number'] ?? '') ?></td>
                    <td><span class="badge <?= (float)$t['quantity'] > 0 ? 'badge-active' : 'badge-danger' ?>"><?= htmlspecialchars($t['transaction_type']) ?></span></td>
                    <td style="text-align:right; font-weight:500; <?= (float)$t['quantity'] < 0 ? 'color:#dc2626;' : 'color:#16a34a;' ?>"><?= number_format((float)$t['quantity'], 4) ?></td>
                    <td style="text-align:right;">$<?= number_format((float)$t['unit_cost'], 4) ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($t['reference_type']) ?> #<?= $t['reference_id'] ?></td>
                    <td><?= htmlspecialchars($t['user_name'] ?? '') ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:center; gap:4px; margin-top:16px;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="/inventory/transactions?page=<?= $p ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>" style="min-width:32px; text-align:center; text-decoration:none;"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <p style="font-size:12px; color:#9ca3af; margin-top:8px; text-align:center;">Showing <?= count($txns) ?> of <?= $total ?> transactions</p>
    </div>
    <script src="/assets/js/settings.js"></script>
</body>
</html>
