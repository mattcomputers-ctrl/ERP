<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?=htmlspecialchars($rma['rma_number'])?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.tab-bar{display:flex;gap:0;border-bottom:2px solid #e5e7eb;margin-bottom:16px;}.tab-btn{padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;border:none;background:none;color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-2px;white-space:nowrap;}.tab-btn.active{color:#2563eb;border-bottom-color:#2563eb;}.tab-panel{display:none;}.tab-panel.active{display:block;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1000px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <?php $sb=match($rma['status']){'OPEN'=>'badge-info','RECEIVED'=>'badge-warning','CLOSED'=>'badge-active','CANCELLED'=>'badge-inactive',default=>''};?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?=htmlspecialchars($rma['rma_number'])?> <span class="badge <?=$sb?>" style="font-size:14px;vertical-align:middle;"><?=$rma['status']?></span></h1>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="/rma" class="btn btn-secondary">&larr; Back</a>
                <?php if($rma['status']==='OPEN'):?>
                    <a href="/rma/<?=$rma['id']?>/edit" class="btn btn-secondary">Edit</a>
                <?php endif;?>
                <?php if($rma['status']==='OPEN'):?>
                    <form method="POST" action="/rma/<?=$rma['id']?>/receive" id="receiveForm">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Process receipt for all lines?')">Receive Returns</button>
                    </form>
                <?php elseif($rma['status']==='RECEIVED'):?>
                    <form method="POST" action="/rma/<?=$rma['id']?>/close" style="display:inline;"><button type="submit" class="btn btn-primary" onclick="return confirm('Close RMA and generate credit memo?')">Close + Credit Memo</button></form>
                <?php endif;?>
                <a href="/scars/create?supplier_id=&lot_number=<?=urlencode($lines[0]['lot_number']??'')?>&description=<?=urlencode('Customer return: '.$rma['return_reason'])?>" class="btn btn-secondary">Create SCAR</a>
                <?php if(!in_array($rma['status'],['CLOSED','CANCELLED'])):?>
                    <form method="POST" action="/rma/<?=$rma['id']?>/cancel" style="display:inline;"><button type="submit" class="btn btn-warning" onclick="return confirm('Cancel?')">Cancel</button></form>
                <?php endif;?>
            </div>
        </div>

        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('details')">Details</button>
            <button class="tab-btn" onclick="switchTab('lines')">Lines</button>
            <button class="tab-btn" onclick="switchTab('attachments')">Attachments</button>
            <button class="tab-btn" onclick="switchTab('email')">Email History</button>
        </div>

        <!-- Details Tab -->
        <div id="tab-details" class="tab-panel active">
            <div class="form-section">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Customer</strong><p style="margin:2px 0;"><a href="/customers/<?=$rma['customer_id']?>" style="color:#2563eb;"><?=htmlspecialchars(($rma['customer_code']??'').' — '.($rma['customer_name']??''))?></a></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">RMA Date</strong><p style="margin:2px 0;"><?=date('M j, Y',strtotime($rma['rma_date']))?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Return Reason</strong><p style="margin:2px 0;"><?=htmlspecialchars($rma['return_reason'])?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Receiving Facility</strong><p style="margin:2px 0;"><?=htmlspecialchars($rma['facility_name'])?></p></div>
                    <?php if($rma['so_number']):?><div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Linked SO</strong><p style="margin:2px 0;"><a href="/orders/<?=$rma['so_id']?>" style="color:#2563eb;"><?=htmlspecialchars($rma['so_number'])?></a></p></div><?php endif;?>
                </div>
                <?php if($rma['notes']):?><div style="margin-top:8px;"><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Notes</strong><p style="margin:2px 0;"><?=nl2br(htmlspecialchars($rma['notes']))?></p></div><?php endif;?>
            </div>
        </div>

        <!-- Lines Tab -->
        <div id="tab-lines" class="tab-panel">
            <?php if($rma['status']==='OPEN'):?>
            <!-- Receipt form embedded in lines -->
            <form method="POST" action="/rma/<?=$rma['id']?>/receive">
            <?php endif;?>
            <table class="data-table">
                <thead><tr><th>Item</th><th>Pack</th><th style="text-align:right;">Authorized</th><th style="text-align:right;">Received</th><th>Lot #</th><th>Disposition</th><th>Condition</th></tr></thead>
                <tbody>
                <?php foreach($lines as $l):
                    $db=match($l['disposition']){'RETURN_TO_STOCK'=>'badge-active','WRITE_OFF'=>'badge-danger','HOLD_FOR_INSPECTION'=>'badge-warning',default=>''};
                ?>
                <tr>
                    <td style="font-weight:500;"><a href="/items/<?=$l['item_id']?>" style="color:#2563eb;"><?=htmlspecialchars($l['item_code'])?></a> <span style="color:#6b7280;font-size:12px;"><?=htmlspecialchars($l['item_description'])?></span></td>
                    <td><?=htmlspecialchars($l['pack_name']??'')?></td>
                    <td style="text-align:right;"><?=number_format((float)$l['authorized_quantity'],4)?></td>
                    <td style="text-align:right;">
                        <?php if($rma['status']==='OPEN'):?>
                            <input type="number" step="0.0001" name="recv[<?=$l['id']?>][received_quantity]" value="<?=$l['authorized_quantity']?>" class="form-input" style="width:100px;font-size:12px;padding:3px 6px;">
                        <?php else:?>
                            <?=$l['received_quantity']!==null?number_format((float)$l['received_quantity'],4):'—'?>
                        <?php endif;?>
                    </td>
                    <td>
                        <?php if($rma['status']==='OPEN'):?>
                            <input type="text" name="recv[<?=$l['id']?>][lot_number]" value="<?=htmlspecialchars($l['lot_number']??'')?>" class="form-input" style="width:100px;font-size:12px;padding:3px 6px;">
                        <?php else:?>
                            <?=htmlspecialchars($l['lot_number']??'—')?>
                        <?php endif;?>
                    </td>
                    <td>
                        <?php if($rma['status']==='OPEN'):?>
                            <select name="recv[<?=$l['id']?>][disposition]" class="form-input" style="font-size:12px;padding:3px 6px;">
                                <option value="RETURN_TO_STOCK" <?=$l['disposition']==='RETURN_TO_STOCK'?'selected':''?>>Return to Stock</option>
                                <option value="WRITE_OFF" <?=$l['disposition']==='WRITE_OFF'?'selected':''?>>Write Off</option>
                                <option value="HOLD_FOR_INSPECTION" <?=$l['disposition']==='HOLD_FOR_INSPECTION'?'selected':''?>>Hold for Inspection</option>
                            </select>
                        <?php else:?>
                            <span class="badge <?=$db?>"><?=str_replace('_',' ',$l['disposition'])?></span>
                        <?php endif;?>
                    </td>
                    <td style="font-size:12px;">
                        <?php if($rma['status']==='OPEN'):?>
                            <input type="text" name="recv[<?=$l['id']?>][condition_notes]" value="<?=htmlspecialchars($l['condition_notes']??'')?>" class="form-input" style="width:120px;font-size:12px;padding:3px 6px;" placeholder="Condition">
                        <?php else:?>
                            <?=htmlspecialchars($l['condition_notes']??'')?>
                        <?php endif;?>
                    </td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
            <?php if($rma['status']==='OPEN'):?>
            <div style="margin-top:12px;"><button type="submit" class="btn btn-primary" onclick="return confirm('Process receipt?')">Receive All Lines</button></div>
            </form>
            <?php endif;?>
        </div>

        <!-- Attachments Tab -->
        <div id="tab-attachments" class="tab-panel"><?php $recordType='rma';$recordId=$rma['id'];require __DIR__.'/../partials/attachments.php';?></div>
        <!-- Email Tab -->
        <div id="tab-email" class="tab-panel"><?php $emailReferenceType='rma';$emailReferenceId=$rma['id'];require __DIR__.'/../partials/email_history_tab.php';?></div>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>
    var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);
    function switchTab(n){document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});document.getElementById('tab-'+n).classList.add('active');event.target.classList.add('active');history.replaceState(null,'','#'+n);}
    (function(){var h=location.hash.replace('#','');if(h&&document.getElementById('tab-'+h)){document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});document.getElementById('tab-'+h).classList.add('active');var btns=document.querySelectorAll('.tab-btn');var names=['details','lines','attachments','email'];var idx=names.indexOf(h);if(idx>=0&&btns[idx])btns[idx].classList.add('active');}})();
    </script>
</body>
</html>
