<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipes — <?= htmlspecialchars($item['item_code']) ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
</head>
<body>
    <header class="app-header">
        <div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?>
                <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span>
            <?php endif; ?>
        </div>
    </header>

    <div style="max-width:1000px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast">
                <?= htmlspecialchars($_SESSION['toast']['message']) ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">
                Recipes for <?= htmlspecialchars($item['item_code']) ?>
                <span style="font-size:14px; font-weight:400; color:#6b7280;"> — <?= htmlspecialchars($item['description']) ?></span>
            </h1>
            <div style="display:flex; gap:8px;">
                <a href="/items/<?= $item['id'] ?>#recipes" class="btn btn-secondary">&larr; Back to Item</a>
                <a href="/items/<?= $item['id'] ?>/recipes/create" class="btn btn-primary">+ New Version</a>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Version #</th>
                    <th>Version Name</th>
                    <th>Active</th>
                    <th>Default</th>
                    <th>Created By</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recipes)): ?>
                    <tr><td colspan="7" class="empty-state">No recipe versions.</td></tr>
                <?php else: ?>
                    <?php foreach ($recipes as $r): ?>
                    <tr class="<?= !$r['is_active'] ? 'inactive-row' : '' ?>">
                        <td>
                            <a href="/items/<?= $item['id'] ?>/recipes/<?= $r['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;">
                                v<?= (int)$r['version_number'] ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($r['version_name']) ?></td>
                        <td>
                            <span class="badge <?= $r['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $r['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($r['is_default']): ?>
                                <span style="color:#f59e0b; font-size:16px;" title="Default version">&#9733;</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['created_by_name'] ?? '') ?></td>
                        <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                        <td class="actions-cell">
                            <a href="/items/<?= $item['id'] ?>/recipes/<?= $r['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            <?php if ($r['is_active']): ?>
                                <a href="/items/<?= $item['id'] ?>/recipes/<?= $r['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                                <?php if (!$r['is_default']): ?>
                                <form method="POST" action="/items/<?= $item['id'] ?>/recipes/<?= $r['id'] ?>/set-default" style="display:inline;">
                                    <button type="submit" class="btn btn-sm btn-secondary" title="Set as default">&#9733;</button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            <form method="POST" action="/items/<?= $item['id'] ?>/recipes/<?= $r['id'] ?>/clone" style="display:inline;">
                                <button type="submit" class="btn btn-sm btn-secondary" title="Clone">Clone</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);
    </script>
</body>
</html>
