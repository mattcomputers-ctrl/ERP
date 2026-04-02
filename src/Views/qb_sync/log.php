<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>QB Sync Log — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1200px;margin:24px auto;padding:0 16px;">
        <div style="margin-bottom:16px;"><a href="/qb-sync" style="color:#2563eb;">&larr; Back to QB Sync</a></div>
        <h1 style="margin:0 0 16px;">QuickBooks Sync Log</h1>

        <form method="GET" action="/qb-sync/log" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div>
                <label style="font-size:12px;display:block;margin-bottom:2px;">Status</label>
                <select name="status" class="form-input" style="font-size:13px;padding:4px 8px;">
                    <option value="">All</option>
                    <?php foreach (['SUCCESS','FAILED','PENDING','SKIPPED'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;display:block;margin-bottom:2px;">Type</label>
                <select name="type" class="form-input" style="font-size:13px;padding:4px 8px;">
                    <option value="">All</option>
                    <?php foreach (['invoice','bill','customer','vendor','item','inventory'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="height:32px;">Filter</button>
            <a href="/qb-sync/log" class="btn btn-secondary" style="height:32px;text-decoration:none;">Clear</a>
        </form>

        <table class="data-table" style="font-size:13px;">
            <thead>
                <tr><th>Date</th><th>Type</th><th>Record</th><th>Mode</th><th>Status</th><th>QB TXN ID</th><th>Error</th></tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="7" style="text-align:center;color:#9ca3af;">No sync log entries.</td></tr>
            <?php else: foreach ($logs as $log): ?>
                <tr>
                    <td><?= date('M j, Y g:ia', strtotime($log['created_at'])) ?></td>
                    <td><?= htmlspecialchars($log['sync_type']) ?></td>
                    <td><?= htmlspecialchars($log['record_type']) ?> #<?= (int)$log['record_id'] ?></td>
                    <td><span class="badge <?= $log['qb_mode'] === 'DESKTOP' ? 'badge-info' : 'badge-warning' ?>"><?= $log['qb_mode'] ?></span></td>
                    <td><span class="badge <?= match($log['status']) { 'SUCCESS' => 'badge-active', 'FAILED' => 'badge-danger', 'PENDING' => 'badge-warning', default => 'badge-info' } ?>"><?= $log['status'] ?></span></td>
                    <td style="font-size:11px;color:#6b7280;"><?= htmlspecialchars($log['qb_txn_id'] ?? '') ?></td>
                    <td style="font-size:11px;color:#dc2626;"><?= htmlspecialchars($log['error_message'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <script src="/assets/js/settings.js"></script>
</body>
</html>
