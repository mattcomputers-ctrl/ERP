<div style="display:flex;align-items:baseline;gap:8px;margin-bottom:12px">
  <div class="stat-value"><?= $data['count'] ?? 0 ?></div>
  <div class="stat-sub">open / in progress</div>
</div>
<?php foreach ($data['rows'] ?? [] as $row): ?>
<div style="display:flex;align-items:center;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border);font-size:12px">
  <a href="/batches/<?= $row['id'] ?>" style="font-weight:500"><?= htmlspecialchars($row['batch_number']) ?></a>
  <span style="color:var(--text-secondary)"><?= htmlspecialchars($row['item_code']) ?></span>
  <span class="badge <?= ($row['status']??'')==='IN_PROGRESS'?'badge-blue':'badge-gray' ?>"><?= ($row['status']??'')==='IN_PROGRESS'?'In Progress':'Open' ?></span>
</div>
<?php endforeach; ?>
<?php if (($data['count'] ?? 0) > 5): ?><a href="<?= $data['url'] ?? '/batches' ?>" class="stat-link" style="margin-top:8px;display:inline-block">View all <?= $data['count'] ?> &rarr;</a><?php endif; ?>
