<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Confirm Shipment — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <?php if(!empty($creditWarnings)):?><div style="padding:10px 14px;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;margin-bottom:12px;color:#854d0e;font-size:13px;"><strong>Credit Warning:</strong> <?=implode(' | ',$creditWarnings)?></div><?php endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Confirm Shipment</h1>
            <a href="/shipping/create" class="btn btn-secondary">&larr; Back</a>
        </div>

        <form method="POST" action="/shipping/create">
            <input type="hidden" name="facility_id" value="<?=$facilityId?>">
            <?php $firstSo=$orders[0]??[];?>
            <input type="hidden" name="ship_to_id" value="<?=$firstSo['ship_to_id']??0?>">
            <?php foreach($orders as $o):?><input type="hidden" name="so_ids[]" value="<?=$o['id']?>"><?php endforeach;?>

            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;">
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Ship Date</label><input type="date" name="ship_date" value="<?=date('Y-m-d')?>" class="form-input" style="width:100%;" required></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Ship Via</label><select name="ship_via_id" class="form-input" style="width:100%;"><option value="">—</option><?php foreach($shipVias as $sv):?><option value="<?=$sv['id']?>" <?=($firstSo['ship_via_id']??0)==$sv['id']?'selected':''?>><?=htmlspecialchars($sv['name'])?></option><?php endforeach;?></select></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Tracking #</label><input type="text" name="tracking_number" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Freight Cost</label><input type="number" step="0.01" name="freight_cost" value="0" class="form-input" style="width:100%;"></div>
                </div>
            </div>

            <?php foreach($orders as $so):?>
            <div style="margin-bottom:16px;">
                <h3 style="margin-bottom:8px;"><?=htmlspecialchars($so['so_number'])?> — <?=htmlspecialchars($so['company_name'])?></h3>
                <table class="data-table">
                    <thead><tr><th>Item</th><th>Description</th><th>Pack</th><th>Location</th><th style="text-align:right;">Ordered</th><th style="text-align:right;">Shipped</th><th>Ship Qty</th><th style="text-align:right;">Price</th></tr></thead>
                    <tbody>
                    <?php foreach($allLines[$so['id']]??[] as $l):$remaining=(float)$l['ordered_quantity']-(float)$l['shipped_quantity'];?>
                    <tr>
                        <td style="font-weight:500;"><?=htmlspecialchars($l['item_code'])?></td>
                        <td><?=htmlspecialchars($l['item_description'])?></td>
                        <td><?=htmlspecialchars($l['pack_name']??'')?></td>
                        <td style="font-weight:600;color:#1d4ed8;"><?=htmlspecialchars($l['location'])?></td>
                        <td style="text-align:right;"><?=number_format((float)$l['ordered_quantity'],4)?></td>
                        <td style="text-align:right;"><?=number_format((float)$l['shipped_quantity'],4)?></td>
                        <td><input type="number" step="0.0001" name="lines[<?=$l['id']?>][quantity_shipped]" value="<?=$remaining?>" class="form-input" style="width:100px;"></td>
                        <td style="text-align:right;">
                            <input type="hidden" name="lines[<?=$l['id']?>][unit_price]" value="<?=$l['unit_price']?>">
                            <input type="hidden" name="lines[<?=$l['id']?>][packing_slip_note]" value="<?=htmlspecialchars($l['packing_slip_note']??'')?>">
                            $<?=number_format((float)$l['unit_price'],4)?>
                        </td>
                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
            <?php endforeach;?>

            <div style="margin-top:16px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Confirm shipment? Inventory will be consumed and invoice generated.')">Confirm Shipment</button>
                <a href="/shipping/create" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
