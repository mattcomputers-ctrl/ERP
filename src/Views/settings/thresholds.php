<h1>Thresholds &amp; Defaults</h1>

<form method="POST" action="/settings/thresholds" class="settings-form">
    <div class="form-section">
        <h2>Operations</h2>

        <div class="form-row">
            <div class="form-group">
                <label for="default_lead_time_days">Default Lead Time (days)</label>
                <input type="number" id="default_lead_time_days" name="default_lead_time_days"
                       value="<?= htmlspecialchars($settings['default_lead_time_days'] ?? '3') ?>" min="1" step="1" required>
            </div>
            <div class="form-group">
                <label for="quote_expiration_days">Quote Expiration (days)</label>
                <input type="number" id="quote_expiration_days" name="quote_expiration_days"
                       value="<?= htmlspecialchars($settings['quote_expiration_days'] ?? '30') ?>" min="1" step="1" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="receiving_discrepancy_threshold">Receiving Discrepancy Threshold (%)</label>
                <input type="number" id="receiving_discrepancy_threshold" name="receiving_discrepancy_threshold"
                       value="<?= htmlspecialchars($settings['receiving_discrepancy_threshold'] ?? '5') ?>" min="0.01" step="0.01" required>
                <span class="help-text">Percentage variance allowed before flagging a receiving discrepancy.</span>
            </div>
            <div class="form-group">
                <label for="cost_change_alert_threshold">Cost Change Alert Threshold (%)</label>
                <input type="number" id="cost_change_alert_threshold" name="cost_change_alert_threshold"
                       value="<?= htmlspecialchars($settings['cost_change_alert_threshold'] ?? '5') ?>" min="0.01" step="0.01" required>
                <span class="help-text">Percentage cost change that triggers an alert notification.</span>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h2>Session &amp; Security</h2>

        <div class="form-row">
            <div class="form-group">
                <label for="session_timeout_minutes">Session Timeout (minutes)</label>
                <input type="number" id="session_timeout_minutes" name="session_timeout_minutes"
                       value="<?= htmlspecialchars($settings['session_timeout_minutes'] ?? '30') ?>" min="1" step="1" required>
            </div>
            <div class="form-group">
                <label for="session_warning_minutes">Session Warning (minutes before timeout)</label>
                <input type="number" id="session_warning_minutes" name="session_warning_minutes"
                       value="<?= htmlspecialchars($settings['session_warning_minutes'] ?? '5') ?>" min="1" step="1" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="max_login_attempts">Max Login Attempts</label>
                <input type="number" id="max_login_attempts" name="max_login_attempts"
                       value="<?= htmlspecialchars($settings['max_login_attempts'] ?? '5') ?>" min="1" step="1" required>
            </div>
            <div class="form-group">
                <label for="lockout_duration_minutes">Lockout Duration (minutes)</label>
                <input type="number" id="lockout_duration_minutes" name="lockout_duration_minutes"
                       value="<?= htmlspecialchars($settings['lockout_duration_minutes'] ?? '15') ?>" min="1" step="1" required>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Thresholds</button>
    </div>
</form>
