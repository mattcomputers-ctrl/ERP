<h1>Facilities</h1>

<!-- Add/Edit Form -->
<div class="dropdown-add-form" id="facilityForm" style="display:none;">
    <form method="POST" action="/settings/facilities" class="settings-form">
        <input type="hidden" name="action" value="add" id="fac_action">
        <input type="hidden" name="id" value="" id="fac_id">
        <div class="form-section">
            <div class="form-row">
                <div class="form-group">
                    <label for="fac_code">Code *</label>
                    <input type="text" id="fac_code" name="code" required maxlength="20">
                </div>
                <div class="form-group flex-2">
                    <label for="fac_name">Name *</label>
                    <input type="text" id="fac_name" name="name" required maxlength="100">
                </div>
            </div>
            <div class="form-group">
                <label for="fac_street">Street</label>
                <input type="text" id="fac_street" name="street" maxlength="255">
            </div>
            <div class="form-row">
                <div class="form-group flex-2">
                    <label for="fac_city">City</label>
                    <input type="text" id="fac_city" name="city" maxlength="100">
                </div>
                <div class="form-group">
                    <label for="fac_state">State</label>
                    <input type="text" id="fac_state" name="state" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="fac_zip">Zip</label>
                    <input type="text" id="fac_zip" name="zip" maxlength="20">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="fac_country">Country</label>
                    <input type="text" id="fac_country" name="country" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="fac_phone">Phone</label>
                    <input type="text" id="fac_phone" name="phone" maxlength="30">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="fac_type">Facility Type *</label>
                    <select id="fac_type" name="facility_type" required>
                        <option value="">-- Select --</option>
                        <option value="MANUFACTURING">Manufacturing</option>
                        <option value="WAREHOUSE">Warehouse</option>
                        <option value="DISTRIBUTION">Distribution</option>
                    </select>
                </div>
                <div class="form-group" style="padding-top: 22px;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_default" value="1" id="fac_default"> Default Facility
                    </label>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Facility</button>
            <button type="button" class="btn btn-secondary" onclick="closeFacForm()">Cancel</button>
        </div>
    </form>
</div>

<div class="table-toolbar">
    <button class="btn btn-primary" id="addFacBtn" onclick="openFacForm()">+ Add Facility</button>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Type</th>
            <th>City / State</th>
            <th>Default</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="7" class="empty-state">No facilities found.</td></tr>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <tr class="<?= $item['active'] ? '' : 'inactive-row' ?>">
                    <td><?= htmlspecialchars($item['code']) ?></td>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $item['facility_type'])))) ?></td>
                    <td>
                        <?= htmlspecialchars($item['city'] ?? '') ?>
                        <?= ($item['city'] && $item['state']) ? ', ' : '' ?>
                        <?= htmlspecialchars($item['state'] ?? '') ?>
                    </td>
                    <td>
                        <?php if ($item['is_default']): ?>
                            <span class="badge badge-active">Default</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $item['active'] ? 'badge-active' : 'badge-inactive' ?>">
                            <?= $item['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="actions-cell">
                        <button class="btn btn-sm btn-secondary" onclick='editFacility(<?= json_encode($item) ?>)'>Edit</button>
                        <?php if ($item['active']): ?>
                            <form method="POST" action="/settings/facilities" class="inline-toggle">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-warning">Deactivate</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="/settings/facilities" class="inline-toggle">
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
function openFacForm() {
    document.getElementById('fac_action').value = 'add';
    document.getElementById('fac_id').value = '';
    document.getElementById('fac_code').value = '';
    document.getElementById('fac_name').value = '';
    document.getElementById('fac_street').value = '';
    document.getElementById('fac_city').value = '';
    document.getElementById('fac_state').value = '';
    document.getElementById('fac_zip').value = '';
    document.getElementById('fac_country').value = '';
    document.getElementById('fac_phone').value = '';
    document.getElementById('fac_type').value = '';
    document.getElementById('fac_default').checked = false;
    document.getElementById('facilityForm').style.display = 'block';
    document.getElementById('addFacBtn').style.display = 'none';
    document.getElementById('fac_code').focus();
}

function closeFacForm() {
    document.getElementById('facilityForm').style.display = 'none';
    document.getElementById('addFacBtn').style.display = '';
}

function editFacility(item) {
    document.getElementById('fac_action').value = 'edit';
    document.getElementById('fac_id').value = item.id;
    document.getElementById('fac_code').value = item.code || '';
    document.getElementById('fac_name').value = item.name || '';
    document.getElementById('fac_street').value = item.street || '';
    document.getElementById('fac_city').value = item.city || '';
    document.getElementById('fac_state').value = item.state || '';
    document.getElementById('fac_zip').value = item.zip || '';
    document.getElementById('fac_country').value = item.country || '';
    document.getElementById('fac_phone').value = item.phone || '';
    document.getElementById('fac_type').value = item.facility_type || '';
    document.getElementById('fac_default').checked = !!parseInt(item.is_default);
    document.getElementById('facilityForm').style.display = 'block';
    document.getElementById('addFacBtn').style.display = 'none';
    document.getElementById('fac_code').focus();
}
</script>
