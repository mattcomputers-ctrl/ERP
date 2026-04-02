<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Invoice <?=htmlspecialchars($invoice['invoice_number'])?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;}.modal-overlay.active{display:flex;}.modal-box{background:#fff;border-radius:8px;padding:24px;max-width:500px;width:90%;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1000px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <?php $sb=match($invoice['status']){'OPEN'=>'badge-info','PAID'=>'badge-active','VOID'=>'badge-danger',default=>''};
        $overdue=$invoice['status']==='OPEN'&&$invoice['due_date']<date('Y-m-d');?>

        <?php if($invoice['status']==='VOID'&&$invoice['void_reason']):?><div style="padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;margin-bottom:12px;color:#991b1b;font-size:13px;"><strong>VOID:</strong> <?=htmlspecialchars($invoice['void_reason'])?></div><?php endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?=htmlspecialchars($invoice['invoice_number'])?> <span class="badge <?=$sb?>" style="font-size:14px;vertical-align:middle;"><?=$invoice['status']?></span><?=$overdue?' <span style="color:#dc2626;font-size:13px;">OVERDUE</span>':''?></h1>
            <div style="display:flex;gap:8px;">
                <a href="/invoices" class="btn btn-secondary">&larr; Back</a>
                <a href="/invoices/<?=$invoice['id']?>/pdf" class="btn btn-secondary" target="_blank">PDF</a>
                <form method="POST" action="/invoices/<?=$invoice['id']?>/email" style="display:inline;"><button type="submit" class="btn btn-secondary">Email</button></form>
                <?php if($invoice['status']==='OPEN'):?>
                    <button class="btn btn-danger" onclick="document.getElementById('voidModal').classList.add('active')">Void</button>
                <?php endif;?>
            </div>
        </div>

        <div class="form-section" style="margin-bottom:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Customer</strong><p style="margin:2px 0;"><a href="/customers/<?=$invoice['customer_id']?>" style="color:#2563eb;"><?=htmlspecialchars(($invoice['customer_code']??'').' — '.($invoice['customer_name']??''))?></a></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Invoice Date</strong><p style="margin:2px 0;"><?=date('M j, Y',strtotime($invoice['invoice_date']))?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Due Date</strong><p style="margin:2px 0;<?=$overdue?'color:#dc2626;font-weight:600;':''?>"><?=date('M j, Y',strtotime($invoice['due_date']))?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Shipment</strong><p style="margin:2px 0;"><a href="/shipping/<?=$invoice['shipment_id']?>" style="color:#2563eb;"><?=htmlspecialchars($invoice['shipment_number']??'')?></a></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Payment Terms</strong><p style="margin:2px 0;"><?=htmlspecialchars($invoice['payment_terms_name']??'—')?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Total Due</strong><p style="margin:2px 0;font-size:18px;font-weight:700;">$<?=number_format((float)$invoice['total_due'],2)?></p></div>
            </div>
        </div>

        <table class="data-table" style="margin-bottom:16px;">
            <thead><tr><th>#</th><th>Item</th><th>Description</th><th>Pack</th><th style="text-align:right;">Qty</th><th>UOM</th><th style="text-align:right;">Price</th><th style="text-align:right;">Extended</th></tr></thead>
            <tbody>
            <?php $n=1;foreach($lines as $l):$ext=(float)$l['quantity_shipped']*(float)$l['unit_price'];?>
            <tr><td style="color:#9ca3af;"><?=$n++?></td><td style="font-weight:500;"><?=htmlspecialchars($l['item_code'])?></td><td><?=htmlspecialchars($l['item_description'])?></td><td><?=htmlspecialchars($l['pack_name']??'')?></td><td style="text-align:right;"><?=number_format((float)$l['quantity_shipped'],4)?></td><td><?=htmlspecialchars($l['uom_abbr']??'')?></td><td style="text-align:right;">$<?=number_format((float)$l['unit_price'],4)?></td><td style="text-align:right;">$<?=number_format($ext,2)?></td></tr>
            <?php endforeach;?>
            <tr style="font-weight:600;border-top:2px solid #d1d5db;"><td colspan="7" style="text-align:right;">Subtotal</td><td style="text-align:right;">$<?=number_format((float)$invoice['subtotal'],2)?></td></tr>
            <?php if((float)$invoice['freight_amount']>0):?><tr><td colspan="7" style="text-align:right;">Freight</td><td style="text-align:right;">$<?=number_format((float)$invoice['freight_amount'],2)?></td></tr><?php endif;?>
            <?php if((float)$invoice['deposit_amount']>0):?><tr><td colspan="7" style="text-align:right;">Less Deposit</td><td style="text-align:right;">-$<?=number_format((float)$invoice['deposit_amount'],2)?></td></tr><?php endif;?>
            <tr style="font-weight:700;"><td colspan="7" style="text-align:right;">Total Due</td><td style="text-align:right;font-size:14px;">$<?=number_format((float)$invoice['total_due'],2)?></td></tr>
            </tbody>
        </table>

        <!-- Edit History -->
        <?php if(!empty($editHistory)):?>
        <h3 style="margin-bottom:8px;">Edit History</h3>
        <?php foreach($editHistory as $eh):?>
        <div style="padding:8px 12px;background:#f9fafb;border-radius:4px;margin-bottom:4px;font-size:12px;">
            <strong><?=htmlspecialchars($eh['edited_by_name']??'')?></strong> on <?=date('M j, Y g:ia',strtotime($eh['created_at']))?>
            <?php $changes=json_decode($eh['field_changes'],true)??[];foreach($changes as $c):?> — <?=htmlspecialchars($c['field']??'')?>: <?=htmlspecialchars($c['old']??'')?>→<?=htmlspecialchars($c['new']??'')?><?php endforeach;?>
        </div>
        <?php endforeach;endif;?>

        <?php $emailReferenceType='invoice';$emailReferenceId=$invoice['id'];require __DIR__.'/../partials/email_history_tab.php';?>
    </div>

    <!-- Void Modal -->
    <div id="voidModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="modal-box"><h3 style="margin:0 0 16px;">Void Invoice</h3>
            <form method="POST" action="/invoices/<?=$invoice['id']?>/void">
                <div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Void Reason <span style="color:red;">*</span></label><textarea name="void_reason" rows="3" class="form-input" style="width:100%;" required></textarea></div>
                <div style="display:flex;gap:8px;"><button type="submit" class="btn btn-danger">Void Invoice</button><button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button></div>
            </form>
        </div>
    </div>

    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
