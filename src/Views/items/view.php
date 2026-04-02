<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item['item_code']) ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .tab-bar { display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:16px; }
        .tab-btn { padding:8px 16px; font-size:13px; font-weight:500; cursor:pointer; border:none; background:none; color:#6b7280; border-bottom:2px solid transparent; margin-bottom:-2px; }
        .tab-btn.active { color:#2563eb; border-bottom-color:#2563eb; }
        .tab-btn:hover { color:#1d4ed8; }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }
        .inline-form { display:flex; gap:8px; align-items:end; flex-wrap:wrap; padding:12px; background:#f9fafb; border-radius:6px; margin-bottom:12px; }
        .inline-form .form-group { display:flex; flex-direction:column; }
        .inline-form .form-group label { font-size:12px; margin-bottom:2px; }
        .inline-form .form-group input, .inline-form .form-group select { font-size:13px; padding:4px 8px; }
        .item-search-wrap { position:relative; }
        .item-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none; }
        .item-suggestions div { padding:6px 10px; font-size:13px; cursor:pointer; }
        .item-suggestions div:hover { background:#eff6ff; }
        .loc-input { border:1px solid transparent; background:transparent; padding:2px 4px; font-size:13px; cursor:pointer; width:100%; }
        .loc-input:focus { border-color:#2563eb; background:#fff; outline:none; cursor:text; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="header-left">
            <a href="/" class="app-logo">Precision Ink ERP</a>
        </div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?>
                <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span>
            <?php endif; ?>
        </div>
    </header>

    <div style="max-width:1000px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast">
                <?= htmlspecialchars($_SESSION['toast']['message']) ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">
                <?= htmlspecialchars($item['item_code']) ?>
                <?php
                $typeBadge = match($item['item_type']) {
                    'RAW_MATERIAL' => 'badge-info',
                    'FINISHED_GOOD' => 'badge-active',
                    'INTERMEDIATE' => 'badge-inactive',
                    'RESALE' => 'badge-warning',
                    default => '',
                };
                $typeLabel = match($item['item_type']) {
                    'RAW_MATERIAL' => 'Raw Material',
                    'FINISHED_GOOD' => 'Finished Good',
                    'INTERMEDIATE' => 'Intermediate',
                    'RESALE' => 'Resale',
                    'SERVICE' => 'Service',
                    default => $item['item_type'],
                };
                ?>
                <span class="badge <?= $typeBadge ?>" style="font-size:14px; vertical-align:middle;"><?= $typeLabel ?></span>
                <?php if (!$item['active']): ?>
                    <span class="badge badge-danger" style="font-size:14px; vertical-align:middle;">Inactive</span>
                <?php endif; ?>
            </h1>
            <div style="display:flex; gap:8px;">
                <a href="/items" class="btn btn-secondary">&larr; Back to Items</a>
                <a href="/items/<?= $item['id'] ?>/edit" class="btn btn-secondary">Edit</a>
                <form method="POST" action="/items/<?= $item['id'] ?>/clone" style="display:inline;">
                    <button type="submit" class="btn btn-secondary" onclick="return confirm('Clone this item?')">Clone</button>
                </form>
                <?php if ($item['active']): ?>
                <form method="POST" action="/items/<?= $item['id'] ?>/deactivate" style="display:inline;">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Deactivate this item?')">Deactivate</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('details')">Details</button>
            <button class="tab-btn" onclick="switchTab('packs')">Pack Extensions</button>
            <button class="tab-btn" onclick="switchTab('aliases')">Aliases</button>
            <button class="tab-btn" onclick="switchTab('substitutions')">Substitutions</button>
            <button class="tab-btn" onclick="switchTab('locations')">Locations</button>
            <button class="tab-btn" onclick="switchTab('suppliers')">Suppliers</button>
            <button class="tab-btn" onclick="switchTab('custom-fields')">Custom Fields</button>
            <button class="tab-btn" onclick="switchTab('recipes')">Recipes</button>
            <button class="tab-btn" onclick="switchTab('qc-specs')">QC Specs</button>
        </div>

        <!-- Details Tab -->
        <div id="tab-details" class="tab-panel active">
            <div class="form-section">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Item Code</strong>
                        <p style="margin:2px 0;"><?= htmlspecialchars($item['item_code']) ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Description</strong>
                        <p style="margin:2px 0;"><?= htmlspecialchars($item['description']) ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Item Type</strong>
                        <p style="margin:2px 0;"><?= $typeLabel ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">GL Group</strong>
                        <p style="margin:2px 0;"><?= str_replace('_', ' ', ucwords(strtolower($item['gl_group']), '_')) ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Unit of Measure</strong>
                        <p style="margin:2px 0;"><?= htmlspecialchars($item['uom_abbr'] ?? '') ?> — <?= htmlspecialchars($item['uom_name'] ?? '') ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Unit Cost</strong>
                        <p style="margin:2px 0;">$<?= number_format((float)$item['unit_cost'], 4) ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Sale Price</strong>
                        <p style="margin:2px 0;">$<?= number_format((float)$item['sale_price'], 4) ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Reorder Min / Max</strong>
                        <p style="margin:2px 0;">
                            <?= $item['reorder_min'] !== null ? number_format((float)$item['reorder_min'], 4) : '—' ?>
                            /
                            <?= $item['reorder_max'] !== null ? number_format((float)$item['reorder_max'], 4) : '—' ?>
                        </p>
                    </div>
                    <?php if ($item['shelf_life_days']): ?>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Shelf Life (days)</strong>
                        <p style="margin:2px 0;"><?= (int)$item['shelf_life_days'] ?></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Requires Inspection</strong>
                        <p style="margin:2px 0;"><?= $item['requires_inspection'] ? '<span style="color:#16a34a;">Yes</span>' : 'No' ?></p>
                    </div>
                    <?php if ($item['item_type'] === 'RAW_MATERIAL'): ?>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">SDS on File</strong>
                        <p style="margin:2px 0;"><?= $item['sds_on_file'] ? '<span style="color:#16a34a;">Yes</span>' : 'No' ?></p>
                    </div>
                    <?php if ($item['sds_last_received']): ?>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">SDS Last Received</strong>
                        <p style="margin:2px 0;"><?= date('M j, Y', strtotime($item['sds_last_received'])) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Status</strong>
                        <p style="margin:2px 0;">
                            <span class="badge <?= $item['active'] ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $item['active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </p>
                    </div>
                </div>
                <?php if ($item['notes']): ?>
                <div style="margin-top:12px;">
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Notes</strong>
                    <p style="margin:2px 0;"><?= nl2br(htmlspecialchars($item['notes'])) ?></p>
                </div>
                <?php endif; ?>
                <div style="margin-top:12px; font-size:11px; color:#9ca3af;">
                    Created <?= date('M j, Y g:ia', strtotime($item['created_at'])) ?>
                    | Updated <?= date('M j, Y g:ia', strtotime($item['updated_at'])) ?>
                </div>
            </div>
        </div>

        <!-- Pack Extensions Tab -->
        <div id="tab-packs" class="tab-panel">
            <p style="font-size:12px;color:#6b7280;margin-bottom:8px;">Global pack types with per-item overrides. Manage types in <a href="/settings/pack-extensions" style="color:#2563eb;">Settings &rarr; Pack Extensions</a>.</p>

            <?php if (!empty($globalPackTypes)): ?>
            <table class="data-table" style="font-size:13px;">
                <thead><tr><th>Code</th><th>Pack Type</th><th style="text-align:right;">Global Net Wt</th><th style="text-align:right;">Item Override</th><th style="text-align:right;">Effective Net</th><th style="text-align:right;">Tare Wt</th><th style="text-align:right;">Gross Wt</th><th>Active</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($globalPackTypes as $pt):
                    $effectiveNet = (float)$pt['effective_net'];
                    $gross = $effectiveNet + (float)$pt['tare_weight'];
                ?>
                <tr class="<?= !$pt['item_active'] ? 'inactive-row' : '' ?>">
                    <td style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($pt['code']) ?></td>
                    <td><?= htmlspecialchars($pt['name']) ?></td>
                    <td style="text-align:right;"><?= number_format((float)$pt['default_net_weight'], 4) ?></td>
                    <td style="text-align:right;<?= $pt['net_weight_override'] !== null ? 'font-weight:600;color:#1d4ed8;' : 'color:#9ca3af;' ?>"><?= $pt['net_weight_override'] !== null ? number_format((float)$pt['net_weight_override'], 4) : '—' ?></td>
                    <td style="text-align:right;font-weight:500;"><?= number_format($effectiveNet, 4) ?></td>
                    <td style="text-align:right;"><?= number_format((float)$pt['tare_weight'], 4) ?></td>
                    <td style="text-align:right;font-weight:500;"><?= number_format($gross, 4) ?></td>
                    <td><span class="badge <?= $pt['item_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $pt['item_active'] ? 'Active' : 'Off' ?></span></td>
                    <td class="actions-cell">
                        <form method="POST" action="/items/<?= $item['id'] ?>/pack-overrides/<?= $pt['id'] ?>" style="display:inline-flex;gap:4px;align-items:center;">
                            <input type="number" step="0.0001" name="net_weight_override" value="<?= $pt['net_weight_override'] ?? '' ?>" placeholder="<?= $pt['default_net_weight'] ?>" class="form-input" style="width:80px;font-size:12px;padding:2px 6px;">
                            <label style="font-size:11px;display:flex;align-items:center;gap:2px;"><input type="checkbox" name="active" value="1" <?= $pt['item_active'] ? 'checked' : '' ?>> On</label>
                            <button type="submit" class="btn btn-sm btn-secondary" style="font-size:11px;">Save</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="color:#9ca3af;">No global pack extension types defined. <a href="/settings/pack-extensions" style="color:#2563eb;">Create one in Settings</a>.</p>
            <?php endif; ?>

            <?php if (!empty($packExtensions)): ?>
            <h4 style="margin-top:16px;margin-bottom:8px;color:#6b7280;">Legacy Pack Extensions (from before redesign)</h4>
            <table class="data-table" style="font-size:12px;">
                <thead><tr><th>Name</th><th>Net Weight</th><th>Tare Weight</th><th>Active</th></tr></thead>
                <tbody>
                <?php foreach ($packExtensions as $pack): ?>
                <tr class="<?= !$pack['active'] ? 'inactive-row' : '' ?>"><td><?= htmlspecialchars($pack['name']) ?></td><td><?= number_format((float)$pack['net_weight'], 4) ?></td><td><?= number_format((float)$pack['tare_weight'], 4) ?></td><td><span class="badge <?= $pack['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $pack['active'] ? 'Active' : 'Inactive' ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Aliases Tab -->
        <div id="tab-aliases" class="tab-panel">
            <form method="POST" action="/items/<?= $item['id'] ?>/aliases" class="inline-form" id="aliasForm">
                <input type="hidden" name="alias_id" id="aliasId" value="">
                <div class="form-group">
                    <label>Alias Code <span style="color:red;">*</span></label>
                    <input type="text" name="alias_code" id="aliasCode" required class="form-input" style="width:150px;">
                </div>
                <div class="form-group">
                    <label>Description <span style="color:red;">*</span></label>
                    <input type="text" name="alias_description" id="aliasDesc" required class="form-input" style="width:200px;">
                </div>
                <div class="form-group">
                    <label>Customer (optional)</label>
                    <select name="customer_id" id="aliasCustomer" class="form-input" style="width:180px;">
                        <option value="">Global</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="resetAliasForm()" style="display:none;" id="aliasCancelBtn">Cancel</button>
            </form>

            <table class="data-table">
                <thead>
                    <tr><th>Alias Code</th><th>Description</th><th>Customer</th><th>Active</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($aliases)): ?>
                        <tr><td colspan="5" class="empty-state">No aliases.</td></tr>
                    <?php else: ?>
                        <?php foreach ($aliases as $alias): ?>
                        <tr class="<?= !$alias['active'] ? 'inactive-row' : '' ?>">
                            <td><?= htmlspecialchars($alias['alias_code']) ?></td>
                            <td><?= htmlspecialchars($alias['alias_description']) ?></td>
                            <td><?= $alias['customer_name'] ? htmlspecialchars($alias['customer_name']) : '<span style="color:#9ca3af;">Global</span>' ?></td>
                            <td><span class="badge <?= $alias['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $alias['active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="actions-cell">
                                <?php if ($alias['active']): ?>
                                <button class="btn btn-sm btn-secondary" onclick="editAlias(<?= htmlspecialchars(json_encode($alias)) ?>)">Edit</button>
                                <form method="POST" action="/items/<?= $item['id'] ?>/aliases/<?= $alias['id'] ?>/deactivate" style="display:inline;">
                                    <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate?')">Deactivate</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Substitutions Tab -->
        <div id="tab-substitutions" class="tab-panel">
            <form method="POST" action="/items/<?= $item['id'] ?>/substitutions" class="inline-form" id="subForm">
                <input type="hidden" name="substitution_id" id="subId" value="">
                <div class="form-group item-search-wrap">
                    <label>Substitute Item <span style="color:red;">*</span></label>
                    <input type="text" id="subItemSearch" placeholder="Search items..." class="form-input" style="width:250px;" autocomplete="off">
                    <input type="hidden" name="substitute_item_id" id="subItemId" required>
                    <div class="item-suggestions" id="subSuggestions"></div>
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <input type="number" name="priority" id="subPriority" value="1" min="1" class="form-input" style="width:80px;">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" name="notes" id="subNotes" class="form-input" style="width:200px;">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="resetSubForm()" style="display:none;" id="subCancelBtn">Cancel</button>
            </form>

            <table class="data-table">
                <thead>
                    <tr><th>Priority</th><th>Substitute Item</th><th>Notes</th><th>Active</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($substitutions)): ?>
                        <tr><td colspan="5" class="empty-state">No substitutions.</td></tr>
                    <?php else: ?>
                        <?php foreach ($substitutions as $sub): ?>
                        <tr class="<?= !$sub['active'] ? 'inactive-row' : '' ?>">
                            <td><?= (int)$sub['priority'] ?></td>
                            <td>
                                <a href="/items/<?= $sub['substitute_item_id'] ?>" style="color:#2563eb; text-decoration:none;">
                                    <?= htmlspecialchars($sub['sub_item_code']) ?>
                                </a>
                                <span style="color:#6b7280; font-size:12px;"> — <?= htmlspecialchars($sub['sub_description']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($sub['notes'] ?? '') ?></td>
                            <td><span class="badge <?= $sub['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $sub['active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="actions-cell">
                                <?php if ($sub['active']): ?>
                                <button class="btn btn-sm btn-secondary" onclick="editSub(<?= htmlspecialchars(json_encode($sub)) ?>)">Edit</button>
                                <form method="POST" action="/items/<?= $item['id'] ?>/substitutions/<?= $sub['id'] ?>/deactivate" style="display:inline;">
                                    <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate?')">Deactivate</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Locations Tab -->
        <div id="tab-locations" class="tab-panel">
            <table class="data-table">
                <thead>
                    <tr><th>Facility</th><th>Storage Location</th><th></th></tr>
                </thead>
                <tbody>
                    <?php if (empty($locations)): ?>
                        <tr><td colspan="3" class="empty-state">No active facilities.</td></tr>
                    <?php else: ?>
                        <?php foreach ($locations as $loc): ?>
                        <tr>
                            <td><?= htmlspecialchars($loc['name']) ?></td>
                            <td>
                                <form method="POST" action="/items/<?= $item['id'] ?>/location" id="locForm-<?= $loc['id'] ?>" style="display:inline;">
                                    <input type="hidden" name="facility_id" value="<?= $loc['id'] ?>">
                                    <input type="text" name="location" value="<?= htmlspecialchars($loc['location']) ?>"
                                           class="loc-input" placeholder="Enter location..."
                                           onfocus="document.getElementById('locSave-<?= $loc['id'] ?>').style.display='inline'">
                                </form>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" id="locSave-<?= $loc['id'] ?>" style="display:none;"
                                        onclick="document.getElementById('locForm-<?= $loc['id'] ?>').submit()">Save</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Suppliers (AVL) Tab -->
        <div id="tab-suppliers" class="tab-panel">
            <?php if (empty($approvedVendors)): ?>
                <p style="color:#9ca3af;">No approved vendors for this item.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr><th>Supplier Code</th><th>Company Name</th><th style="text-align:right;">Approved Cost</th><th>Lead Time</th><th>Preferred</th><th>Active</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvedVendors as $av): ?>
                        <tr class="<?= !$av['active'] ? 'inactive-row' : '' ?>">
                            <td>
                                <a href="/suppliers/<?= $av['supplier_id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;">
                                    <?= htmlspecialchars($av['supplier_code']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($av['supplier_name']) ?></td>
                            <td style="text-align:right;">$<?= number_format((float)$av['approved_unit_cost'], 4) ?></td>
                            <td><?= $av['lead_time_days'] !== null ? (int)$av['lead_time_days'] . ' days' : '<span style="color:#9ca3af;">—</span>' ?></td>
                            <td><?= $av['is_preferred'] ? '<span style="color:#f59e0b; font-size:16px;">&#9733;</span>' : '' ?></td>
                            <td><span class="badge <?= $av['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $av['active'] ? 'Active' : 'Inactive' ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Custom Fields Tab -->
        <div id="tab-custom-fields" class="tab-panel">
            <?php
            $cfRecordType = 'items';
            $cfRecordId = $item['id'] ?? 0;
            if (isset($customFieldService)) {
                require __DIR__ . '/../partials/custom_fields_view.php';
            } else {
                echo '<p style="color:#9ca3af;">No custom fields configured.</p>';
            }
            ?>
        </div>

        <!-- Recipes Tab -->
        <div id="tab-recipes" class="tab-panel">
            <div style="display:flex; justify-content:flex-end; margin-bottom:8px;">
                <a href="/items/<?= $item['id'] ?>/recipes/create" class="btn btn-primary btn-sm">+ New Version</a>
            </div>
            <?php if (empty($recipes)): ?>
                <p style="color:#9ca3af;">No recipes — <a href="/items/<?= $item['id'] ?>/recipes/create" style="color:#2563eb;">add one</a></p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr><th>Version #</th><th>Version Name</th><th>Active</th><th>Default</th><th>Yield %</th><th>Created By</th><th>Created</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipes as $r): ?>
                        <tr class="<?= !$r['is_active'] ? 'inactive-row' : '' ?>">
                            <td>
                                <a href="/items/<?= $item['id'] ?>/recipes/<?= $r['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;">v<?= (int)$r['version_number'] ?></a>
                            </td>
                            <td><?= htmlspecialchars($r['version_name'] ?? '') ?></td>
                            <td><span class="badge <?= $r['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $r['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td><?= $r['is_default'] ? '<span style="color:#f59e0b; font-size:16px;" title="Default">&#9733;</span>' : '' ?></td>
                            <td><?= $r['yield_percentage'] !== null ? number_format((float)$r['yield_percentage'], 2) . '%' : '—' ?></td>
                            <td><?= htmlspecialchars($r['created_by_name'] ?? '') ?></td>
                            <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                            <td class="actions-cell">
                                <a href="/items/<?= $item['id'] ?>/recipes/<?= $r['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                                <?php if ($r['is_active']): ?>
                                    <a href="/items/<?= $item['id'] ?>/recipes/<?= $r['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                                    <?php if (!$r['is_default']): ?>
                                    <form method="POST" action="/items/<?= $item['id'] ?>/recipes/<?= $r['id'] ?>/set-default" style="display:inline;">
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Set as default">&#9733;</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" action="/items/<?= $item['id'] ?>/recipes/<?= $r['id'] ?>/deactivate" style="display:inline;">
                                        <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate?')">Deactivate</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="/items/<?= $item['id'] ?>/recipes/<?= $r['id'] ?>/clone" style="display:inline;">
                                    <button type="submit" class="btn btn-sm btn-secondary">Clone</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- QC Specs Tab -->
        <div id="tab-qc-specs" class="tab-panel">
            <?php if (!empty($qcSpec)): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <h4 style="margin:0;">Active Spec v<?= (int)$qcSpec['version_number'] ?></h4>
                    <div style="display:flex;gap:8px;">
                        <a href="/qc/specs/<?= $qcSpec['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit Spec</a>
                        <a href="/qc/specs/create?item_id=<?= $item['id'] ?>" class="btn btn-sm btn-secondary">New Version</a>
                    </div>
                </div>
                <table class="data-table">
                    <thead><tr><th>#</th><th>Test Name</th><th>Type</th><th>Range</th><th>UOM</th><th>Required</th></tr></thead>
                    <tbody>
                    <?php $n=1; foreach ($qcSpecTests as $t): ?>
                    <tr>
                        <td style="color:#9ca3af;"><?= $n++ ?></td>
                        <td style="font-weight:500;"><?= htmlspecialchars($t['test_name']) ?></td>
                        <td><span class="badge <?= $t['test_type']==='PASS_FAIL'?'badge-info':'badge-warning' ?>"><?= $t['test_type']==='PASS_FAIL'?'Pass/Fail':'Numeric' ?></span></td>
                        <td><?= $t['test_type']==='NUMERIC_RANGE' ? number_format((float)($t['min_value']??0),4).' — '.number_format((float)($t['max_value']??0),4) : '—' ?></td>
                        <td><?= htmlspecialchars($t['uom'] ?? '') ?></td>
                        <td><?= $t['is_required'] ? '<span style="color:#16a34a;">Yes</span>' : 'No' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:#9ca3af;">No QC spec defined. <a href="/qc/specs/create?item_id=<?= $item['id'] ?>" style="color:#2563eb;">Create one</a></p>
            <?php endif; ?>
        </div>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    // Toast
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);

    // Tab switching
    function switchTab(name) {
        document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
        document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.getElementById('tab-' + name).classList.add('active');
        event.target.classList.add('active');
        history.replaceState(null, '', '#' + name);
    }

    // Activate tab from hash
    (function() {
        var hash = location.hash.replace('#', '');
        if (hash && document.getElementById('tab-' + hash)) {
            document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            document.getElementById('tab-' + hash).classList.add('active');
            var btns = document.querySelectorAll('.tab-btn');
            var tabNames = ['details','packs','aliases','substitutions','locations','suppliers','custom-fields','recipes','qc-specs'];
            var idx = tabNames.indexOf(hash);
            if (idx >= 0 && btns[idx]) btns[idx].classList.add('active');
        }
    })();

    // Pack form
    function editPack(pack) {
        document.getElementById('packId').value = pack.id;
        document.getElementById('packName').value = pack.name;
        document.getElementById('packNetWeight').value = pack.net_weight;
        document.getElementById('packTareWeight').value = pack.tare_weight;
        document.getElementById('packCancelBtn').style.display = '';
    }
    function resetPackForm() {
        document.getElementById('packId').value = '';
        document.getElementById('packName').value = '';
        document.getElementById('packNetWeight').value = '0';
        document.getElementById('packTareWeight').value = '0';
        document.getElementById('packCancelBtn').style.display = 'none';
    }

    // Alias form
    function editAlias(alias) {
        document.getElementById('aliasId').value = alias.id;
        document.getElementById('aliasCode').value = alias.alias_code;
        document.getElementById('aliasDesc').value = alias.alias_description;
        document.getElementById('aliasCustomer').value = alias.customer_id || '';
        document.getElementById('aliasCancelBtn').style.display = '';
    }
    function resetAliasForm() {
        document.getElementById('aliasId').value = '';
        document.getElementById('aliasCode').value = '';
        document.getElementById('aliasDesc').value = '';
        document.getElementById('aliasCustomer').value = '';
        document.getElementById('aliasCancelBtn').style.display = 'none';
    }

    // Substitution form with item search
    function editSub(sub) {
        document.getElementById('subId').value = sub.id;
        document.getElementById('subItemId').value = sub.substitute_item_id;
        document.getElementById('subItemSearch').value = sub.sub_item_code + ' — ' + sub.sub_description;
        document.getElementById('subPriority').value = sub.priority;
        document.getElementById('subNotes').value = sub.notes || '';
        document.getElementById('subCancelBtn').style.display = '';
    }
    function resetSubForm() {
        document.getElementById('subId').value = '';
        document.getElementById('subItemId').value = '';
        document.getElementById('subItemSearch').value = '';
        document.getElementById('subPriority').value = '1';
        document.getElementById('subNotes').value = '';
        document.getElementById('subCancelBtn').style.display = 'none';
    }

    // Item search typeahead for substitutions
    (function() {
        var searchInput = document.getElementById('subItemSearch');
        var suggestions = document.getElementById('subSuggestions');
        var hiddenInput = document.getElementById('subItemId');
        var timer;

        if (!searchInput) return;

        searchInput.addEventListener('input', function() {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 1) { suggestions.style.display = 'none'; return; }
            timer = setTimeout(function() {
                fetch('/items/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        suggestions.innerHTML = '';
                        data.forEach(function(item) {
                            var div = document.createElement('div');
                            div.textContent = item.display;
                            div.addEventListener('click', function() {
                                searchInput.value = item.display;
                                hiddenInput.value = item.id;
                                suggestions.style.display = 'none';
                            });
                            suggestions.appendChild(div);
                        });
                        suggestions.style.display = data.length ? 'block' : 'none';
                    });
            }, 300);
        });

        searchInput.addEventListener('focus', function() {
            if (suggestions.children.length > 0) suggestions.style.display = 'block';
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestions.contains(e.target)) {
                suggestions.style.display = 'none';
            }
        });
    })();

    // Lazy-load customers for alias dropdown
    (function() {
        var select = document.getElementById('aliasCustomer');
        if (!select) return;
        var loaded = false;
        select.addEventListener('focus', function() {
            if (loaded) return;
            loaded = true;
            fetch('/items/customers-list')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    data.forEach(function(c) {
                        var opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.company_name;
                        select.appendChild(opt);
                    });
                })
                .catch(function() {});
        });
    })();
    </script>
</body>
</html>
