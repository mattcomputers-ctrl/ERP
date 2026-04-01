<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer <?= htmlspecialchars($transfer['trf_number']) ?> — Precision Ink ERP</title>
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
            <h1 style="margin:0;">
                Transfer <?= htmlspecialchars($transfer['trf_number']) ?>
                <?php
                $badgeClass = match($transfer['status']) {
                    'DRAFT' => 'badge-inactive',
                    'SHIPPED' => 'badge-info',
                    'RECEIVED' => 'badge-active',
                    'CANCELLED' => 'badge-danger',
                    default => ''
                };
                ?>
                <span class="badge <?= $badgeClass ?>" style="font-size:14px; vertical-align:middle; <?= $transfer['status'] === 'CANCELLED' ? 'text-decoration:line-through;' : '' ?>">
                    <?= htmlspecialchars($transfer['status']) ?>
                </span>
            </h1>
            <div style="display:flex; gap:8px;">
                <a href="/transfers" class="btn btn-secondary">&larr; Back to List</a>
                <?php if ($transfer['status'] === 'DRAFT'): ?>
                    <a href="/transfers/<?= $transfer['id'] ?>/edit" class="btn btn-secondary">Edit</a>
                    <a href="/transfers/<?= $transfer['id'] ?>/ship" class="btn btn-primary">Ship</a>
                    <form method="POST" action="/transfers/<?= $transfer['id'] ?>/cancel" style="display:inline;" onsubmit="return confirm('Cancel this transfer?')">
                        <button type="submit" class="btn btn-warning">Cancel</button>
                    </form>
                <?php elseif ($transfer['status'] === 'SHIPPED'): ?>
                    <a href="/transfers/<?= $transfer['id'] ?>/receive" class="btn btn-primary">Receive</a>
                    <form method="POST" action="/transfers/<?= $transfer['id'] ?>/cancel" style="display:inline;" onsubmit="return confirm('Cancel this shipped transfer?')">
                        <button type="submit" class="btn btn-warning">Cancel</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Header Info -->
        <div class="form-section" style="margin-bottom:16px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">From Facility</strong>
                    <p style="margin:2px 0;"><?= htmlspecialchars($transfer['from_facility_name']) ?></p>
                </div>
                <div>
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">To Facility</strong>
                    <p style="margin:2px 0;"><?= htmlspecialchars($transfer['to_facility_name']) ?></p>
                </div>
                <div>
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Requested Date</strong>
                    <p style="margin:2px 0;"><?= date('M j, Y', strtotime($transfer['requested_date'])) ?></p>
                </div>
                <div>
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Created By</strong>
                    <p style="margin:2px 0;"><?= htmlspecialchars($transfer['created_by_name'] ?? '') ?> on <?= date('M j, Y g:ia', strtotime($transfer['created_at'])) ?></p>
                </div>
                <?php if ($transfer['shipped_by_name']): ?>
                <div>
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Shipped By</strong>
                    <p style="margin:2px 0;"><?= htmlspecialchars($transfer['shipped_by_name']) ?> on <?= date('M j, Y g:ia', strtotime($transfer['shipped_at'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($transfer['received_by_name']): ?>
                <div>
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Received By</strong>
                    <p style="margin:2px 0;"><?= htmlspecialchars($transfer['received_by_name']) ?> on <?= date('M j, Y g:ia', strtotime($transfer['received_at'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($transfer['notes']): ?>
            <div style="margin-top:12px;">
                <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Notes</strong>
                <p style="margin:2px 0;"><?= nl2br(htmlspecialchars($transfer['notes'])) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Line Items -->
        <h3 style="margin-bottom:8px;">Line Items</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Pack</th>
                    <th>Requested</th>
                    <th>Shipped</th>
                    <th>Received</th>
                    <th>UOM</th>
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
                    <td><?= htmlspecialchars($line['pack_description'] ?? 'Bulk') ?></td>
                    <td><?= number_format((float)$line['quantity'], 4) ?></td>
                    <td><?= $line['shipped_quantity'] !== null ? number_format((float)$line['shipped_quantity'], 4) : '—' ?></td>
                    <td><?= $line['received_quantity'] !== null ? number_format((float)$line['received_quantity'], 4) : '—' ?></td>
                    <td><?= htmlspecialchars($line['uom_abbr'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Attachments -->
        <?php require __DIR__ . '/../partials/attachments.php'; ?>
    </div>

    <script>
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);
    </script>
</body>
</html>
