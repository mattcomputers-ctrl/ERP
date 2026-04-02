<div style="text-align:center;padding:8px 0">
  <div class="stat-value <?= ($data['count'] ?? 0) > 0 ? 'warning' : '' ?>"><?= $data['count'] ?? 0 ?></div>
  <div class="stat-sub">requisitions awaiting approval</div>
  <a href="<?= $data['url'] ?? '/requisitions' ?>" class="stat-link">View requisitions &rarr;</a>
</div>
