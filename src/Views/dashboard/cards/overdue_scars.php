<div style="text-align:center;padding:8px 0">
  <div class="stat-value <?= ($data['count'] ?? 0) > 0 ? 'danger' : 'success' ?>"><?= $data['count'] ?? 0 ?></div>
  <div class="stat-sub">SCARs past due date</div>
  <a href="<?= $data['url'] ?? '/scars' ?>" class="stat-link">View SCARs &rarr;</a>
</div>
