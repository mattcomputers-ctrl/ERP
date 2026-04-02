<?php $rows = $data['rows'] ?? []; ?>
<?php if (empty($rows)): ?>
<div style="text-align:center;padding:20px;color:var(--color-success);font-size:13px;font-weight:500">All demand is covered</div>
<?php else: ?>
<div style="font-size:12px;color:var(--color-danger);font-weight:600;margin-bottom:8px"><?= count($rows) ?> items with no coverage plan</div>
<table style="font-size:12px;width:100%;border-collapse:collapse">
  <thead><tr><th style="text-align:left;padding:4px 8px;font-size:10px;text-transform:uppercase;color:var(--text-muted)">Item</th><th style="text-align:right;padding:4px 8px;font-size:10px;text-transform:uppercase;color:var(--text-muted)">Demand</th><th style="text-align:right;padding:4px 8px;font-size:10px;text-transform:uppercase;color:var(--text-muted)">Coverage</th><th style="text-align:right;padding:4px 8px;font-size:10px;text-transform:uppercase;color:var(--text-muted)">Gap</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): $coverage = (float)($row['on_hand']??0) + (float)($row['batch_supply']??0) + (float)($row['po_supply']??0); $gap = (float)($row['demand']??0) - $coverage; ?>
  <tr>
    <td style="padding:4px 8px"><?= htmlspecialchars($row['item_code'] ?? '') ?><br><span style="color:var(--text-muted);font-size:11px"><?= htmlspecialchars($row['description'] ?? '') ?></span></td>
    <td style="padding:4px 8px;text-align:right"><?= number_format((float)($row['demand']??0), 1) ?></td>
    <td style="padding:4px 8px;text-align:right"><?= number_format($coverage, 1) ?></td>
    <td style="padding:4px 8px;text-align:right;color:var(--color-danger);font-weight:600"><?= number_format($gap, 1) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<a href="/mrp" class="stat-link" style="margin-top:8px;display:inline-block">View MRP &rarr;</a>
<?php endif; ?>
