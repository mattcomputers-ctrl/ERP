<h1>Company Settings</h1>

<form method="POST" action="/settings/company" enctype="multipart/form-data" class="settings-form">
    <div class="form-section">
        <h2>Company Information</h2>

        <div class="form-group">
            <label for="company_name">Company Name</label>
            <input type="text" id="company_name" name="company_name"
                   value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>" maxlength="255">
        </div>

        <div class="form-group">
            <label for="company_email">Email</label>
            <input type="email" id="company_email" name="company_email"
                   value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>" maxlength="255">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="company_phone">Phone</label>
                <input type="text" id="company_phone" name="company_phone"
                       value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>" maxlength="30">
            </div>
            <div class="form-group">
                <label for="company_fax">Fax</label>
                <input type="text" id="company_fax" name="company_fax"
                       value="<?= htmlspecialchars($settings['company_fax'] ?? '') ?>" maxlength="30">
            </div>
        </div>
    </div>

    <div class="form-section">
        <h2>Address</h2>

        <div class="form-group">
            <label for="company_street">Street</label>
            <input type="text" id="company_street" name="company_street"
                   value="<?= htmlspecialchars($settings['company_street'] ?? '') ?>" maxlength="255">
        </div>

        <div class="form-row">
            <div class="form-group flex-2">
                <label for="company_city">City</label>
                <input type="text" id="company_city" name="company_city"
                       value="<?= htmlspecialchars($settings['company_city'] ?? '') ?>" maxlength="100">
            </div>
            <div class="form-group">
                <label for="company_state">State</label>
                <input type="text" id="company_state" name="company_state"
                       value="<?= htmlspecialchars($settings['company_state'] ?? '') ?>" maxlength="50">
            </div>
            <div class="form-group">
                <label for="company_zip">ZIP</label>
                <input type="text" id="company_zip" name="company_zip"
                       value="<?= htmlspecialchars($settings['company_zip'] ?? '') ?>" maxlength="20">
            </div>
        </div>

        <div class="form-group">
            <label for="company_country">Country</label>
            <input type="text" id="company_country" name="company_country"
                   value="<?= htmlspecialchars($settings['company_country'] ?? '') ?>" maxlength="50">
        </div>
    </div>

    <div class="form-section">
        <h2>Company Logo</h2>

        <?php $logoPath = $settings['company_logo_path'] ?? ''; ?>
        <?php if ($logoPath): ?>
            <div class="logo-preview">
                <img src="/storage/<?= htmlspecialchars($logoPath) ?>" alt="Company Logo" class="current-logo">
                <label class="checkbox-label">
                    <input type="checkbox" name="remove_logo" value="1"> Remove current logo
                </label>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="company_logo">Upload Logo <span class="help-text">(JPG, PNG, or GIF — max 2MB)</span></label>
            <input type="file" id="company_logo" name="company_logo" accept="image/jpeg,image/png,image/gif">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Company Settings</button>
    </div>
</form>
