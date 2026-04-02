<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?=htmlspecialchars($title)?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>@media print{.no-print{display:none!important;}body{font-size:10px;margin:10px;}}</style>
</head>
<body>
    <header class="app-header no-print"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1400px;margin:24px auto;padding:0 16px;">
        <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?=htmlspecialchars($title)?></h1>
            <div style="display:flex;gap:8px;">
                <a href="/reports" class="btn btn-secondary">&larr; All Reports</a>
                <?php if(!empty($data)):?>
                <a href="/reports/<?=$category?>/<?=$report?>/csv?<?=http_build_query($filters)?>" class="btn btn-secondary">CSV</a>
                <a href="/reports/<?=$category?>/<?=$report?>/pdf?<?=http_build_query($filters)?>" class="btn btn-secondary" target="_blank">PDF</a>
                <?php endif;?>
                <button class="btn btn-secondary" onclick="window.print()">Print</button>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="/reports/<?=$category?>/<?=$report?>" class="no-print" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">From</label><input type="date" name="date_from" value="<?=htmlspecialchars($filters['date_from']??'')?>" class="form-input" style="font-size:13px;padding:4px 8px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">To</label><input type="date" name="date_to" value="<?=htmlspecialchars($filters['date_to']??'')?>" class="form-input" style="font-size:13px;padding:4px 8px;"></div>
            <?php if(in_array($report,['lot-status'])):?><div><label style="font-size:12px;display:block;margin-bottom:2px;">Status</label><select name="status" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><option value="AVAILABLE" <?=($filters['status']??'')==='AVAILABLE'?'selected':''?>>Available</option><option value="QUARANTINED" <?=($filters['status']??'')==='QUARANTINED'?'selected':''?>>Quarantined</option><option value="PENDING_INSPECTION" <?=($filters['status']??'')==='PENDING_INSPECTION'?'selected':''?>>Pending Inspection</option></select></div><?php endif;?>
            <?php if(in_array($report,['slow-moving'])):?><div><label style="font-size:12px;display:block;margin-bottom:2px;">Days Threshold</label><input type="number" name="days" value="<?=htmlspecialchars($filters['days']??'90')?>" class="form-input" style="font-size:13px;padding:4px 8px;width:80px;"></div><?php endif;?>
            <button type="submit" class="btn btn-primary" style="height:32px;">Run Report</button>
        </form>

        <!-- Report Header (print) -->
        <div style="display:none;" class="print-only">
            <p style="font-size:10px;color:#666;">Report: <?=htmlspecialchars($title)?> | Generated: <?=date('M j, Y g:ia')?> | By: <?=htmlspecialchars($_SESSION['user']['full_name']??'')?></p>
        </div>

        <!-- Results -->
        <?php if(empty($data)):?>
            <p style="color:#9ca3af;padding:24px;text-align:center;">No data for the selected filters.</p>
        <?php else:?>
            <p style="font-size:12px;color:#6b7280;margin-bottom:8px;"><?=count($data)?> row(s)</p>
            <table class="data-table" style="font-size:12px;">
                <thead><tr><?php foreach($columns as $label):?><th><?=htmlspecialchars($label)?></th><?php endforeach;?></tr></thead>
                <tbody>
                <?php foreach($data as $row):?>
                <tr>
                    <?php foreach(array_keys($columns) as $key):
                        $val = $row[$key] ?? '';
                        // Format numbers
                        if(is_numeric($val) && strpos($key,'cost')!==false || strpos($key,'value')!==false || strpos($key,'price')!==false || strpos($key,'spend')!==false || strpos($key,'revenue')!==false || strpos($key,'invoiced')!==false || strpos($key,'total')!==false || strpos($key,'amount')!==false || $key==='total_due') {
                            $val = '$' . number_format((float)$val, 2);
                        } elseif(is_numeric($val) && (strpos($key,'qty')!==false || strpos($key,'quantity')!==false || strpos($key,'on_hand')!==false || strpos($key,'available')!==false || strpos($key,'yield')!==false || strpos($key,'produced')!==false || strpos($key,'sold')!==false)) {
                            $val = number_format((float)$val, 4);
                        } elseif(is_numeric($val) && strpos($key,'pct')!==false || strpos($key,'percentage')!==false) {
                            $val = number_format((float)$val, 1) . '%';
                        }
                    ?>
                    <td><?=htmlspecialchars((string)$val)?></td>
                    <?php endforeach;?>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        <?php endif;?>
    </div>
    <script src="/assets/js/settings.js"></script>
</body>
</html>
