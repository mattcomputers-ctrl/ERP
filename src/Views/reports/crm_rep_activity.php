<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Rep Activity Report — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>@media print{.no-print{display:none!important;}body{font-size:11px;}}</style></head>
<body>
    <header class="app-header no-print"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1000px;margin:24px auto;padding:0 16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Rep Activity Report</h1>
            <div class="no-print" style="display:flex;gap:8px;"><a href="/crm/dashboard" class="btn btn-secondary">&larr; CRM</a><button class="btn btn-secondary" onclick="window.print()">Print</button></div>
        </div>
        <form method="GET" action="/reports/crm/rep-activity" class="no-print" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">From</label><input type="date" name="date_from" value="<?=htmlspecialchars($filters['date_from'])?>" class="form-input" style="font-size:13px;padding:4px 8px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">To</label><input type="date" name="date_to" value="<?=htmlspecialchars($filters['date_to'])?>" class="form-input" style="font-size:13px;padding:4px 8px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Rep</label><select name="rep_id" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><?php foreach($users as $u):?><option value="<?=$u['id']?>" <?=($filters['rep_id']??0)==$u['id']?'selected':''?>><?=htmlspecialchars($u['full_name'])?></option><?php endforeach;?></select></div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Run</button>
        </form>
        <table class="data-table">
            <thead><tr><th>Rep</th><th>Activity Type</th><th style="text-align:right;">Count</th></tr></thead>
            <tbody>
            <?php if(empty($rows)):?><tr><td colspan="3" class="empty-state">No activity data.</td></tr>
            <?php else:$lastRep='';foreach($rows as $r):?>
            <tr><td style="font-weight:<?=$r['rep_name']!==$lastRep?'600':'400'?>;"><?=$r['rep_name']!==$lastRep?htmlspecialchars($r['rep_name']):''?></td><td><span class="badge badge-info"><?=$r['activity_type']?></span></td><td style="text-align:right;font-weight:500;"><?=(int)$r['cnt']?></td></tr>
            <?php $lastRep=$r['rep_name'];endforeach;endif;?>
            </tbody>
        </table>
    </div>
</body>
</html>
