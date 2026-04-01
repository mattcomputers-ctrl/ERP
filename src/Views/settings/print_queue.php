<h1>Print Queue</h1>

<?php if (empty($queue)): ?>
    <p style="color:#999; margin:20px 0;">Print queue is empty.</p>
    <p><a href="/" class="btn btn-secondary">Back to Dashboard</a></p>
<?php else: ?>
    <div style="display:flex; gap:8px; margin-bottom:16px;">
        <form method="POST" action="/settings/print-queue/print" style="display:inline;">
            <button type="submit" class="btn btn-primary">Print All (<?= count($queue) ?> items)</button>
        </form>
        <form method="POST" action="/settings/print-queue/clear" style="display:inline;" onsubmit="return confirm('Clear all items from the print queue?');">
            <button type="submit" class="btn btn-danger">Clear All</button>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Document</th>
                <th style="width:80px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($queue as $item): ?>
                <tr id="pq-<?= htmlspecialchars($item['type']) ?>-<?= (int)$item['id'] ?>">
                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $item['type']))) ?></td>
                    <td><?= htmlspecialchars($item['label']) ?></td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="removeFromQueue('<?= htmlspecialchars($item['type']) ?>', <?= (int)$item['id'] ?>)">Remove</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<script>
function removeFromQueue(type, id) {
    fetch('/print-queue/remove', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'type=' + encodeURIComponent(type) + '&id=' + id
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var row = document.getElementById('pq-' + type + '-' + id);
        if (row) row.remove();
        // Update badge
        var badge = document.querySelector('.print-queue-badge');
        if (badge) badge.textContent = data.count;
        if (data.count === 0) location.reload();
    });
}
</script>
