<h1>Scheduled Report Delivery</h1>

<div class="form-section" style="max-width:900px;">
    <div class="table-toolbar">
        <button type="button" class="btn btn-primary btn-sm" onclick="toggleReportForm(0)">+ Add Scheduled Report</button>
    </div>

    <!-- Add/Edit Form (hidden by default) -->
    <div id="report-form-wrap" class="dropdown-add-form" style="display:none; margin-bottom:16px;">
        <form method="POST" action="/settings/scheduled-reports/save" id="report-form">
            <input type="hidden" name="id" id="rf-id" value="0">
            <div class="inline-form" style="flex-wrap:wrap;">
                <div class="inline-field" style="min-width:200px;">
                    <label for="rf-report_name">Report Name</label>
                    <input type="text" id="rf-report_name" name="report_name" required maxlength="100">
                </div>
                <div class="inline-field" style="min-width:130px;">
                    <label for="rf-schedule_type">Frequency</label>
                    <select id="rf-schedule_type" name="schedule_type" onchange="updateScheduleDayVisibility()">
                        <option value="DAILY">Daily</option>
                        <option value="WEEKLY">Weekly</option>
                        <option value="MONTHLY">Monthly</option>
                    </select>
                </div>
                <div class="inline-field" id="rf-day-wrap" style="min-width:120px; display:none;">
                    <label for="rf-schedule_day">Day</label>
                    <select id="rf-schedule_day" name="schedule_day">
                        <option value="">—</option>
                    </select>
                </div>
                <div class="inline-field" style="min-width:100px;">
                    <label for="rf-schedule_time">Time</label>
                    <input type="text" id="rf-schedule_time" name="schedule_time" placeholder="HH:MM" value="06:00" maxlength="5" pattern="\d{2}:\d{2}">
                </div>
                <div class="inline-field" style="min-width:90px;">
                    <label for="rf-output_format">Format</label>
                    <select id="rf-output_format" name="output_format">
                        <option value="CSV">CSV</option>
                        <option value="PDF">PDF</option>
                    </select>
                </div>
                <div class="inline-field" style="min-width:250px;">
                    <label for="rf-recipients">Recipients</label>
                    <input type="text" id="rf-recipients" name="recipients" required placeholder="email1@example.com, email2@example.com">
                </div>
                <div class="inline-field" style="min-width:60px;">
                    <label>&nbsp;</label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="active" value="1" id="rf-active" checked> Active
                    </label>
                </div>
                <div class="inline-actions">
                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="hideReportForm()">Cancel</button>
                </div>
            </div>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Report</th>
                <th>Schedule</th>
                <th>Format</th>
                <th>Recipients</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
                <tr><td colspan="6" class="empty-state">No scheduled reports configured.</td></tr>
            <?php else: ?>
                <?php foreach ($reports as $r):
                    $scheduleSummary = $r['schedule_type'];
                    $days = ['','Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
                    if ($r['schedule_type'] === 'DAILY') {
                        $scheduleSummary = 'Daily at ' . htmlspecialchars($r['schedule_time']);
                    } elseif ($r['schedule_type'] === 'WEEKLY') {
                        $dayName = $days[(int)$r['schedule_day']] ?? '?';
                        $scheduleSummary = 'Weekly ' . $dayName . ' at ' . htmlspecialchars($r['schedule_time']);
                    } elseif ($r['schedule_type'] === 'MONTHLY') {
                        $ord = ((int)$r['schedule_day']);
                        $suffix = 'th';
                        if ($ord === 1 || $ord === 21 || $ord === 31) $suffix = 'st';
                        elseif ($ord === 2 || $ord === 22) $suffix = 'nd';
                        elseif ($ord === 3 || $ord === 23) $suffix = 'rd';
                        $scheduleSummary = 'Monthly ' . $ord . $suffix . ' at ' . htmlspecialchars($r['schedule_time']);
                    }
                ?>
                    <tr class="<?= $r['active'] ? '' : 'inactive-row' ?>">
                        <td><?= htmlspecialchars($r['report_name']) ?></td>
                        <td><?= $scheduleSummary ?></td>
                        <td><?= htmlspecialchars($r['output_format']) ?></td>
                        <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?= htmlspecialchars($r['recipients']) ?>
                        </td>
                        <td>
                            <span class="badge <?= $r['active'] ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $r['active'] ? 'Active' : 'Paused' ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <button type="button" class="btn btn-sm btn-secondary"
                                        onclick='editReport(<?= json_encode($r) ?>)'>Edit</button>
                                <form method="POST" action="/settings/scheduled-reports/delete/<?= $r['id'] ?>"
                                      style="display:inline;"
                                      onsubmit="return confirm('Delete this scheduled report?');">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
var weekDays = [{v:1,l:'Monday'},{v:2,l:'Tuesday'},{v:3,l:'Wednesday'},{v:4,l:'Thursday'},{v:5,l:'Friday'},{v:6,l:'Saturday'},{v:7,l:'Sunday'}];

function updateScheduleDayVisibility() {
    var type = document.getElementById('rf-schedule_type').value;
    var wrap = document.getElementById('rf-day-wrap');
    var sel = document.getElementById('rf-schedule_day');

    if (type === 'DAILY') {
        wrap.style.display = 'none';
        return;
    }

    wrap.style.display = '';
    sel.innerHTML = '<option value="">--</option>';

    if (type === 'WEEKLY') {
        weekDays.forEach(function(d) {
            sel.innerHTML += '<option value="'+d.v+'">'+d.l+'</option>';
        });
    } else if (type === 'MONTHLY') {
        for (var i = 1; i <= 31; i++) {
            sel.innerHTML += '<option value="'+i+'">'+i+'</option>';
        }
    }
}

function toggleReportForm(id) {
    var wrap = document.getElementById('report-form-wrap');
    wrap.style.display = '';
    // Reset
    document.getElementById('rf-id').value = 0;
    document.getElementById('rf-report_name').value = '';
    document.getElementById('rf-schedule_type').value = 'DAILY';
    document.getElementById('rf-schedule_time').value = '06:00';
    document.getElementById('rf-output_format').value = 'CSV';
    document.getElementById('rf-recipients').value = '';
    document.getElementById('rf-active').checked = true;
    updateScheduleDayVisibility();
    document.getElementById('rf-report_name').focus();
}

function hideReportForm() {
    document.getElementById('report-form-wrap').style.display = 'none';
}

function editReport(r) {
    document.getElementById('report-form-wrap').style.display = '';
    document.getElementById('rf-id').value = r.id;
    document.getElementById('rf-report_name').value = r.report_name;
    document.getElementById('rf-schedule_type').value = r.schedule_type;
    document.getElementById('rf-schedule_time').value = r.schedule_time;
    document.getElementById('rf-output_format').value = r.output_format;
    document.getElementById('rf-recipients').value = r.recipients;
    document.getElementById('rf-active').checked = r.active == 1;
    updateScheduleDayVisibility();
    if (r.schedule_day) {
        document.getElementById('rf-schedule_day').value = r.schedule_day;
    }
    document.getElementById('rf-report_name').focus();
}
</script>
