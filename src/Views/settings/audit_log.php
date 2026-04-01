<h1>Audit Log</h1>

<form method="GET" action="/settings/audit-log" class="filter-bar" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end; margin-bottom:20px;">
    <div class="form-group" style="min-width:150px;">
        <label>User</label>
        <select name="user_id" class="form-control">
            <option value="">All Users</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= ($filters['userId'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['username']) ?>
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
    <div class="form-group" style="min-width:140px;">
        <label>Module</label>
        <select name="module" class="form-control">
            <option value="">All Modules</option>
            <?php foreach ($modules as $m): ?>
                <option value="<?= htmlspecialchars($m) ?>" <?= ($filters['module'] ?? '') === $m ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group" style="min-width:150px;">
        <label>Action</label>
        <select name="action_type" class="form-control">
            <option value="">All Actions</option>
            <?php foreach (['CREATE','UPDATE','DELETE','LOGIN','LOGOUT','PERMISSION_OVERRIDE'] as $act): ?>
                <option value="<?= $act ?>" <?= ($filters['actionType'] ?? '') === $act ? 'selected' : '' ?>>
                    <?= $act ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-primary">Apply</button>
        <a href="/settings/audit-log" class="btn btn-secondary" style="margin-left:4px;">Clear</a>
    </div>
</form>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
    <p style="color:#666; margin:0;"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found</p>
    <?php
    $exportQs = $_GET;
    unset($exportQs['page']);
    ?>
    <a href="/settings/audit-log/export?<?= http_build_query($exportQs) ?>" class="btn btn-secondary btn-sm">Export to CSV</a>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Timestamp</th>
            <th>User</th>
            <th>Action</th>
            <th>Module</th>
            <th>Record ID</th>
            <th>IP Address</th>
            <th style="width:60px;">Changes</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="7" style="text-align:center; color:#999;">No audit log entries found.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $idx => $row): ?>
                <?php
                $hasChanges = !empty($row['field_changes']);
                $actionColors = [
                    'CREATE' => 'success', 'UPDATE' => 'info', 'DELETE' => 'danger',
                    'LOGIN' => 'primary', 'LOGOUT' => 'secondary', 'PERMISSION_OVERRIDE' => 'warning',
                ];
                $badgeClass = $actionColors[$row['action_type']] ?? 'secondary';
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td><?= htmlspecialchars($row['username'] ?? 'System') ?></td>
                    <td><span class="badge badge-<?= $badgeClass ?>"><?= htmlspecialchars($row['action_type']) ?></span></td>
                    <td><?= htmlspecialchars($row['module']) ?></td>
                    <td><?= htmlspecialchars($row['record_id'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['ip_address'] ?? '—') ?></td>
                    <td>
                        <?php if ($hasChanges): ?>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleChanges(<?= $idx ?>)">View</button>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($hasChanges): ?>
                    <tr id="changes-<?= $idx ?>" style="display:none;">
                        <td colspan="7" style="background:#f8f9fa; padding:12px;">
                            <div style="font-family:monospace; font-size:13px; line-height:1.6;">
                                <?php
                                $decoded = json_decode($row['field_changes'], true);
                                if (is_array($decoded)):
                                    foreach ($decoded as $field => $vals):
                                        $oldVal = $vals['old'] ?? '(empty)';
                                        $newVal = $vals['new'] ?? '(empty)';
                                        if (is_array($oldVal)) $oldVal = json_encode($oldVal);
                                        if (is_array($newVal)) $newVal = json_encode($newVal);
                                ?>
                                    <div><strong><?= htmlspecialchars($field) ?>:</strong> <span style="color:#c0392b;"><?= htmlspecialchars((string)$oldVal) ?></span> &rarr; <span style="color:#27ae60;"><?= htmlspecialchars((string)$newVal) ?></span></div>
                                <?php
                                    endforeach;
                                else:
                                ?>
                                    <div><?= htmlspecialchars($row['field_changes']) ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top:16px; display:flex; gap:4px; align-items:center;">
        <?php
        $qs = $_GET;
        unset($qs['page']);
        $base = '/settings/audit-log?' . http_build_query($qs);
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

<script>
function toggleChanges(idx) {
    var row = document.getElementById('changes-' + idx);
    if (row) {
        row.style.display = row.style.display === 'none' ? '' : 'none';
    }
}
</script>
