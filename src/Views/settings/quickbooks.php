<h2 style="margin:0 0 16px;">QuickBooks Settings</h2>

<form method="POST" action="/settings/quickbooks" style="max-width:800px;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <div style="margin-bottom:16px;padding:16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;">
        <h3 style="margin:0 0 12px;font-size:14px;">General</h3>
        <div style="display:flex;gap:16px;margin-bottom:12px;">
            <div style="flex:1;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">QB Mode</label>
                <select name="qb_mode" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
                    <option value="DESKTOP" <?= ($settings['qb_mode'] ?? '') === 'DESKTOP' ? 'selected' : '' ?>>Desktop (IIF Export)</option>
                    <option value="ONLINE" <?= ($settings['qb_mode'] ?? '') === 'ONLINE' ? 'selected' : '' ?>>Online (API)</option>
                </select>
            </div>
            <div style="flex:1;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Company Name</label>
                <input type="text" name="qb_company_name" value="<?= htmlspecialchars($settings['qb_company_name'] ?? '') ?>"
                       style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
            </div>
        </div>
    </div>

    <div style="margin-bottom:16px;padding:16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;">
        <h3 style="margin:0 0 12px;font-size:14px;">Desktop Account Names</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">AR Account</label>
                <input type="text" name="qb_ar_account" value="<?= htmlspecialchars($settings['qb_ar_account'] ?? 'Accounts Receivable') ?>"
                       style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">AP Account</label>
                <input type="text" name="qb_ap_account" value="<?= htmlspecialchars($settings['qb_ap_account'] ?? 'Accounts Payable') ?>"
                       style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Default Income Account</label>
                <input type="text" name="qb_default_income_account" value="<?= htmlspecialchars($settings['qb_default_income_account'] ?? 'Sales') ?>"
                       style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Default COGS Account</label>
                <input type="text" name="qb_default_cogs_account" value="<?= htmlspecialchars($settings['qb_default_cogs_account'] ?? 'Cost of Goods Sold') ?>"
                       style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Default Inventory Account</label>
                <input type="text" name="qb_default_inventory_account" value="<?= htmlspecialchars($settings['qb_default_inventory_account'] ?? 'Inventory Asset') ?>"
                       style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
            </div>
        </div>
    </div>

    <div style="margin-bottom:16px;padding:16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;">
        <h3 style="margin:0 0 12px;font-size:14px;">QB Online Credentials</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Client ID</label>
                <input type="text" name="qb_online_client_id" value="<?= htmlspecialchars($settings['qb_online_client_id'] ?? '') ?>"
                       style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;" placeholder="From Intuit Developer Portal">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Client Secret</label>
                <input type="password" name="qb_online_client_secret" value="<?= htmlspecialchars($settings['qb_online_client_secret'] ?? '') ?>"
                       style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
            </div>
        </div>
    </div>

    <div style="margin-bottom:16px;padding:16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;">
        <h3 style="margin:0 0 12px;font-size:14px;">GL Group Account Mappings</h3>
        <p style="font-size:12px;color:#6b7280;margin-bottom:12px;">Map each GL group to specific QuickBooks accounts. Leave blank to use defaults above.</p>
        <table class="data-table" style="font-size:13px;">
            <thead>
                <tr><th>GL Group</th><th>QB Income Account</th><th>QB COGS Account</th><th>QB Inventory Account</th></tr>
            </thead>
            <tbody>
            <?php foreach ($glGroups as $gl): ?>
            <tr>
                <td style="font-weight:500;"><?= str_replace('_', ' ', ucwords(strtolower($gl), '_')) ?></td>
                <td><input type="text" name="mapping_income_<?= $gl ?>" value="<?= htmlspecialchars($mappingMap['gl_group_income'][$gl] ?? '') ?>"
                           style="width:100%;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;" placeholder="Default"></td>
                <td><input type="text" name="mapping_cogs_<?= $gl ?>" value="<?= htmlspecialchars($mappingMap['gl_group_cogs'][$gl] ?? '') ?>"
                           style="width:100%;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;" placeholder="Default"></td>
                <td><input type="text" name="mapping_inventory_<?= $gl ?>" value="<?= htmlspecialchars($mappingMap['gl_group_inventory'][$gl] ?? '') ?>"
                           style="width:100%;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;" placeholder="Default"></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <button type="submit" class="btn btn-primary">Save Settings</button>
</form>
