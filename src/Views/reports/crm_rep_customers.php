<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Rep Customer List — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>@media print{.no-print{display:none!important;}body{font-size:11px;}}</style></head>
<body>
    <header class="app-header no-print"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Rep Customer List</h1>
            <div class="no-print" style="display:flex;gap:8px;"><a href="/crm/dashboard" class="btn btn-secondary">&larr; CRM</a><button class="btn btn-secondary" onclick="window.print()">Print</button></div>
        </div>
        <form method="GET" action="/reports/crm/rep-customers" class="no-print" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Rep</label><select name="rep_id" class="form-input" style="font-size:13px;padding:4px 8px;"><?php foreach($users as $u):?><option value="<?=$u['id']?>" <?=$repId==$u['id']?'selected':''?>><?=htmlspecialchars($u['full_name'])?></option><?php endforeach;?></select></div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Run</button>
        </form>
        <table class="data-table">
            <thead><tr><th>Customer</th><th>Last Order</th><th>Last Contact</th><th style="text-align:right;">Open Quotes</th><th style="text-align:right;">Open Orders</th><th>Credit</th></tr></thead>
            <tbody>
            <?php if(empty($rows)):?><tr><td colspan="6" class="empty-state">No customers for this rep.</td></tr>
            <?php else:foreach($rows as $r):
                $overLimit=$r['credit_limit']>0&&(float)$r['ar_balance']>(float)$r['credit_limit'];
            ?>
            <tr>
                <td><a href="/customers/<?=$r['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($r['customer_code'].' — '.$r['company_name'])?></a><?=$r['account_hold']?' <span class="badge badge-danger" style="font-size:10px;">HOLD</span>':''?></td>
                <td><?=$r['last_order']?date('M j, Y',strtotime($r['last_order'])):'<span style="color:#9ca3af;">—</span>'?></td>
                <td><?=$r['last_contact']?date('M j, Y',strtotime($r['last_contact'])):'<span style="color:#9ca3af;">—</span>'?></td>
                <td style="text-align:right;"><?=(int)$r['open_quotes']?></td>
                <td style="text-align:right;"><?=(int)$r['open_orders']?></td>
                <td style="<?=$overLimit?'color:#dc2626;font-weight:600;':''?>">$<?=number_format((float)$r['ar_balance'],2)?> / <?=$r['credit_limit']>0?'$'.number_format((float)$r['credit_limit'],2):'No Limit'?></td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
    </div>
</body>
</html>
