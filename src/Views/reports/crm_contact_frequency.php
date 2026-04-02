<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Contact Frequency — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>@media print{.no-print{display:none!important;}body{font-size:11px;}}</style></head>
<body>
    <header class="app-header no-print"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1000px;margin:24px auto;padding:0 16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Customer Contact Frequency</h1>
            <div class="no-print" style="display:flex;gap:8px;"><a href="/crm/dashboard" class="btn btn-secondary">&larr; CRM</a><button class="btn btn-secondary" onclick="window.print()">Print</button></div>
        </div>
        <form method="GET" action="/reports/crm/contact-frequency" class="no-print" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Days Without Contact</label><input type="number" name="days" value="<?=(int)$threshold?>" min="1" class="form-input" style="font-size:13px;padding:4px 8px;width:80px;"></div>
            <button type="submit" class="btn btn-secondary" style="height:32px;">Run</button>
        </form>
        <p style="font-size:13px;color:#6b7280;margin-bottom:8px;">Customers with no activity in the last <?=(int)$threshold?> days:</p>
        <table class="data-table">
            <thead><tr><th>Customer</th><th>Rep</th><th>Last Activity</th><th>Days Since</th><th>Next Contact</th></tr></thead>
            <tbody>
            <?php if(empty($rows)):?><tr><td colspan="5" class="empty-state">All customers contacted within threshold.</td></tr>
            <?php else:foreach($rows as $r):
                $ds=(int)($r['days_since']??999);
            ?>
            <tr style="<?=$ds>60?'background:#fef2f2;':''?>">
                <td><a href="/customers/<?=$r['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($r['customer_code'].' — '.$r['company_name'])?></a></td>
                <td><?=htmlspecialchars($r['rep_name']??'')?></td>
                <td><?=$r['last_activity']?date('M j, Y',strtotime($r['last_activity'])):'<span style="color:#dc2626;">Never</span>'?></td>
                <td style="font-weight:600;<?=$ds>60?'color:#dc2626;':($ds>$threshold?'color:#f59e0b;':'')?>;"><?=$r['last_activity']?$ds.' days':'—'?></td>
                <td><?=$r['next_contact_date']?date('M j, Y',strtotime($r['next_contact_date'])):'—'?></td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
    </div>
</body>
</html>
