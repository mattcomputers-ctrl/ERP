<?php $rows = $data['rows'] ?? []; ?>
<?php if (empty($rows)): ?>
<div style="text-align:center;padding:20px;color:var(--color-success);font-size:13px;font-weight:500">All orders can be fulfilled</div>
<?php else: ?>
<div style="font-size:12px;color:var(--color-danger);font-weight:600;margin-bottom:8px"><?= count($rows) ?> item/order combinations with insufficient inventory</div>
<table style="font-size:12px;width:100%;border-collapse:collapse">
  <thead><tr><th style="text-align:left;padding:4px 8px;font-size:10px;text-transform:uppercase;color:var(--text-muted)">SO#</th><th style="text-align:left;padding:4px 8px;font-size:10px;text-transform:uppercase;color:var(--text-muted)">Customer</th><th style="text-align:left;padding:4px 8px;font-size:10px;text-transform:uppercase;color:var(--text-muted)">Item</th><th style="text-align:right;padding:4px 8px;font-size:10px;text-transform:uppercase;color:var(--text-muted)">Demand</th><th style="text-align:right;padding:4px 8px;font-size:10px;text-transform:uppercase;color:var(--text-muted)">On Hand</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
  <tr>
    <td style="padding:4px 8px"><a href="/orders" style="font-weight:500"><?= htmlspecialchars($row['so_number'] ?? '') ?></a></td>
    <td style="padding:4px 8px;color:var(--text-secondary)"><?= htmlspecialchars($row['company_name'] ?? '') ?></td>
    <td style="padding:4px 8px"><?= htmlspecialchars($row['item_code'] ?? '') ?></td>
    <td style="padding:4px 8px;text-align:right"><?= number_format((float)($row['demand'] ?? 0), 1) ?></td>
    <td style="padding:4px 8px;text-align:right;color:var(--color-danger)"><?= number_format((float)($row['on_hand'] ?? 0), 1) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
