<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>QC Spec v<?=(int)$spec['version_number']?> — <?=htmlspecialchars($spec['item_code'])?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:900px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">
                QC Spec v<?=(int)$spec['version_number']?>
                <span style="font-size:14px;color:#6b7280;"> — <a href="/items/<?=$spec['item_id']?>" style="color:#2563eb;text-decoration:none;"><?=htmlspecialchars($spec['item_code'])?></a> <?=htmlspecialchars($spec['item_description'])?></span>
                <span class="badge <?=$spec['is_active']?'badge-active':'badge-inactive'?>" style="font-size:12px;vertical-align:middle;"><?=$spec['is_active']?'Active':'Inactive'?></span>
            </h1>
            <div style="display:flex;gap:8px;">
                <a href="/qc/specs" class="btn btn-secondary">&larr; Back</a>
                <?php if($spec['is_active']):?>
                    <a href="/qc/specs/<?=$spec['id']?>/edit" class="btn btn-secondary">Edit (New Version)</a>
                    <form method="POST" action="/qc/specs/<?=$spec['id']?>/deactivate" style="display:inline;"><button type="submit" class="btn btn-warning" onclick="return confirm('Deactivate?')">Deactivate</button></form>
                <?php endif;?>
            </div>
        </div>
        <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">Created by <?=htmlspecialchars($spec['created_by_name']??'')?> on <?=date('M j, Y',strtotime($spec['created_at']))?></p>

        <h3 style="margin-bottom:8px;">Tests</h3>
        <table class="data-table">
            <thead><tr><th>#</th><th>Test Name</th><th>Type</th><th>Range</th><th>UOM</th><th>Required</th></tr></thead>
            <tbody>
            <?php $n=1;foreach($tests as $t):?>
            <tr>
                <td style="color:#9ca3af;"><?=$n++?></td>
                <td style="font-weight:500;"><?=htmlspecialchars($t['test_name'])?></td>
                <td><span class="badge <?=$t['test_type']==='PASS_FAIL'?'badge-info':'badge-warning'?>"><?=$t['test_type']==='PASS_FAIL'?'Pass/Fail':'Numeric'?></span></td>
                <td><?=$t['test_type']==='NUMERIC_RANGE'?number_format((float)($t['min_value']??0),4).' — '.number_format((float)($t['max_value']??0),4):'—'?></td>
                <td><?=htmlspecialchars($t['uom']??'')?></td>
                <td><?=$t['is_required']?'<span style="color:#16a34a;">Yes</span>':'No'?></td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>

        <!-- Version History -->
        <h3 style="margin-top:24px;margin-bottom:8px;">Version History</h3>
        <table class="data-table">
            <thead><tr><th>Version</th><th>Active</th><th>Created By</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach($history as $h):?>
            <tr class="<?=!$h['is_active']?'inactive-row':''?> <?=$h['id']==$spec['id']?'':'';?>">
                <td style="font-weight:<?=$h['id']==$spec['id']?'700':'400'?>;">v<?=(int)$h['version_number']?><?=$h['id']==$spec['id']?' (viewing)':''?></td>
                <td><span class="badge <?=$h['is_active']?'badge-active':'badge-inactive'?>"><?=$h['is_active']?'Active':'Inactive'?></span></td>
                <td><?=htmlspecialchars($h['created_by_name']??'')?></td>
                <td><?=date('M j, Y',strtotime($h['created_at']))?></td>
                <td><?php if($h['id']!=$spec['id']):?><a href="/qc/specs/<?=$h['id']?>" class="btn btn-sm btn-secondary">View</a><?php endif;?></td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
