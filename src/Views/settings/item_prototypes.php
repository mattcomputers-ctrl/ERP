<h1>Item Prototypes</h1>

<div class="table-toolbar">
    <a href="/settings/item-prototypes/form" class="btn btn-primary">+ Add Item Prototype</a>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Item Type</th>
            <th>UOM</th>
            <th>Inspection</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="6" class="empty-state">No item prototypes yet. Click "Add Item Prototype" to create one.</td></tr>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <tr class="<?= $item['active'] ? '' : 'inactive-row' ?>">
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $item['item_type'])))) ?></td>
                    <td><?= htmlspecialchars($item['uom_name'] ?? '—') ?></td>
                    <td><?= $item['requires_inspection'] ? 'Yes' : 'No' ?></td>
                    <td>
                        <span class="badge <?= $item['active'] ? 'badge-active' : 'badge-inactive' ?>">
                            <?= $item['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="actions-cell">
                        <a href="/settings/item-prototypes/form?id=<?= $item['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <?php if ($item['active']): ?>
                            <form method="POST" action="/settings/item-prototypes" class="inline-toggle">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-warning">Deactivate</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="/settings/item-prototypes" class="inline-toggle">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">Activate</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
