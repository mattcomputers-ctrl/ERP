<?php $rows = $data['rows'] ?? []; ?>
<?php if (empty($rows)): ?>
<div style="text-align:center;padding:12px;color:var(--color-success);font-size:13px">All items above reorder minimum</div>
<?php else: foreach ($rows as $row): ?>
<div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid var(--border);font-size:12px">
  <div style="flex:1"><div style="font-weight:500"><?= htmlspecialchars($row['item_code']) ?></div><div style="color:var(--text-muted);font-size:11px"><?= htmlspecialchars($row['description']) ?></div></div>
  <div style="text-align:right"><div style="color:var(--color-danger);font-weight:600"><?= number_format((float)$row['on_hand'],1) ?></div><div style="color:var(--text-muted);font-size:11px">min: <?= number_format((float)$row['reorder_min'],1) ?></div></div>
</div>
<?php endforeach; ?>
<a href="<?= $data['url'] ?? '/mrp' ?>" class="stat-link" style="margin-top:8px;display:inline-block">Run MRP &rarr;</a>
<?php endif; ?>
