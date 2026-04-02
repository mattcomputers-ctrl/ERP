<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Create Shipment — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:900px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Select Orders to Ship</h1>
        </div>
        <form method="GET" action="/shipping/create">
            <?php if(empty($orders)):?><p style="color:#9ca3af;padding:24px;text-align:center;">No confirmed orders ready to ship.</p>
            <?php else:?>
            <table class="data-table">
                <thead><tr><th style="width:30px;"><input type="checkbox" onclick="document.querySelectorAll('.so-cb').forEach(c=>c.checked=this.checked)"></th><th>SO #</th><th>Customer</th><th>Ship-To</th><th>Promised Ship</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach($orders as $o):$pastDue=$o['promised_ship_date']&&$o['promised_ship_date']<date('Y-m-d');?>
                <tr><td><input type="checkbox" name="so_ids[]" value="<?=$o['id']?>" class="so-cb"></td><td style="font-weight:500;"><?=htmlspecialchars($o['so_number'])?></td><td><?=htmlspecialchars($o['company_name'])?></td><td><?=htmlspecialchars($o['ship_to_name']??'—')?></td><td style="<?=$pastDue?'color:#dc2626;font-weight:600;':''?>"><?=$o['promised_ship_date']?date('M j, Y',strtotime($o['promised_ship_date'])):'—'?></td><td><span class="badge badge-info"><?=$o['status']?></span></td></tr>
                <?php endforeach;?>
                </tbody>
            </table>
            <div style="margin-top:16px;"><button type="submit" class="btn btn-primary">Continue to Ship</button></div>
            <?php endif;?>
        </form>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
