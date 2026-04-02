<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Shipment <?=htmlspecialchars($shipment['shipment_number'])?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1000px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Shipment <?=htmlspecialchars($shipment['shipment_number'])?></h1>
            <div style="display:flex;gap:8px;">
                <a href="/shipping/<?=$shipment['id']?>/packing-slip" class="btn btn-secondary" target="_blank">Packing Slip</a>
                <a href="/shipping/<?=$shipment['id']?>/bol" class="btn btn-secondary" target="_blank">BOL</a>
                <?php if($invoice):?><a href="/invoices/<?=$invoice['id']?>" class="btn btn-secondary">Invoice <?=htmlspecialchars($invoice['invoice_number'])?></a><?php endif;?>
            </div>
        </div>

        <div class="form-section" style="margin-bottom:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Ship Date</strong><p style="margin:2px 0;"><?=date('M j, Y',strtotime($shipment['ship_date']))?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Ship Via</strong><p style="margin:2px 0;"><?=htmlspecialchars($shipment['ship_via_name']??'—')?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Tracking</strong><p style="margin:2px 0;"><?=htmlspecialchars($shipment['tracking_number']??'—')?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Facility</strong><p style="margin:2px 0;"><?=htmlspecialchars($shipment['facility_name'])?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Ship To</strong><p style="margin:2px 0;"><?=htmlspecialchars($shipment['ship_to_name']??'')?></p></div>
                <div>
                    <strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Actual Delivery</strong>
                    <p style="margin:2px 0;"><?=$shipment['actual_delivery_date']?date('M j, Y',strtotime($shipment['actual_delivery_date'])):'<em style="color:#9ca3af;">Not yet confirmed</em>'?></p>
                    <form method="POST" action="/shipping/<?=$shipment['id']?>/delivery-date" style="display:inline;margin-top:4px;">
                        <input type="date" name="actual_delivery_date" value="<?=htmlspecialchars($shipment['actual_delivery_date']??'')?>" class="form-input" style="font-size:12px;padding:2px 6px;width:140px;">
                        <button type="submit" class="btn btn-sm btn-secondary">Update</button>
                    </form>
                </div>
            </div>
        </div>

        <h3 style="margin-bottom:8px;">Lines</h3>
        <table class="data-table">
            <thead><tr><th>Item</th><th>Description</th><th>Pack</th><th>Lots</th><th style="text-align:right;">Qty Shipped</th><th style="text-align:right;">Price</th><th style="text-align:right;">Extended</th></tr></thead>
            <tbody>
            <?php $total=0;foreach($lines as $l):$ext=(float)$l['quantity_shipped']*(float)$l['unit_price'];$total+=$ext;?>
            <tr><td style="font-weight:500;"><?=htmlspecialchars($l['item_code'])?></td><td><?=htmlspecialchars($l['item_description'])?></td><td><?=htmlspecialchars($l['pack_name']??'')?></td><td style="font-size:11px;"><?=htmlspecialchars($l['lot_numbers']??'')?></td><td style="text-align:right;"><?=number_format((float)$l['quantity_shipped'],4)?></td><td style="text-align:right;">$<?=number_format((float)$l['unit_price'],4)?></td><td style="text-align:right;">$<?=number_format($ext,2)?></td></tr>
            <?php endforeach;?>
            <tr style="font-weight:600;border-top:2px solid #d1d5db;"><td colspan="6" style="text-align:right;">Total</td><td style="text-align:right;">$<?=number_format($total,2)?></td></tr>
            </tbody>
        </table>

        <div style="margin-top:12px;font-size:11px;color:#9ca3af;">Created by <?=htmlspecialchars($shipment['created_by_name']??'')?> on <?=date('M j, Y g:ia',strtotime($shipment['created_at']))?></div>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
