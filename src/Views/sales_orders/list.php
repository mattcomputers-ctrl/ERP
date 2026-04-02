<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Sales Orders — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1200px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Sales Orders</h1>
            <a href="/orders/create" class="btn btn-primary">+ New Order</a>
        </div>
        <form method="GET" action="/orders" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Search</label><input type="text" name="q" value="<?=htmlspecialchars($filters['q']??'')?>" placeholder="SO#, customer, PO#..." class="form-input" style="font-size:13px;padding:4px 8px;width:200px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Status</label><select name="status" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><?php foreach(['DRAFT','CONFIRMED','ON_HOLD','PARTIAL','SHIPPED','CANCELLED'] as $s):?><option value="<?=$s?>" <?=($filters['status']??'')===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Facility</label><select name="facility_id" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><?php foreach($facilities as $f):?><option value="<?=$f['id']?>" <?=($filters['facility_id']??0)==$f['id']?'selected':''?>><?=htmlspecialchars($f['name'])?></option><?php endforeach;?></select></div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
            <a href="/orders" class="btn btn-secondary" style="height:32px;text-decoration:none;">Clear</a>
        </form>
        <table class="data-table">
            <thead><tr><th>SO #</th><th>Customer</th><th>Facility</th><th>Order Date</th><th>Promised Ship</th><th>Status</th><th style="text-align:right;">Value</th><th>Flags</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($orders)):?><tr><td colspan="9" class="empty-state">No orders found.</td></tr>
            <?php else:foreach($orders as $o):
                $pastDue=$o['promised_ship_date']&&$o['promised_ship_date']<date('Y-m-d')&&!in_array($o['status'],['SHIPPED','CANCELLED']);
            ?>
            <tr class="<?=$o['status']==='CANCELLED'?'inactive-row':''?>">
                <td><a href="/orders/<?=$o['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($o['so_number'])?></a></td>
                <td><?=htmlspecialchars(($o['customer_code']??'').' — '.($o['customer_name']??''))?></td>
                <td><?=htmlspecialchars($o['facility_name']??'')?></td>
                <td><?=date('M j, Y',strtotime($o['order_date']))?></td>
                <td style="<?=$pastDue?'color:#dc2626;font-weight:600;':''?>"><?=$o['promised_ship_date']?date('M j, Y',strtotime($o['promised_ship_date'])):'—'?></td>
                <td><?php $sb=match($o['status']){'DRAFT'=>'badge-inactive','CONFIRMED'=>'badge-info','ON_HOLD'=>'badge-warning','PARTIAL'=>'badge-warning','SHIPPED'=>'badge-active','CANCELLED'=>'badge-danger',default=>''};?><span class="badge <?=$sb?>"><?=$o['status']?></span></td>
                <td style="text-align:right;">$<?=number_format((float)$o['order_value'],2)?></td>
                <td><?=$o['is_sample']?'<span class="badge badge-info" style="font-size:10px;">SAMPLE</span> ':''?><?=$o['total_backorder']?'<span class="badge badge-warning" style="font-size:10px;">BACKORDER</span>':''?></td>
                <td class="actions-cell"><a href="/orders/<?=$o['id']?>" class="btn btn-sm btn-secondary">View</a><?php if(in_array($o['status'],['DRAFT','CONFIRMED'])):?><a href="/orders/<?=$o['id']?>/edit" class="btn btn-sm btn-secondary">Edit</a><?php endif;?></td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
        <?php if($totalPages>1):?><div style="display:flex;justify-content:center;gap:4px;margin-top:16px;"><?php for($p=1;$p<=$totalPages;$p++):$prms=$filters;$prms['page']=$p;$qs=http_build_query(array_filter($prms,fn($v)=>$v!==''&&$v!==0));?><a href="/orders?<?=$qs?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>" style="min-width:32px;text-align:center;text-decoration:none;"><?=$p?></a><?php endfor;?></div><?php endif;?>
        <p style="font-size:12px;color:#9ca3af;margin-top:8px;text-align:center;">Showing <?=count($orders)?> of <?=$total?></p>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
