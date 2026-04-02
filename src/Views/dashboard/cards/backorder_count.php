<div style="text-align:center;padding:8px 0">
  <div class="stat-value <?= ($data['count'] ?? 0) > 0 ? 'warning' : 'success' ?>"><?= $data['count'] ?? 0 ?></div>
  <div class="stat-sub">backordered lines</div>
  <a href="<?= $data['url'] ?? '/orders' ?>" class="stat-link">View orders &rarr;</a>
</div>
