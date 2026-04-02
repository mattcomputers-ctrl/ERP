<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>CRM Tasks — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;}.modal-overlay.active{display:flex;}.modal-box{background:#fff;border-radius:8px;padding:24px;max-width:500px;width:90%;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1200px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">CRM Tasks</h1>
            <div style="display:flex;gap:8px;">
                <a href="/crm/dashboard" class="btn btn-secondary">&larr; Dashboard</a>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" action="/crm/tasks" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Assigned To</label>
                <select name="assigned_to" class="form-input" style="font-size:13px;padding:4px 8px;">
                    <option value="0">All Users</option>
                    <?php foreach($users as $u):?><option value="<?=$u['id']?>" <?=($filters['assigned_to']??0)==$u['id']?'selected':''?>><?=htmlspecialchars($u['full_name'])?></option><?php endforeach;?>
                </select></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Customer</label><input type="text" name="customer" value="<?=htmlspecialchars($filters['customer']??'')?>" placeholder="Name..." class="form-input" style="font-size:13px;padding:4px 8px;width:150px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Priority</label>
                <select name="priority" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option>
                <?php foreach(['LOW','NORMAL','HIGH','URGENT'] as $p):?><option value="<?=$p?>" <?=($filters['priority']??'')===$p?'selected':''?>><?=$p?></option><?php endforeach;?>
                </select></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Status</label>
                <select name="status" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="OPEN" <?=($filters['status']??'OPEN')==='OPEN'?'selected':''?>>Open</option><option value="COMPLETED" <?=($filters['status']??'')==='COMPLETED'?'selected':''?>>Completed</option><option value="CANCELLED" <?=($filters['status']??'')==='CANCELLED'?'selected':''?>>Cancelled</option><option value="ALL" <?=($filters['status']??'')==='ALL'?'selected':''?>>All</option></select></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">From</label><input type="date" name="date_from" value="<?=htmlspecialchars($filters['date_from']??'')?>" class="form-input" style="font-size:13px;padding:4px 8px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">To</label><input type="date" name="date_to" value="<?=htmlspecialchars($filters['date_to']??'')?>" class="form-input" style="font-size:13px;padding:4px 8px;"></div>
            <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer;padding-bottom:2px;"><input type="checkbox" name="overdue" <?=!empty($filters['overdue'])?'checked':''?>> Overdue Only</label>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/crm/tasks" class="btn btn-secondary" style="height:32px;text-decoration:none;">Clear</a>
        </form>

        <!-- Bulk Actions -->
        <form method="POST" action="/crm/tasks/bulk" id="bulkForm">
            <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
                <select name="action" class="form-input" style="font-size:13px;padding:4px 8px;">
                    <option value="">Bulk Actions...</option>
                    <option value="complete">Mark Complete</option>
                    <option value="reassign">Reassign</option>
                    <option value="due_date">Change Due Date</option>
                </select>
                <select name="assigned_to" class="form-input" style="font-size:13px;padding:4px 8px;width:150px;" id="bulkReassign" style="display:none;">
                    <?php foreach($users as $u):?><option value="<?=$u['id']?>"><?=htmlspecialchars($u['full_name'])?></option><?php endforeach;?>
                </select>
                <input type="date" name="due_date" class="form-input" style="font-size:13px;padding:4px 8px;" id="bulkDate">
                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
            </div>

            <table class="data-table">
                <thead><tr><th style="width:30px;"><input type="checkbox" id="selAll" onclick="document.querySelectorAll('.task-cb').forEach(function(c){c.checked=this.checked;}.bind(this))"></th><th>Title</th><th>Customer</th><th>Assigned To</th><th>Due Date</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if(empty($tasks)):?><tr><td colspan="8" class="empty-state">No tasks found.</td></tr>
                <?php else:foreach($tasks as $t):
                    $overdue=$t['status']==='OPEN'&&$t['due_date']<date('Y-m-d');
                    $pb=match($t['priority']){'URGENT'=>'badge-danger','HIGH'=>'badge-warning','NORMAL'=>'badge-info','LOW'=>'badge-inactive',default=>''};
                    $sb=match($t['status']){'OPEN'=>'badge-info','COMPLETED'=>'badge-active','CANCELLED'=>'badge-inactive',default=>''};
                ?>
                <tr style="<?=$overdue?'background:#fef2f2;':''?>" id="trow-<?=$t['id']?>">
                    <td><input type="checkbox" name="task_ids[]" value="<?=$t['id']?>" class="task-cb"></td>
                    <td style="font-weight:500;"><?=htmlspecialchars($t['title'])?></td>
                    <td><a href="/customers/<?=$t['customer_id']?>" style="color:#2563eb;text-decoration:none;"><?=htmlspecialchars($t['company_name'])?></a></td>
                    <td><?=htmlspecialchars($t['assigned_name']??'')?></td>
                    <td style="<?=$overdue?'color:#dc2626;font-weight:600;':''?>"><?=date('M j, Y',strtotime($t['due_date']))?><?=$overdue?' (OVERDUE)':''?></td>
                    <td><span class="badge <?=$pb?>"><?=$t['priority']?></span></td>
                    <td><span class="badge <?=$sb?>"><?=$t['status']?></span></td>
                    <td class="actions-cell">
                        <?php if($t['status']==='OPEN'):?>
                        <form method="POST" action="/crm/tasks/<?=$t['id']?>/complete" style="display:inline;"><button type="submit" class="btn btn-sm btn-primary" style="font-size:11px;" title="Complete">&#10003;</button></form>
                        <form method="POST" action="/crm/tasks/<?=$t['id']?>/cancel" style="display:inline;"><button type="submit" class="btn btn-sm btn-warning" style="font-size:11px;" title="Cancel" onclick="return confirm('Cancel?')">&times;</button></form>
                        <?php endif;?>
                    </td>
                </tr>
                <?php endforeach;endif;?>
                </tbody>
            </table>
        </form>

        <?php if($totalPages>1):?><div style="display:flex;justify-content:center;gap:4px;margin-top:16px;"><?php for($p=1;$p<=$totalPages;$p++):$prms=$filters;$prms['page']=$p;$qs=http_build_query(array_filter($prms,fn($v)=>$v!==''&&$v!==false&&$v!==0));?><a href="/crm/tasks?<?=$qs?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>" style="min-width:32px;text-align:center;text-decoration:none;"><?=$p?></a><?php endfor;?></div><?php endif;?>
        <p style="font-size:12px;color:#9ca3af;margin-top:8px;text-align:center;">Showing <?=count($tasks)?> of <?=$total?></p>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
