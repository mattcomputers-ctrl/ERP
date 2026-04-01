<h1>Notification Log</h1>

<form method="GET" action="/settings/notification-log" class="filter-bar" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end; margin-bottom:20px;">
    <div class="form-group" style="min-width:160px;">
        <label>Alert Type</label>
        <select name="alert_type" class="form-control">
            <option value="">All Types</option>
            <?php foreach ($alertTypes as $at): ?>
                <option value="<?= htmlspecialchars($at) ?>" <?= ($filters['alertType'] ?? '') === $at ? 'selected' : '' ?>>
                    <?= htmlspecialchars($labelMap[$at] ?? ucwords(str_replace('_', ' ', $at))) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>From</label>
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['dateFrom'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label>To</label>
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['dateTo'] ?? '') ?>">
    </div>
    <div class="form-group" style="min-width:120px;">
        <label>Status</label>
        <select name="status" class="form-control">
            <option value="">All</option>
            <option value="SENT" <?= ($filters['status'] ?? '') === 'SENT' ? 'selected' : '' ?>>Sent</option>
            <option value="FAILED" <?= ($filters['status'] ?? '') === 'FAILED' ? 'selected' : '' ?>>Failed</option>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-primary">Apply</button>
        <a href="/settings/notification-log" class="btn btn-secondary" style="margin-left:4px;">Clear</a>
    </div>
</form>

<p style="color:#666; margin-bottom:10px;"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found</p>

<table class="data-table">
    <thead>
        <tr>
            <th>Timestamp</th>
            <th>Alert Type</th>
            <th>Recipients</th>
            <th>Reference</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="5" style="text-align:center; color:#999;">No notification log entries found.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td><?= htmlspecialchars($labelMap[$row['alert_type']] ?? ucwords(str_replace('_', ' ', $row['alert_type']))) ?></td>
                    <td><?= htmlspecialchars($row['recipients']) ?></td>
                    <td>
                        <?php if ($row['reference_type']): ?>
                            <?= htmlspecialchars($row['reference_type']) ?> #<?= htmlspecialchars($row['reference_id']) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $row['status'] === 'SENT' ? 'success' : 'danger' ?>">
                            <?= htmlspecialchars($row['status']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top:16px; display:flex; gap:4px; align-items:center;">
        <?php
        $qs = $_GET;
        unset($qs['page']);
        $base = '/settings/notification-log?' . http_build_query($qs);
        ?>
        <?php if ($page > 1): ?>
            <a href="<?= $base . '&page=' . ($page - 1) ?>" class="btn btn-secondary btn-sm">&laquo; Prev</a>
        <?php endif; ?>
        <span style="padding:0 8px;">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="<?= $base . '&page=' . ($page + 1) ?>" class="btn btn-secondary btn-sm">Next &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
