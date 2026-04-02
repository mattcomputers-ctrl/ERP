<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Pick Lists — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Pick Lists</h1>
            <a href="/pick-lists/create" class="btn btn-primary">+ New Pick List</a>
        </div>
        <form method="GET" action="/pick-lists" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Status</label><select name="status" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><?php foreach(['OPEN','IN_PROGRESS','COMPLETE'] as $s):?><option value="<?=$s?>" <?=($filters['status']??'')===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Facility</label><select name="facility_id" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><?php foreach($facilities as $f):?><option value="<?=$f['id']?>" <?=($filters['facility_id']??0)==$f['id']?'selected':''?>><?=htmlspecialchars($f['name'])?></option><?php endforeach;?></select></div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/pick-lists" class="btn btn-secondary" style="height:32px;text-decoration:none;">Clear</a>
        </form>
        <table class="data-table">
            <thead><tr><th>PKL #</th><th>Facility</th><th>SOs</th><th>Lines</th><th>Picked</th><th>Status</th><th>Created By</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($pickLists)):?><tr><td colspan="9" class="empty-state">No pick lists found.</td></tr>
            <?php else:foreach($pickLists as $p):?>
            <tr>
                <td><a href="/pick-lists/<?=$p['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($p['pkl_number'])?></a></td>
                <td><?=htmlspecialchars($p['facility_name']??'')?></td>
                <td><?=(int)$p['so_count']?></td>
                <td><?=(int)$p['line_count']?></td>
                <td><?=(int)$p['picked_count']?> / <?=(int)$p['line_count']?></td>
                <td><?php $sb=match($p['status']){'OPEN'=>'badge-info','IN_PROGRESS'=>'badge-warning','COMPLETE'=>'badge-active',default=>''};?><span class="badge <?=$sb?>"><?=$p['status']?></span></td>
                <td><?=htmlspecialchars($p['created_by_name']??'')?></td>
                <td><?=date('M j, Y',strtotime($p['created_at']))?></td>
                <td class="actions-cell">
                    <a href="/pick-lists/<?=$p['id']?>" class="btn btn-sm btn-primary"><?=$p['status']==='OPEN'?'Pick':'View'?></a>
                    <a href="/pick-lists/<?=$p['id']?>/print" class="btn btn-sm btn-secondary" target="_blank">Print</a>
                </td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
        <?php if($totalPages>1):?><div style="display:flex;justify-content:center;gap:4px;margin-top:16px;"><?php for($p=1;$p<=$totalPages;$p++):?><a href="/pick-lists?page=<?=$p?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>" style="min-width:32px;text-align:center;text-decoration:none;"><?=$p?></a><?php endfor;?></div><?php endif;?>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
