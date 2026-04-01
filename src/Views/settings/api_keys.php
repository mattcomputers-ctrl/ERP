<h1>API Keys</h1>

<?php if (!empty($newKey)): ?>
    <div class="api-key-reveal" id="keyReveal">
        <div class="form-section" style="border-color: #f59e0b; background: #fffbeb;">
            <h2 style="color: #92400e;">New API Key Generated</h2>
            <p style="color: #92400e; font-size: 13px; margin-bottom: 12px;">
                Copy this key now. It will not be shown again.
            </p>
            <div style="display: flex; gap: 8px; align-items: center;">
                <input type="text" id="apiKeyValue" value="<?= htmlspecialchars($newKey) ?>" readonly
                       style="flex: 1; font-family: monospace; font-size: 13px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; background: #fff;">
                <button type="button" class="btn btn-primary btn-sm" onclick="copyApiKey()">Copy</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="table-toolbar">
    <form method="POST" action="/settings/api-keys">
        <input type="hidden" name="action" value="generate">
        <button type="submit" class="btn btn-primary">Generate New Key</button>
    </form>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Key Prefix</th>
            <th>Rate Limit</th>
            <th>Status</th>
            <th>Created</th>
            <th>Last Used</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($keys)): ?>
            <tr><td colspan="6" class="empty-state">No API keys. Click "Generate New Key" to create one.</td></tr>
        <?php else: ?>
            <?php foreach ($keys as $key): ?>
                <tr class="<?= $key['active'] ? '' : 'inactive-row' ?>">
                    <td><code><?= htmlspecialchars($key['key_prefix']) ?>...</code></td>
                    <td>
                        <?php if ($key['active']): ?>
                            <form method="POST" action="/settings/api-keys" class="inline-form" style="gap: 4px;">
                                <input type="hidden" name="action" value="update_rate">
                                <input type="hidden" name="id" value="<?= $key['id'] ?>">
                                <input type="number" name="rate_limit_per_minute" value="<?= (int) $key['rate_limit_per_minute'] ?>"
                                       min="1" step="1" style="width: 70px; padding: 3px 6px; font-size: 12px; border: 1px solid #d1d5db; border-radius: 3px;">
                                <span style="font-size: 12px; color: #6b7280;">/min</span>
                                <button type="submit" class="btn btn-sm btn-secondary" style="padding: 2px 6px; font-size: 11px;">Save</button>
                            </form>
                        <?php else: ?>
                            <?= (int) $key['rate_limit_per_minute'] ?>/min
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $key['active'] ? 'badge-active' : 'badge-inactive' ?>">
                            <?= $key['active'] ? 'Active' : 'Revoked' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars(date('M j, Y', strtotime($key['created_at']))) ?></td>
                    <td><?= $key['last_used_at'] ? htmlspecialchars(date('M j, Y g:ia', strtotime($key['last_used_at']))) : 'Never' ?></td>
                    <td class="actions-cell">
                        <?php if ($key['active']): ?>
                            <form method="POST" action="/settings/api-keys" class="inline-toggle">
                                <input type="hidden" name="action" value="rotate">
                                <input type="hidden" name="id" value="<?= $key['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary"
                                        onclick="return confirm('Rotate this key? The old key will be revoked and a new key generated.')">Rotate</button>
                            </form>
                            <form method="POST" action="/settings/api-keys" class="inline-toggle">
                                <input type="hidden" name="action" value="revoke">
                                <input type="hidden" name="id" value="<?= $key['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-warning"
                                        onclick="return confirm('Revoke this API key? This cannot be undone.')">Revoke</button>
                            </form>
                        <?php else: ?>
                            <span style="color: #9ca3af; font-size: 12px;">Revoked</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<script>
function copyApiKey() {
    var input = document.getElementById('apiKeyValue');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(function() {
        var btn = input.nextElementSibling;
        btn.textContent = 'Copied!';
        setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
    });
}
</script>
