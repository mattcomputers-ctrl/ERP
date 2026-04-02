<?php
$thisMo = (float)($data['this_month'] ?? 0);
$lastMo = (float)($data['last_month'] ?? 0);
$change = $lastMo > 0 ? (($thisMo - $lastMo) / $lastMo * 100) : null;
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div><div class="stat-label">This Month</div><div class="stat-value"><?= '$'.number_format($thisMo) ?></div></div>
  <div><div class="stat-label">Last Month</div><div class="stat-value" style="color:var(--text-secondary)"><?= '$'.number_format($lastMo) ?></div></div>
</div>
<?php if ($change !== null): ?>
<div style="margin-top:10px;font-size:13px;color:<?= $change >= 0 ? 'var(--color-success)' : 'var(--color-danger)' ?>">
  <?= $change >= 0 ? '&#9650;' : '&#9660;' ?> <?= abs(round($change,1)) ?>% vs last month
</div>
<?php endif; ?>
