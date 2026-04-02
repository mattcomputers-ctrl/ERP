<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>New Pick List — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:900px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">New Pick List</h1>
            <a href="/pick-lists" class="btn btn-secondary">&larr; Back</a>
        </div>
        <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">Facility: <strong><?=htmlspecialchars($facilityName)?></strong> — Select confirmed orders to pick:</p>

        <form method="POST" action="/pick-lists/create">
            <?php if(empty($orders)):?>
                <p style="color:#9ca3af;padding:24px;text-align:center;">No confirmed orders with unshipped lines at this facility.</p>
            <?php else:?>
            <table class="data-table">
                <thead><tr><th style="width:30px;"><input type="checkbox" id="selAll" onclick="document.querySelectorAll('.so-cb').forEach(function(c){c.checked=this.checked;}.bind(this))"></th><th>SO #</th><th>Customer</th><th>Ship-To</th><th>Promised Ship</th><th>Unshipped Lines</th></tr></thead>
                <tbody>
                <?php foreach($orders as $o):
                    $pastDue=$o['promised_ship_date']&&$o['promised_ship_date']<date('Y-m-d');
                ?>
                <tr>
                    <td><input type="checkbox" name="so_ids[]" value="<?=$o['id']?>" class="so-cb"></td>
                    <td style="font-weight:500;"><?=htmlspecialchars($o['so_number'])?></td>
                    <td><?=htmlspecialchars($o['customer_name'])?></td>
                    <td><?=htmlspecialchars($o['ship_to_name']??'—')?></td>
                    <td style="<?=$pastDue?'color:#dc2626;font-weight:600;':''?>"><?=$o['promised_ship_date']?date('M j, Y',strtotime($o['promised_ship_date'])):'—'?></td>
                    <td><?=(int)$o['unshipped_lines']?></td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
            <div style="margin-top:16px;"><button type="submit" class="btn btn-primary">Create Pick List</button></div>
            <?php endif;?>
        </form>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
