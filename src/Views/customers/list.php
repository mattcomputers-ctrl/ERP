<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers — Precision Ink ERP</title>
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
            <h1 style="margin:0;">Customers</h1>
            <a href="/customers/create" class="btn btn-primary">+ New Customer</a>
        </div>

        <form method="GET" action="/customers" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; align-items:end;">
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Search</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Code or name..." class="form-input" style="font-size:13px; padding:4px 8px; width:200px;">
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Sales Rep</label>
                <select name="sales_rep_id" class="form-input" style="font-size:13px; padding:4px 8px;">
                    <option value="">All</option>
                    <?php foreach ($reps as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= ($filters['sales_rep_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Active</label>
                <select name="active" class="form-input" style="font-size:13px; padding:4px 8px;">
                    <option value="1" <?= ($filters['active'] ?? '1') === '1' ? 'selected' : '' ?>>Active Only</option>
                    <option value="0" <?= ($filters['active'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
                    <option value="" <?= ($filters['active'] ?? '1') === '' ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/customers" class="btn btn-secondary" style="height:32px; text-decoration:none;">Clear</a>
        </form>

        <table class="data-table">
            <thead>
                <tr><th>Code</th><th>Company Name</th><th>City/State</th><th>Phone</th><th>Sales Rep</th><th style="text-align:right;">Credit Limit</th><th style="text-align:right;">AR Balance</th><th>Hold</th><th>Active</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr><td colspan="10" class="empty-state">No customers found.</td></tr>
                <?php else: foreach ($customers as $c): ?>
                    <tr class="<?= !$c['active'] ? 'inactive-row' : '' ?>">
                        <td><a href="/customers/<?= $c['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($c['customer_code']) ?></a></td>
                        <td><?= htmlspecialchars($c['company_name']) ?></td>
                        <td><?php $cs = array_filter([$c['billing_city'], $c['billing_state']]); echo htmlspecialchars(implode(', ', $cs)) ?: '<span style="color:#9ca3af;">—</span>'; ?></td>
                        <td><?= $c['phone'] ? htmlspecialchars($c['phone']) : '' ?></td>
                        <td><?= htmlspecialchars($c['sales_rep_name'] ?? '') ?></td>
                        <td style="text-align:right;"><?= $c['credit_limit'] > 0 ? '$' . number_format((float)$c['credit_limit'], 2) : '<span style="color:#9ca3af;">No Limit</span>' ?></td>
                        <td style="text-align:right; <?= ($c['credit_limit'] > 0 && (float)$c['ar_balance'] > (float)$c['credit_limit']) ? 'color:#dc2626; font-weight:600;' : '' ?>">$<?= number_format((float)$c['ar_balance'], 2) ?></td>
                        <td><?= $c['account_hold'] ? '<span class="badge badge-danger">HOLD</span>' : '' ?></td>
                        <td><span class="badge <?= $c['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $c['active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td class="actions-cell">
                            <a href="/customers/<?= $c['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            <a href="/customers/<?= $c['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:center; gap:4px; margin-top:16px;">
            <?php for ($p = 1; $p <= $totalPages; $p++): $params = $filters; $params['page'] = $p; $qs = http_build_query(array_filter($params, fn($v) => $v !== '')); ?>
                <a href="/customers?<?= $qs ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>" style="min-width:32px; text-align:center; text-decoration:none;"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <p style="font-size:12px; color:#9ca3af; margin-top:8px; text-align:center;">Showing <?= count($customers) ?> of <?= $total ?> customers</p>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);</script>
</body>
</html>
