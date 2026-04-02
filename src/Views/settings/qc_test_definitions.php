<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h2 style="margin:0;">QC Test Library</h2>
    <a href="/settings/qc-tests/create" class="btn btn-primary">+ New Test</a>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Test Name</th>
            <th>Type</th>
            <th>Default Range</th>
            <th>UOM</th>
            <th>Items</th>
            <th>Active</th>
            <th style="width:140px;">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($tests)): ?>
        <tr><td colspan="7" style="text-align:center;color:#9ca3af;">No QC tests defined yet.</td></tr>
    <?php else: foreach ($tests as $t): ?>
        <tr>
            <td style="font-weight:500;"><?= htmlspecialchars($t['test_name']) ?></td>
            <td>
                <span class="badge <?= $t['test_type'] === 'PASS_FAIL' ? 'badge-info' : 'badge-warning' ?>">
                    <?= $t['test_type'] === 'PASS_FAIL' ? 'Pass/Fail' : 'Numeric Range' ?>
                </span>
            </td>
            <td>
                <?php if ($t['test_type'] === 'NUMERIC_RANGE'): ?>
                    <?= $t['default_min_value'] !== null ? number_format((float)$t['default_min_value'], 4) : '—' ?>
                    –
                    <?= $t['default_max_value'] !== null ? number_format((float)$t['default_max_value'], 4) : '—' ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($t['uom'] ?? '') ?></td>
            <td><?= (int)$t['item_count'] ?></td>
            <td>
                <?php if ($t['active']): ?>
                    <span style="color:#16a34a;">Active</span>
                <?php else: ?>
                    <span style="color:#dc2626;">Inactive</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="/settings/qc-tests/<?= $t['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                <?php if ($t['active']): ?>
                    <form method="POST" action="/settings/qc-tests/save" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <input type="hidden" name="action" value="deactivate">
                        <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Deactivate this test? <?= (int)$t['item_count'] ?> item(s) have this test assigned. Existing assignments will not be changed.')">
                            Deactivate
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="/settings/qc-tests/save" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <input type="hidden" name="action" value="activate">
                        <button type="submit" class="btn btn-sm btn-success">Activate</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
