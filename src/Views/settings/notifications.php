<h1>Notification Settings</h1>

<form method="POST" action="/settings/notifications" class="settings-form" style="max-width:900px;">
    <div class="form-section">
        <h2>Alert Configuration</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Alert</th>
                    <th style="width:80px;">Enabled</th>
                    <th>Recipients</th>
                    <th>Threshold</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alerts as $alert): ?>
                    <?php
                    $id = $alert['id'];
                    $type = $alert['alert_type'];
                    $label = $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
                    $hasThreshold = $alert['threshold_value'] !== null || $alert['threshold_unit'] !== null;
                    $isTaskOverdue = $type === 'task_overdue';
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($label) ?></strong>
                            <?php if ($isTaskOverdue): ?>
                                <br><span class="help-text">Emails assigned rep directly</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" name="enabled_<?= $id ?>" value="1"
                                       <?= $alert['enabled'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </td>
                        <td>
                            <?php if (!$isTaskOverdue): ?>
                                <input type="text" name="recipients_<?= $id ?>"
                                       value="<?= htmlspecialchars($alert['recipients'] ?? '') ?>"
                                       placeholder="user@example.com, admin@example.com"
                                       class="table-input">
                            <?php else: ?>
                                <span class="help-text">N/A — sends to assigned rep</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($hasThreshold): ?>
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <input type="number" name="threshold_<?= $id ?>"
                                           value="<?= htmlspecialchars($alert['threshold_value'] ?? '') ?>"
                                           step="0.01" min="0"
                                           class="table-input" style="width:80px;">
                                    <span class="help-text"><?= htmlspecialchars($alert['threshold_unit'] ?? '') ?></span>
                                </div>
                            <?php else: ?>
                                <span class="help-text">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Notification Settings</button>
    </div>
</form>
