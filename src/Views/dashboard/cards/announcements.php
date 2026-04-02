<?php $rows = $data['rows'] ?? []; ?>
<?php if (empty($rows)): ?>
<div style="text-align:center;padding:12px;color:var(--text-muted);font-size:13px">No announcements</div>
<?php else: foreach ($rows as $ann):
  $typeClass = match($ann['priority'] ?? 'INFO') { 'URGENT' => 'critical', 'WARNING' => 'warning', default => 'info' };
?>
<div class="announcement-bar <?= $typeClass ?>" style="margin-bottom:6px;border-radius:4px">
  <strong><?= htmlspecialchars($ann['title']) ?>:</strong>
  <?= htmlspecialchars($ann['message'] ?? $ann['body'] ?? '') ?>
</div>
<?php endforeach; endif; ?>
