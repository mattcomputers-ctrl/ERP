<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Incoming Inspection Queue — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Incoming Inspection Queue</h1>
            <a href="/qc/specs" class="btn btn-secondary">QC Specs</a>
        </div>
        <form method="GET" action="/qc/inspection" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Facility</label>
                <select name="facility_id" class="form-input" style="font-size:13px;padding:4px 8px;">
                    <option value="">All</option>
                    <?php foreach($facilities as $f):?><option value="<?=$f['id']?>" <?=$filterFacility==$f['id']?'selected':''?>><?=htmlspecialchars($f['name'])?></option><?php endforeach;?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
        </form>
        <table class="data-table">
            <thead><tr><th>Item</th><th>Lot Number</th><th>Facility</th><th style="text-align:right;">Quantity</th><th>Received</th><th>Days Waiting</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($inspections)):?><tr><td colspan="7" class="empty-state">No lots pending inspection.</td></tr>
            <?php else:foreach($inspections as $i):?>
            <tr style="<?=(int)$i['days_waiting']>7?'background:#fef2f2;':''?>">
                <td><a href="/items/<?=$i['lot_id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($i['item_code'])?></a> <span style="color:#6b7280;font-size:12px;"><?=htmlspecialchars($i['item_description'])?></span></td>
                <td style="font-weight:500;"><?=htmlspecialchars($i['lot_number'])?></td>
                <td><?=htmlspecialchars($i['facility_name'])?></td>
                <td style="text-align:right;"><?=number_format((float)$i['remaining_quantity'],4)?></td>
                <td><?=date('M j, Y',strtotime($i['lot_date']))?></td>
                <td style="<?=(int)$i['days_waiting']>7?'color:#dc2626;font-weight:600;':''?>"><?=(int)$i['days_waiting']?> day<?=(int)$i['days_waiting']!=1?'s':''?></td>
                <td class="actions-cell"><a href="/qc/inspection/<?=$i['id']?>" class="btn btn-sm btn-primary">Inspect</a></td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
