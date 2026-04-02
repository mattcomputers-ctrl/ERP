<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reports — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1000px;margin:24px auto;padding:0 16px;">
        <h1 style="margin:0 0 24px;">Reports</h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
            <?php foreach($registry as $cat=>$reports):?>
            <div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                <div style="padding:10px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-weight:600;font-size:14px;text-transform:capitalize;"><?=htmlspecialchars($cat)?></div>
                <div style="padding:8px 14px;">
                    <?php foreach($reports as $key=>$name):?>
                    <a href="/reports/<?=$cat?>/<?=$key?>" style="display:block;padding:6px 0;color:#2563eb;text-decoration:none;font-size:13px;border-bottom:1px solid #f3f4f6;"><?=htmlspecialchars($name)?></a>
                    <?php endforeach;?>
                </div>
            </div>
            <?php endforeach;?>
            <!-- CRM Reports -->
            <div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                <div style="padding:10px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-weight:600;font-size:14px;">CRM</div>
                <div style="padding:8px 14px;">
                    <a href="/reports/crm/rep-activity" style="display:block;padding:6px 0;color:#2563eb;text-decoration:none;font-size:13px;border-bottom:1px solid #f3f4f6;">Rep Activity</a>
                    <a href="/reports/crm/contact-frequency" style="display:block;padding:6px 0;color:#2563eb;text-decoration:none;font-size:13px;border-bottom:1px solid #f3f4f6;">Contact Frequency</a>
                    <a href="/reports/crm/tasks" style="display:block;padding:6px 0;color:#2563eb;text-decoration:none;font-size:13px;border-bottom:1px solid #f3f4f6;">Task Report</a>
                    <a href="/reports/crm/rep-customers" style="display:block;padding:6px 0;color:#2563eb;text-decoration:none;font-size:13px;border-bottom:1px solid #f3f4f6;">Rep Customer List</a>
                    <a href="/reports/crm/next-contact" style="display:block;padding:6px 0;color:#2563eb;text-decoration:none;font-size:13px;">Next Contact</a>
                </div>
            </div>
        </div>
    </div>
    <script src="/assets/js/settings.js"></script>
</body>
</html>
