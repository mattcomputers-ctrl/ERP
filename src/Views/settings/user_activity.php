<h1>User Activity</h1>

<!-- Panel 1: Active Sessions -->
<div class="form-section" style="margin-bottom:32px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <h2 style="margin:0;">Active Sessions</h2>
        <div style="display:flex; align-items:center; gap:12px;">
            <span style="color:#999; font-size:12px;">Refreshes every 60s</span>
            <a href="/settings/user-activity" class="btn btn-secondary btn-sm">Refresh Now</a>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th>Session Started</th>
                <th>Last Activity</th>
                <th>Last Page</th>
                <th style="width:110px;">Actions</th>
            </tr>
        </thead>
        <tbody id="active-sessions-body">
            <?php if (empty($activeSessions)): ?>
                <tr><td colspan="6" style="text-align:center; color:#999;">No active sessions.</td></tr>
            <?php else: ?>
                <?php foreach ($activeSessions as $s): ?>
                    <?php
                    $lastAct = strtotime($s['last_activity']);
                    $diff = max(0, time() - $lastAct);
                    if ($diff < 60) {
                        $ago = 'Just now';
                    } elseif ($diff < 3600) {
                        $ago = (int)($diff / 60) . ' min ago';
                    } else {
                        $ago = (int)($diff / 3600) . ' hr ago';
                    }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($s['username']) ?></td>
                        <td><?= htmlspecialchars($s['full_name']) ?></td>
                        <td><?= htmlspecialchars($s['started_at']) ?></td>
                        <td><?= $ago ?></td>
                        <td><?= htmlspecialchars($s['last_page'] ?? '—') ?></td>
                        <td>
                            <form method="POST" action="/settings/user-activity/force-logout/<?= (int)$s['id'] ?>" style="display:inline;" onsubmit="return confirm('Force logout <?= htmlspecialchars($s['username']) ?>?');">
                                <button type="submit" class="btn btn-danger btn-sm">Force Logout</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Panel 2: Recent Login History -->
<div class="form-section">
    <h2 style="margin-bottom:12px;">Recent Login History (Last 20)</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Timestamp</th>
                <th>IP Address</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($loginHistory)): ?>
                <tr><td colspan="4" style="text-align:center; color:#999;">No login history yet.</td></tr>
            <?php else: ?>
                <?php foreach ($loginHistory as $attempt): ?>
                    <tr>
                        <td><?= htmlspecialchars($attempt['username']) ?></td>
                        <td><?= htmlspecialchars($attempt['attempted_at']) ?></td>
                        <td><?= htmlspecialchars($attempt['ip_address']) ?></td>
                        <td>
                            <?php if ($attempt['success']): ?>
                                <span class="badge badge-success">Success</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Failed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
setInterval(function() {
    fetch('/settings/user-activity/sessions-json')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('active-sessions-body').innerHTML = data.html;
        })
        .catch(function() { /* silently ignore refresh errors */ });
}, 60000);
</script>
