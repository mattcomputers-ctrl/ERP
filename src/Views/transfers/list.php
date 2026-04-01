<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Orders — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
</head>
<body>
    <header class="app-header">
        <div class="header-left">
            <a href="/" class="app-logo">Precision Ink ERP</a>
        </div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php
            $__facSvc = $this->facilityService ?? null;
            $__currentUser = $_SESSION['user'] ?? null;
            if ($__facSvc && $__currentUser):
                $__activeFacilityCount = $__facSvc->getActiveFacilityCount();
                if ($__activeFacilityCount > 1):
                    $__userFacilities = $__facSvc->getUserFacilities((int)$__currentUser['id']);
                    $__activeFacility = $__facSvc->getActiveFacility((int)$__currentUser['id']);
                    if (count($__userFacilities) > 1):
            ?>
            <form method="POST" action="/facility/switch" id="facility-form" style="margin:0;">
                <select name="facility_id" onchange="this.form.submit()" style="padding:4px 8px; border-radius:4px; border:1px solid #555; background:#fff; font-size:13px;">
                    <?php foreach ($__userFacilities as $__f): ?>
                    <option value="<?= $__f['id'] ?>" <?= $__f['id'] == $__activeFacility['id'] ? 'selected' : '' ?>><?= htmlspecialchars($__f['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php elseif (count($__userFacilities) === 1): ?>
            <span style="color:#fff; font-size:13px;"><?= htmlspecialchars($__activeFacility['name']) ?></span>
            <?php endif; endif; endif; ?>

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
            <h1 style="margin:0;">Transfer Orders</h1>
            <a href="/transfers/create" class="btn btn-primary">+ New Transfer</a>
        </div>

        <!-- Filters -->
        <form method="GET" action="/transfers" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; align-items:end;">
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">Status</label>
                <select name="status" class="form-input" style="font-size:13px; padding:4px 8px;">
                    <option value="">All</option>
                    <?php foreach (['DRAFT','SHIPPED','RECEIVED','CANCELLED'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">From Facility</label>
                <select name="from_facility" class="form-input" style="font-size:13px; padding:4px 8px;">
                    <option value="">All</option>
                    <?php foreach ($facilities as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= ($filters['from_facility'] ?? 0) == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">To Facility</label>
                <select name="to_facility" class="form-input" style="font-size:13px; padding:4px 8px;">
                    <option value="">All</option>
                    <?php foreach ($facilities as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= ($filters['to_facility'] ?? 0) == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">From Date</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" class="form-input" style="font-size:13px; padding:4px 8px;">
            </div>
            <div>
                <label style="font-size:12px; display:block; margin-bottom:2px;">To Date</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" class="form-input" style="font-size:13px; padding:4px 8px;">
            </div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/transfers" class="btn btn-secondary" style="height:32px; text-decoration:none;">Clear</a>
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>TRF #</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Requested Date</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transfers)): ?>
                    <tr><td colspan="7" class="empty-state">No transfer orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($transfers as $t): ?>
                    <tr class="<?= $t['status'] === 'CANCELLED' ? 'inactive-row' : '' ?>">
                        <td><a href="/transfers/<?= $t['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($t['trf_number']) ?></a></td>
                        <td><?= htmlspecialchars($t['from_facility_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($t['to_facility_name'] ?? '') ?></td>
                        <td><?= $t['requested_date'] ? date('M j, Y', strtotime($t['requested_date'])) : '' ?></td>
                        <td>
                            <?php
                            $badgeClass = match($t['status']) {
                                'DRAFT' => 'badge-inactive',
                                'SHIPPED' => 'badge-info',
                                'RECEIVED' => 'badge-active',
                                'CANCELLED' => 'badge-danger',
                                default => ''
                            };
                            ?>
                            <span class="badge <?= $badgeClass ?>" <?= $t['status'] === 'CANCELLED' ? 'style="text-decoration:line-through;"' : '' ?>>
                                <?= htmlspecialchars($t['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($t['created_by_name'] ?? '') ?></td>
                        <td class="actions-cell">
                            <a href="/transfers/<?= $t['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            <?php if ($t['status'] === 'DRAFT'): ?>
                                <a href="/transfers/<?= $t['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:center; gap:4px; margin-top:16px;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php
                $params = $filters;
                $params['page'] = $p;
                $qs = http_build_query(array_filter($params));
                ?>
                <a href="/transfers?<?= $qs ?>"
                   class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"
                   style="min-width:32px; text-align:center; text-decoration:none;">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <p style="font-size:12px; color:#9ca3af; margin-top:8px; text-align:center;">
            Showing <?= count($transfers) ?> of <?= $total ?> transfer orders
        </p>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    // Auto-dismiss toast
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);
    </script>
</body>
</html>
