<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Repack Tickets — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1200px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Repack Tickets</h1>
            <a href="/repack/create" class="btn btn-primary">+ New Repack</a>
        </div>
        <form method="GET" action="/repack" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Search</label><input type="text" name="q" value="<?=htmlspecialchars($filters['q']??'')?>" placeholder="RPK# or item..." class="form-input" style="font-size:13px;padding:4px 8px;width:180px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Status</label><select name="status" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><option value="OPEN" <?=($filters['status']??'')==='OPEN'?'selected':''?>>Open</option><option value="CLOSED" <?=($filters['status']??'')==='CLOSED'?'selected':''?>>Closed</option></select></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Facility</label><select name="facility_id" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><?php foreach($facilities as $f):?><option value="<?=$f['id']?>" <?=($filters['facility_id']??0)==$f['id']?'selected':''?>><?=htmlspecialchars($f['name'])?></option><?php endforeach;?></select></div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/repack" class="btn btn-secondary" style="height:32px;text-decoration:none;">Clear</a>
        </form>
        <table class="data-table">
            <thead><tr><th>RPK #</th><th>Facility</th><th>Item</th><th>Source Pack</th><th>Dest Pack</th><th style="text-align:right;">Source Qty</th><th style="text-align:right;">Dest Qty</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($repacks)):?><tr><td colspan="10" class="empty-state">No repack tickets found.</td></tr>
            <?php else:foreach($repacks as $r):?>
            <tr>
                <td><a href="/repack/<?=$r['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($r['rpk_number'])?></a></td>
                <td><?=htmlspecialchars($r['facility_name']??'')?></td>
                <td><?=htmlspecialchars($r['item_code'])?> <span style="color:#6b7280;font-size:12px;"><?=htmlspecialchars($r['item_description'])?></span></td>
                <td><?=htmlspecialchars($r['source_pack_name']??'Bulk')?></td>
                <td><?=htmlspecialchars($r['dest_pack_name']??'Bulk')?></td>
                <td style="text-align:right;"><?=number_format((float)$r['source_quantity'],4)?></td>
                <td style="text-align:right;"><?=number_format((float)$r['destination_quantity'],4)?></td>
                <td><?php $sb=match($r['status']??''){'OPEN'=>'badge-info','CLOSED'=>'badge-active','CANCELLED'=>'badge-inactive',default=>'badge-inactive'};?><span class="badge <?=$sb?>"><?=$r['status']?></span></td>
                <td><?=date('M j, Y',strtotime($r['repack_date']))?></td>
                <td class="actions-cell"><a href="/repack/<?=$r['id']?>" class="btn btn-sm btn-secondary">View</a><?php if($r['status']==='OPEN'):?><a href="/repack/<?=$r['id']?>/edit" class="btn btn-sm btn-secondary">Edit</a><?php endif;?></td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
        <?php if($totalPages>1):?><div style="display:flex;justify-content:center;gap:4px;margin-top:16px;"><?php for($p=1;$p<=$totalPages;$p++):?><a href="/repack?page=<?=$p?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>" style="min-width:32px;text-align:center;text-decoration:none;"><?=$p?></a><?php endfor;?></div><?php endif;?>
        <p style="font-size:12px;color:#9ca3af;margin-top:8px;text-align:center;">Showing <?=count($repacks)?> of <?=$total?></p>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
