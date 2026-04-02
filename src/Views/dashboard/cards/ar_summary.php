<?php
$buckets = ['Current'=>(float)($data['current_due']??0),'1-30'=>(float)($data['d1_30']??0),'31-60'=>(float)($data['d31_60']??0),'61-90'=>(float)($data['d61_90']??0),'90+'=>(float)($data['d90plus']??0)];
$total = (float)($data['total']??0);
?>
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:12px">
  <?php foreach ($buckets as $label => $amount): ?>
  <div style="text-align:center">
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);margin-bottom:4px"><?= $label ?></div>
    <div style="font-size:15px;font-weight:700;<?= $amount > 0 && $label !== 'Current' ? 'color:var(--color-danger)' : '' ?>"><?= '$'.number_format($amount) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<div style="border-top:1px solid var(--border);padding-top:8px;display:flex;justify-content:space-between;align-items:center">
  <span style="font-size:12px;color:var(--text-muted)">Total Outstanding</span>
  <span style="font-size:18px;font-weight:700"><?= '$'.number_format($total) ?></span>
</div>
<a href="/reports/sales/ar-aging" class="stat-link" style="margin-top:8px;display:inline-block">Full AR Aging &rarr;</a>
