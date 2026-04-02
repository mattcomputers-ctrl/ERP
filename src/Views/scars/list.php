<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>SCARs — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Supplier Corrective Action Requests</h1>
            <a href="/scars/create" class="btn btn-primary">+ New SCAR</a>
        </div>
        <form method="GET" action="/scars" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Supplier</label><input type="text" name="supplier" value="<?=htmlspecialchars($filters['supplier']??'')?>" placeholder="Code or name..." class="form-input" style="font-size:13px;padding:4px 8px;width:180px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Status</label>
                <select name="status" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option>
                <?php foreach(['OPEN','RESPONSE_RECEIVED','CLOSED','CANCELLED'] as $s):?><option value="<?=$s?>" <?=($filters['status']??'')===$s?'selected':''?>><?=$s?></option><?php endforeach;?>
                </select></div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/scars" class="btn btn-secondary" style="height:32px;text-decoration:none;">Clear</a>
        </form>
        <table class="data-table">
            <thead><tr><th>SCAR #</th><th>Supplier</th><th>Issue Date</th><th>Due Date</th><th>Status</th><th>Created By</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($scars)):?><tr><td colspan="7" class="empty-state">No SCARs found.</td></tr>
            <?php else:foreach($scars as $s):
                $overdue=$s['due_date']&&$s['due_date']<date('Y-m-d')&&!in_array($s['status'],['CLOSED','CANCELLED']);
            ?>
            <tr class="<?=$s['status']==='CANCELLED'?'inactive-row':''?>">
                <td><a href="/scars/<?=$s['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($s['scar_number'])?></a></td>
                <td><?=htmlspecialchars($s['supplier_code'].' — '.$s['supplier_name'])?></td>
                <td><?=date('M j, Y',strtotime($s['issue_date']))?></td>
                <td style="<?=$overdue?'color:#dc2626;font-weight:600;':''?>"><?=$s['due_date']?date('M j, Y',strtotime($s['due_date'])):'—'?></td>
                <td><?php $sb=match($s['status']){'OPEN'=>'badge-info','RESPONSE_RECEIVED'=>'badge-warning','CLOSED'=>'badge-active','CANCELLED'=>'badge-inactive',default=>''};?><span class="badge <?=$sb?>"><?=$s['status']?></span></td>
                <td><?=htmlspecialchars($s['created_by_name']??'')?></td>
                <td class="actions-cell"><a href="/scars/<?=$s['id']?>" class="btn btn-sm btn-secondary">View</a></td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
        <?php if($totalPages>1):?><div style="display:flex;justify-content:center;gap:4px;margin-top:16px;"><?php for($p=1;$p<=$totalPages;$p++):$params=$filters;$params['page']=$p;$qs=http_build_query(array_filter($params,fn($v)=>$v!==''));?><a href="/scars?<?=$qs?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>" style="min-width:32px;text-align:center;text-decoration:none;"><?=$p?></a><?php endfor;?></div><?php endif;?>
        <p style="font-size:12px;color:#9ca3af;margin-top:8px;text-align:center;">Showing <?=count($scars)?> of <?=$total?></p>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
