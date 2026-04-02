<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Requisitions — Precision Ink ERP</title>
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
            <h1 style="margin:0;">Purchase Requisitions</h1>
            <a href="/purchase-requisitions/create" class="btn btn-primary">+ New Requisition</a>
        </div>

        <?php if (!empty($pendingApprovals)): ?>
        <div style="padding:16px; background:#fef9c3; border:1px solid #fde68a; border-radius:8px; margin-bottom:16px;">
            <h3 style="margin:0 0 8px; font-size:14px; color:#854d0e;">Pending Approvals (<?= count($pendingApprovals) ?>)</h3>
            <table class="data-table" style="margin:0;">
                <thead><tr><th>REQ #</th><th>Requested By</th><th>Request Date</th><th>Required By</th><th>Items</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($pendingApprovals as $pa): ?>
                    <tr>
                        <td><a href="/purchase-requisitions/<?= $pa['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($pa['req_number']) ?></a></td>
                        <td><?= htmlspecialchars($pa['requested_by_name']) ?></td>
                        <td><?= date('M j, Y', strtotime($pa['request_date'])) ?></td>
                        <td style="<?= $pa['required_by_date'] && $pa['required_by_date'] < date('Y-m-d') ? 'color:#dc2626; font-weight:600;' : '' ?>">
                            <?= $pa['required_by_date'] ? date('M j, Y', strtotime($pa['required_by_date'])) : '—' ?>
                        </td>
                        <td><?= (int)$pa['line_count'] ?></td>
                        <td class="actions-cell"><a href="/purchase-requisitions/<?= $pa['id'] ?>" class="btn btn-sm btn-primary">Review</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <form method="GET" action="/purchase-requisitions" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; align-items:end;">
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Search</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="REQ # or justification..." class="form-input" style="font-size:13px; padding:4px 8px; width:200px;">
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Status</label>
                <select name="status" class="form-input" style="font-size:13px; padding:4px 8px;">
                    <option value="">All</option>
                    <?php foreach (['DRAFT','SUBMITTED','APPROVED','REJECTED','CONVERTED','CANCELLED'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label style="display:flex; align-items:center; gap:4px; font-size:13px; cursor:pointer; padding-bottom:2px;">
                <input type="checkbox" name="mine" <?= !empty($filters['mine']) ? 'checked' : '' ?>> My Requisitions
            </label>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/purchase-requisitions" class="btn btn-secondary" style="height:32px; text-decoration:none;">Clear</a>
        </form>

        <table class="data-table">
            <thead><tr><th>REQ #</th><th>Requested By</th><th>Request Date</th><th>Required By</th><th>Status</th><th>Items</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($requisitions)): ?>
                    <tr><td colspan="7" class="empty-state">No requisitions found.</td></tr>
                <?php else: foreach ($requisitions as $r): ?>
                <tr class="<?= $r['status'] === 'CANCELLED' ? 'inactive-row' : '' ?>">
                    <td><a href="/purchase-requisitions/<?= $r['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($r['req_number']) ?></a></td>
                    <td><?= htmlspecialchars($r['requested_by_name']) ?></td>
                    <td><?= date('M j, Y', strtotime($r['request_date'])) ?></td>
                    <td style="<?= $r['required_by_date'] && $r['required_by_date'] < date('Y-m-d') && !in_array($r['status'], ['CONVERTED','CANCELLED']) ? 'color:#dc2626; font-weight:600;' : '' ?>">
                        <?= $r['required_by_date'] ? date('M j, Y', strtotime($r['required_by_date'])) : '—' ?>
                    </td>
                    <td><?php
                        $sb = match($r['status']) { 'DRAFT'=>'badge-inactive','SUBMITTED'=>'badge-info','APPROVED'=>'badge-active','REJECTED'=>'badge-danger','CONVERTED'=>'badge-active','CANCELLED'=>'badge-inactive', default=>'' };
                    ?><span class="badge <?= $sb ?>" <?= $r['status'] === 'CANCELLED' ? 'style="text-decoration:line-through;"' : '' ?>><?= $r['status'] ?></span></td>
                    <td><?= (int)$r['line_count'] ?></td>
                    <td class="actions-cell">
                        <a href="/purchase-requisitions/<?= $r['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                        <?php if ($r['status'] === 'DRAFT'): ?>
                            <a href="/purchase-requisitions/<?= $r['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:center; gap:4px; margin-top:16px;">
            <?php for ($p = 1; $p <= $totalPages; $p++): $params = $filters; $params['page'] = $p; $qs = http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== false)); ?>
                <a href="/purchase-requisitions?<?= $qs ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>" style="min-width:32px; text-align:center; text-decoration:none;"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <p style="font-size:12px; color:#9ca3af; margin-top:8px; text-align:center;">Showing <?= count($requisitions) ?> of <?= $total ?></p>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display='none'; }, 4000);</script>
</body>
</html>
