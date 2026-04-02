<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?=htmlspecialchars($scar['scar_number'])?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;}.modal-overlay.active{display:flex;}.modal-box{background:#fff;border-radius:8px;padding:24px;max-width:500px;width:90%;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:900px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <?php $sb=match($scar['status']){'OPEN'=>'badge-info','RESPONSE_RECEIVED'=>'badge-warning','CLOSED'=>'badge-active','CANCELLED'=>'badge-inactive',default=>''}; ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?=htmlspecialchars($scar['scar_number'])?> <span class="badge <?=$sb?>" style="font-size:14px;vertical-align:middle;"><?=$scar['status']?></span></h1>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="/scars" class="btn btn-secondary">&larr; Back</a>
                <?php if($scar['status']==='OPEN'):?>
                    <a href="/scars/<?=$scar['id']?>/edit" class="btn btn-secondary">Edit</a>
                <?php endif;?>
                <a href="/scars/<?=$scar['id']?>/pdf" class="btn btn-secondary" target="_blank">Download PDF</a>
                <form method="POST" action="/scars/<?=$scar['id']?>/email" style="display:inline;"><button type="submit" class="btn btn-secondary">Email to Supplier</button></form>
                <?php if(in_array($scar['status'],['OPEN','RESPONSE_RECEIVED'])):?>
                    <?php if($scar['status']==='OPEN'):?><button class="btn btn-primary" onclick="document.getElementById('respondModal').classList.add('active')">Record Response</button><?php endif;?>
                    <button class="btn btn-primary" onclick="document.getElementById('closeModal').classList.add('active')">Close SCAR</button>
                <?php endif;?>
                <?php if($scar['status']!=='CANCELLED'&&$scar['status']!=='CLOSED'):?>
                    <form method="POST" action="/scars/<?=$scar['id']?>/cancel" style="display:inline;"><button type="submit" class="btn btn-warning" onclick="return confirm('Cancel?')">Cancel</button></form>
                <?php endif;?>
            </div>
        </div>

        <div class="form-section" style="margin-bottom:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Supplier</strong><p style="margin:2px 0;"><a href="/suppliers/<?=$scar['supplier_id']?>" style="color:#2563eb;"><?=htmlspecialchars($scar['supplier_code'].' — '.$scar['supplier_name'])?></a></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Issue Date</strong><p style="margin:2px 0;"><?=date('M j, Y',strtotime($scar['issue_date']))?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Due Date</strong>
                    <?php $overdue=$scar['due_date']&&$scar['due_date']<date('Y-m-d')&&!in_array($scar['status'],['CLOSED','CANCELLED']);?>
                    <p style="margin:2px 0;<?=$overdue?'color:#dc2626;font-weight:600;':''?>"><?=$scar['due_date']?date('M j, Y',strtotime($scar['due_date'])):'—'?><?=$overdue?' (OVERDUE)':''?></p>
                </div>
                <?php if($scar['lot_number']):?><div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Lot Number</strong><p style="margin:2px 0;"><?=htmlspecialchars($scar['lot_number'])?></p></div><?php endif;?>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Created By</strong><p style="margin:2px 0;"><?=htmlspecialchars($scar['created_by_name']??'')?> on <?=date('M j, Y',strtotime($scar['created_at']))?></p></div>
            </div>

            <div style="margin-top:12px;"><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Description of Issue</strong><p style="margin:2px 0;"><?=nl2br(htmlspecialchars($scar['description']))?></p></div>
            <?php if($scar['required_action']):?><div style="margin-top:8px;"><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Required Corrective Action</strong><p style="margin:2px 0;"><?=nl2br(htmlspecialchars($scar['required_action']))?></p></div><?php endif;?>

            <?php if($scar['supplier_response']):?>
            <div style="margin-top:12px;padding:12px;background:#eff6ff;border-radius:6px;">
                <strong style="font-size:12px;color:#1d4ed8;text-transform:uppercase;">Supplier Response</strong>
                <p style="margin:4px 0;"><?=nl2br(htmlspecialchars($scar['supplier_response']))?></p>
            </div>
            <?php endif;?>

            <?php if($scar['closure_notes']):?>
            <div style="margin-top:12px;padding:12px;background:#f0fdf4;border-radius:6px;">
                <strong style="font-size:12px;color:#166534;text-transform:uppercase;">Closure Notes</strong>
                <p style="margin:4px 0;"><?=nl2br(htmlspecialchars($scar['closure_notes']))?></p>
                <p style="font-size:11px;color:#6b7280;margin-top:4px;">Closed by <?=htmlspecialchars($scar['closed_by_name']??'')?> on <?=$scar['closed_at']?date('M j, Y g:ia',strtotime($scar['closed_at'])):''?></p>
            </div>
            <?php endif;?>
        </div>

        <!-- Custom Fields -->
        <?php $cfRecordType='scars';$cfRecordId=$scar['id']??0;if(isset($customFieldService))require __DIR__.'/../partials/custom_fields_view.php';?>

        <!-- Email History -->
        <?php $emailReferenceType='scar';$emailReferenceId=$scar['id']??0;require __DIR__.'/../partials/email_history_tab.php';?>
    </div>

    <!-- Respond Modal -->
    <div id="respondModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="modal-box"><h3 style="margin:0 0 16px;">Record Supplier Response</h3>
            <form method="POST" action="/scars/<?=$scar['id']?>/respond">
                <div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Supplier Response <span style="color:red;">*</span></label><textarea name="supplier_response" rows="4" class="form-input" style="width:100%;" required></textarea></div>
                <div style="display:flex;gap:8px;"><button type="submit" class="btn btn-primary">Save Response</button><button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button></div>
            </form>
        </div>
    </div>

    <!-- Close Modal -->
    <div id="closeModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="modal-box"><h3 style="margin:0 0 16px;">Close SCAR</h3>
            <form method="POST" action="/scars/<?=$scar['id']?>/close">
                <div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Closure Notes <span style="color:red;">*</span></label><textarea name="closure_notes" rows="4" class="form-input" style="width:100%;" required></textarea></div>
                <div style="display:flex;gap:8px;"><button type="submit" class="btn btn-primary">Close SCAR</button><button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button></div>
            </form>
        </div>
    </div>

    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
