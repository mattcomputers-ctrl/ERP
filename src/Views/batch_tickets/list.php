<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Batch Tickets — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1200px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Batch Tickets</h1>
            <a href="/batches/create" class="btn btn-primary">+ New Batch</a>
        </div>
        <form method="GET" action="/batches" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Search</label><input type="text" name="q" value="<?=htmlspecialchars($filters['q']??'')?>" placeholder="Batch # or item..." class="form-input" style="font-size:13px;padding:4px 8px;width:180px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Facility</label><select name="facility_id" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><?php foreach($facilities as $f):?><option value="<?=$f['id']?>" <?=($filters['facility_id']??0)==$f['id']?'selected':''?>><?=htmlspecialchars($f['name'])?></option><?php endforeach;?></select></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Status</label><select name="status" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><?php foreach(['OPEN','IN_PROGRESS','CLOSED','CANCELLED'] as $s):?><option value="<?=$s?>" <?=($filters['status']??'')===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Priority</label><select name="priority" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><option value="RUSH" <?=($filters['priority']??'')==='RUSH'?'selected':''?>>RUSH</option><option value="NORMAL" <?=($filters['priority']??'')==='NORMAL'?'selected':''?>>Normal</option></select></div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/batches" class="btn btn-secondary" style="height:32px;text-decoration:none;">Clear</a>
        </form>
        <table class="data-table">
            <thead><tr><th>Batch #</th><th>Item</th><th>Recipe</th><th>Facility</th><th>Status</th><th>Priority</th><th>Scheduled</th><th>Assigned To</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($batches)):?><tr><td colspan="9" class="empty-state">No batches found.</td></tr>
            <?php else:foreach($batches as $b):
                $isRush=$b['priority']==='RUSH';
            ?>
            <tr style="<?=$isRush?'border-left:4px solid #dc2626;':''?>" class="<?=$b['status']==='CANCELLED'?'inactive-row':''?>">
                <td><a href="/batches/<?=$b['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($b['batch_number'])?></a></td>
                <td><?=htmlspecialchars($b['item_code'])?> <span style="color:#6b7280;font-size:12px;"><?=htmlspecialchars($b['item_description'])?></span></td>
                <td>v<?=(int)$b['version_number']?> <?=htmlspecialchars($b['version_name']??'')?></td>
                <td><?=htmlspecialchars($b['facility_name']??'')?></td>
                <td><?php $sb=match($b['status']){'OPEN'=>'badge-info','IN_PROGRESS'=>'badge-warning','CLOSED'=>'badge-active','CANCELLED'=>'badge-inactive',default=>''};?><span class="badge <?=$sb?>"><?=$b['status']?></span></td>
                <td><?=$isRush?'<span class="badge badge-danger">RUSH</span>':''?></td>
                <td><?=date('M j, Y',strtotime($b['scheduled_date']))?></td>
                <td><?=htmlspecialchars($b['assigned_name']??'')?></td>
                <td class="actions-cell"><a href="/batches/<?=$b['id']?>" class="btn btn-sm btn-secondary">View</a>
                    <?php if($b['status']==='OPEN'):?><a href="/batches/<?=$b['id']?>/edit" class="btn btn-sm btn-secondary">Edit</a><?php endif;?>
                </td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
        <?php if($totalPages>1):?><div style="display:flex;justify-content:center;gap:4px;margin-top:16px;"><?php for($p=1;$p<=$totalPages;$p++):$prms=$filters;$prms['page']=$p;$qs=http_build_query(array_filter($prms,fn($v)=>$v!==''&&$v!==0));?><a href="/batches?<?=$qs?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>" style="min-width:32px;text-align:center;text-decoration:none;"><?=$p?></a><?php endfor;?></div><?php endif;?>
        <p style="font-size:12px;color:#9ca3af;margin-top:8px;text-align:center;">Showing <?=count($batches)?> of <?=$total?></p>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
