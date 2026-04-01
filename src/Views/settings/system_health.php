<style>
.health-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:16px; margin-bottom:24px; }
.health-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px; }
.health-card h4 { margin:0 0 8px; font-size:13px; color:#6b7280; display:flex; align-items:center; gap:6px; }
.health-card .val { font-size:20px; font-weight:700; color:#1a1a2e; }
.health-card .val-sm { font-size:14px; font-weight:600; color:#1a1a2e; }
.progress-bar { width:100%; height:8px; background:#e5e7eb; border-radius:4px; margin-top:8px; overflow:hidden; }
.progress-fill { height:100%; border-radius:4px; transition:width 0.3s; }
.error-log-pre { background:#1a1a2e; color:#a3e635; padding:12px; border-radius:6px; font-size:11px; line-height:1.5; overflow-x:auto; max-height:250px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; }
.backup-section { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px; margin-bottom:24px; }
.restore-cmd { background:#f3f4f6; border:1px solid #d1d5db; border-radius:6px; padding:12px; font-family:monospace; font-size:12px; position:relative; }
.copy-btn { position:absolute; top:6px; right:6px; background:#fff; border:1px solid #d1d5db; border-radius:4px; padding:4px 8px; cursor:pointer; font-size:11px; }
.copy-btn:hover { background:#e5e7eb; }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h1 style="margin:0;">System Health</h1>
    <a href="/settings/system-health" class="btn btn-secondary">Refresh</a>
</div>

<!-- Metrics Grid -->
<div class="health-grid">
    <div class="health-card">
        <h4>Disk Usage</h4>
        <div class="val"><?= htmlspecialchars($disk['used']) ?> / <?= htmlspecialchars($disk['size']) ?></div>
        <div style="font-size:12px; color:#6b7280; margin-top:2px;">
            <?= htmlspecialchars($disk['avail']) ?> available (<?= $disk['pct'] ?>% used)
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width:<?= min(100, $disk['pct']) ?>%; background:<?= $disk['pct'] > 90 ? '#ef4444' : ($disk['pct'] > 75 ? '#f59e0b' : '#22c55e') ?>;"></div>
        </div>
    </div>

    <div class="health-card">
        <h4>Database Size</h4>
        <div class="val"><?= htmlspecialchars($dbSize) ?> MB</div>
    </div>

    <div class="health-card">
        <h4>Storage Size</h4>
        <div class="val"><?= htmlspecialchars($storageSize) ?></div>
        <div style="font-size:12px; color:#6b7280;">attachments directory</div>
    </div>

    <div class="health-card">
        <h4>Last Backup</h4>
        <div class="val-sm" style="color:<?= str_contains($lastBackup, 'No backups') ? '#ef4444' : '#1a1a2e' ?>;">
            <?= htmlspecialchars($lastBackup) ?>
        </div>
    </div>

    <div class="health-card">
        <h4>PHP Version</h4>
        <div class="val"><?= htmlspecialchars($phpVersion) ?></div>
    </div>

    <div class="health-card">
        <h4>MySQL Version</h4>
        <div class="val"><?= htmlspecialchars($mysqlVersion) ?></div>
    </div>

    <div class="health-card">
        <h4>Cron — Notifications</h4>
        <div class="val-sm"><?= htmlspecialchars($cronNotify) ?></div>
    </div>

    <div class="health-card">
        <h4>Cron — Monthly Snapshot</h4>
        <div class="val-sm"><?= htmlspecialchars($cronSnapshot) ?></div>
    </div>

    <div class="health-card">
        <h4>Cron — Backup</h4>
        <div class="val-sm"><?= htmlspecialchars($cronBackup) ?></div>
    </div>

    <div class="health-card">
        <h4>SMTP Last Success</h4>
        <div class="val-sm"><?= htmlspecialchars($lastEmail) ?></div>
    </div>
</div>

<!-- PHP Error Log -->
<div class="backup-section">
    <h3 style="margin:0 0 12px;">PHP Error Log (Last 10 Lines)</h3>
    <pre class="error-log-pre"><?= htmlspecialchars($errorLog) ?></pre>
</div>

<!-- Backup Management -->
<div class="backup-section">
    <h3 style="margin:0 0 16px;">Database Backup</h3>

    <div style="display:flex; gap:16px; align-items:flex-start; margin-bottom:20px;">
        <div>
            <button id="backup-btn" class="btn btn-primary" onclick="runBackup()">Run Backup Now</button>
        </div>
    </div>

    <div id="backup-output" style="display:none; margin-bottom:20px;">
        <pre class="error-log-pre" id="backup-output-text"></pre>
    </div>

    <h4 style="margin:0 0 8px; font-size:13px; color:#6b7280;">Restore Command</h4>
    <div class="restore-cmd">
        <button class="copy-btn" onclick="copyRestore()">Copy</button>
        <code id="restore-cmd-text"><?= htmlspecialchars($restoreCmd) ?></code>
    </div>
</div>

<script>
function runBackup() {
    var btn = document.getElementById('backup-btn');
    btn.disabled = true;
    btn.textContent = 'Running...';
    document.getElementById('backup-output').style.display = 'none';

    fetch('/settings/backup/run', { method: 'POST' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('backup-output-text').textContent = data.output || 'No output.';
            document.getElementById('backup-output').style.display = 'block';
        })
        .catch(function(e) {
            document.getElementById('backup-output-text').textContent = 'Error: ' + e.message;
            document.getElementById('backup-output').style.display = 'block';
        })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = 'Run Backup Now';
        });
}

function copyRestore() {
    var text = document.getElementById('restore-cmd-text').textContent;
    navigator.clipboard.writeText(text).then(function() {
        var btn = document.querySelector('.copy-btn');
        btn.textContent = 'Copied!';
        setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
    });
}
</script>
