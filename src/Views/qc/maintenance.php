<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Maintenance — <?=htmlspecialchars($equipment['name'])?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1000px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Maintenance: <?=htmlspecialchars($equipment['name'])?></h1>
            <a href="/settings/equipment" class="btn btn-secondary">&larr; Back to Equipment</a>
        </div>

        <div class="form-section" style="margin-bottom:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Type</strong><p style="margin:2px 0;"><?=htmlspecialchars($equipment['equipment_type'])?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Facility</strong><p style="margin:2px 0;"><?=htmlspecialchars($equipment['facility_name']??'—')?></p></div>
                <div>
                    <strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Next Maintenance Due</strong>
                    <?php $overdue=$equipment['next_maintenance_due']&&$equipment['next_maintenance_due']<date('Y-m-d');?>
                    <p style="margin:2px 0;font-size:18px;font-weight:700;<?=$overdue?'color:#dc2626;':'color:#16a34a;'?>">
                        <?=$equipment['next_maintenance_due']?date('M j, Y',strtotime($equipment['next_maintenance_due'])):'Not scheduled'?>
                        <?=$overdue?' (OVERDUE)':''?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Log Form -->
        <h3 style="margin-bottom:8px;">Log Maintenance Event</h3>
        <form method="POST" action="/equipment/<?=$equipment['id']?>/maintenance" style="padding:12px;background:#f9fafb;border-radius:6px;margin-bottom:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;">
                <div><label style="font-size:12px;display:block;margin-bottom:2px;">Date <span style="color:red;">*</span></label><input type="date" name="maintenance_date" value="<?=date('Y-m-d')?>" required class="form-input" style="width:100%;"></div>
                <div><label style="font-size:12px;display:block;margin-bottom:2px;">Type <span style="color:red;">*</span></label>
                    <select name="maintenance_type" required class="form-input" style="width:100%;">
                        <option value="PREVENTIVE">Preventive</option><option value="CORRECTIVE">Corrective</option><option value="CALIBRATION">Calibration</option><option value="CLEANING">Cleaning</option>
                    </select>
                </div>
                <div><label style="font-size:12px;display:block;margin-bottom:2px;">Performed By <span style="color:red;">*</span></label><input type="text" name="performed_by" required class="form-input" style="width:100%;"></div>
                <div><label style="font-size:12px;display:block;margin-bottom:2px;">Next Due Date</label><input type="date" name="next_due_date" class="form-input" style="width:100%;"></div>
            </div>
            <div style="margin-top:8px;"><label style="font-size:12px;display:block;margin-bottom:2px;">Description <span style="color:red;">*</span></label><textarea name="description" rows="2" required class="form-input" style="width:100%;"></textarea></div>
            <button type="submit" class="btn btn-primary" style="margin-top:8px;">Log Event</button>
        </form>

        <!-- History -->
        <h3 style="margin-bottom:8px;">Maintenance History</h3>
        <table class="data-table">
            <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Performed By</th><th>Logged By</th><th>Next Due Set</th></tr></thead>
            <tbody>
            <?php if(empty($logs)):?><tr><td colspan="6" class="empty-state">No maintenance events logged.</td></tr>
            <?php else:foreach($logs as $l):?>
            <tr>
                <td><?=date('M j, Y',strtotime($l['maintenance_date']))?></td>
                <td><?php $tb=match($l['maintenance_type']){'PREVENTIVE'=>'badge-active','CORRECTIVE'=>'badge-danger','CALIBRATION'=>'badge-warning','CLEANING'=>'badge-info',default=>''};?>
                    <span class="badge <?=$tb?>"><?=$l['maintenance_type']?></span></td>
                <td><?=htmlspecialchars($l['description'])?></td>
                <td><?=htmlspecialchars($l['performed_by'])?></td>
                <td><?=htmlspecialchars($l['logged_by_name']??'')?></td>
                <td><?=$l['next_due_date']?date('M j, Y',strtotime($l['next_due_date'])):'—'?></td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
