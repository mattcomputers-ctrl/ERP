<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Production Schedule — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.sched-day{padding:8px;min-height:50px;border-bottom:1px solid #e5e7eb;}.sched-card{padding:6px 8px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;margin-bottom:4px;font-size:12px;}.sched-card.rush{border-left:3px solid #dc2626;}.sched-card.overdue{background:#fef2f2;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1200px;margin:24px auto;padding:0 16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Production Schedule (4 Weeks)</h1>
            <div style="display:flex;gap:8px;"><a href="/mrp" class="btn btn-secondary">&larr; MRP</a><a href="/production/calendar" class="btn btn-secondary">Calendar</a></div>
        </div>

        <div style="display:grid;grid-template-columns:100px 1fr;gap:0;">
            <!-- Header -->
            <div style="padding:8px;font-weight:600;font-size:12px;background:#f9fafb;border-bottom:2px solid #d1d5db;">Date</div>
            <div style="padding:8px;font-weight:600;font-size:12px;background:#f9fafb;border-bottom:2px solid #d1d5db;">Batches</div>

            <?php $today=date('Y-m-d');foreach($dates as $date):
                $isWeekend=in_array(date('w',strtotime($date)),[0,6]);
                $isToday=($date===$today);
                $dayBatches=$byDate[$date]??[];
            ?>
            <div class="sched-day" style="<?=$isWeekend?'background:#f3f4f6;color:#9ca3af;':($isToday?'background:#eff6ff;font-weight:600;':'')?>">
                <?=date('D M j',strtotime($date))?><?=$isToday?' <span style="color:#2563eb;">(today)</span>':''?>
            </div>
            <div class="sched-day" style="<?=$isWeekend?'background:#f3f4f6;':($isToday?'background:#eff6ff;':'')?>">
                <?php if(empty($dayBatches)):?><span style="color:#d1d5db;font-size:12px;">—</span>
                <?php else:foreach($dayBatches as $b):
                    $overdue=$b['scheduled_date']<$today&&in_array($b['status'],['OPEN','IN_PROGRESS']);
                    $rush=$b['priority']==='RUSH';
                ?>
                <div class="sched-card <?=$rush?'rush':''?> <?=$overdue?'overdue':''?>">
                    <a href="/batches/<?=$b['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($b['batch_number'])?></a>
                    <span style="color:#6b7280;"> <?=htmlspecialchars($b['item_code'])?></span>
                    <span style="float:right;"><?=number_format((float)$b['target_quantity'],0)?></span>
                    <?php if($b['equipment_names']):?><div style="font-size:10px;color:#6b7280;margin-top:2px;">Equip: <?=htmlspecialchars($b['equipment_names'])?></div><?php endif;?>
                    <span class="badge <?=$b['status']==='OPEN'?'badge-info':'badge-warning'?>" style="font-size:9px;"><?=$b['status']?></span>
                    <?=$rush?'<span class="badge badge-danger" style="font-size:9px;">RUSH</span>':''?>
                    <?=$overdue?'<span class="badge badge-danger" style="font-size:9px;">OVERDUE</span>':''?>
                </div>
                <?php endforeach;endif;?>
            </div>
            <?php endforeach;?>
        </div>
    </div>
    <script src="/assets/js/settings.js"></script>
</body>
</html>
