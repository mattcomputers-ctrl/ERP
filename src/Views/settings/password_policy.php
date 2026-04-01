<h1>Password Policy</h1>

<form method="POST" action="/settings/password-policy" class="settings-form">
    <div class="form-section">
        <h2>Complexity Requirements</h2>

        <div class="form-group">
            <label for="password_min_length">Minimum Password Length</label>
            <input type="number" id="password_min_length" name="password_min_length"
                   value="<?= htmlspecialchars($settings['password_min_length'] ?? '10') ?>"
                   min="6" max="128">
            <span class="help-text">Minimum 6 characters.</span>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="password_require_uppercase" value="1"
                       <?= ($settings['password_require_uppercase'] ?? '1') === '1' ? 'checked' : '' ?>>
                Require at least one uppercase letter
            </label>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="password_require_number" value="1"
                       <?= ($settings['password_require_number'] ?? '1') === '1' ? 'checked' : '' ?>>
                Require at least one number
            </label>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="password_require_special" value="1"
                       <?= ($settings['password_require_special'] ?? '1') === '1' ? 'checked' : '' ?>>
                Require at least one special character
            </label>
        </div>
    </div>

    <div class="form-section">
        <h2>Expiration &amp; History</h2>

        <div class="form-group">
            <label for="password_expiry_days">Password Expiry (days)</label>
            <input type="number" id="password_expiry_days" name="password_expiry_days"
                   value="<?= htmlspecialchars($settings['password_expiry_days'] ?? '0') ?>"
                   min="0">
            <span class="help-text">Set to 0 to disable password expiration.</span>
        </div>

        <div class="form-group">
            <label for="password_history_count">Password History Count</label>
            <input type="number" id="password_history_count" name="password_history_count"
                   value="<?= htmlspecialchars($settings['password_history_count'] ?? '5') ?>"
                   min="0" max="50">
            <span class="help-text">Number of previous passwords a user cannot reuse. Set to 0 to disable.</span>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Password Policy</button>
    </div>
</form>
