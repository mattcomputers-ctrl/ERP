<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($supplier['supplier_code']) ?> — Precision Ink ERP</title>
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
        .stat-card { background:#f9fafb; border-radius:8px; padding:16px; text-align:center; }
        .stat-card .stat-value { font-size:24px; font-weight:700; color:#1d4ed8; }
        .stat-card .stat-label { font-size:12px; color:#6b7280; margin-top:4px; text-transform:uppercase; }
        .item-search-wrap { position:relative; }
        .item-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none; }
        .item-suggestions div { padding:6px 10px; font-size:13px; cursor:pointer; }
        .item-suggestions div:hover { background:#eff6ff; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div>
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
                <?= htmlspecialchars($supplier['supplier_code']) ?>
                <span style="font-size:16px; font-weight:400; color:#6b7280;"> — <?= htmlspecialchars($supplier['company_name']) ?></span>
                <?php if (!$supplier['active']): ?>
                    <span class="badge badge-danger" style="font-size:14px; vertical-align:middle;">Inactive</span>
                <?php endif; ?>
            </h1>
            <div style="display:flex; gap:8px;">
                <a href="/suppliers" class="btn btn-secondary">&larr; Back to Suppliers</a>
                <a href="/suppliers/<?= $supplier['id'] ?>/edit" class="btn btn-secondary">Edit</a>
                <?php if ($supplier['active']): ?>
                <form method="POST" action="/suppliers/<?= $supplier['id'] ?>/deactivate" style="display:inline;">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Deactivate this supplier?')">Deactivate</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('details')">Details</button>
            <button class="tab-btn" onclick="switchTab('contacts')">Contacts</button>
            <button class="tab-btn" onclick="switchTab('performance')">Performance</button>
            <button class="tab-btn" onclick="switchTab('avl')">AVL</button>
            <button class="tab-btn" onclick="switchTab('scars')">SCARs</button>
            <button class="tab-btn" onclick="switchTab('pricing')">Price Lists</button>
            <button class="tab-btn" onclick="switchTab('pos')">Purchase Orders</button>
            <button class="tab-btn" onclick="switchTab('custom-fields')">Custom Fields</button>
        </div>

        <!-- Details Tab -->
        <div id="tab-details" class="tab-panel active">
            <div class="form-section">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Supplier Code</strong>
                        <p style="margin:2px 0;"><?= htmlspecialchars($supplier['supplier_code']) ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Company Name</strong>
                        <p style="margin:2px 0;"><?= htmlspecialchars($supplier['company_name']) ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Address</strong>
                        <p style="margin:2px 0;">
                            <?php
                            $addrParts = array_filter([$supplier['street'], $supplier['city'], $supplier['state'], $supplier['zip'], $supplier['country']]);
                            echo $addrParts ? htmlspecialchars(implode(', ', $addrParts)) : '<span style="color:#9ca3af;">—</span>';
                            ?>
                        </p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Phone</strong>
                        <p style="margin:2px 0;"><?= $supplier['phone'] ? htmlspecialchars($supplier['phone']) : '<span style="color:#9ca3af;">—</span>' ?></p>
                    </div>
                    <?php if ($supplier['fax']): ?>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Fax</strong>
                        <p style="margin:2px 0;"><?= htmlspecialchars($supplier['fax']) ?></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Payment Terms</strong>
                        <p style="margin:2px 0;"><?= $supplier['payment_terms_name'] ? htmlspecialchars($supplier['payment_terms_name']) : '<span style="color:#9ca3af;">—</span>' ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Status</strong>
                        <p style="margin:2px 0;">
                            <span class="badge <?= $supplier['active'] ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $supplier['active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </p>
                    </div>
                </div>
                <?php if ($supplier['notes']): ?>
                <div style="margin-top:12px;">
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Notes</strong>
                    <p style="margin:2px 0;"><?= nl2br(htmlspecialchars($supplier['notes'])) ?></p>
                </div>
                <?php endif; ?>
                <div style="margin-top:12px; font-size:11px; color:#9ca3af;">
                    Created <?= date('M j, Y g:ia', strtotime($supplier['created_at'])) ?>
                    | Updated <?= date('M j, Y g:ia', strtotime($supplier['updated_at'])) ?>
                </div>
            </div>
        </div>

        <!-- Contacts Tab -->
        <div id="tab-contacts" class="tab-panel">
            <form method="POST" action="/suppliers/<?= $supplier['id'] ?>/contacts" class="inline-form" id="contactForm">
                <input type="hidden" name="contact_id" id="contactId" value="">
                <div class="form-group">
                    <label>First Name <span style="color:red;">*</span></label>
                    <input type="text" name="first_name" id="contactFirst" required class="form-input" style="width:120px;">
                </div>
                <div class="form-group">
                    <label>Last Name <span style="color:red;">*</span></label>
                    <input type="text" name="last_name" id="contactLast" required class="form-input" style="width:120px;">
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" id="contactTitle" class="form-input" style="width:120px;">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" id="contactPhone" class="form-input" style="width:120px;">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="contactEmail" class="form-input" style="width:160px;">
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="contact_type" id="contactType" class="form-input">
                        <option value="GENERAL">General</option>
                        <option value="SALES">Sales</option>
                        <option value="ACCOUNTING">Accounting</option>
                        <option value="QUALITY">Quality</option>
                        <option value="LOGISTICS">Logistics</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <label style="display:flex; align-items:center; gap:4px; font-size:12px; cursor:pointer;">
                        <input type="checkbox" name="is_primary" id="contactPrimary" value="1" class="form-checkbox"> Primary
                    </label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="resetContactForm()" style="display:none;" id="contactCancelBtn">Cancel</button>
            </form>

            <table class="data-table">
                <thead>
                    <tr><th>Name</th><th>Title</th><th>Type</th><th>Phone</th><th>Email</th><th>Primary</th><th>Active</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($contacts)): ?>
                        <tr><td colspan="8" class="empty-state">No contacts.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contacts as $c): ?>
                        <tr class="<?= !$c['active'] ? 'inactive-row' : '' ?>">
                            <td><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></td>
                            <td><?= $c['title'] ? htmlspecialchars($c['title']) : '<span style="color:#9ca3af;">—</span>' ?></td>
                            <td>
                                <?php
                                $typeBadge = match($c['contact_type']) {
                                    'SALES' => 'badge-info',
                                    'ACCOUNTING' => 'badge-warning',
                                    'QUALITY' => 'badge-active',
                                    'LOGISTICS' => '',
                                    default => 'badge-inactive',
                                };
                                ?>
                                <span class="badge <?= $typeBadge ?>"><?= $c['contact_type'] ?></span>
                            </td>
                            <td><?= $c['phone'] ? htmlspecialchars($c['phone']) : '' ?></td>
                            <td><?= $c['email'] ? '<a href="mailto:' . htmlspecialchars($c['email']) . '" style="color:#2563eb;">' . htmlspecialchars($c['email']) . '</a>' : '' ?></td>
                            <td><?= $c['is_primary'] ? '<span style="color:#f59e0b; font-size:16px;">&#9733;</span>' : '' ?></td>
                            <td><span class="badge <?= $c['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $c['active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="actions-cell">
                                <?php if ($c['active']): ?>
                                <button class="btn btn-sm btn-secondary" onclick="editContact(<?= htmlspecialchars(json_encode($c)) ?>)">Edit</button>
                                <form method="POST" action="/suppliers/<?= $supplier['id'] ?>/contacts/<?= $c['id'] ?>/deactivate" style="display:inline;">
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

        <!-- Performance Tab -->
        <div id="tab-performance" class="tab-panel">
            <?php if (!$performance['has_data']): ?>
                <p style="color:#9ca3af; padding:24px 0; text-align:center;">Insufficient data — no receipts recorded yet.</p>
            <?php else: ?>
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px;">
                    <div class="stat-card">
                        <div class="stat-value"><?= $performance['on_time_rate'] !== null ? $performance['on_time_rate'] . '%' : '—' ?></div>
                        <div class="stat-label">On-Time Delivery Rate</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $performance['avg_actual_lead_days'] !== null ? $performance['avg_actual_lead_days'] : '—' ?></div>
                        <div class="stat-label">Avg Actual Lead Time (days)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $performance['avg_quoted_lead_days'] !== null ? $performance['avg_quoted_lead_days'] : '—' ?></div>
                        <div class="stat-label">Avg Quoted Lead Time (days)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" style="<?= $performance['lead_time_variance'] !== null && $performance['lead_time_variance'] > 0 ? 'color:#dc2626;' : 'color:#16a34a;' ?>">
                            <?= $performance['lead_time_variance'] !== null ? ($performance['lead_time_variance'] > 0 ? '+' : '') . $performance['lead_time_variance'] : '—' ?>
                        </div>
                        <div class="stat-label">Lead Time Variance (days)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">$<?= number_format($performance['spend_ytd'], 2) ?></div>
                        <div class="stat-label">Total Spend YTD</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">$<?= number_format($performance['spend_all_time'], 2) ?></div>
                        <div class="stat-label">Total Spend All-Time</div>
                    </div>
                </div>
                <p style="font-size:12px; color:#9ca3af; margin-top:12px;">
                    Based on <?= $performance['total_receipts'] ?> receipt(s).
                    <a href="/reports/supplier-performance?supplier_id=<?= $supplier['id'] ?>" style="color:#2563eb;">View Full Performance Report</a>
                </p>
            <?php endif; ?>
        </div>

        <!-- AVL Tab -->
        <div id="tab-avl" class="tab-panel">
            <form method="POST" action="/suppliers/<?= $supplier['id'] ?>/avl" class="inline-form" id="avlForm">
                <input type="hidden" name="avl_id" id="avlId" value="">
                <div class="form-group item-search-wrap">
                    <label>Item <span style="color:red;">*</span></label>
                    <input type="text" id="avlItemSearch" placeholder="Search items..." class="form-input" style="width:220px;" autocomplete="off">
                    <input type="hidden" name="item_id" id="avlItemId" required>
                    <div class="item-suggestions" id="avlSuggestions"></div>
                </div>
                <div class="form-group">
                    <label>Approved Unit Cost</label>
                    <input type="number" step="0.0001" name="approved_unit_cost" id="avlCost" value="0" class="form-input" style="width:120px;">
                </div>
                <div class="form-group">
                    <label>Lead Time (days)</label>
                    <input type="number" name="lead_time_days" id="avlLeadTime" class="form-input" style="width:100px;">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <label style="display:flex; align-items:center; gap:4px; font-size:12px; cursor:pointer;">
                        <input type="checkbox" name="is_preferred" id="avlPreferred" value="1" class="form-checkbox"> Preferred
                    </label>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" name="notes" id="avlNotes" class="form-input" style="width:150px;">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="resetAvlForm()" style="display:none;" id="avlCancelBtn">Cancel</button>
            </form>

            <table class="data-table">
                <thead>
                    <tr><th>Item Code</th><th>Description</th><th style="text-align:right;">Approved Cost</th><th>Lead Time</th><th>Preferred</th><th>Active</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($avlItems)): ?>
                        <tr><td colspan="7" class="empty-state">No items on approved vendor list.</td></tr>
                    <?php else: ?>
                        <?php foreach ($avlItems as $avl): ?>
                        <tr class="<?= !$avl['active'] ? 'inactive-row' : '' ?>">
                            <td>
                                <a href="/items/<?= $avl['item_id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;">
                                    <?= htmlspecialchars($avl['item_code']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($avl['item_description']) ?></td>
                            <td style="text-align:right;">$<?= number_format((float)$avl['approved_unit_cost'], 4) ?></td>
                            <td><?= $avl['lead_time_days'] !== null ? (int)$avl['lead_time_days'] . ' days' : '<span style="color:#9ca3af;">—</span>' ?></td>
                            <td><?= $avl['is_preferred'] ? '<span style="color:#f59e0b; font-size:16px;">&#9733;</span>' : '' ?></td>
                            <td><span class="badge <?= $avl['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $avl['active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="actions-cell">
                                <?php if ($avl['active']): ?>
                                <button class="btn btn-sm btn-secondary" onclick="editAvl(<?= htmlspecialchars(json_encode($avl)) ?>)">Edit</button>
                                <form method="POST" action="/suppliers/<?= $supplier['id'] ?>/avl/<?= $avl['id'] ?>/deactivate" style="display:inline;">
                                    <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Remove?')">Remove</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- SCARs Tab -->
        <div id="tab-scars" class="tab-panel">
            <?php if (empty($scars)): ?>
                <p style="color:#9ca3af; padding:24px 0; text-align:center;">No SCARs on record.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr><th>SCAR #</th><th>Status</th><th>Created</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scars as $scar): ?>
                        <tr>
                            <td><a href="/qc/scars/<?= $scar['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($scar['scar_number']) ?></a></td>
                            <td><span class="badge"><?= htmlspecialchars($scar['status']) ?></span></td>
                            <td><?= date('M j, Y', strtotime($scar['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Price Lists Tab -->
        <div id="tab-pricing" class="tab-panel">
            <h3 style="font-size:14px; margin:0 0 8px;">Assigned Price Lists</h3>
            <form method="POST" action="/suppliers/<?= $supplier['id'] ?>/price-lists" class="inline-form" style="margin-bottom:8px;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <div class="fg">
                    <label>Price List</label>
                    <select name="price_list_id" class="form-input" style="width:250px;" required>
                        <option value="">— Select —</option>
                        <?php foreach ($availablePriceLists ?? [] as $apl): ?>
                        <option value="<?= $apl['id'] ?>"><?= htmlspecialchars($apl['name']) ?> (priority <?= (int)$apl['default_priority'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg"><label>Priority Override</label><input type="number" name="priority_override" placeholder="Default" class="form-input" style="width:80px;"></div>
                <button type="submit" class="btn btn-primary btn-sm">Assign</button>
            </form>
            <table class="data-table">
                <thead><tr><th>Price List</th><th>Priority</th><th>Effective</th><th>Expires</th><th>Active</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (empty($priceListAssignments ?? [])): ?>
                    <tr><td colspan="6" class="empty-state">No price lists assigned.</td></tr>
                <?php else: foreach ($priceListAssignments as $pla): ?>
                <tr class="<?= !$pla['active'] ? 'inactive-row' : '' ?>">
                    <td><a href="/price-lists/<?= $pla['price_list_id'] ?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?= htmlspecialchars($pla['list_name']) ?></a></td>
                    <td>
                        <?php $effP = $pla['priority_override'] ?? $pla['default_priority']; ?>
                        <?= (int)$effP ?>
                        <?php if ($pla['priority_override'] !== null): ?><small style="color:#6b7280;">(override)</small><?php endif; ?>
                    </td>
                    <td><?= date('M j, Y', strtotime($pla['effective_date'])) ?></td>
                    <td><?= $pla['expiration_date'] ? date('M j, Y', strtotime($pla['expiration_date'])) : '—' ?></td>
                    <td><span class="badge <?= $pla['active']?'badge-active':'badge-inactive' ?>"><?= $pla['active']?'Active':'Inactive' ?></span></td>
                    <td class="actions-cell">
                        <?php if ($pla['active']): ?>
                        <form method="POST" action="/suppliers/<?= $supplier['id'] ?>/price-lists/<?= $pla['id'] ?>" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <input type="number" name="priority_override" value="<?= $pla['priority_override'] ?? '' ?>" placeholder="Default" style="width:60px;padding:2px 4px;font-size:12px;border:1px solid #d1d5db;border-radius:3px;">
                            <button type="submit" class="btn btn-sm btn-secondary">Update</button>
                        </form>
                        <form method="POST" action="/suppliers/<?= $supplier['id'] ?>/price-lists/<?= $pla['id'] ?>/remove" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Remove?')">Remove</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Purchase Orders Tab -->
        <div id="tab-pos" class="tab-panel">
            <?php if (empty($recentPOs)): ?>
                <p style="color:#9ca3af; padding:24px 0; text-align:center;">No purchase orders on record.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr><th>PO #</th><th>Order Date</th><th>Expected Delivery</th><th>Facility</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPOs as $po): ?>
                        <tr>
                            <td><a href="/purchase-orders/<?= $po['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($po['po_number']) ?></a></td>
                            <td><?= date('M j, Y', strtotime($po['order_date'])) ?></td>
                            <td><?= $po['expected_delivery_date'] ? date('M j, Y', strtotime($po['expected_delivery_date'])) : '—' ?></td>
                            <td><?= htmlspecialchars($po['facility_name'] ?? '') ?></td>
                            <td>
                                <?php
                                $poBadge = match($po['status']) {
                                    'DRAFT' => 'badge-inactive',
                                    'SENT' => 'badge-info',
                                    'PARTIAL' => 'badge-warning',
                                    'RECEIVED' => 'badge-active',
                                    'CANCELLED' => 'badge-danger',
                                    default => '',
                                };
                                ?>
                                <span class="badge <?= $poBadge ?>"><?= $po['status'] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="font-size:12px; color:#9ca3af; margin-top:8px;">
                    Showing 10 most recent. <a href="/purchase-orders?supplier_id=<?= $supplier['id'] ?>" style="color:#2563eb;">View all purchase orders</a>
                </p>
            <?php endif; ?>
        </div>

        <!-- Custom Fields Tab -->
        <div id="tab-custom-fields" class="tab-panel">
            <?php
            $cfRecordType = 'suppliers';
            $cfRecordId = $supplier['id'] ?? 0;
            if (isset($customFieldService)) {
                require __DIR__ . '/../partials/custom_fields_view.php';
            } else {
                echo '<p style="color:#9ca3af;">No custom fields configured.</p>';
            }
            ?>
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
            var tabNames = ['details','contacts','performance','avl','scars','pos','custom-fields'];
            var idx = tabNames.indexOf(hash);
            if (idx >= 0 && btns[idx]) btns[idx].classList.add('active');
        }
    })();

    // Contact form
    function editContact(c) {
        document.getElementById('contactId').value = c.id;
        document.getElementById('contactFirst').value = c.first_name;
        document.getElementById('contactLast').value = c.last_name;
        document.getElementById('contactTitle').value = c.title || '';
        document.getElementById('contactPhone').value = c.phone || '';
        document.getElementById('contactEmail').value = c.email || '';
        document.getElementById('contactType').value = c.contact_type;
        document.getElementById('contactPrimary').checked = !!c.is_primary;
        document.getElementById('contactCancelBtn').style.display = '';
    }
    function resetContactForm() {
        document.getElementById('contactId').value = '';
        document.getElementById('contactFirst').value = '';
        document.getElementById('contactLast').value = '';
        document.getElementById('contactTitle').value = '';
        document.getElementById('contactPhone').value = '';
        document.getElementById('contactEmail').value = '';
        document.getElementById('contactType').value = 'GENERAL';
        document.getElementById('contactPrimary').checked = false;
        document.getElementById('contactCancelBtn').style.display = 'none';
    }

    // AVL form
    function editAvl(avl) {
        document.getElementById('avlId').value = avl.id;
        document.getElementById('avlItemId').value = avl.item_id;
        document.getElementById('avlItemSearch').value = avl.item_code + ' — ' + avl.item_description;
        document.getElementById('avlCost').value = avl.approved_unit_cost;
        document.getElementById('avlLeadTime').value = avl.lead_time_days || '';
        document.getElementById('avlPreferred').checked = !!avl.is_preferred;
        document.getElementById('avlNotes').value = avl.notes || '';
        document.getElementById('avlCancelBtn').style.display = '';
    }
    function resetAvlForm() {
        document.getElementById('avlId').value = '';
        document.getElementById('avlItemId').value = '';
        document.getElementById('avlItemSearch').value = '';
        document.getElementById('avlCost').value = '0';
        document.getElementById('avlLeadTime').value = '';
        document.getElementById('avlPreferred').checked = false;
        document.getElementById('avlNotes').value = '';
        document.getElementById('avlCancelBtn').style.display = 'none';
    }

    // Item search typeahead for AVL
    (function() {
        var input = document.getElementById('avlItemSearch');
        var suggestions = document.getElementById('avlSuggestions');
        var hidden = document.getElementById('avlItemId');
        var timer;

        if (!input) return;

        input.addEventListener('input', function() {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 1) { suggestions.style.display = 'none'; return; }
            timer = setTimeout(function() {
                fetch('/items/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        suggestions.innerHTML = '';
                        data.forEach(function(item) {
                            var d = document.createElement('div');
                            d.textContent = item.display;
                            d.addEventListener('click', function() {
                                input.value = item.display;
                                hidden.value = item.id;
                                suggestions.style.display = 'none';
                            });
                            suggestions.appendChild(d);
                        });
                        suggestions.style.display = data.length ? 'block' : 'none';
                    });
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                suggestions.style.display = 'none';
            }
        });
    })();
    </script>
</body>
</html>
