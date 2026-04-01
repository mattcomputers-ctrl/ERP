<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive Transfer <?= htmlspecialchars($transfer['trf_number']) ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
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

    <div style="max-width:900px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast">
                <?= htmlspecialchars($_SESSION['toast']['message']) ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">Receive Transfer: <?= htmlspecialchars($transfer['trf_number']) ?></h1>
            <a href="/transfers/<?= $transfer['id'] ?>" class="btn btn-secondary">&larr; Back</a>
        </div>

        <div class="form-section" style="margin-bottom:16px; padding:12px;">
            <strong>From:</strong> <?= htmlspecialchars($transfer['from_facility_name']) ?>
            &rarr;
            <strong>To:</strong> <?= htmlspecialchars($transfer['to_facility_name']) ?>
            &nbsp; | &nbsp;
            <strong>Shipped:</strong> <?= $transfer['shipped_at'] ? date('M j, Y g:ia', strtotime($transfer['shipped_at'])) : '' ?>
        </div>

        <form method="POST" action="/transfers/<?= $transfer['id'] ?>/receive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Shipped Qty</th>
                        <th>Lots</th>
                        <th>Received Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $i => $line): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <strong><?= htmlspecialchars($line['item_code'] ?? '') ?></strong>
                            <br><span style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($line['item_description'] ?? '') ?></span>
                        </td>
                        <td><?= number_format((float)$line['shipped_quantity'], 4) ?> <?= htmlspecialchars($line['uom_abbr'] ?? '') ?></td>
                        <td>
                            <?php if (!empty($line['lots'])): ?>
                                <?php foreach ($line['lots'] as $lot): ?>
                                <div style="font-size:12px;">
                                    <?= htmlspecialchars($lot['lot_number']) ?>: <?= number_format((float)$lot['quantity'], 4) ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color:#9ca3af;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="number" step="0.0001" min="0"
                                   max="<?= (float)$line['shipped_quantity'] ?>"
                                   name="received_quantities[<?= $line['id'] ?>]"
                                   value="<?= (float)$line['shipped_quantity'] ?>"
                                   class="form-input"
                                   style="width:120px; font-size:13px; text-align:right;">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="font-size:12px; color:#6b7280; margin-top:8px;">
                Adjust received quantities if items were damaged in transit.
            </p>

            <div style="display:flex; gap:8px; margin-top:16px;">
                <button type="submit" class="btn btn-primary">Confirm Receipt</button>
                <a href="/transfers/<?= $transfer['id'] ?>" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
            </div>
        </form>
    </div>

    <script>
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);
    </script>
</body>
</html>
