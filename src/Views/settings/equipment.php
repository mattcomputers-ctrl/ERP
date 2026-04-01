<h1>Equipment</h1>

<!-- Add/Edit Form -->
<div class="dropdown-add-form" id="equipForm" style="display:none;">
    <form method="POST" action="/settings/equipment" class="settings-form">
        <input type="hidden" name="action" value="add" id="eq_action">
        <input type="hidden" name="id" value="" id="eq_id">
        <div class="form-section">
            <div class="form-row">
                <div class="form-group flex-2">
                    <label for="eq_name">Name *</label>
                    <input type="text" id="eq_name" name="name" required maxlength="100">
                </div>
                <div class="form-group">
                    <label for="eq_type">Equipment Type *</label>
                    <input type="text" id="eq_type" name="equipment_type" required maxlength="100">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="eq_facility">Facility</label>
                    <select id="eq_facility" name="facility_id">
                        <option value="">-- None --</option>
                        <?php foreach ($facilities as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="eq_maint">Next Maintenance Due</label>
                    <input type="date" id="eq_maint" name="next_maintenance_due">
                </div>
            </div>
            <div class="form-group">
                <label for="eq_notes">Notes</label>
                <textarea id="eq_notes" name="notes" rows="3"></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Equipment</button>
            <button type="button" class="btn btn-secondary" onclick="closeEqForm()">Cancel</button>
        </div>
    </form>
</div>

<div class="table-toolbar">
    <button class="btn btn-primary" id="addEqBtn" onclick="openEqForm()">+ Add Equipment</button>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Facility</th>
            <th>Next Maintenance</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="6" class="empty-state">No equipment found. Click "Add Equipment" to create one.</td></tr>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <?php
                    $overdue = false;
                    if ($item['next_maintenance_due']) {
                        $overdue = strtotime($item['next_maintenance_due']) < strtotime('today');
                    }
                ?>
                <tr class="<?= $item['active'] ? '' : 'inactive-row' ?>">
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= htmlspecialchars($item['equipment_type']) ?></td>
                    <td><?= htmlspecialchars($item['facility_name'] ?? '—') ?></td>
                    <td>
                        <?php if ($item['next_maintenance_due']): ?>
                            <span class="<?= $overdue ? 'text-danger' : '' ?>">
                                <?= htmlspecialchars($item['next_maintenance_due']) ?>
                                <?= $overdue ? ' (overdue)' : '' ?>
                            </span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $item['active'] ? 'badge-active' : 'badge-inactive' ?>">
                            <?= $item['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="actions-cell">
                        <button class="btn btn-sm btn-secondary" onclick='editEquipment(<?= json_encode($item) ?>)'>Edit</button>
                        <?php if ($item['active']): ?>
                            <form method="POST" action="/settings/equipment" class="inline-toggle">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-warning">Deactivate</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="/settings/equipment" class="inline-toggle">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">Activate</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<script>
function openEqForm() {
    document.getElementById('eq_action').value = 'add';
    document.getElementById('eq_id').value = '';
    document.getElementById('eq_name').value = '';
    document.getElementById('eq_type').value = '';
    document.getElementById('eq_facility').value = '';
    document.getElementById('eq_maint').value = '';
    document.getElementById('eq_notes').value = '';
    document.getElementById('equipForm').style.display = 'block';
    document.getElementById('addEqBtn').style.display = 'none';
    document.getElementById('eq_name').focus();
}

function closeEqForm() {
    document.getElementById('equipForm').style.display = 'none';
    document.getElementById('addEqBtn').style.display = '';
}

function editEquipment(item) {
    document.getElementById('eq_action').value = 'edit';
    document.getElementById('eq_id').value = item.id;
    document.getElementById('eq_name').value = item.name || '';
    document.getElementById('eq_type').value = item.equipment_type || '';
    document.getElementById('eq_facility').value = item.facility_id || '';
    document.getElementById('eq_maint').value = item.next_maintenance_due || '';
    document.getElementById('eq_notes').value = item.notes || '';
    document.getElementById('equipForm').style.display = 'block';
    document.getElementById('addEqBtn').style.display = 'none';
    document.getElementById('eq_name').focus();
}
</script>
