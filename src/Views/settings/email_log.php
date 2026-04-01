<h1>Outbound Email Log</h1>

<form method="GET" action="/settings/email-log" class="filter-bar" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end; margin-bottom:20px;">
    <div class="form-group" style="min-width:160px;">
        <label>Recipient</label>
        <input type="text" name="recipient" class="form-control" placeholder="Search recipients..." value="<?= htmlspecialchars($filters['recipient'] ?? '') ?>">
    </div>
    <div class="form-group" style="min-width:150px;">
        <label>Document Type</label>
        <select name="document_type" class="form-control">
            <option value="">All Types</option>
            <?php foreach ($documentTypes as $dt): ?>
                <option value="<?= htmlspecialchars($dt) ?>" <?= ($filters['documentType'] ?? '') === $dt ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $dt))) ?>
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
        <a href="/settings/email-log" class="btn btn-secondary" style="margin-left:4px;">Clear</a>
    </div>
</form>

<p style="color:#666; margin-bottom:10px;"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found</p>

<table class="data-table">
    <thead>
        <tr>
            <th>Timestamp</th>
            <th>Sent By</th>
            <th>Document Type</th>
            <th>Reference</th>
            <th>Recipients</th>
            <th>Subject</th>
            <th>Status</th>
            <th style="width:100px;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="8" style="text-align:center; color:#999;">No email log entries found.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $idx => $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td><?= htmlspecialchars($row['sent_by_name'] ?? 'System') ?></td>
                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['document_type']))) ?></td>
                    <td>
                        <?php if ($row['reference_type']): ?>
                            <?= htmlspecialchars($row['reference_type']) ?> #<?= htmlspecialchars($row['reference_id']) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td title="<?= htmlspecialchars($row['recipients']) ?>"><?= htmlspecialchars(mb_strimwidth($row['recipients'], 0, 40, '...')) ?></td>
                    <td><?= htmlspecialchars($row['subject']) ?></td>
                    <td>
                        <span class="badge badge-<?= $row['status'] === 'SENT' ? 'success' : 'danger' ?>">
                            <?= htmlspecialchars($row['status']) ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEmailDetail(<?= $idx ?>)">Details</button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openResendModal(<?= $row['id'] ?>, <?= htmlspecialchars(json_encode($row['recipients'])) ?>)">Resend</button>
                    </td>
                </tr>
                <tr id="email-detail-<?= $idx ?>" style="display:none;">
                    <td colspan="8" style="background:#f8f9fa; padding:12px;">
                        <div style="line-height:1.8;">
                            <strong>Full Recipients:</strong> <?= htmlspecialchars($row['recipients']) ?><br>
                            <strong>Subject:</strong> <?= htmlspecialchars($row['subject']) ?><br>
                            <?php if ($row['attachment_filename']): ?>
                                <strong>Attachment:</strong> <?= htmlspecialchars($row['attachment_filename']) ?><br>
                            <?php endif; ?>
                            <?php if ($row['status'] === 'FAILED' && $row['failure_reason']): ?>
                                <strong>Failure Reason:</strong> <span style="color:#c0392b;"><?= htmlspecialchars($row['failure_reason']) ?></span><br>
                            <?php endif; ?>
                        </div>
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
        $base = '/settings/email-log?' . http_build_query($qs);
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

<!-- Resend Modal -->
<div id="resend-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:8px; padding:24px; max-width:500px; width:90%; margin:auto; margin-top:15vh; box-shadow:0 4px 20px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0;">Resend Email</h3>
        <div class="form-group">
            <label>Recipients <small style="color:#666;">(comma-separated)</small></label>
            <textarea id="resend-recipients" class="form-control" rows="3" style="width:100%;"></textarea>
        </div>
        <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
            <button type="button" class="btn btn-secondary" onclick="closeResendModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="resend-btn" onclick="doResend()">Resend</button>
        </div>
        <div id="resend-result" style="margin-top:12px; display:none;"></div>
    </div>
</div>

<script>
var currentResendId = null;

function toggleEmailDetail(idx) {
    var row = document.getElementById('email-detail-' + idx);
    if (row) {
        row.style.display = row.style.display === 'none' ? '' : 'none';
    }
}

function openResendModal(id, recipients) {
    currentResendId = id;
    document.getElementById('resend-recipients').value = recipients;
    document.getElementById('resend-result').style.display = 'none';
    document.getElementById('resend-modal').style.display = 'block';
}

function closeResendModal() {
    document.getElementById('resend-modal').style.display = 'none';
    currentResendId = null;
}

function doResend() {
    var recipients = document.getElementById('resend-recipients').value.trim();
    if (!recipients) { alert('Please enter at least one recipient.'); return; }

    var btn = document.getElementById('resend-btn');
    btn.disabled = true;
    btn.textContent = 'Sending...';

    var formData = new FormData();
    formData.append('recipients', recipients);

    fetch('/settings/email-log/resend/' + currentResendId, {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var resultDiv = document.getElementById('resend-result');
        resultDiv.style.display = 'block';
        if (data.success) {
            resultDiv.innerHTML = '<div class="toast toast-success">Email resent successfully.</div>';
            setTimeout(function() { closeResendModal(); location.reload(); }, 1500);
        } else {
            resultDiv.innerHTML = '<div class="toast toast-error">Failed: ' + (data.error || 'Unknown error') + '</div>';
        }
    })
    .catch(function(err) {
        alert('Request failed: ' + err.message);
    })
    .finally(function() {
        btn.disabled = false;
        btn.textContent = 'Resend';
    });
}

// Close modal on backdrop click
document.getElementById('resend-modal').addEventListener('click', function(e) {
    if (e.target === this) closeResendModal();
});
</script>
