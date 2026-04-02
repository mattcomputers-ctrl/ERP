<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Batch <?=htmlspecialchars($batch['batch_number'])?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>
    .tab-bar{display:flex;gap:0;border-bottom:2px solid #e5e7eb;margin-bottom:16px;overflow-x:auto;}
    .tab-btn{padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;border:none;background:none;color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-2px;white-space:nowrap;}
    .tab-btn.active{color:#2563eb;border-bottom-color:#2563eb;}.tab-btn:hover{color:#1d4ed8;}
    .tab-panel{display:none;}.tab-panel.active{display:block;}
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;}.modal-overlay.active{display:flex;}.modal-box{background:#fff;border-radius:8px;padding:24px;max-width:500px;width:90%;}
</style></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <?php
        $sb=match($batch['status']){'OPEN'=>'badge-info','IN_PROGRESS'=>'badge-warning','CLOSED'=>'badge-active','CANCELLED'=>'badge-inactive',default=>''};
        $isRush=$batch['priority']==='RUSH';
        ?>

        <?php if($isRush):?><div style="padding:8px 16px;background:#dc2626;color:#fff;font-weight:700;text-align:center;border-radius:6px;margin-bottom:12px;">RUSH BATCH</div><?php endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">
                <?=htmlspecialchars($batch['batch_number'])?>
                <span class="badge <?=$sb?>" style="font-size:14px;vertical-align:middle;"><?=$batch['status']?></span>
                <?=$isRush?'<span class="badge badge-danger" style="font-size:12px;vertical-align:middle;">RUSH</span>':''?>
            </h1>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="/batches" class="btn btn-secondary">&larr; Back</a>
                <a href="/batches/<?=$batch['id']?>/print" class="btn btn-secondary" target="_blank">Print PDF</a>
                <?php if($batch['status']==='OPEN'):?>
                    <a href="/batches/<?=$batch['id']?>/edit" class="btn btn-secondary">Edit</a>
                    <form method="POST" action="/batches/<?=$batch['id']?>/start" style="display:inline;"><button type="submit" class="btn btn-primary" onclick="return confirm('Start batch?')">Start Batch</button></form>
                    <button class="btn btn-secondary" onclick="document.getElementById('splitModal').classList.add('active')">Split</button>
                    <button class="btn btn-secondary" onclick="document.getElementById('templateModal').classList.add('active')">Save Template</button>
                <?php endif;?>
                <?php if(in_array($batch['status'],['OPEN','IN_PROGRESS'])):?>
                    <a href="/batches/<?=$batch['id']?>/close" class="btn btn-primary">Close Batch</a>
                <?php endif;?>
                <?php if($batch['status']==='CLOSED'):?>
                    <a href="/batches/<?=$batch['id']?>/coa" class="btn btn-secondary" target="_blank">COA PDF</a>
                    <a href="/batches/<?=$batch['id']?>/rework" class="btn btn-warning">Rework</a>
                <?php endif;?>
                <form method="POST" action="/batches/<?=$batch['id']?>/clone" style="display:inline;"><button type="submit" class="btn btn-secondary">Clone</button></form>
                <?php if(!in_array($batch['status'],['CLOSED','CANCELLED'])):?>
                    <form method="POST" action="/batches/<?=$batch['id']?>/cancel" style="display:inline;"><button type="submit" class="btn btn-warning" onclick="return confirm('Cancel?')">Cancel</button></form>
                <?php endif;?>
            </div>
        </div>

        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('overview')">Overview</button>
            <?php if($batch['status']==='CLOSED'):?><button class="tab-btn" onclick="switchTab('cost')">Cost Summary</button><?php endif;?>
            <button class="tab-btn" onclick="switchTab('scrap')">Scrap</button>
            <button class="tab-btn" onclick="switchTab('attachments')">Attachments</button>
            <button class="tab-btn" onclick="switchTab('custom-fields')">Custom Fields</button>
        </div>

        <!-- Overview Tab -->
        <div id="tab-overview" class="tab-panel active">
            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Item</strong><p style="margin:2px 0;"><a href="/items/<?=$batch['item_id']?>" style="color:#2563eb;"><?=htmlspecialchars($batch['item_code'])?></a> — <?=htmlspecialchars($batch['item_description'])?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Recipe</strong><p style="margin:2px 0;">v<?=(int)$batch['version_number']?> <?=htmlspecialchars($batch['version_name']??'')?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Facility</strong><p style="margin:2px 0;"><?=htmlspecialchars($batch['facility_name'])?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Target Quantity</strong><p style="margin:2px 0;font-weight:600;"><?=number_format((float)$batch['target_quantity'],4)?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Scheduled Date</strong><p style="margin:2px 0;"><?=date('M j, Y',strtotime($batch['scheduled_date']))?></p></div>
                    <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Assigned To</strong><p style="margin:2px 0;"><?=htmlspecialchars($batch['assigned_name']??'—')?></p></div>
                </div>
                <?php if($batch['internal_notes']):?><div style="margin-top:8px;"><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Internal Notes</strong><p style="margin:2px 0;"><?=nl2br(htmlspecialchars($batch['internal_notes']))?></p></div><?php endif;?>
                <?php if($batch['external_notes']):?><div style="margin-top:8px;"><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Instructions</strong><p style="margin:2px 0;"><?=nl2br(htmlspecialchars($batch['external_notes']))?></p></div><?php endif;?>
                <div style="margin-top:8px;font-size:11px;color:#9ca3af;">Created by <?=htmlspecialchars($batch['created_by_name']??'')?> on <?=date('M j, Y g:ia',strtotime($batch['created_at']))?></div>
            </div>

            <!-- Equipment -->
            <?php if(!empty($equipment)):?>
            <h4 style="margin-bottom:4px;">Equipment</h4>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
                <?php foreach($equipment as $eq):
                    $overdue=$eq['next_maintenance_due']&&$eq['next_maintenance_due']<date('Y-m-d');
                ?>
                <span style="padding:4px 10px;background:<?=$overdue?'#fef2f2':'#f0fdf4'?>;border:1px solid <?=$overdue?'#fecaca':'#bbf7d0'?>;border-radius:4px;font-size:13px;">
                    <?=htmlspecialchars($eq['name'])?> <?=$overdue?'<span style="color:#dc2626;" title="Overdue">&#9888;</span>':''?>
                </span>
                <?php endforeach;?>
            </div>
            <?php endif;?>

            <!-- Ingredient Lines -->
            <h4 style="margin-bottom:4px;">Ingredients</h4>
            <table class="data-table" style="margin-bottom:16px;">
                <thead><tr><th>Item</th><th>Description</th><th style="text-align:right;">Theoretical</th><th>UOM</th><th style="text-align:right;">Available</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach($lines as $l):
                    $avail=(float)$l['available'];$theo=(float)$l['theoretical_quantity'];
                    $statusColor=$avail>=$theo?'#16a34a':($avail>0?'#f59e0b':'#dc2626');
                ?>
                <tr>
                    <td style="font-weight:500;"><a href="/items/<?=$l['item_id']?>" style="color:#2563eb;text-decoration:none;"><?=htmlspecialchars($l['item_code'])?></a></td>
                    <td><?=htmlspecialchars($l['item_description'])?></td>
                    <td style="text-align:right;"><?=number_format($theo,4)?></td>
                    <td><?=htmlspecialchars($l['uom_abbr']??'')?></td>
                    <td style="text-align:right;color:<?=$statusColor?>;font-weight:600;"><?=number_format($avail,4)?></td>
                    <td><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?=$statusColor?>;"></span></td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>

            <!-- Target Packs -->
            <?php if(!empty($packs)):?>
            <h4 style="margin-bottom:4px;">Target Packs</h4>
            <table class="data-table" style="margin-bottom:16px;">
                <thead><tr><th>Pack</th><th style="text-align:right;">Target Qty</th></tr></thead>
                <tbody><?php foreach($packs as $p):?><tr><td><?=htmlspecialchars($p['pack_name'])?></td><td style="text-align:right;"><?=number_format((float)$p['target_quantity'],4)?></td></tr><?php endforeach;?></tbody>
            </table>
            <?php endif;?>

            <?php if($batch['status']==='CLOSED'):?>
            <div style="margin-top:16px;padding:14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;">
                <h4 style="margin:0 0 8px;font-size:13px;color:#166534;text-transform:uppercase;">Batch Results</h4>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;text-align:center;">
                    <div><div style="font-size:11px;color:#6b7280;">Actual Yield</div><div style="font-size:16px;font-weight:600;"><?=number_format((float)$batch['actual_yield'],4)?></div></div>
                    <div><div style="font-size:11px;color:#6b7280;">Yield %</div><div style="font-size:16px;font-weight:600;"><?=number_format((float)$batch['yield_percentage'],2)?>%</div></div>
                    <div><div style="font-size:11px;color:#6b7280;">Total Cost</div><div style="font-size:16px;font-weight:600;">$<?=number_format((float)$batch['total_batch_cost'],2)?></div></div>
                    <div><div style="font-size:11px;color:#6b7280;">Cost/Unit</div><div style="font-size:16px;font-weight:600;">$<?=number_format((float)$batch['cost_per_unit'],4)?></div></div>
                </div>
            </div>
            <?php endif;?>
        </div>

        <!-- Cost Summary Tab (closed batches only) -->
        <?php if($batch['status']==='CLOSED'):?>
        <div id="tab-cost" class="tab-panel">
            <p style="color:#6b7280;font-size:13px;margin-bottom:8px;">Ingredient costs from FIFO lot consumption:</p>
            <div id="costData" style="color:#9ca3af;">Loading cost data...</div>
            <script>
            fetch('/batches/<?=$batch['id']?>/cost').then(r=>r.json()).then(function(d){
                var html='<table class="data-table"><thead><tr><th>Item</th><th>Supplier Lot</th><th style="text-align:right;">Qty Used</th><th style="text-align:right;">Unit Cost</th><th style="text-align:right;">Line Cost</th></tr></thead><tbody>';
                d.ingredients.forEach(function(i){
                    html+='<tr><td style="font-weight:500;">'+i.item_code+'</td><td>'+((i.supplier_lot_number)||'—')+'</td><td style="text-align:right;">'+parseFloat(i.quantity_used).toFixed(4)+'</td><td style="text-align:right;">$'+parseFloat(i.unit_cost).toFixed(4)+'</td><td style="text-align:right;">$'+(parseFloat(i.quantity_used)*parseFloat(i.unit_cost)).toFixed(2)+'</td></tr>';
                });
                html+='<tr style="font-weight:600;border-top:2px solid #d1d5db;"><td colspan="4" style="text-align:right;">Total</td><td style="text-align:right;">$'+parseFloat(d.total_cost).toFixed(2)+'</td></tr>';
                html+='</tbody></table>';
                html+='<div style="margin-top:12px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;text-align:center;"><div><strong>Total Yield:</strong> '+parseFloat(d.total_yield).toFixed(4)+'</div><div><strong>Total Cost:</strong> $'+parseFloat(d.total_cost).toFixed(2)+'</div><div><strong>Cost/Unit:</strong> $'+parseFloat(d.cost_per_unit).toFixed(4)+'</div></div>';
                document.getElementById('costData').innerHTML=html;
            });
            </script>
        </div>
        <?php endif;?>

        <!-- Scrap Tab -->
        <div id="tab-scrap" class="tab-panel">
            <?php if(in_array($batch['status'],['OPEN','IN_PROGRESS'])):?>
            <form method="POST" action="/batches/<?=$batch['id']?>/scrap" style="padding:12px;background:#f9fafb;border-radius:6px;margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
                <div style="flex:2;min-width:180px;"><label style="font-size:12px;display:block;margin-bottom:2px;">Material <span style="color:red;">*</span></label><input type="text" name="material_description" required class="form-input" style="width:100%;"></div>
                <div style="width:90px;"><label style="font-size:12px;display:block;margin-bottom:2px;">Qty <span style="color:red;">*</span></label><input type="number" step="0.0001" name="quantity" required class="form-input" style="width:100%;"></div>
                <div style="width:80px;"><label style="font-size:12px;display:block;margin-bottom:2px;">UOM</label><select name="uom_id" class="form-input" style="width:100%;" required><?php foreach($uoms as $u):?><option value="<?=$u['id']?>"><?=htmlspecialchars($u['abbreviation'])?></option><?php endforeach;?></select></div>
                <div style="width:150px;"><label style="font-size:12px;display:block;margin-bottom:2px;">Type</label><select name="scrap_type" class="form-input" style="width:100%;"><option value="PROCESS_LOSS">Process Loss</option><option value="CONTAMINATION">Contamination</option><option value="DAMAGED">Damaged</option><option value="DISPOSAL_REQUIRED">Disposal Required</option></select></div>
                <button type="submit" class="btn btn-sm btn-primary">Log Scrap</button>
            </form>
            <?php endif;?>
            <table class="data-table">
                <thead><tr><th>Material</th><th style="text-align:right;">Qty</th><th>UOM</th><th>Type</th><th>Notes</th><th>Date</th></tr></thead>
                <tbody>
                <?php if(empty($scrap)):?><tr><td colspan="6" class="empty-state">No scrap entries.</td></tr>
                <?php else:foreach($scrap as $s):?>
                <tr style="<?=$s['scrap_type']==='DISPOSAL_REQUIRED'?'background:#fef9c3;':''?>">
                    <td><?=htmlspecialchars($s['material_description'])?></td>
                    <td style="text-align:right;"><?=number_format((float)$s['quantity'],4)?></td>
                    <td><?=htmlspecialchars($s['uom_abbr']??'')?></td>
                    <td><?php $stb=match($s['scrap_type']){'PROCESS_LOSS'=>'badge-inactive','CONTAMINATION'=>'badge-danger','DAMAGED'=>'badge-warning','DISPOSAL_REQUIRED'=>'badge-danger',default=>''};?><span class="badge <?=$stb?>"><?=$s['scrap_type']?></span></td>
                    <td style="font-size:12px;"><?=htmlspecialchars($s['notes']??'')?></td>
                    <td><?=date('M j',strtotime($s['created_at']))?></td>
                </tr>
                <?php endforeach;endif;?>
                </tbody>
            </table>
        </div>

        <!-- Attachments Tab -->
        <div id="tab-attachments" class="tab-panel">
            <?php $recordType='batch_ticket';$recordId=$batch['id'];require __DIR__.'/../partials/attachments.php';?>
        </div>

        <!-- Custom Fields Tab -->
        <div id="tab-custom-fields" class="tab-panel">
            <?php $cfRecordType='batch_tickets';$cfRecordId=$batch['id']??0;if(isset($customFieldService))require __DIR__.'/../partials/custom_fields_view.php';?>
        </div>
    </div>

    <!-- Split Modal -->
    <div id="splitModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="modal-box"><h3 style="margin:0 0 16px;">Split Batch</h3>
            <form method="POST" action="/batches/<?=$batch['id']?>/split">
                <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">Current target: <?=number_format((float)$batch['target_quantity'],4)?></p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Keep in original</label><input type="number" step="0.0001" name="keep_quantity" required class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">New batch gets</label><input type="number" step="0.0001" name="new_quantity" required class="form-input" style="width:100%;"></div>
                </div>
                <div style="display:flex;gap:8px;"><button type="submit" class="btn btn-primary">Split</button><button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button></div>
            </form>
        </div>
    </div>

    <!-- Save Template Modal -->
    <div id="templateModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="modal-box"><h3 style="margin:0 0 16px;">Save as Template</h3>
            <form method="POST" action="/batches/<?=$batch['id']?>/save-template">
                <div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Template Name <span style="color:red;">*</span></label><input type="text" name="template_name" required class="form-input" style="width:100%;"></div>
                <div style="display:flex;gap:8px;"><button type="submit" class="btn btn-primary">Save Template</button><button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button></div>
            </form>
        </div>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);
    function switchTab(name){document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});document.getElementById('tab-'+name).classList.add('active');event.target.classList.add('active');history.replaceState(null,'','#'+name);}
    (function(){var h=location.hash.replace('#','');if(h&&document.getElementById('tab-'+h)){document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});document.getElementById('tab-'+h).classList.add('active');var btns=document.querySelectorAll('.tab-btn');var names=['overview','scrap','attachments','custom-fields'];var idx=names.indexOf(h);if(idx>=0&&btns[idx])btns[idx].classList.add('active');}})();
    </script>
</body>
</html>
