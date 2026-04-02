<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Variance Review — Session #<?= $session['id'] ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
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
            <h1 style="margin:0;">
                Variance Review — Session #<?= $session['id'] ?>
                <span class="badge <?= match($session['status']) { 'IN_REVIEW'=>'badge-warning','POSTED'=>'badge-active', default=>'badge-info' } ?>" style="font-size:12px; vertical-align:middle;"><?= $session['status'] ?></span>
            </h1>
            <div style="display:flex; gap:8px;">
                <?php if ($session['status'] === 'OPEN'): ?>
                <a href="/inventory/counts/<?= $session['id'] ?>" class="btn btn-secondary">Edit Counts</a>
                <?php endif; ?>
                <a href="/inventory/counts" class="btn btn-secondary">&larr; Back</a>
            </div>
        </div>
        <p style="font-size:13px; color:#6b7280; margin-bottom:16px;">Facility: <?= htmlspecialchars($session['facility_name']) ?>
            <?php if ($session['status'] === 'POSTED'): ?> | Posted by <?= htmlspecialchars($session['posted_by_name'] ?? '') ?> on <?= date('M j, Y g:ia', strtotime($session['posted_at'])) ?><?php endif; ?>
        </p>

        <form method="POST" action="/inventory/counts/<?= $session['id'] ?>/post">
            <?php
            $totalBookQty = 0; $totalCountedQty = 0; $totalVarianceValue = 0;
            $hasVariance = false;
            ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <?php if ($session['status'] !== 'POSTED'): ?><th style="width:40px;"><input type="checkbox" id="approveAll" onclick="document.querySelectorAll('.approve-cb').forEach(function(c){c.checked=this.checked;}.bind(this))"></th><?php endif; ?>
                        <th>Item</th><th style="text-align:right;">Book Qty</th><th style="text-align:right;">Counted Qty</th><th style="text-align:right;">Variance Qty</th><th style="text-align:right;">Avg Cost</th><th style="text-align:right;">Variance Value</th>
                        <?php if ($session['status'] === 'POSTED'): ?><th>Approved</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lines)): ?>
                        <tr><td colspan="<?= $session['status'] !== 'POSTED' ? 7 : 8 ?>" class="empty-state">No counted lines to review.</td></tr>
                    <?php else: foreach ($lines as $l):
                        $variance = (float)$l['variance'];
                        $varianceValue = $variance * (float)$l['avg_unit_cost'];
                        $totalBookQty += (float)$l['book_quantity'];
                        $totalCountedQty += (float)$l['counted_quantity'];
                        $totalVarianceValue += $varianceValue;
                        if ($variance != 0) $hasVariance = true;
                    ?>
                    <tr style="<?= $variance != 0 ? 'background:#fefce8;' : '' ?>">
                        <?php if ($session['status'] !== 'POSTED'): ?>
                        <td><input type="checkbox" name="approved[]" value="<?= $l['id'] ?>" class="approve-cb" <?= $l['approved'] ? 'checked' : '' ?> <?= $variance == 0 ? 'disabled' : '' ?>></td>
                        <?php endif; ?>
                        <td><span style="font-weight:500;"><?= htmlspecialchars($l['item_code']) ?></span> <span style="color:#6b7280; font-size:12px;"><?= htmlspecialchars($l['item_description']) ?></span></td>
                        <td style="text-align:right;"><?= number_format((float)$l['book_quantity'], 4) ?></td>
                        <td style="text-align:right;"><?= number_format((float)$l['counted_quantity'], 4) ?></td>
                        <td style="text-align:right; font-weight:600; <?= $variance > 0 ? 'color:#16a34a;' : ($variance < 0 ? 'color:#dc2626;' : '') ?>">
                            <?= $variance > 0 ? '+' : '' ?><?= number_format($variance, 4) ?>
                        </td>
                        <td style="text-align:right;">$<?= number_format((float)$l['avg_unit_cost'], 4) ?></td>
                        <td style="text-align:right; <?= $varianceValue > 0 ? 'color:#16a34a;' : ($varianceValue < 0 ? 'color:#dc2626;' : '') ?>">
                            <?= $varianceValue >= 0 ? '$' : '-$' ?><?= number_format(abs($varianceValue), 2) ?>
                        </td>
                        <?php if ($session['status'] === 'POSTED'): ?>
                        <td><?= $l['approved'] ? '<span style="color:#16a34a;">&#10003;</span>' : '' ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Totals row -->
                    <tr style="font-weight:600; border-top:2px solid #d1d5db;">
                        <?php if ($session['status'] !== 'POSTED'): ?><td></td><?php endif; ?>
                        <td style="text-align:right;">Totals</td>
                        <td style="text-align:right;"><?= number_format($totalBookQty, 4) ?></td>
                        <td style="text-align:right;"><?= number_format($totalCountedQty, 4) ?></td>
                        <td style="text-align:right;"><?= number_format($totalCountedQty - $totalBookQty, 4) ?></td>
                        <td></td>
                        <td style="text-align:right; <?= $totalVarianceValue < 0 ? 'color:#dc2626;' : '' ?>">
                            <?= $totalVarianceValue >= 0 ? '$' : '-$' ?><?= number_format(abs($totalVarianceValue), 2) ?>
                        </td>
                        <?php if ($session['status'] === 'POSTED'): ?><td></td><?php endif; ?>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (in_array($session['status'], ['OPEN', 'IN_REVIEW']) && $hasVariance): ?>
            <div style="margin-top:16px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Post approved variances? This will adjust inventory.')">Post Approved Variances</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display='none'; }, 4000);</script>
</body>
</html>
