<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cycle Counts — Precision Ink ERP</title>
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
    <div style="max-width:1000px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast"><?= htmlspecialchars($_SESSION['toast']['message']) ?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">Cycle Count Sessions</h1>
            <div style="display:flex; gap:8px;">
                <a href="/inventory" class="btn btn-secondary">&larr; Back to Stock</a>
                <a href="/inventory/counts/create" class="btn btn-primary">+ New Count</a>
            </div>
        </div>

        <table class="data-table">
            <thead><tr><th>#</th><th>Facility</th><th>Blind</th><th>Items</th><th>Counted</th><th>Status</th><th>Created By</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($sessions)): ?>
                    <tr><td colspan="9" class="empty-state">No count sessions.</td></tr>
                <?php else: foreach ($sessions as $s): ?>
                <tr class="<?= $s['status'] === 'CANCELLED' ? 'inactive-row' : '' ?>">
                    <td style="font-weight:500;"><?= $s['id'] ?></td>
                    <td><?= htmlspecialchars($s['facility_name']) ?></td>
                    <td><?= $s['is_blind'] ? 'Yes' : 'No' ?></td>
                    <td><?= (int)$s['line_count'] ?></td>
                    <td><?= (int)$s['counted_count'] ?> / <?= (int)$s['line_count'] ?></td>
                    <td><?php
                        $sb = match($s['status']) { 'OPEN'=>'badge-info','IN_REVIEW'=>'badge-warning','POSTED'=>'badge-active','CANCELLED'=>'badge-inactive', default=>'' };
                    ?><span class="badge <?= $sb ?>"><?= $s['status'] ?></span></td>
                    <td><?= htmlspecialchars($s['created_by_name']) ?></td>
                    <td><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
                    <td class="actions-cell">
                        <?php if ($s['status'] === 'OPEN'): ?>
                            <a href="/inventory/counts/<?= $s['id'] ?>" class="btn btn-sm btn-primary">Enter Counts</a>
                            <a href="/inventory/counts/<?= $s['id'] ?>/sheet" class="btn btn-sm btn-secondary" target="_blank">Print Sheet</a>
                        <?php elseif ($s['status'] === 'IN_REVIEW'): ?>
                            <a href="/inventory/counts/<?= $s['id'] ?>/review" class="btn btn-sm btn-primary">Review</a>
                        <?php else: ?>
                            <a href="/inventory/counts/<?= $s['id'] ?>/review" class="btn btn-sm btn-secondary">View</a>
                        <?php endif; ?>
                        <?php if (in_array($s['status'], ['OPEN', 'IN_REVIEW'])): ?>
                            <form method="POST" action="/inventory/counts/<?= $s['id'] ?>/cancel" style="display:inline;">
                                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Cancel?')">Cancel</button>
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
