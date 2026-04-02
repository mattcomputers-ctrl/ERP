<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Export Data — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:800px;margin:24px auto;padding:0 16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Export Data</h1>
            <a href="/import" class="btn btn-secondary">&larr; Import Data</a>
        </div>
        <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">Download data as CSV files.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
            <?php foreach($modules as $key=>$name):?>
            <form method="POST" action="/export/<?=$key?>" style="display:block;">
                <button type="submit" style="display:block;width:100%;padding:16px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;text-align:left;cursor:pointer;font-size:14px;font-weight:500;color:#1d4ed8;">
                    <?=htmlspecialchars($name)?> <span style="float:right;color:#9ca3af;">&#8681;</span>
                </button>
            </form>
            <?php endforeach;?>
        </div>
    </div>
    <script src="/assets/js/settings.js"></script>
</body>
</html>
