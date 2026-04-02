<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($so['so_number'])?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.tab-bar{display:flex;gap:0;border-bottom:2px solid #e5e7eb;margin-bottom:16px;overflow-x:auto;}.tab-btn{padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;border:none;background:none;color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-2px;white-space:nowrap;}.tab-btn.active{color:#2563eb;border-bottom-color:#2563eb;}.tab-panel{display:none;}.tab-panel.active{display:block;}.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;}.modal-overlay.active{display:flex;}.modal-box{background:#fff;border-radius:8px;padding:24px;max-width:500px;width:90%;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <?php
        $sb=match($so['status']){'DRAFT'=>'badge-inactive','CONFIRMED'=>'badge-info','ON_HOLD'=>'badge-warning','PARTIAL'=>'badge-warning','SHIPPED'=>'badge-active','CANCELLED'=>'badge-danger',default=>''};
        $subtotal=0;foreach($lines as $l)$subtotal+=(float)$l['ordered_quantity']*(float)$l['unit_price'];
        $surchTotal=0;foreach($surcharges as $s)$surchTotal+=(float)$s['amount'];
        ?>

        <?php if($so['status']==='ON_HOLD'):?><div style="padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;margin-bottom:12px;color:#991b1b;font-size:13px;"><strong>ON HOLD:</strong> <?=htmlspecialchars($so['hold_reason']??'')?></div><?php endif;?>
        <?php if(!empty($credit['show_warning'])):?><div style="padding:10px 14px;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;margin-bottom:12px;color:#854d0e;font-size:13px;">Credit warning: $<?=number_format($credit['total_exposure'],2)?> exposure vs $<?=number_format($credit['credit_limit'],2)?> limit (<?=$credit['utilization_pct']?>%)</div><?php endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">
                <?=htmlspecialchars($so['so_number'])?>
                <span class="badge <?=$sb?>" style="font-size:14px;vertical-align:middle;"><?=$so['status']?></span>
                <?=$so['is_sample']?'<span class="badge badge-info" style="font-size:12px;vertical-align:middle;">SAMPLE</span>':''?>
            </h1>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="/orders" class="btn btn-secondary">&larr; Back</a>
                <?php if(in_array($so['status'],['DRAFT','CONFIRMED'])):?><a href="/orders/<?=$so['id']?>/edit" class="btn btn-secondary">Edit</a><?php endif;?>
                <?php if($so['status']==='DRAFT'):?>
                    <form method="POST" action="/orders/<?=$so['id']?>/confirm" style="display:inline;"><button type="submit" class="btn btn-primary" onclick="return confirm('Confirm order?')">Confirm</button></form>
                <?php endif;?>
                <?php if(in_array($so['status'],['CONFIRMED','PARTIAL'])):?>
                    <form method="POST" action="/orders/<?=$so['id']?>/acknowledgment" style="display:inline;"><button type="submit" class="btn btn-secondary">Send Acknowledgment</button></form>
                    <form method="POST" action="/orders/<?=$so['id']?>/proforma" style="display:inline;" target="_blank"><button type="submit" class="btn btn-secondary">Proforma</button></form>
                <?php endif;?>
                <?php if(!in_array($so['status'],['ON_HOLD','SHIPPED','CANCELLED'])):?>
                    <button class="btn btn-warning" onclick="document.getElementById('holdModal').classList.add('active')">Hold</button>
                <?php endif;?>
                <?php if($so['status']==='ON_HOLD'):?>
                    <form method="POST" action="/orders/<?=$so['id']?>/release-hold" style="display:inline;"><button type="submit" class="btn btn-primary">Release Hold</button></form>
                <?php endif;?>
                <form method="POST" action="/orders/<?=$so['id']?>/clone" style="display:inline;"><button type="submit" class="btn btn-secondary">Clone</button></form>
                <?php if(!in_array($so['status'],['SHIPPED','CANCELLED'])):?>
                    <form method="POST" action="/orders/<?=$so['id']?>/cancel" style="display:inline;"><button type="submit" class="btn btn-danger" onclick="return confirm('Cancel order?')">Cancel</button></form>
                <?php endif;?>
            </div>
        </div>

        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('details')">Details</button>
            <button class="tab-btn" onclick="switchTab('lines')">Lines</button>
            <button class="tab-btn" onclick="switchTab('freight')">Freight</button>
            <button class="tab-btn" onclick="switchTab('shipments')">Shipments</button>
            <button class="tab-btn" onclick="switchTab('invoices')">Invoices</button>
            <button class="tab-btn" onclick="switchTab('attachments')">Attachments</button>
            <button class="tab-btn" onclick="switchTab('email')">Email</button>
            <button class="tab-btn" onclick="switchTab('custom-fields')">Custom Fields</button>
        </div>

        <!-- Details Tab -->
        <div id="tab-details" class="tab-panel active">
            <div class="form-section">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Customer</strong><p style="margin:2px 0;"><a href="/customers/<?=$so['customer_id']?>" style="color:#2563eb;"><?=htmlspecialchars(($so['customer_code']??'').' — '.($so['customer_name']??''))?></a></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Ship To</strong><p style="margin:2px 0;"><?=htmlspecialchars($so['ship_to_name']??'—')?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Facility</strong><p style="margin:2px 0;"><?=htmlspecialchars($so['facility_name']??'')?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Order Date</strong><p style="margin:2px 0;"><?=date('M j, Y',strtotime($so['order_date']))?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Promised Ship</strong><p style="margin:2px 0;"><?=$so['promised_ship_date']?date('M j, Y',strtotime($so['promised_ship_date'])):'—'?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Promised Delivery</strong><p style="margin:2px 0;"><?=$so['promised_delivery_date']?date('M j, Y',strtotime($so['promised_delivery_date'])):'—'?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Payment Terms</strong><p style="margin:2px 0;"><?=htmlspecialchars($so['payment_terms_name']??'—')?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Ship Via</strong><p style="margin:2px 0;"><?=htmlspecialchars($so['ship_via_name']??'—')?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Customer PO</strong><p style="margin:2px 0;"><?=htmlspecialchars($so['customer_po_number']??'—')?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Sales Rep</strong><p style="margin:2px 0;"><?=htmlspecialchars($so['sales_rep_name']??'—')?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Subtotal</strong><p style="margin:2px 0;font-weight:600;">$<?=number_format($subtotal,2)?></p></div>
                    <?php if($so['deposit_amount']>0):?><div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Deposit</strong><p style="margin:2px 0;">$<?=number_format((float)$so['deposit_amount'],2)?></p></div><?php endif;?>
                </div>
                <?php if($so['external_notes']):?><div style="margin-top:8px;"><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">External Notes</strong><p style="margin:2px 0;"><?=nl2br(htmlspecialchars($so['external_notes']))?></p></div><?php endif;?>
                <?php if($so['internal_notes']):?><div style="margin-top:8px;"><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Internal Notes</strong><p style="margin:2px 0;"><?=nl2br(htmlspecialchars($so['internal_notes']))?></p></div><?php endif;?>
            </div>
        </div>

        <!-- Lines Tab -->
        <div id="tab-lines" class="tab-panel">
            <table class="data-table">
                <thead><tr><th>#</th><th>Item</th><th>Pack</th><th style="text-align:right;">Ordered</th><th style="text-align:right;">Shipped</th><th style="text-align:right;">Backorder</th><th>UOM</th><th style="text-align:right;">Price</th><th style="text-align:right;">Extended</th></tr></thead>
                <tbody>
                <?php $n=1;foreach($lines as $l):$ext=(float)$l['ordered_quantity']*(float)$l['unit_price'];?>
                <tr><td style="color:#9ca3af;"><?=$n++?></td><td><a href="/items/<?=$l['item_id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($l['item_code'])?></a> <span style="color:#6b7280;font-size:12px;"><?=htmlspecialchars($l['item_description'])?></span></td><td><?=htmlspecialchars($l['pack_name']??'')?></td><td style="text-align:right;"><?=number_format((float)$l['ordered_quantity'],4)?></td><td style="text-align:right;"><?=number_format((float)$l['shipped_quantity'],4)?></td><td style="text-align:right;<?=(float)$l['backordered_quantity']>0?'color:#dc2626;font-weight:600;':''?>"><?=(float)$l['backordered_quantity']>0?number_format((float)$l['backordered_quantity'],4):'—'?></td><td><?=htmlspecialchars($l['uom_abbr']??'')?></td><td style="text-align:right;">$<?=number_format((float)$l['unit_price'],4)?></td><td style="text-align:right;">$<?=number_format($ext,2)?></td></tr>
                <?php if($l['packing_slip_note']):?><tr><td></td><td colspan="8" style="font-size:12px;color:#6b7280;font-style:italic;padding:0 8px 4px;">Packing note: <?=htmlspecialchars($l['packing_slip_note'])?></td></tr><?php endif;?>
                <?php endforeach;?>
                <tr style="font-weight:600;border-top:2px solid #d1d5db;"><td colspan="8" style="text-align:right;">Subtotal</td><td style="text-align:right;">$<?=number_format($subtotal,2)?></td></tr>
                <?php if($surchTotal>0):?><tr><td colspan="8" style="text-align:right;">Surcharges</td><td style="text-align:right;">$<?=number_format($surchTotal,2)?></td></tr><?php endif;?>
                <?php if((float)$so['deposit_amount']>0):?><tr><td colspan="8" style="text-align:right;">Less Deposit</td><td style="text-align:right;">-$<?=number_format((float)$so['deposit_amount'],2)?></td></tr><?php endif;?>
                <tr style="font-weight:700;"><td colspan="8" style="text-align:right;">Grand Total</td><td style="text-align:right;">$<?=number_format($subtotal+$surchTotal-(float)$so['deposit_amount'],2)?></td></tr>
                </tbody>
            </table>
        </div>

        <!-- Freight Tab -->
        <div id="tab-freight" class="tab-panel">
            <?php if(empty($freightQuotes)):?><p style="color:#9ca3af;padding:16px;">No freight quotes.</p>
            <?php else:?>
            <table class="data-table"><thead><tr><th>Carrier</th><th>Service</th><th style="text-align:right;">Cost</th><th>Transit</th><th>Selected</th></tr></thead><tbody>
            <?php foreach($freightQuotes as $fq):?><tr style="<?=$fq['is_selected']?'background:#f0fdf4;':''?>"><td><?=htmlspecialchars($fq['carrier_name'])?></td><td><?=htmlspecialchars($fq['service_level'])?></td><td style="text-align:right;">$<?=number_format((float)$fq['quoted_cost'],2)?></td><td><?=$fq['transit_days']?$fq['transit_days'].' days':'—'?></td><td><?=$fq['is_selected']?'<span style="color:#16a34a;">&#10003;</span>':''?></td></tr>
            <?php endforeach;?></tbody></table>
            <?php endif;?>
        </div>

        <!-- Shipments Tab -->
        <div id="tab-shipments" class="tab-panel"><p style="color:#9ca3af;padding:16px;">Shipments built in Session 20.</p></div>
        <!-- Invoices Tab -->
        <div id="tab-invoices" class="tab-panel"><p style="color:#9ca3af;padding:16px;">Invoices built in Session 20.</p></div>
        <!-- Attachments Tab -->
        <div id="tab-attachments" class="tab-panel"><?php $recordType='sales_order';$recordId=$so['id'];require __DIR__.'/../partials/attachments.php';?></div>
        <!-- Email Tab -->
        <div id="tab-email" class="tab-panel"><?php $emailReferenceType='sales_order';$emailReferenceId=$so['id'];require __DIR__.'/../partials/email_history_tab.php';?></div>
        <!-- Custom Fields Tab -->
        <div id="tab-custom-fields" class="tab-panel"><?php $cfRecordType='sales_orders';$cfRecordId=$so['id']??0;if(isset($customFieldService))require __DIR__.'/../partials/custom_fields_view.php';?></div>
    </div>

    <!-- Hold Modal -->
    <div id="holdModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="modal-box"><h3 style="margin:0 0 16px;">Place Order on Hold</h3>
            <form method="POST" action="/orders/<?=$so['id']?>/hold">
                <div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Hold Reason <span style="color:red;">*</span></label><textarea name="hold_reason" rows="3" class="form-input" style="width:100%;" required></textarea></div>
                <div style="display:flex;gap:8px;"><button type="submit" class="btn btn-warning">Place on Hold</button><button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button></div>
            </form>
        </div>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);
    function switchTab(n){document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});document.getElementById('tab-'+n).classList.add('active');event.target.classList.add('active');history.replaceState(null,'','#'+n);}
    (function(){var h=location.hash.replace('#','');if(h&&document.getElementById('tab-'+h)){document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});document.getElementById('tab-'+h).classList.add('active');var btns=document.querySelectorAll('.tab-btn');var names=['details','lines','freight','shipments','invoices','attachments','email','custom-fields'];var idx=names.indexOf(h);if(idx>=0&&btns[idx])btns[idx].classList.add('active');}})();
    </script>
</body>
</html>
