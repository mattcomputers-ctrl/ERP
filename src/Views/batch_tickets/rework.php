<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rework Batch — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:700px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Rework: <?=htmlspecialchars($originalBatch['batch_number'])?></h1>
            <a href="/batches/<?=$originalBatch['id']?>" class="btn btn-secondary">&larr; Back</a>
        </div>

        <div style="padding:10px 14px;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;margin-bottom:16px;font-size:13px;color:#854d0e;">
            Original batch output will be <strong>quarantined</strong>. A new rework batch will be created.
        </div>

        <div class="form-section" style="margin-bottom:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Item</strong><p style="margin:2px 0;"><?=htmlspecialchars($originalBatch['item_code'].' — '.$originalBatch['item_description'])?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Original Yield</strong><p style="margin:2px 0;"><?=number_format((float)$originalBatch['actual_yield'],4)?></p></div>
            </div>
        </div>

        <form method="POST" action="/batches/<?=$originalBatch['id']?>/rework">
            <div style="margin-bottom:12px;">
                <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Rework Reason <span style="color:red;">*</span></label>
                <textarea name="rework_reason" rows="3" required class="form-input" style="width:100%;"></textarea>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Target Quantity (expected output from rework)</label>
                <input type="number" step="0.0001" name="target_quantity" value="<?=(float)$originalBatch['actual_yield']?>" class="form-input" style="width:200px;">
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Create rework batch and quarantine original output?')">Create Rework Batch</button>
                <a href="/batches/<?=$originalBatch['id']?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
