<?php $rows = $data['rows'] ?? []; ?>
<?php if (empty($rows)): ?>
<div style="text-align:center;padding:12px;color:var(--color-success);font-size:13px">No open tasks</div>
<?php else: foreach ($rows as $row):
  $overdue = !empty($row['due_date']) && strtotime($row['due_date']) < strtotime('today');
  $pColors = ['URGENT'=>'badge-red','HIGH'=>'badge-orange','NORMAL'=>'badge-blue','LOW'=>'badge-gray'];
?>
<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border);font-size:12px">
  <div style="flex:1"><div style="font-weight:500;<?= $overdue ? 'color:var(--color-danger)' : '' ?>"><?= htmlspecialchars($row['title']) ?></div><div style="color:var(--text-muted);font-size:11px"><?= htmlspecialchars($row['company_name'] ?? '') ?></div></div>
  <div style="text-align:right;flex-shrink:0">
    <span class="badge <?= $pColors[$row['priority'] ?? 'NORMAL'] ?? 'badge-gray' ?>"><?= $row['priority'] ?? 'NORMAL' ?></span>
    <div style="color:<?= $overdue ? 'var(--color-danger)' : 'var(--text-muted)' ?>;font-size:11px;margin-top:2px"><?= $row['due_date'] ?? '' ?></div>
  </div>
</div>
<?php endforeach; ?>
<a href="<?= $data['url'] ?? '/crm/tasks' ?>" class="stat-link" style="margin-top:8px;display:inline-block">View all tasks &rarr;</a>
<?php endif; ?>
