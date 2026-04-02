<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landed Costs — <?= htmlspecialchars($po['po_number']) ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
    <header class="app-header">
        <div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?><span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span><?php endif; ?>
        </div>
    </header>
    <div style="max-width:1100px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast"><?= htmlspecialchars($_SESSION['toast']['message']) ?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">Landed Costs: <?= htmlspecialchars($po['po_number']) ?></h1>
            <a href="/purchase-orders/<?= $po['id'] ?>" class="btn btn-secondary">&larr; Back to PO</a>
        </div>

        <!-- Existing Landed Costs -->
        <h3 style="margin-bottom:8px;">Existing Landed Costs</h3>
        <?php if (empty($landedCosts)): ?>
            <p style="color:#9ca3af; margin-bottom:16px;">No landed costs recorded yet.</p>
        <?php else: ?>
            <?php foreach ($landedCosts as $lc): ?>
            <div style="padding:12px; background:<?= $lc['posted'] ? '#f0fdf4' : '#f9fafb' ?>; border:1px solid <?= $lc['posted'] ? '#bbf7d0' : '#e5e7eb' ?>; border-radius:6px; margin-bottom:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong><?= htmlspecialchars($lc['cost_type']) ?></strong>
                        <span style="font-size:16px; font-weight:600; margin-left:8px;">$<?= number_format((float)$lc['amount'], 2) ?></span>
                        <span class="badge <?= $lc['posted'] ? 'badge-active' : 'badge-warning' ?>" style="margin-left:8px;"><?= $lc['posted'] ? 'POSTED' : 'PENDING' ?></span>
                        <span class="badge badge-inactive" style="margin-left:4px;"><?= $lc['allocation_method'] ?></span>
                        <span style="font-size:12px; color:#6b7280; margin-left:8px;">Receipt: <?= htmlspecialchars($lc['supplier_invoice_number']) ?></span>
                    </div>
                    <?php if (!$lc['posted']): ?>
                    <form method="POST" action="/purchase-orders/<?= $po['id'] ?>/landed-costs/<?= $lc['id'] ?>/post" style="display:inline;"
                          <?php if ($lc['allocation_method'] === 'MANUAL'): ?>
                          id="postForm-<?= $lc['id'] ?>"
                          <?php endif; ?>>
                        <?php if ($lc['allocation_method'] === 'MANUAL'): ?>
                            <!-- Manual allocation inputs shown below -->
                        <?php endif; ?>
                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Post this landed cost? FIFO lot costs will be updated.')">Post</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php if ($lc['notes']): ?>
                <p style="font-size:12px; color:#6b7280; margin:4px 0 0;"><?= htmlspecialchars($lc['notes']) ?></p>
                <?php endif; ?>

                <?php if ($lc['posted'] && !empty($allocations[$lc['id']])): ?>
                <table class="data-table" style="margin-top:8px; font-size:12px;">
                    <thead><tr><th>Item</th><th style="text-align:right;">Allocated</th><th>FIFO Lot</th></tr></thead>
                    <tbody>
                        <?php foreach ($allocations[$lc['id']] as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['item_code']) ?></td>
                            <td style="text-align:right;">$<?= number_format((float)$a['allocated_amount'], 4) ?></td>
                            <td><?= $a['fifo_lot_id'] ? '#' . $a['fifo_lot_id'] : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!$lc['posted'] && $lc['allocation_method'] === 'MANUAL'): ?>
                <!-- Manual allocation inputs -->
                <div style="margin-top:8px; padding:8px; background:#fff; border-radius:4px;">
                    <p style="font-size:12px; font-weight:600; margin-bottom:4px;">Enter allocation per receipt line (total must equal $<?= number_format((float)$lc['amount'], 2) ?>):</p>
                    <form method="POST" action="/purchase-orders/<?= $po['id'] ?>/landed-costs/<?= $lc['id'] ?>/post">
                        <?php $rlForReceipt = $receiptLines[$lc['receipt_id']] ?? []; ?>
                        <?php foreach ($rlForReceipt as $rl): ?>
                        <div style="display:flex; gap:8px; align-items:center; margin-bottom:4px;">
                            <span style="font-size:12px; width:200px;"><?= htmlspecialchars($rl['item_code']) ?> (<?= number_format((float)$rl['received_quantity'], 4) ?> @ $<?= number_format((float)$rl['unit_cost'], 4) ?>)</span>
                            <input type="number" step="0.0001" name="manual_allocation[<?= $rl['id'] ?>]" class="form-input" style="width:120px; font-size:12px; padding:3px 6px;" placeholder="$0.00">
                        </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-sm btn-primary" style="margin-top:4px;" onclick="return confirm('Post manual allocation?')">Post Manual Allocation</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if (!$lc['posted'] && $lc['allocation_method'] !== 'MANUAL'): ?>
                <!-- Preview auto-allocation -->
                <?php
                $rlForReceipt = $receiptLines[$lc['receipt_id']] ?? [];
                $totalQty = 0; $totalValue = 0;
                foreach ($rlForReceipt as $rl) {
                    $totalQty += (float)$rl['received_quantity'];
                    $totalValue += (float)$rl['received_quantity'] * (float)$rl['unit_cost'];
                }
                ?>
                <?php if (!empty($rlForReceipt)): ?>
                <table class="data-table" style="margin-top:8px; font-size:12px;">
                    <thead><tr><th>Item</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Value</th><th style="text-align:right;">Preview Allocation</th></tr></thead>
                    <tbody>
                        <?php foreach ($rlForReceipt as $rl):
                            $lineQty = (float)$rl['received_quantity'];
                            $lineVal = $lineQty * (float)$rl['unit_cost'];
                            if ($lc['allocation_method'] === 'BY_VALUE' && $totalValue > 0) {
                                $preview = (float)$lc['amount'] * ($lineVal / $totalValue);
                            } else {
                                $preview = $totalQty > 0 ? (float)$lc['amount'] * ($lineQty / $totalQty) : 0;
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($rl['item_code']) ?></td>
                            <td style="text-align:right;"><?= number_format($lineQty, 4) ?></td>
                            <td style="text-align:right;">$<?= number_format($lineVal, 2) ?></td>
                            <td style="text-align:right; font-weight:600;">$<?= number_format($preview, 4) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Add Landed Cost Form -->
        <?php if (!empty($receipts)): ?>
        <h3 style="margin-top:24px; margin-bottom:8px;">Add Landed Cost</h3>
        <form method="POST" action="/purchase-orders/<?= $po['id'] ?>/landed-costs" class="form-section">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:12px; display:block; margin-bottom:2px;">Receipt <span style="color:red;">*</span></label>
                    <select name="receipt_id" required class="form-input" style="width:100%;">
                        <?php foreach ($receipts as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['supplier_invoice_number']) ?> (<?= date('M j', strtotime($r['receipt_date'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px; display:block; margin-bottom:2px;">Cost Type <span style="color:red;">*</span></label>
                    <input type="text" name="cost_type" required class="form-input" style="width:100%;" placeholder="e.g. Freight, Duty, Brokerage">
                </div>
                <div>
                    <label style="font-size:12px; display:block; margin-bottom:2px;">Amount <span style="color:red;">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" required class="form-input" style="width:100%;">
                </div>
                <div>
                    <label style="font-size:12px; display:block; margin-bottom:2px;">Allocation Method</label>
                    <select name="allocation_method" class="form-input" style="width:100%;">
                        <option value="BY_QUANTITY">By Quantity</option>
                        <option value="BY_VALUE">By Value</option>
                        <option value="MANUAL">Manual</option>
                    </select>
                </div>
            </div>
            <div style="margin-top:8px;">
                <label style="font-size:12px; display:block; margin-bottom:2px;">Notes</label>
                <input type="text" name="notes" class="form-input" style="width:100%;">
            </div>
            <div style="margin-top:12px;">
                <button type="submit" class="btn btn-primary">Add Landed Cost</button>
            </div>
        </form>
        <?php else: ?>
        <p style="color:#9ca3af; margin-top:16px;">No receipts recorded yet — receive a shipment first before adding landed costs.</p>
        <?php endif; ?>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>var toast = document.getElementById('toast'); if (toast) setTimeout(function(){toast.style.display='none';}, 4000);</script>
</body>
</html>
