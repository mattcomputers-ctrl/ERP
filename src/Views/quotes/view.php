<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($quote['quote_number'])?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.tab-bar{display:flex;gap:0;border-bottom:2px solid #e5e7eb;margin-bottom:16px;}.tab-btn{padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;border:none;background:none;color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-2px;white-space:nowrap;}.tab-btn.active{color:#2563eb;border-bottom-color:#2563eb;}.tab-panel{display:none;}.tab-panel.active{display:block;}.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;}.modal-overlay.active{display:flex;}.modal-box{background:#fff;border-radius:8px;padding:24px;max-width:500px;width:90%;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <?php
        $sb=match($quote['status']){'DRAFT'=>'badge-inactive','SENT'=>'badge-info','ACCEPTED'=>'badge-active','DECLINED'=>'badge-danger','EXPIRED'=>'badge-inactive','CONVERTED'=>'badge-active',default=>''};
        $total=0;foreach($lines as $l)$total+=(float)$l['quantity']*(float)$l['unit_price'];
        $daysLeft=(strtotime($quote['expiration_date'])-time())/86400;
        ?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">
                <?=htmlspecialchars($quote['quote_number'])?>
                <span class="badge <?=$sb?>" style="font-size:14px;vertical-align:middle;"><?=$quote['status']?></span>
                <?php if(in_array($quote['status'],['DRAFT','SENT'])):?>
                    <span style="font-size:13px;color:<?=$daysLeft<3?'#dc2626':'#6b7280'?>;vertical-align:middle;">
                        <?=$daysLeft>0?'Expires in '.ceil($daysLeft).' day(s)':'Expired '.abs((int)$daysLeft).' day(s) ago'?>
                    </span>
                <?php endif;?>
            </h1>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="/quotes" class="btn btn-secondary">&larr; Back</a>
                <?php if(in_array($quote['status'],['DRAFT','SENT'])):?><a href="/quotes/<?=$quote['id']?>/edit" class="btn btn-secondary">Edit</a><?php endif;?>
                <?php if($quote['status']==='DRAFT'):?>
                    <form method="POST" action="/quotes/<?=$quote['id']?>/send" style="display:inline;"><button type="submit" class="btn btn-primary">Send to Customer</button></form>
                <?php elseif($quote['status']==='SENT'):?>
                    <form method="POST" action="/quotes/<?=$quote['id']?>/accept" style="display:inline;"><button type="submit" class="btn btn-primary" onclick="return confirm('Accept?')">Accept</button></form>
                    <button class="btn btn-danger" onclick="document.getElementById('declineModal').classList.add('active')">Decline</button>
                <?php elseif($quote['status']==='ACCEPTED'):?>
                    <form method="POST" action="/quotes/<?=$quote['id']?>/convert" style="display:inline;"><button type="submit" class="btn btn-primary" onclick="return confirm('Convert to Sales Order?')">Convert to SO</button></form>
                <?php endif;?>
                <form method="POST" action="/quotes/<?=$quote['id']?>/clone" style="display:inline;"><button type="submit" class="btn btn-secondary">Clone</button></form>
            </div>
        </div>

        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('details')">Details</button>
            <button class="tab-btn" onclick="switchTab('attachments')">Attachments</button>
            <button class="tab-btn" onclick="switchTab('email')">Email History</button>
        </div>

        <!-- Details Tab -->
        <div id="tab-details" class="tab-panel active">
            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Customer</strong><p style="margin:2px 0;"><a href="/customers/<?=$quote['customer_id']?>" style="color:#2563eb;"><?=htmlspecialchars($quote['customer_code'].' — '.$quote['customer_name'])?></a><?=$quote['account_hold']?' <span class="badge badge-danger" style="font-size:10px;">HOLD</span>':''?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Quote Date</strong><p style="margin:2px 0;"><?=date('M j, Y',strtotime($quote['quote_date']))?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Expiration</strong><p style="margin:2px 0;<?=$daysLeft<3&&in_array($quote['status'],['DRAFT','SENT'])?'color:#dc2626;font-weight:600;':''?>"><?=date('M j, Y',strtotime($quote['expiration_date']))?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Soft Reserve</strong><p style="margin:2px 0;"><?=$quote['soft_reserve']?'<span style="color:#16a34a;">Yes</span>':'No'?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Created By</strong><p style="margin:2px 0;"><?=htmlspecialchars($quote['created_by_name']??'')?></p></div>
                    <?php if($quote['converted_so_id']):?><div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Converted SO</strong><p style="margin:2px 0;"><a href="/sales-orders/<?=$quote['converted_so_id']?>" style="color:#2563eb;">View SO</a></p></div><?php endif;?>
                </div>
                <?php if($quote['lost_reason']):?><div style="margin-top:8px;padding:8px 12px;background:#fef2f2;border-radius:4px;"><strong style="font-size:12px;color:#991b1b;">Lost Reason:</strong> <?=htmlspecialchars($quote['lost_reason'])?><?=$quote['lost_notes']?' — '.htmlspecialchars($quote['lost_notes']):''?></div><?php endif;?>
                <?php if($quote['notes']):?><div style="margin-top:8px;"><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Notes</strong><p style="margin:2px 0;"><?=nl2br(htmlspecialchars($quote['notes']))?></p></div><?php endif;?>
            </div>

            <table class="data-table">
                <thead><tr><th>#</th><th>Item</th><th>Pack</th><th style="text-align:right;">Qty</th><th>UOM</th><th style="text-align:right;">Price</th><th style="text-align:right;">Extended</th></tr></thead>
                <tbody>
                <?php $n=1;foreach($lines as $l):$ext=(float)$l['quantity']*(float)$l['unit_price'];?>
                <tr><td style="color:#9ca3af;"><?=$n++?></td><td><a href="/items/<?=$l['item_id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($l['item_code'])?></a> <span style="color:#6b7280;font-size:12px;"><?=htmlspecialchars($l['item_description'])?></span></td><td><?=htmlspecialchars($l['pack_name']??'')?></td><td style="text-align:right;"><?=number_format((float)$l['quantity'],4)?></td><td><?=htmlspecialchars($l['uom_abbr']??'')?></td><td style="text-align:right;">$<?=number_format((float)$l['unit_price'],4)?></td><td style="text-align:right;">$<?=number_format($ext,2)?></td></tr>
                <?php endforeach;?>
                <tr style="font-weight:600;border-top:2px solid #d1d5db;"><td colspan="6" style="text-align:right;">Total</td><td style="text-align:right;">$<?=number_format($total,2)?></td></tr>
                </tbody>
            </table>
        </div>

        <!-- Attachments Tab -->
        <div id="tab-attachments" class="tab-panel"><?php $recordType='quote';$recordId=$quote['id'];require __DIR__.'/../partials/attachments.php';?></div>

        <!-- Email History Tab -->
        <div id="tab-email" class="tab-panel"><?php $emailReferenceType='quote';$emailReferenceId=$quote['id'];require __DIR__.'/../partials/email_history_tab.php';?></div>
    </div>

    <!-- Decline Modal -->
    <div id="declineModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="modal-box"><h3 style="margin:0 0 16px;">Decline Quote</h3>
            <form method="POST" action="/quotes/<?=$quote['id']?>/decline">
                <div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Lost Reason <span style="color:red;">*</span></label>
                    <select name="lost_reason" required class="form-input" style="width:100%;"><option value="">— Select —</option><?php foreach($lostReasons as $r):?><option value="<?=htmlspecialchars($r['name'])?>"><?=htmlspecialchars($r['name'])?></option><?php endforeach;?></select></div>
                <div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Notes</label><textarea name="lost_notes" rows="3" class="form-input" style="width:100%;"></textarea></div>
                <div style="display:flex;gap:8px;"><button type="submit" class="btn btn-danger">Decline Quote</button><button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button></div>
            </form>
        </div>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);
    function switchTab(n){document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});document.getElementById('tab-'+n).classList.add('active');event.target.classList.add('active');history.replaceState(null,'','#'+n);}
    (function(){var h=location.hash.replace('#','');if(h&&document.getElementById('tab-'+h)){document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});document.getElementById('tab-'+h).classList.add('active');var btns=document.querySelectorAll('.tab-btn');var names=['details','attachments','email'];var idx=names.indexOf(h);if(idx>=0&&btns[idx])btns[idx].classList.add('active');}})();
    </script>
</body>
</html>
