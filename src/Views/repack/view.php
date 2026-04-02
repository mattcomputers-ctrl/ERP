<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?=htmlspecialchars($repack['rpk_number'])?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:800px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <?php $sb=match($repack['status']??''){'OPEN'=>'badge-info','CLOSED'=>'badge-active','CANCELLED'=>'badge-inactive',default=>'badge-inactive'};?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?=htmlspecialchars($repack['rpk_number'])?> <span class="badge <?=$sb?>" style="font-size:14px;vertical-align:middle;"><?=$repack['status']?></span></h1>
            <div style="display:flex;gap:8px;">
                <a href="/repack" class="btn btn-secondary">&larr; Back</a>
                <?php if($repack['status']==='OPEN'):?>
                    <a href="/repack/<?=$repack['id']?>/edit" class="btn btn-secondary">Edit</a>
                    <form method="POST" action="/repack/<?=$repack['id']?>/close" style="display:inline;">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Close repack? Source will be consumed and destination lot created.')">Close Repack</button>
                    </form>
                    <form method="POST" action="/repack/<?=$repack['id']?>/cancel" style="display:inline;">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Cancel?')">Cancel</button>
                    </form>
                <?php endif;?>
            </div>
        </div>

        <div class="form-section" style="margin-bottom:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Item</strong><p style="margin:2px 0;"><a href="/items/<?=$repack['item_id']?>" style="color:#2563eb;"><?=htmlspecialchars($repack['item_code'])?></a> — <?=htmlspecialchars($repack['item_description'])?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Facility</strong><p style="margin:2px 0;"><?=htmlspecialchars($repack['facility_name'])?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Repack Date</strong><p style="margin:2px 0;"><?=date('M j, Y',strtotime($repack['repack_date']))?></p></div>
                <?php if($repack['status']==='OPEN'):?>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Available (Source)</strong><p style="margin:2px 0;font-weight:600;<?=$available<(float)$repack['source_quantity']?'color:#dc2626;':''?>"><?=number_format($available,4)?></p></div>
                <?php endif;?>
            </div>
        </div>

        <!-- Source → Destination -->
        <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:16px;align-items:center;margin-bottom:16px;">
            <div style="padding:16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;text-align:center;">
                <div style="font-size:11px;color:#991b1b;text-transform:uppercase;font-weight:600;">Source</div>
                <div style="font-size:16px;font-weight:700;margin-top:4px;"><?=htmlspecialchars($repack['source_pack_name']??'Bulk')?></div>
                <div style="font-size:20px;font-weight:700;color:#dc2626;"><?=number_format((float)$repack['source_quantity'],4)?></div>
            </div>
            <div style="font-size:24px;color:#6b7280;">&rarr;</div>
            <div style="padding:16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;text-align:center;">
                <div style="font-size:11px;color:#166534;text-transform:uppercase;font-weight:600;">Destination</div>
                <div style="font-size:16px;font-weight:700;margin-top:4px;"><?=htmlspecialchars($repack['dest_pack_name']??'Bulk')?></div>
                <div style="font-size:20px;font-weight:700;color:#16a34a;"><?=number_format((float)$repack['destination_quantity'],4)?></div>
            </div>
        </div>

        <?php if($repack['reason']):?>
        <div style="margin-bottom:16px;"><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Reason</strong><p style="margin:2px 0;"><?=nl2br(htmlspecialchars($repack['reason']))?></p></div>
        <?php endif;?>

        <div style="font-size:11px;color:#9ca3af;">Created <?=date('M j, Y g:ia',strtotime($repack['created_at']))?></div>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
