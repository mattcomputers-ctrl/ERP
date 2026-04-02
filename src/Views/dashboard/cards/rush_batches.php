<?php $rows = $data['rows'] ?? []; ?>
<?php if (empty($rows)): ?>
<div style="text-align:center;padding:12px;color:var(--color-success);font-size:13px">No RUSH batches</div>
<?php else: foreach ($rows as $row): ?>
<div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid var(--border);font-size:12px">
  <span class="badge badge-orange">RUSH</span>
  <a href="/batches/<?= $row['id'] ?>" style="font-weight:500;flex:1"><?= htmlspecialchars($row['batch_number']) ?></a>
  <span style="color:var(--text-secondary)"><?= htmlspecialchars($row['item_code']) ?></span>
  <span style="color:var(--text-muted);font-size:11px"><?= $row['scheduled_date'] ?? '' ?></span>
</div>
<?php endforeach; endif; ?>
