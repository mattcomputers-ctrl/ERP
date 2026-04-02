<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Production Calendar — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;}.cal-header{padding:6px;text-align:center;font-weight:600;font-size:12px;color:#6b7280;}.cal-day{padding:8px;min-height:60px;border:1px solid #e5e7eb;border-radius:4px;font-size:12px;cursor:pointer;}.cal-day.workday{background:#fff;}.cal-day.nonwork{background:#f3f4f6;color:#9ca3af;}.cal-day.today{border-color:#2563eb;border-width:2px;}.cal-day:hover{background:#eff6ff;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:900px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Production Calendar</h1>
            <div style="display:flex;gap:8px;"><a href="/mrp" class="btn btn-secondary">&larr; MRP</a><a href="/production/schedule" class="btn btn-secondary">Schedule View</a></div>
        </div>

        <?php
        $prevMonth = $month - 1; $prevYear = $year; if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1; $nextYear = $year; if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
        $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $today = date('Y-m-d');
        ?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <a href="/production/calendar?facility_id=<?=$facilityId?>&month=<?=$prevMonth?>&year=<?=$prevYear?>" class="btn btn-secondary">&larr; Prev</a>
            <h2 style="margin:0;"><?=$monthName?></h2>
            <a href="/production/calendar?facility_id=<?=$facilityId?>&month=<?=$nextMonth?>&year=<?=$nextYear?>" class="btn btn-secondary">Next &rarr;</a>
        </div>

        <div class="cal-grid">
            <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d):?><div class="cal-header"><?=$d?></div><?php endforeach;?>

            <?php for($i=0;$i<$firstDow;$i++):?><div class="cal-day nonwork"></div><?php endfor;?>

            <?php for($day=1;$day<=$daysInMonth;$day++):
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $dow = (int)date('w', strtotime($dateStr));
                $isWeekend = ($dow === 0 || $dow === 6);
                $override = $overrides[$dateStr] ?? null;
                $isWorkday = $override ? (bool)$override['is_workday'] : !$isWeekend;
                $isToday = ($dateStr === $today);
                $batchCount = $batchCounts[$dateStr] ?? 0;
            ?>
            <div class="cal-day <?=$isWorkday?'workday':'nonwork'?> <?=$isToday?'today':''?>" onclick="toggleDay('<?=$dateStr?>',<?=$isWorkday?'1':'0'?>)">
                <div style="font-weight:600;"><?=$day?></div>
                <?php if($batchCount > 0):?><div style="margin-top:4px;"><span class="badge badge-info" style="font-size:10px;"><?=$batchCount?> batch<?=$batchCount>1?'es':''?></span></div><?php endif;?>
                <?php if($override && $override['override_reason']):?><div style="font-size:10px;color:#f59e0b;margin-top:2px;"><?=htmlspecialchars($override['override_reason'])?></div><?php endif;?>
            </div>
            <?php endfor;?>
        </div>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);
    function toggleDay(date, currentIsWorkday) {
        var reason = '';
        var newIsWorkday = currentIsWorkday ? 0 : 1;
        if (!newIsWorkday) reason = prompt('Reason for marking as non-workday (optional):') || '';
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/production/calendar/toggle';
        form.innerHTML = '<input type="hidden" name="facility_id" value="<?=$facilityId?>"><input type="hidden" name="calendar_date" value="'+date+'"><input type="hidden" name="is_workday" value="'+newIsWorkday+'"><input type="hidden" name="override_reason" value="'+reason+'">';
        document.body.appendChild(form);
        form.submit();
    }
    </script>
</body>
</html>
