<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Consignment — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1200px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Consignment Placements</h1>
            <a href="/consignment/create" class="btn btn-primary">+ New Placement</a>
        </div>
        <form method="GET" action="/consignment" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Search</label><input type="text" name="q" value="<?=htmlspecialchars($filters['q']??'')?>" placeholder="CON#, customer, item..." class="form-input" style="font-size:13px;padding:4px 8px;width:200px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Status</label><select name="status" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="ACTIVE" <?=($filters['status']??'ACTIVE')==='ACTIVE'?'selected':''?>>Active</option><option value="CLOSED" <?=($filters['status']??'')==='CLOSED'?'selected':''?>>Closed</option><option value="" <?=($filters['status']??'ACTIVE')===''?'selected':''?>>All</option></select></div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
        </form>
        <table class="data-table">
            <thead><tr><th>CON #</th><th>Customer</th><th>Item</th><th>Facility</th><th style="text-align:right;">Placed</th><th style="text-align:right;">Consumed</th><th style="text-align:right;">Balance</th><th>Last Consumption</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($placements)):?><tr><td colspan="10" class="empty-state">No placements found.</td></tr>
            <?php else:foreach($placements as $p):?>
            <tr>
                <td><a href="/consignment/<?=$p['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($p['con_number'])?></a></td>
                <td><?=htmlspecialchars(($p['customer_code']??'').' — '.($p['customer_name']??''))?></td>
                <td><?=htmlspecialchars($p['item_code'])?> <span style="color:#6b7280;font-size:12px;"><?=htmlspecialchars($p['item_description'])?></span></td>
                <td><?=htmlspecialchars($p['facility_name']??'')?></td>
                <td style="text-align:right;"><?=number_format((float)$p['quantity_placed'],4)?></td>
                <td style="text-align:right;"><?=number_format((float)$p['total_consumed'],4)?></td>
                <td style="text-align:right;font-weight:600;<?=$p['is_low']?'color:#dc2626;':''?>"><?=number_format((float)$p['balance'],4)?> <?=$p['is_low']?'<span class="badge badge-warning" style="font-size:10px;">LOW</span>':''?></td>
                <td><?=$p['last_consumption']?date('M j, Y',strtotime($p['last_consumption'])):'—'?></td>
                <td><span class="badge <?=$p['status']==='ACTIVE'?'badge-active':'badge-inactive'?>"><?=$p['status']?></span></td>
                <td class="actions-cell"><a href="/consignment/<?=$p['id']?>" class="btn btn-sm btn-secondary">View</a></td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
        <?php if($totalPages>1):?><div style="display:flex;justify-content:center;gap:4px;margin-top:16px;"><?php for($pg=1;$pg<=$totalPages;$pg++):?><a href="/consignment?page=<?=$pg?>&status=<?=htmlspecialchars($filters['status']??'')?>" class="btn btn-sm <?=$pg===$page?'btn-primary':'btn-secondary'?>" style="min-width:32px;text-align:center;text-decoration:none;"><?=$pg?></a><?php endfor;?></div><?php endif;?>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
