<h1>SMTP Email Settings</h1>

<form method="POST" action="/settings/smtp" class="settings-form">
    <div class="form-section">
        <h2>SMTP Server</h2>

        <div class="form-row">
            <div class="form-group flex-2">
                <label for="smtp_host">SMTP Host</label>
                <input type="text" id="smtp_host" name="smtp_host"
                       value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>"
                       placeholder="smtp.example.com" maxlength="255">
            </div>
            <div class="form-group">
                <label for="smtp_port">Port</label>
                <input type="number" id="smtp_port" name="smtp_port"
                       value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>"
                       min="1" max="65535">
            </div>
        </div>

        <div class="form-group">
            <label for="smtp_encryption">Encryption</label>
            <select id="smtp_encryption" name="smtp_encryption">
                <option value="tls" <?= ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
            </select>
        </div>
    </div>

    <div class="form-section">
        <h2>Authentication</h2>

        <div class="form-group">
            <label for="smtp_username">Username</label>
            <input type="text" id="smtp_username" name="smtp_username"
                   value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>"
                   autocomplete="off" maxlength="255">
        </div>

        <div class="form-group">
            <?php $hasPassword = !empty($settings['smtp_password_set']); ?>
            <?php if ($hasPassword): ?>
                <p class="help-text" style="margin-bottom:6px;">Password is set. Check below to change it.</p>
            <?php endif; ?>
            <label class="checkbox-label" style="margin-bottom:6px;">
                <input type="checkbox" name="change_password" value="1" id="change_password_cb"
                       onchange="document.getElementById('smtp_password').disabled = !this.checked; if(this.checked) document.getElementById('smtp_password').focus();">
                <?= $hasPassword ? 'Change password' : 'Set password' ?>
            </label>
            <input type="password" id="smtp_password" name="smtp_password"
                   placeholder="<?= $hasPassword ? '********' : 'Enter SMTP password' ?>"
                   disabled autocomplete="new-password" maxlength="255">
        </div>
    </div>

    <div class="form-section">
        <h2>Sender Information</h2>

        <div class="form-row">
            <div class="form-group">
                <label for="smtp_from_name">From Name</label>
                <input type="text" id="smtp_from_name" name="smtp_from_name"
                       value="<?= htmlspecialchars($settings['smtp_from_name'] ?? '') ?>"
                       placeholder="Precision Ink ERP" maxlength="255">
            </div>
            <div class="form-group">
                <label for="smtp_from_address">From Address</label>
                <input type="email" id="smtp_from_address" name="smtp_from_address"
                       value="<?= htmlspecialchars($settings['smtp_from_address'] ?? '') ?>"
                       placeholder="noreply@example.com" maxlength="255">
            </div>
        </div>
    </div>

    <div class="form-actions" style="display:flex; gap:12px; align-items:center;">
        <button type="submit" class="btn btn-primary">Save SMTP Settings</button>
        <button type="button" class="btn btn-secondary" id="btn-test-smtp">Send Test Email</button>
    </div>
</form>

<div id="smtp-test-result" style="margin-top:12px;"></div>

<script>
document.getElementById('btn-test-smtp').addEventListener('click', function() {
    var btn = this;
    var result = document.getElementById('smtp-test-result');
    btn.disabled = true;
    btn.textContent = 'Sending...';
    result.innerHTML = '';

    fetch('/settings/smtp/test', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
        body: ''
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            result.innerHTML = '<div class="toast toast-success">Test email sent successfully.</div>';
        } else {
            result.innerHTML = '<div class="toast toast-error">Failed: ' + (data.error || 'Unknown error') + '</div>';
        }
    })
    .catch(function(err) {
        result.innerHTML = '<div class="toast toast-error">Failed: Network error.</div>';
    })
    .finally(function() {
        btn.disabled = false;
        btn.textContent = 'Send Test Email';
    });
});
</script>
