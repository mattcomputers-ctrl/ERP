<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?=htmlspecialchars($placement['con_number'])?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1000px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <?php $isLow=$placement['reorder_min']&&$balance<(float)$placement['reorder_min'];?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?=htmlspecialchars($placement['con_number'])?> <span class="badge <?=$placement['status']==='ACTIVE'?'badge-active':'badge-inactive'?>" style="font-size:14px;vertical-align:middle;"><?=$placement['status']?></span><?=$isLow?' <span class="badge badge-warning" style="font-size:12px;vertical-align:middle;">LOW BALANCE</span>':''?></h1>
            <div style="display:flex;gap:8px;">
                <a href="/consignment" class="btn btn-secondary">&larr; Back</a>
                <a href="/consignment/<?=$placement['id']?>/statement" class="btn btn-secondary" target="_blank">Statement PDF</a>
                <form method="POST" action="/consignment/<?=$placement['id']?>/statement/email" style="display:inline;"><button type="submit" class="btn btn-secondary">Email Statement</button></form>
                <?php if($placement['status']==='ACTIVE'):?>
                    <form method="POST" action="/consignment/<?=$placement['id']?>/close" style="display:inline;"><button type="submit" class="btn btn-warning" onclick="return confirm('Close this placement?')">Close</button></form>
                <?php endif;?>
            </div>
        </div>

        <!-- Placement Details -->
        <div class="form-section" style="margin-bottom:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Customer</strong><p style="margin:2px 0;"><a href="/customers/<?=$placement['customer_id']?>" style="color:#2563eb;"><?=htmlspecialchars(($placement['customer_code']??'').' — '.($placement['customer_name']??''))?></a></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Item</strong><p style="margin:2px 0;"><a href="/items/<?=$placement['item_id']?>" style="color:#2563eb;"><?=htmlspecialchars($placement['item_code'])?></a> — <?=htmlspecialchars($placement['item_description'])?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Facility</strong><p style="margin:2px 0;"><?=htmlspecialchars($placement['facility_name']??'—')?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Placed</strong><p style="margin:2px 0;font-weight:600;"><?=number_format((float)$placement['quantity_placed'],4)?> <?=htmlspecialchars($placement['uom_abbr']??'')?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Total Consumed</strong><p style="margin:2px 0;"><?=number_format($totalConsumed,4)?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Balance</strong><p style="margin:2px 0;font-size:18px;font-weight:700;<?=$isLow?'color:#dc2626;':''?>"><?=number_format($balance,4)?></p></div>
            </div>
        </div>

        <!-- Record Consumption -->
        <?php if($placement['status']==='ACTIVE'):?>
        <h3 style="margin-bottom:8px;">Record Consumption</h3>
        <form method="POST" action="/consignment/<?=$placement['id']?>/consume" style="padding:12px;background:#f9fafb;border-radius:6px;margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Date</label><input type="date" name="consumption_date" value="<?=date('Y-m-d')?>" class="form-input" style="font-size:13px;padding:4px 8px;" required></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Quantity <span style="color:red;">*</span></label><input type="number" step="0.0001" name="quantity_consumed" class="form-input" style="width:120px;font-size:13px;padding:4px 8px;" required></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Customer Reference</label><input type="text" name="customer_reference" class="form-input" style="width:150px;font-size:13px;padding:4px 8px;"></div>
            <button type="submit" class="btn btn-primary btn-sm">Record + Invoice</button>
        </form>
        <?php endif;?>

        <!-- Consumption History -->
        <h3 style="margin-bottom:8px;">Consumption History</h3>
        <table class="data-table">
            <thead><tr><th>Date</th><th style="text-align:right;">Qty</th><th>Reference</th><th>Invoice</th><th>Entered By</th></tr></thead>
            <tbody>
            <?php if(empty($consumptions)):?><tr><td colspan="5" class="empty-state">No consumption recorded.</td></tr>
            <?php else:foreach($consumptions as $c):?>
            <tr>
                <td><?=date('M j, Y',strtotime($c['consumption_date']))?></td>
                <td style="text-align:right;"><?=number_format((float)$c['quantity_consumed'],4)?></td>
                <td><?=htmlspecialchars($c['customer_reference']??'—')?></td>
                <td><?=$c['invoice_number']?'<a href="/invoices/'.$c['invoice_id'].'" style="color:#2563eb;">'.htmlspecialchars($c['invoice_number']).'</a>':'—'?></td>
                <td><?=htmlspecialchars($c['entered_by_name']??'')?></td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
