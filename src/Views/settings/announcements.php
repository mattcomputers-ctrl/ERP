<h1>Announcements</h1>

<div style="margin-bottom:16px;">
    <button type="button" class="btn btn-primary" onclick="openAnnouncementModal()">+ New Announcement</button>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Title</th>
            <th>Priority</th>
            <th>Date Range</th>
            <th>Target</th>
            <th>Active</th>
            <th style="width:120px;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="6" style="text-align:center; color:#999;">No announcements yet.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <?php
                $priorityBadge = match($row['priority']) {
                    'URGENT'  => 'danger',
                    'WARNING' => 'warning',
                    default   => 'info',
                };
                $dateRange = htmlspecialchars($row['start_date']);
                if ($row['end_date']) {
                    $dateRange .= ' to ' . htmlspecialchars($row['end_date']);
                } else {
                    $dateRange .= ' — No end date';
                }
                $target = $row['target_all'] ? 'All Users' : implode(', ', $row['target_groups'] ?: ['(no groups)']);
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><span class="badge badge-<?= $priorityBadge ?>"><?= htmlspecialchars($row['priority']) ?></span></td>
                    <td><?= $dateRange ?></td>
                    <td><?= htmlspecialchars($target) ?></td>
                    <td>
                        <?php if ($row['active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-secondary btn-sm"
                                onclick='editAnnouncement(<?= htmlspecialchars(json_encode($row)) ?>)'>Edit</button>
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="deleteAnnouncement(<?= $row['id'] ?>)">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- Add/Edit Modal -->
<div id="ann-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:#fff; border-radius:8px; padding:24px; max-width:650px; width:95%; margin:5vh auto; box-shadow:0 4px 20px rgba(0,0,0,0.2); max-height:85vh; overflow-y:auto;">
        <h2 id="ann-modal-title" style="margin-top:0;">New Announcement</h2>
        <form id="ann-form" onsubmit="saveAnnouncement(event)">
            <input type="hidden" name="id" id="ann-id" value="0">

            <div class="form-group" style="margin-bottom:12px;">
                <label>Title <span style="color:red;">*</span></label>
                <input type="text" name="title" id="ann-title" class="form-control" required style="width:100%;">
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label>Message</label>
                <div style="border:1px solid #d1d5db; border-radius:4px; overflow:hidden;">
                    <div style="background:#f3f4f6; padding:4px 8px; border-bottom:1px solid #d1d5db; display:flex; gap:4px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.execCommand('bold')" title="Bold"><strong>B</strong></button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.execCommand('italic')" title="Italic"><em>I</em></button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.execCommand('insertUnorderedList')" title="Bullet List">&#8226; List</button>
                    </div>
                    <div id="ann-message" contenteditable="true"
                         style="min-height:100px; padding:10px; font-size:14px; line-height:1.5; outline:none;"></div>
                </div>
            </div>

            <div style="display:flex; gap:12px; margin-bottom:12px;">
                <div class="form-group" style="flex:1;">
                    <label>Start Date <span style="color:red;">*</span></label>
                    <input type="date" name="start_date" id="ann-start-date" class="form-control" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>End Date <small style="color:#999;">(optional)</small></label>
                    <input type="date" name="end_date" id="ann-end-date" class="form-control">
                </div>
            </div>

            <div style="display:flex; gap:12px; margin-bottom:12px;">
                <div class="form-group" style="flex:1;">
                    <label>Priority</label>
                    <select name="priority" id="ann-priority" class="form-control">
                        <option value="INFO">INFO</option>
                        <option value="WARNING">WARNING</option>
                        <option value="URGENT">URGENT</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1; display:flex; align-items:end; gap:16px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" name="active" id="ann-active" value="1" checked>
                        Active
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" name="target_all" id="ann-target-all" value="1" checked onchange="toggleGroupSelect()">
                        All Users
                    </label>
                </div>
            </div>

            <div class="form-group" id="ann-groups-wrap" style="margin-bottom:16px; display:none;">
                <label>Target Groups</label>
                <select name="group_ids[]" id="ann-group-ids" class="form-control" multiple size="5" style="width:100%;">
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color:#999;">Hold Ctrl/Cmd to select multiple groups.</small>
            </div>

            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeAnnouncementModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="ann-save-btn">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAnnouncementModal(data) {
    document.getElementById('ann-id').value = data ? data.id : 0;
    document.getElementById('ann-title').value = data ? data.title : '';
    document.getElementById('ann-message').innerHTML = data ? data.message : '';
    document.getElementById('ann-start-date').value = data ? data.start_date : new Date().toISOString().split('T')[0];
    document.getElementById('ann-end-date').value = data ? (data.end_date || '') : '';
    document.getElementById('ann-priority').value = data ? data.priority : 'INFO';
    document.getElementById('ann-active').checked = data ? !!parseInt(data.active) : true;
    document.getElementById('ann-target-all').checked = data ? !!parseInt(data.target_all) : true;
    document.getElementById('ann-modal-title').textContent = data ? 'Edit Announcement' : 'New Announcement';

    // Set selected groups
    var select = document.getElementById('ann-group-ids');
    for (var i = 0; i < select.options.length; i++) {
        select.options[i].selected = false;
    }
    if (data && data.target_groups && Array.isArray(data.target_groups)) {
        // target_groups contains names, we need to match by name
        // Actually load group_ids from announcement_target_groups if editing
    }
    if (data && data._group_ids) {
        data._group_ids.forEach(function(gid) {
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].value == gid) select.options[i].selected = true;
            }
        });
    }

    toggleGroupSelect();
    document.getElementById('ann-modal').style.display = 'block';
}

function editAnnouncement(row) {
    // We need to also fetch group IDs — for simplicity include them in the data
    openAnnouncementModal(row);
}

function closeAnnouncementModal() {
    document.getElementById('ann-modal').style.display = 'none';
}

function toggleGroupSelect() {
    var checked = document.getElementById('ann-target-all').checked;
    document.getElementById('ann-groups-wrap').style.display = checked ? 'none' : 'block';
}

function saveAnnouncement(e) {
    e.preventDefault();
    var btn = document.getElementById('ann-save-btn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    var form = new FormData();
    form.append('id', document.getElementById('ann-id').value);
    form.append('title', document.getElementById('ann-title').value);
    form.append('message', document.getElementById('ann-message').innerHTML);
    form.append('start_date', document.getElementById('ann-start-date').value);
    form.append('end_date', document.getElementById('ann-end-date').value);
    form.append('priority', document.getElementById('ann-priority').value);
    form.append('active', document.getElementById('ann-active').checked ? '1' : '');
    form.append('target_all', document.getElementById('ann-target-all').checked ? '1' : '');

    var select = document.getElementById('ann-group-ids');
    for (var i = 0; i < select.options.length; i++) {
        if (select.options[i].selected) {
            form.append('group_ids[]', select.options[i].value);
        }
    }

    fetch('/settings/announcements/save', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(function(err) { alert('Request failed: ' + err.message); })
        .finally(function() { btn.disabled = false; btn.textContent = 'Save'; });
}

function deleteAnnouncement(id) {
    if (!confirm('Delete this announcement?')) return;

    fetch('/settings/announcements/delete/' + id, { method: 'POST' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(function(err) { alert('Request failed: ' + err.message); });
}

// Close modal on backdrop click
document.getElementById('ann-modal').addEventListener('click', function(e) {
    if (e.target === this) closeAnnouncementModal();
});
</script>
