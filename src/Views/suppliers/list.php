<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
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

    <div style="max-width:1200px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast">
                <?= htmlspecialchars($_SESSION['toast']['message']) ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">Suppliers</h1>
            <a href="/suppliers/create" class="btn btn-primary">+ New Supplier</a>
        </div>

        <!-- Filters -->
        <form method="GET" action="/suppliers" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; align-items:end;">
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Search</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Code or name..." class="form-input" style="font-size:13px; padding:4px 8px; width:200px;">
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Active</label>
                <select name="active" class="form-input" style="font-size:13px; padding:4px 8px;">
                    <option value="1" <?= ($filters['active'] ?? '1') === '1' ? 'selected' : '' ?>>Active Only</option>
                    <option value="0" <?= ($filters['active'] ?? '') === '0' ? 'selected' : '' ?>>Inactive Only</option>
                    <option value="" <?= ($filters['active'] ?? '1') === '' ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/suppliers" class="btn btn-secondary" style="height:32px; text-decoration:none;">Clear</a>
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Supplier Code</th>
                    <th>Company Name</th>
                    <th>City / State</th>
                    <th>Phone</th>
                    <th>Payment Terms</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr><td colspan="7" class="empty-state">No suppliers found.</td></tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $sup): ?>
                    <tr class="<?= !$sup['active'] ? 'inactive-row' : '' ?>">
                        <td>
                            <a href="/suppliers/<?= $sup['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;">
                                <?= htmlspecialchars($sup['supplier_code']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($sup['company_name']) ?></td>
                        <td>
                            <?php
                            $cityState = array_filter([$sup['city'], $sup['state']]);
                            echo htmlspecialchars(implode(', ', $cityState)) ?: '<span style="color:#9ca3af;">—</span>';
                            ?>
                        </td>
                        <td><?= $sup['phone'] ? htmlspecialchars($sup['phone']) : '<span style="color:#9ca3af;">—</span>' ?></td>
                        <td><?= $sup['payment_terms_name'] ? htmlspecialchars($sup['payment_terms_name']) : '<span style="color:#9ca3af;">—</span>' ?></td>
                        <td>
                            <span class="badge <?= $sup['active'] ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $sup['active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="actions-cell">
                            <a href="/suppliers/<?= $sup['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            <a href="/suppliers/<?= $sup['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:center; gap:4px; margin-top:16px;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php
                $params = $filters;
                $params['page'] = $p;
                $qs = http_build_query(array_filter($params, fn($v) => $v !== ''));
                ?>
                <a href="/suppliers?<?= $qs ?>"
                   class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"
                   style="min-width:32px; text-align:center; text-decoration:none;">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <p style="font-size:12px; color:#9ca3af; margin-top:8px; text-align:center;">
            Showing <?= count($suppliers) ?> of <?= $total ?> suppliers
        </p>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);
    </script>
</body>
</html>
