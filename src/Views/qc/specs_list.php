<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>QC Specs — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??$user['username']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1000px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">QC Specifications</h1>
            <div style="display:flex;gap:8px;"><a href="/qc/inspection" class="btn btn-secondary">Inspection Queue</a><a href="/qc/specs/create" class="btn btn-primary">+ New Spec</a></div>
        </div>
        <table class="data-table">
            <thead><tr><th>Item</th><th>Version</th><th>Tests</th><th>Active</th><th>Created By</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($specs)):?><tr><td colspan="7" class="empty-state">No QC specs defined.</td></tr>
            <?php else:foreach($specs as $s):?>
            <tr class="<?=!$s['is_active']?'inactive-row':''?>">
                <td><a href="/items/<?=$s['item_id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($s['item_code'])?></a> <span style="color:#6b7280;font-size:12px;"><?=htmlspecialchars($s['item_description'])?></span></td>
                <td>v<?=(int)$s['version_number']?></td>
                <td><?=(int)$s['test_count']?></td>
                <td><span class="badge <?=$s['is_active']?'badge-active':'badge-inactive'?>"><?=$s['is_active']?'Active':'Inactive'?></span></td>
                <td><?=htmlspecialchars($s['created_by_name']??'')?></td>
                <td><?=date('M j, Y',strtotime($s['created_at']))?></td>
                <td class="actions-cell">
                    <a href="/qc/specs/<?=$s['id']?>" class="btn btn-sm btn-secondary">View</a>
                    <?php if($s['is_active']):?><a href="/qc/specs/<?=$s['id']?>/edit" class="btn btn-sm btn-secondary">Edit</a><?php endif;?>
                </td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
