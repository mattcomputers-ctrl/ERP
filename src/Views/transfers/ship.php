<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ship Transfer <?= htmlspecialchars($transfer['trf_number']) ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .lot-row { display:flex; gap:8px; align-items:center; margin:4px 0; padding:4px 8px; background:#f9fafb; border-radius:4px; font-size:13px; }
        .lot-row input { font-size:13px; padding:2px 6px; }
        .expired { color:#dc2626; font-weight:600; }
        .other-stock { font-size:12px; color:#6b7280; font-style:italic; margin-top:4px; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="header-left">
            <a href="/" class="app-logo">Precision Ink ERP</a>
        </div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?>
                <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span>
            <?php endif; ?>
        </div>
    </header>

    <div style="max-width:1000px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast">
                <?= htmlspecialchars($_SESSION['toast']['message']) ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">Ship Transfer: <?= htmlspecialchars($transfer['trf_number']) ?></h1>
            <a href="/transfers/<?= $transfer['id'] ?>" class="btn btn-secondary">&larr; Back</a>
        </div>

        <div class="form-section" style="margin-bottom:16px; padding:12px;">
            <strong>From:</strong> <?= htmlspecialchars($transfer['from_facility_name']) ?>
            &rarr;
            <strong>To:</strong> <?= htmlspecialchars($transfer['to_facility_name']) ?>
            &nbsp; | &nbsp;
            <strong>Date:</strong> <?= date('M j, Y', strtotime($transfer['requested_date'])) ?>
        </div>

        <form method="POST" action="/transfers/<?= $transfer['id'] ?>/ship" id="ship-form">
            <?php foreach ($lines as $lineIdx => $line): ?>
            <div style="margin-bottom:20px; border:1px solid #e5e7eb; border-radius:6px; padding:12px;">
                <h4 style="margin:0 0 8px;">
                    <?= htmlspecialchars($line['item_code']) ?> — <?= htmlspecialchars($line['item_description']) ?>
                    <span style="font-size:13px; color:#6b7280; font-weight:normal;">
                        (Requested: <?= number_format((float)$line['quantity'], 4) ?> <?= htmlspecialchars($line['uom_abbr'] ?? '') ?>)
                    </span>
                </h4>

                <?php if (!empty($line['available_lots'])): ?>
                <p style="font-size:12px; color:#6b7280; margin:0 0 6px;">Select lot(s) to ship from:</p>
                <table style="width:100%; font-size:13px; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f3f4f6;">
                            <th style="padding:4px 8px; text-align:left;">Lot #</th>
                            <th style="padding:4px 8px; text-align:left;">Expires</th>
                            <th style="padding:4px 8px; text-align:right;">Available</th>
                            <th style="padding:4px 8px; text-align:left;">Location</th>
                            <th style="padding:4px 8px; text-align:right;">Ship Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($line['available_lots'] as $lotIdx => $lot): ?>
                        <tr>
                            <td style="padding:4px 8px;">
                                <?= htmlspecialchars($lot['lot_number']) ?>
                                <input type="hidden"
                                       name="lot_selections[<?= $line['id'] ?>][<?= $lotIdx ?>][lot_number]"
                                       value="<?= htmlspecialchars($lot['lot_number']) ?>">
                                <input type="hidden"
                                       name="lot_selections[<?= $line['id'] ?>][<?= $lotIdx ?>][fifo_lot_id]"
                                       value="<?= $lot['id'] ?>">
                            </td>
                            <td style="padding:4px 8px;">
                                <?php if ($lot['expiration_date']): ?>
                                    <?php $expired = strtotime($lot['expiration_date']) < time(); ?>
                                    <span class="<?= $expired ? 'expired' : '' ?>">
                                        <?= date('M j, Y', strtotime($lot['expiration_date'])) ?>
                                        <?= $expired ? ' (EXPIRED)' : '' ?>
                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td style="padding:4px 8px; text-align:right;"><?= number_format((float)$lot['remaining_quantity'], 4) ?></td>
                            <td style="padding:4px 8px;"><?= htmlspecialchars($lot['location'] ?? '') ?></td>
                            <td style="padding:4px 8px; text-align:right;">
                                <input type="number" step="0.0001" min="0"
                                       max="<?= $lot['remaining_quantity'] ?>"
                                       name="lot_selections[<?= $line['id'] ?>][<?= $lotIdx ?>][quantity]"
                                       value="0" class="form-input lot-qty-input"
                                       data-line-id="<?= $line['id'] ?>"
                                       style="width:100px; text-align:right;">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="font-size:12px; margin:4px 0 0;">
                    Total selected: <strong class="line-total" data-line-id="<?= $line['id'] ?>">0.0000</strong>
                    / <?= number_format((float)$line['quantity'], 4) ?> required
                </p>
                <?php else: ?>
                <p style="color:#dc2626; font-size:13px;">No lots available at this facility for this item.</p>
                <?php endif; ?>

                <?php if (!empty($line['other_facility_stock'])): ?>
                <div class="other-stock">
                    Also available:
                    <?php foreach ($line['other_facility_stock'] as $os): ?>
                        <?= number_format((float)$os['available_qty'], 2) ?> at <?= htmlspecialchars($os['facility_name']) ?>;
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div style="display:flex; gap:8px; margin-top:16px;">
                <button type="submit" class="btn btn-primary">Confirm Shipment</button>
                <a href="/transfers/<?= $transfer['id'] ?>" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
            </div>
        </form>
    </div>

    <script>
    // Live total calculation for lot selections
    document.querySelectorAll('.lot-qty-input').forEach(function(input) {
        input.addEventListener('input', function() {
            var lineId = this.dataset.lineId;
            var inputs = document.querySelectorAll('.lot-qty-input[data-line-id="' + lineId + '"]');
            var total = 0;
            inputs.forEach(function(inp) { total += parseFloat(inp.value) || 0; });
            var display = document.querySelector('.line-total[data-line-id="' + lineId + '"]');
            if (display) display.textContent = total.toFixed(4);
        });
    });

    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);
    </script>
</body>
</html>
