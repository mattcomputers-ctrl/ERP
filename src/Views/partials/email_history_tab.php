<?php
/**
 * Email History Tab — reusable partial for record view pages.
 *
 * Expected variables (set by the including view):
 *   $emailReferenceType  string  e.g. 'shipment', 'batch_ticket', 'purchase_order'
 *   $emailReferenceId    int     Primary key of the record
 *
 * Optional: $emailHistory array — if already fetched by the controller.
 *
 * Usage in a view:
 *   $emailReferenceType = 'shipment';
 *   $emailReferenceId = $record['id'] ?? 0;
 *   require __DIR__ . '/../partials/email_history_tab.php';
 */

$emailReferenceType = $emailReferenceType ?? '';
$emailReferenceId = (int)($emailReferenceId ?? 0);

if (!isset($emailHistory) && $emailReferenceId > 0) {
    try {
        // Reuse the shared PDO set by the service container
        $ehPdo = \PrecisionInk\Controllers\BaseController::getSharedDb();
        if ($ehPdo) {
            $ehStmt = $ehPdo->prepare(
                'SELECT * FROM outbound_email_log WHERE reference_type = ? AND reference_id = ? ORDER BY created_at DESC'
            );
            $ehStmt->execute([$emailReferenceType, $emailReferenceId]);
            $emailHistory = $ehStmt->fetchAll(\PDO::FETCH_ASSOC);
        }
    } catch (\Exception $e) {
        $emailHistory = [];
    }
}
$emailHistory = $emailHistory ?? [];
?>

<!-- Email History Tab -->
<div style="margin-top:24px; border-top:1px solid #e5e7eb; padding-top:16px;">
    <h3 style="font-size:13px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px;">Email History</h3>
    <?php if (empty($emailHistory)): ?>
        <p style="color:#9ca3af; font-size:14px;">No emails sent for this record yet.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid #e5e7eb; text-align:left;">
                        <th style="padding:8px 12px; color:#6b7280; font-weight:600;">Date/Time</th>
                        <th style="padding:8px 12px; color:#6b7280; font-weight:600;">Type</th>
                        <th style="padding:8px 12px; color:#6b7280; font-weight:600;">Recipients</th>
                        <th style="padding:8px 12px; color:#6b7280; font-weight:600;">Subject</th>
                        <th style="padding:8px 12px; color:#6b7280; font-weight:600;">Status</th>
                        <th style="padding:8px 12px; color:#6b7280; font-weight:600;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emailHistory as $email): ?>
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:8px 12px; white-space:nowrap;"><?= htmlspecialchars($email['created_at']) ?></td>
                            <td style="padding:8px 12px;"><?= htmlspecialchars($email['document_type']) ?></td>
                            <td style="padding:8px 12px;"><?= htmlspecialchars($email['recipients']) ?></td>
                            <td style="padding:8px 12px;"><?= htmlspecialchars($email['subject']) ?></td>
                            <td style="padding:8px 12px;">
                                <?php if ($email['status'] === 'SENT'): ?>
                                    <span style="display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; background:#d1fae5; color:#065f46;">Sent</span>
                                <?php else: ?>
                                    <span style="display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; background:#fee2e2; color:#991b1b;" title="<?= htmlspecialchars($email['failure_reason'] ?? '') ?>">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:8px 12px;">
                                <button type="button"
                                        class="email-resend-btn"
                                        style="padding:4px 10px; font-size:12px; border:1px solid #d1d5db; border-radius:4px; background:#fff; cursor:pointer; color:#374151;"
                                        data-email-id="<?= (int)$email['id'] ?>"
                                        data-recipients="<?= htmlspecialchars($email['recipients']) ?>"
                                        data-subject="<?= htmlspecialchars($email['subject']) ?>">
                                    Resend
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Resend Email Modal -->
<div id="resendEmailModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:8px; padding:24px; max-width:480px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <h4 style="margin:0 0 16px; font-size:16px; font-weight:600;">Resend Email</h4>
        <div style="margin-bottom:12px;">
            <label style="display:block; font-size:13px; font-weight:500; color:#374151; margin-bottom:4px;">Subject</label>
            <input type="text" id="resendSubject" readonly style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:4px; background:#f9fafb; font-size:13px; box-sizing:border-box;">
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block; font-size:13px; font-weight:500; color:#374151; margin-bottom:4px;">Recipients</label>
            <input type="text" id="resendRecipients" placeholder="email@example.com" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:4px; font-size:13px; box-sizing:border-box;">
            <small style="color:#9ca3af; font-size:11px;">Separate multiple addresses with commas.</small>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" id="resendCancelBtn" style="padding:8px 16px; border:1px solid #d1d5db; border-radius:4px; background:#fff; cursor:pointer; font-size:13px;">Cancel</button>
            <button type="button" id="resendEmailConfirmBtn" style="padding:8px 16px; border:none; border-radius:4px; background:#2563eb; color:#fff; cursor:pointer; font-size:13px; font-weight:500;">Send</button>
        </div>
    </div>
</div>

<script>
(function() {
    var resendEmailId = null;
    var modal = document.getElementById('resendEmailModal');
    document.querySelectorAll('.email-resend-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            resendEmailId = this.dataset.emailId;
            document.getElementById('resendRecipients').value = this.dataset.recipients;
            document.getElementById('resendSubject').value = this.dataset.subject;
            modal.style.display = 'flex';
        });
    });
    var cancelBtn = document.getElementById('resendCancelBtn');
    if (cancelBtn) cancelBtn.addEventListener('click', function() { modal.style.display = 'none'; });
    var confirmBtn = document.getElementById('resendEmailConfirmBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            var recipients = document.getElementById('resendRecipients').value.trim();
            if (!recipients) { alert('Recipients are required.'); return; }
            this.disabled = true;
            this.textContent = 'Sending...';
            fetch('/settings/email-log/resend/' + resendEmailId, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'recipients=' + encodeURIComponent(recipients)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('Email resent successfully.');
                    location.reload();
                } else {
                    alert('Failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(function() { alert('Network error.'); })
            .finally(function() {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Send';
                modal.style.display = 'none';
            });
        });
    }
})();
</script>
