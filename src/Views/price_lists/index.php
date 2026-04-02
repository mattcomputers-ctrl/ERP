<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Price Lists — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1200px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Price Lists</h1>
            <a href="/price-lists/create" class="btn btn-primary">+ New Price List</a>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Priority</th>
                    <th>Effective</th>
                    <th>Expires</th>
                    <th style="text-align:right;">Lines</th>
                    <th style="text-align:right;">Customers</th>
                    <th style="text-align:right;">Suppliers</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($lists)): ?>
                <tr><td colspan="10" style="text-align:center;color:#9ca3af;">No price lists yet.</td></tr>
            <?php else: foreach ($lists as $l): ?>
                <tr class="<?= !$l['active'] ? 'inactive-row' : '' ?>">
                    <td><a href="/price-lists/<?= $l['id'] ?>" style="color:#2563eb;font-weight:500;text-decoration:none;"><?= htmlspecialchars($l['name']) ?></a></td>
                    <td><span class="badge <?= $l['list_type']==='CUSTOMER'?'badge-info':'badge-warning' ?>"><?= $l['list_type'] ?></span></td>
                    <td><?= (int)$l['default_priority'] ?></td>
                    <td><?= date('M j, Y', strtotime($l['effective_date'])) ?></td>
                    <td><?= $l['expiration_date'] ? date('M j, Y', strtotime($l['expiration_date'])) : '—' ?></td>
                    <td style="text-align:right;"><?= (int)$l['line_count'] ?></td>
                    <td style="text-align:right;"><?= (int)$l['customer_count'] ?></td>
                    <td style="text-align:right;"><?= (int)$l['supplier_count'] ?></td>
                    <td><span class="badge <?= $l['active']?'badge-active':'badge-inactive' ?>"><?= $l['active']?'Active':'Inactive' ?></span></td>
                    <td>
                        <a href="/price-lists/<?= $l['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="/price-lists/<?= $l['id'] ?>/clone" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <button type="submit" class="btn btn-sm btn-secondary">Clone</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
