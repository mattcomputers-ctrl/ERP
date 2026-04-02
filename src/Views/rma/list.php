<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>RMA — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Returns / RMA</h1>
            <a href="/rma/create" class="btn btn-primary">+ New RMA</a>
        </div>
        <form method="GET" action="/rma" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Search</label><input type="text" name="q" value="<?=htmlspecialchars($filters['q']??'')?>" placeholder="RMA # or customer..." class="form-input" style="font-size:13px;padding:4px 8px;width:200px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Status</label><select name="status" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><?php foreach(['OPEN','RECEIVED','CLOSED','CANCELLED'] as $s):?><option value="<?=$s?>" <?=($filters['status']??'')===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/rma" class="btn btn-secondary" style="height:32px;text-decoration:none;">Clear</a>
        </form>
        <table class="data-table">
            <thead><tr><th>RMA #</th><th>Customer</th><th>Reason</th><th>Facility</th><th>Date</th><th>Lines</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($rmas)):?><tr><td colspan="8" class="empty-state">No RMAs found.</td></tr>
            <?php else:foreach($rmas as $r):?>
            <tr class="<?=$r['status']==='CANCELLED'?'inactive-row':''?>">
                <td><a href="/rma/<?=$r['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($r['rma_number'])?></a></td>
                <td><?=htmlspecialchars(($r['customer_code']??'').' — '.($r['customer_name']??''))?></td>
                <td><?=htmlspecialchars($r['return_reason'])?></td>
                <td><?=htmlspecialchars($r['facility_name']??'')?></td>
                <td><?=date('M j, Y',strtotime($r['rma_date']))?></td>
                <td><?=(int)$r['line_count']?></td>
                <td><?php $sb=match($r['status']){'OPEN'=>'badge-info','RECEIVED'=>'badge-warning','CLOSED'=>'badge-active','CANCELLED'=>'badge-inactive',default=>''};?><span class="badge <?=$sb?>"><?=$r['status']?></span></td>
                <td class="actions-cell"><a href="/rma/<?=$r['id']?>" class="btn btn-sm btn-secondary">View</a></td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
        <?php if($totalPages>1):?><div style="display:flex;justify-content:center;gap:4px;margin-top:16px;"><?php for($p=1;$p<=$totalPages;$p++):?><a href="/rma?page=<?=$p?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>" style="min-width:32px;text-align:center;text-decoration:none;"><?=$p?></a><?php endfor;?></div><?php endif;?>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
