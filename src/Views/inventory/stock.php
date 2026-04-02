<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock On Hand — Precision Ink ERP</title>
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
            <h1 style="margin:0;">Stock On Hand</h1>
            <div style="display:flex; gap:8px;">
                <a href="/inventory/adjustments" class="btn btn-secondary">Manual Adjustment</a>
                <a href="/inventory/quarantine" class="btn btn-secondary">Quarantine</a>
                <a href="/inventory/counts" class="btn btn-secondary">Cycle Counts</a>
                <a href="/inventory/transactions" class="btn btn-secondary">Transactions</a>
            </div>
        </div>

        <form method="GET" action="/inventory" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; align-items:end;">
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Facility</label>
                <select name="facility_id" class="form-input" style="font-size:13px; padding:4px 8px;">
                    <option value="0">All Facilities</option>
                    <?php foreach ($facilities as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= $facilityId == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Search</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Code or description..." class="form-input" style="font-size:13px; padding:4px 8px; width:180px;">
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Type</label>
                <select name="item_type" class="form-input" style="font-size:13px; padding:4px 8px;">
                    <option value="">All</option>
                    <?php foreach (['RAW_MATERIAL'=>'Raw Material','FINISHED_GOOD'=>'Finished Good','INTERMEDIATE'=>'Intermediate','RESALE'=>'Resale','SERVICE'=>'Service'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($filters['item_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label style="display:flex; align-items:center; gap:4px; font-size:13px; cursor:pointer; padding-bottom:2px;">
                <input type="checkbox" name="below_min" <?= !empty($filters['below_min']) ? 'checked' : '' ?>> Below Reorder Min
            </label>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/inventory" class="btn btn-secondary" style="height:32px; text-decoration:none;">Clear</a>
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Item Code</th><th>Description</th><th>Type</th>
                    <?php if ($facilityId): ?><th>Location</th><?php endif; ?>
                    <th style="text-align:right;">On Hand</th>
                    <th style="text-align:right;">Batch Rsv</th>
                    <th style="text-align:right;">Quote Rsv</th>
                    <th style="text-align:right;">In Transit</th>
                    <th style="text-align:right;">Available</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="<?= $facilityId ? 9 : 8 ?>" class="empty-state">No inventory data.</td></tr>
                <?php else: foreach ($items as $i):
                    $belowMin = $i['reorder_min'] !== null && (float)$i['on_hand'] < (float)$i['reorder_min'];
                ?>
                <tr class="<?= $belowMin ? 'overdue-row' : '' ?>" style="<?= $belowMin ? 'background:#fef2f2;' : '' ?>">
                    <td><a href="/inventory/lots/<?= $i['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($i['item_code']) ?></a></td>
                    <td><?= htmlspecialchars($i['description']) ?></td>
                    <td><?php $tb = match($i['item_type']) { 'RAW_MATERIAL'=>'badge-info','FINISHED_GOOD'=>'badge-active','INTERMEDIATE'=>'badge-inactive', default=>'' }; ?>
                        <span class="badge <?= $tb ?>"><?= str_replace('_',' ',ucwords(strtolower($i['item_type']),'_')) ?></span></td>
                    <?php if ($facilityId): ?><td><?= htmlspecialchars($i['location']) ?></td><?php endif; ?>
                    <td style="text-align:right; font-weight:500;"><?= number_format((float)$i['on_hand'], 4) ?></td>
                    <td style="text-align:right; color:#6b7280;"><?= (float)$i['batch_reserved'] > 0 ? number_format((float)$i['batch_reserved'], 4) : '—' ?></td>
                    <td style="text-align:right; color:#6b7280;"><?= (float)$i['quote_reserved'] > 0 ? number_format((float)$i['quote_reserved'], 4) : '—' ?></td>
                    <td style="text-align:right; color:#6b7280;"><?= (float)$i['in_transit'] > 0 ? number_format((float)$i['in_transit'], 4) : '—' ?></td>
                    <td style="text-align:right; font-weight:600; <?= (float)$i['available'] <= 0 ? 'color:#dc2626;' : 'color:#16a34a;' ?>"><?= number_format((float)$i['available'], 4) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <p style="font-size:12px; color:#9ca3af; margin-top:8px; text-align:center;"><?= count($items) ?> items with inventory</p>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display='none'; }, 4000);</script>
</body>
</html>
