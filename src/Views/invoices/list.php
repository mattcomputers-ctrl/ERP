<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Invoices — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1200px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;"><h1 style="margin:0;">Invoices</h1></div>
        <form method="GET" action="/invoices" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Search</label><input type="text" name="q" value="<?=htmlspecialchars($filters['q']??'')?>" placeholder="Invoice # or customer..." class="form-input" style="font-size:13px;padding:4px 8px;width:200px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Status</label><select name="status" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><?php foreach(['OPEN','PAID','VOID'] as $s):?><option value="<?=$s?>" <?=($filters['status']??'')===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
            <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer;padding-bottom:2px;"><input type="checkbox" name="overdue" <?=!empty($filters['overdue'])?'checked':''?>> Overdue Only</label>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/invoices" class="btn btn-secondary" style="height:32px;text-decoration:none;">Clear</a>
        </form>
        <table class="data-table">
            <thead><tr><th>Invoice #</th><th>Customer</th><th>Date</th><th>Due Date</th><th style="text-align:right;">Amount</th><th>Status</th><th>Days Out</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($invoices)):?><tr><td colspan="8" class="empty-state">No invoices found.</td></tr>
            <?php else:foreach($invoices as $i):
                $overdue=$i['status']==='OPEN'&&(int)$i['days_outstanding']>0;
            ?>
            <tr class="<?=$i['status']==='VOID'?'inactive-row':''?>">
                <td><a href="/invoices/<?=$i['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($i['invoice_number'])?></a></td>
                <td><?=htmlspecialchars(($i['customer_code']??'').' — '.($i['customer_name']??''))?></td>
                <td><?=date('M j, Y',strtotime($i['invoice_date']))?></td>
                <td style="<?=$overdue?'color:#dc2626;font-weight:600;':''?>"><?=date('M j, Y',strtotime($i['due_date']))?></td>
                <td style="text-align:right;font-weight:500;">$<?=number_format((float)$i['total_due'],2)?></td>
                <td><?php $sb=match($i['status']){'OPEN'=>'badge-info','PAID'=>'badge-active','VOID'=>'badge-danger',default=>''};?><span class="badge <?=$sb?>"><?=$i['status']?></span></td>
                <td style="<?=$overdue?'color:#dc2626;font-weight:600;':''?>"><?=$i['status']==='OPEN'?((int)$i['days_outstanding']>0?(int)$i['days_outstanding'].'d':'Current'):'—'?></td>
                <td class="actions-cell"><a href="/invoices/<?=$i['id']?>" class="btn btn-sm btn-secondary">View</a><a href="/invoices/<?=$i['id']?>/pdf" class="btn btn-sm btn-secondary" target="_blank">PDF</a></td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
        <?php if($totalPages>1):?><div style="display:flex;justify-content:center;gap:4px;margin-top:16px;"><?php for($p=1;$p<=$totalPages;$p++):?><a href="/invoices?page=<?=$p?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>" style="min-width:32px;text-align:center;text-decoration:none;"><?=$p?></a><?php endfor;?></div><?php endif;?>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
