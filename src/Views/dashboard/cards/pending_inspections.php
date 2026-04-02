<div style="text-align:center;padding:8px 0">
  <div class="stat-value <?= ($data['count'] ?? 0) > 0 ? 'warning' : 'success' ?>"><?= $data['count'] ?? 0 ?></div>
  <div class="stat-sub">lots awaiting inspection</div>
  <a href="<?= $data['url'] ?? '/qc/inspection' ?>" class="stat-link">View queue &rarr;</a>
</div>
