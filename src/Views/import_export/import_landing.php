<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Import Data — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:800px;margin:24px auto;padding:0 16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Import Data</h1>
            <a href="/export" class="btn btn-secondary">Export Data &rarr;</a>
        </div>
        <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">Upload CSV files to import records. Download templates first to ensure correct format.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
            <?php foreach($modules as $key=>$name):?>
            <a href="/import/<?=$key?>" style="display:block;padding:16px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:#1d4ed8;font-weight:500;font-size:14px;">
                <?=htmlspecialchars($name)?> <span style="float:right;color:#9ca3af;">&rarr;</span>
            </a>
            <?php endforeach;?>
        </div>
    </div>
    <script src="/assets/js/settings.js"></script>
</body>
</html>
