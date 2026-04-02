<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders — Precision Ink ERP</title>
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
            <h1 style="margin:0;">Purchase Orders</h1>
            <a href="/purchase-orders/create" class="btn btn-primary">+ New PO</a>
        </div>

        <form method="GET" action="/purchase-orders" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; align-items:end;">
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Supplier</label>
                <input type="text" name="supplier" value="<?= htmlspecialchars($filters['supplier'] ?? '') ?>" placeholder="Code or name..." class="form-input" style="font-size:13px; padding:4px 8px; width:160px;">
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Facility</label>
                <select name="facility_id" class="form-input" style="font-size:13px; padding:4px 8px;">
                    <option value="">All</option>
                    <?php foreach ($facilities as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= ($filters['facility_id'] ?? 0) == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Status</label>
                <select name="status" class="form-input" style="font-size:13px; padding:4px 8px;">
                    <option value="">All</option>
                    <?php foreach (['DRAFT','SENT','PARTIAL','RECEIVED','CANCELLED'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" class="form-input" style="font-size:13px; padding:4px 8px;">
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" class="form-input" style="font-size:13px; padding:4px 8px;">
            </div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/purchase-orders" class="btn btn-secondary" style="height:32px; text-decoration:none;">Clear</a>
        </form>

        <table class="data-table">
            <thead>
                <tr><th>PO #</th><th>Type</th><th>Supplier</th><th>Facility</th><th>Order Date</th><th>Expected Delivery</th><th>Status</th><th>Lines</th><th style="text-align:right;">Value</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="10" class="empty-state">No purchase orders found.</td></tr>
                <?php else: foreach ($orders as $o):
                    $pastDue = $o['expected_delivery_date'] && $o['expected_delivery_date'] < date('Y-m-d') && !in_array($o['status'], ['RECEIVED','CANCELLED']);
                ?>
                <tr class="<?= $o['status'] === 'CANCELLED' ? 'inactive-row' : '' ?>">
                    <td><a href="/purchase-orders/<?= $o['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($o['po_number']) ?></a></td>
                    <td><span class="badge <?= $o['po_type'] === 'BLANKET' ? 'badge-warning' : 'badge-inactive' ?>"><?= $o['po_type'] ?></span></td>
                    <td><?= htmlspecialchars($o['supplier_code'] . ' — ' . $o['supplier_name']) ?></td>
                    <td><?= htmlspecialchars($o['facility_name'] ?? '') ?></td>
                    <td><?= date('M j, Y', strtotime($o['order_date'])) ?></td>
                    <td style="<?= $pastDue ? 'color:#dc2626; font-weight:600;' : '' ?>"><?= $o['expected_delivery_date'] ? date('M j, Y', strtotime($o['expected_delivery_date'])) : '—' ?></td>
                    <td><?php
                        $sb = match($o['status']) { 'DRAFT'=>'badge-inactive','SENT'=>'badge-info','PARTIAL'=>'badge-warning','RECEIVED'=>'badge-active','CANCELLED'=>'badge-danger', default=>'' };
                    ?><span class="badge <?= $sb ?>" <?= $o['status']==='CANCELLED' ? 'style="text-decoration:line-through;"' : '' ?>><?= $o['status'] ?><?= $o['revision_number'] > 0 ? ' R'.$o['revision_number'] : '' ?></span></td>
                    <td><?= (int)$o['line_count'] ?></td>
                    <td style="text-align:right;">$<?= number_format((float)$o['po_value'], 2) ?></td>
                    <td class="actions-cell">
                        <a href="/purchase-orders/<?= $o['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                        <?php if ($o['status'] === 'DRAFT'): ?>
                            <a href="/purchase-orders/<?= $o['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:center; gap:4px; margin-top:16px;">
            <?php for ($p = 1; $p <= $totalPages; $p++): $params = $filters; $params['page'] = $p; $qs = http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== 0)); ?>
                <a href="/purchase-orders?<?= $qs ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>" style="min-width:32px; text-align:center; text-decoration:none;"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <p style="font-size:12px; color:#9ca3af; margin-top:8px; text-align:center;">Showing <?= count($orders) ?> of <?= $total ?></p>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display='none'; }, 4000);</script>
</body>
</html>
