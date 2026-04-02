<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Import <?=htmlspecialchars($moduleName)?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1000px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Import <?=htmlspecialchars($moduleName)?></h1>
            <a href="/import" class="btn btn-secondary">&larr; Back</a>
        </div>

        <?php if(!empty($results)):?>
        <!-- Results -->
        <div style="padding:16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-bottom:16px;">
            <h3 style="margin:0 0 8px;color:#166534;">Import Complete</h3>
            <div style="display:flex;gap:16px;margin-bottom:8px;">
                <div style="font-size:14px;"><strong><?=(int)($results['imported']??0)?></strong> imported</div>
                <div style="font-size:14px;"><strong><?=(int)($results['skipped']??0)?></strong> skipped</div>
                <?php if(!empty($results['errors'])):?><div style="font-size:14px;color:#dc2626;"><strong><?=count($results['errors'])?></strong> errors</div><?php endif;?>
            </div>
            <?php if(!empty($results['errors'])):?>
            <div style="max-height:200px;overflow-y:auto;padding:8px;background:#fff;border:1px solid #e5e7eb;border-radius:4px;font-size:12px;">
                <?php foreach($results['errors'] as $err):?>
                <div style="color:#dc2626;margin-bottom:2px;"><?=htmlspecialchars(is_array($err)?$err['message']:$err)?></div>
                <?php endforeach;?>
            </div>
            <?php endif;?>
            <div style="margin-top:12px;display:flex;gap:8px;">
                <a href="/import/<?=$module?>" class="btn btn-primary">Import Another File</a>
            </div>
        </div>

        <?php elseif(!empty($preview)):?>
        <!-- Preview + Dry Run -->
        <div style="padding:16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:16px;">
            <h3 style="margin:0 0 8px;">Preview (<?=$preview['total']?> rows total, showing first 10)</h3>
            <div style="overflow-x:auto;">
                <table class="data-table" style="font-size:11px;">
                    <thead><tr><?php foreach($preview['headers'] as $h):?><th><?=htmlspecialchars($h)?></th><?php endforeach;?></tr></thead>
                    <tbody>
                    <?php foreach($preview['rows'] as $row):?>
                    <tr><?php foreach($preview['headers'] as $h):?><td><?=htmlspecialchars($row[$h]??'')?></td><?php endforeach;?></tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if(!empty($dryRun)):?>
        <div style="padding:16px;background:<?=$errorCount>0?'#fef2f2':'#f0fdf4'?>;border:1px solid <?=$errorCount>0?'#fecaca':'#bbf7d0'?>;border-radius:8px;margin-bottom:16px;">
            <h3 style="margin:0 0 8px;color:<?=$errorCount>0?'#dc2626':'#166534'?>;">Validation: <?=$errorCount?> error(s) in <?=count($dryRun)?> rows</h3>
            <div style="max-height:200px;overflow-y:auto;font-size:12px;">
                <?php foreach($dryRun as $dr):?>
                <?php if($dr['status']!=='valid'):?>
                <div style="color:<?=$dr['status']==='error'?'#dc2626':'#854d0e'?>;margin-bottom:2px;">Row <?=$dr['row']?>: <?=htmlspecialchars($dr['message'])?></div>
                <?php endif;?>
                <?php endforeach;?>
                <?php if($errorCount===0):?><div style="color:#166534;">All rows validated successfully.</div><?php endif;?>
            </div>
        </div>

        <form method="POST" action="/import/<?=$module?>">
            <input type="hidden" name="action" value="commit">
            <button type="submit" class="btn btn-primary" <?=$errorCount>0?'onclick="return confirm(\'There are validation errors. Rows with errors will be skipped. Proceed?\')"':''?>>Confirm Import (<?=$preview['total']?> rows)</button>
            <a href="/import/<?=$module?>" class="btn btn-secondary">Cancel</a>
        </form>
        <?php endif;?>

        <?php else:?>
        <!-- Upload Form -->
        <div style="display:flex;gap:8px;margin-bottom:16px;">
            <a href="/import/<?=$module?>/template" class="btn btn-secondary">Download Template</a>
            <a href="/import/<?=$module?>/sample" class="btn btn-secondary">Download Sample</a>
        </div>

        <form method="POST" action="/import/<?=$module?>" enctype="multipart/form-data" style="padding:24px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;">
            <input type="hidden" name="action" value="preview">
            <div style="margin-bottom:12px;">
                <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">CSV File <span style="color:red;">*</span></label>
                <input type="file" name="file" accept=".csv,.txt" required class="form-input" style="width:100%;">
            </div>
            <p style="font-size:12px;color:#6b7280;margin-bottom:12px;">Upload a CSV file with headers matching the template. The file will be previewed and validated before importing.</p>
            <button type="submit" class="btn btn-primary">Upload &amp; Preview</button>
        </form>
        <?php endif;?>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
