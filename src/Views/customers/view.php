<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($customer['customer_code']) ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .tab-bar { display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:16px; overflow-x:auto; }
        .tab-btn { padding:8px 14px; font-size:13px; font-weight:500; cursor:pointer; border:none; background:none; color:#6b7280; border-bottom:2px solid transparent; margin-bottom:-2px; white-space:nowrap; }
        .tab-btn.active { color:#2563eb; border-bottom-color:#2563eb; }
        .tab-btn:hover { color:#1d4ed8; }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }
        .inline-form { display:flex; gap:8px; align-items:end; flex-wrap:wrap; padding:12px; background:#f9fafb; border-radius:6px; margin-bottom:12px; }
        .inline-form .fg { display:flex; flex-direction:column; }
        .inline-form .fg label { font-size:12px; margin-bottom:2px; }
        .inline-form .fg input, .inline-form .fg select, .inline-form .fg textarea { font-size:13px; padding:4px 8px; }
        .overdue-row { background:#fef2f2 !important; }
        .item-search-wrap { position:relative; }
        .item-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none; box-shadow:0 4px 6px rgba(0,0,0,.1); }
        .item-suggestions div { padding:6px 10px; font-size:13px; cursor:pointer; }
        .item-suggestions div:hover { background:#eff6ff; }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
    <header class="app-header">
        <div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?><span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span><?php endif; ?>
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
                <?= htmlspecialchars($customer['customer_code']) ?>
                <span style="font-size:16px; font-weight:400; color:#6b7280;"> — <?= htmlspecialchars($customer['company_name']) ?></span>
                <?php if ($customer['account_hold']): ?>
                    <span class="badge badge-danger" style="font-size:13px; vertical-align:middle;">ACCOUNT HOLD</span>
                <?php endif; ?>
                <?php if (!$customer['active']): ?>
                    <span class="badge badge-inactive" style="font-size:13px; vertical-align:middle;">Inactive</span>
                <?php endif; ?>
            </h1>
            <div style="display:flex; gap:8px;">
                <a href="/customers" class="btn btn-secondary">&larr; Back</a>
                <a href="/customers/<?= $customer['id'] ?>/edit" class="btn btn-secondary">Edit</a>
                <?php if ($customer['active']): ?>
                <form method="POST" action="/customers/<?= $customer['id'] ?>/deactivate" style="display:inline;">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Deactivate this customer?')">Deactivate</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('details')">Details</button>
            <button class="tab-btn" onclick="switchTab('contacts')">Contacts</button>
            <button class="tab-btn" onclick="switchTab('ship-to')">Ship-To</button>
            <button class="tab-btn" onclick="switchTab('crm')">CRM Profile</button>
            <button class="tab-btn" onclick="switchTab('activities')">Activity Log</button>
            <button class="tab-btn" onclick="switchTab('tasks')">Tasks</button>
            <button class="tab-btn" onclick="switchTab('pricing')">Pricing</button>
            <button class="tab-btn" onclick="switchTab('consignment')">Consignment</button>
            <button class="tab-btn" onclick="switchTab('custom-fields')">Custom Fields</button>
            <button class="tab-btn" onclick="switchTab('orders')">Orders</button>
            <button class="tab-btn" onclick="switchTab('invoices')">Invoices</button>
        </div>

        <!-- ═══ Details Tab ═══ -->
        <div id="tab-details" class="tab-panel active">
            <?php if ($customer['account_hold']): ?>
            <div style="padding:10px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; margin-bottom:16px; color:#991b1b; font-size:13px;">
                <strong>Account Hold:</strong> <?= htmlspecialchars($customer['account_hold_reason'] ?? 'No reason provided') ?>
            </div>
            <?php endif; ?>

            <!-- Credit Exposure Card -->
            <div style="padding:14px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; margin-bottom:16px;">
                <h4 style="margin:0 0 8px; font-size:13px; color:#0369a1; text-transform:uppercase;">Credit Exposure</h4>
                <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; text-align:center;">
                    <div>
                        <div style="font-size:11px; color:#6b7280;">Credit Limit</div>
                        <div style="font-size:16px; font-weight:600;"><?= $credit['credit_limit'] > 0 ? '$' . number_format($credit['credit_limit'], 2) : 'No Limit' ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:#6b7280;">AR Balance</div>
                        <div style="font-size:16px; font-weight:600;">$<?= number_format($credit['ar_balance'], 2) ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:#6b7280;">Open SO Value</div>
                        <div style="font-size:16px; font-weight:600;">$<?= number_format($credit['open_so_value'], 2) ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:#6b7280;">Available Credit</div>
                        <div style="font-size:16px; font-weight:600; <?= $credit['is_over_limit'] ? 'color:#dc2626;' : 'color:#16a34a;' ?>">
                            <?= $credit['available_credit'] !== null ? '$' . number_format($credit['available_credit'], 2) : '—' ?>
                        </div>
                    </div>
                </div>
                <?php if ($credit['utilization_pct'] !== null): ?>
                <div style="margin-top:8px; background:#e0e7ff; border-radius:4px; height:8px; overflow:hidden;">
                    <div style="height:100%; width:<?= min($credit['utilization_pct'], 100) ?>%; background:<?= $credit['is_over_limit'] ? '#dc2626' : '#2563eb' ?>; border-radius:4px;"></div>
                </div>
                <div style="font-size:11px; color:#6b7280; margin-top:2px; text-align:right;"><?= $credit['utilization_pct'] ?>% utilized</div>
                <?php endif; ?>
            </div>

            <div class="form-section">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Customer Code</strong>
                        <p style="margin:2px 0;"><?= htmlspecialchars($customer['customer_code']) ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Company Name</strong>
                        <p style="margin:2px 0;"><?= htmlspecialchars($customer['company_name']) ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Billing Address</strong>
                        <p style="margin:2px 0;">
                            <?php
                            $parts = array_filter([$customer['billing_street'], $customer['billing_city'], $customer['billing_state'], $customer['billing_zip'], $customer['billing_country']]);
                            echo $parts ? htmlspecialchars(implode(', ', $parts)) : '<span style="color:#9ca3af;">—</span>';
                            ?>
                        </p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Phone</strong>
                        <p style="margin:2px 0;"><?= $customer['phone'] ? htmlspecialchars($customer['phone']) : '<span style="color:#9ca3af;">—</span>' ?></p>
                    </div>
                    <?php if ($customer['fax']): ?>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Fax</strong>
                        <p style="margin:2px 0;"><?= htmlspecialchars($customer['fax']) ?></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Payment Terms</strong>
                        <p style="margin:2px 0;"><?= $customer['payment_terms_name'] ? htmlspecialchars($customer['payment_terms_name']) : '<span style="color:#9ca3af;">—</span>' ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Sales Rep</strong>
                        <p style="margin:2px 0;"><?= $customer['sales_rep_name'] ? htmlspecialchars($customer['sales_rep_name']) : '<span style="color:#9ca3af;">—</span>' ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Default Ship Via</strong>
                        <p style="margin:2px 0;"><?= $customer['ship_via_name'] ? htmlspecialchars($customer['ship_via_name']) : '<span style="color:#9ca3af;">—</span>' ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Credit Limit</strong>
                        <p style="margin:2px 0;"><?= $customer['credit_limit'] > 0 ? '$' . number_format((float)$customer['credit_limit'], 2) : '<span style="color:#9ca3af;">No Limit</span>' ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">AR Balance</strong>
                        <p style="margin:2px 0; <?= $credit['is_over_limit'] ? 'color:#dc2626; font-weight:600;' : '' ?>">$<?= number_format((float)$customer['ar_balance'], 2) ?></p>
                    </div>
                    <?php if ($customer['lead_time_days']): ?>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Lead Time</strong>
                        <p style="margin:2px 0;"><?= (int)$customer['lead_time_days'] ?> days</p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Tax Exempt</strong>
                        <p style="margin:2px 0;"><?= $customer['tax_exempt'] ? '<span style="color:#16a34a;">Yes</span>' : 'No' ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Status</strong>
                        <p style="margin:2px 0;"><span class="badge <?= $customer['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $customer['active'] ? 'Active' : 'Inactive' ?></span></p>
                    </div>
                </div>
                <?php if ($customer['default_internal_notes']): ?>
                <div style="margin-top:12px;">
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Default Internal Notes</strong>
                    <p style="margin:2px 0;"><?= nl2br(htmlspecialchars($customer['default_internal_notes'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($customer['notes']): ?>
                <div style="margin-top:12px;">
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Notes</strong>
                    <p style="margin:2px 0;"><?= nl2br(htmlspecialchars($customer['notes'])) ?></p>
                </div>
                <?php endif; ?>
                <div style="margin-top:12px; font-size:11px; color:#9ca3af;">
                    Created <?= date('M j, Y g:ia', strtotime($customer['created_at'])) ?>
                    | Updated <?= date('M j, Y g:ia', strtotime($customer['updated_at'])) ?>
                </div>
            </div>
        </div>

        <!-- ═══ Contacts Tab ═══ -->
        <div id="tab-contacts" class="tab-panel" x-data="contactManager()">
            <div style="margin-bottom:12px; text-align:right;">
                <button class="btn btn-primary btn-sm" @click="showForm = !showForm; resetForm()">+ Add Contact</button>
            </div>
            <div x-show="showForm" x-cloak class="inline-form" style="flex-direction:column; align-items:stretch;">
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:end;">
                    <input type="hidden" x-model="form.contact_id">
                    <div class="fg"><label>First Name *</label><input type="text" x-model="form.first_name" class="form-input" style="width:120px;" required></div>
                    <div class="fg"><label>Last Name *</label><input type="text" x-model="form.last_name" class="form-input" style="width:120px;" required></div>
                    <div class="fg"><label>Title</label><input type="text" x-model="form.title" class="form-input" style="width:120px;"></div>
                    <div class="fg"><label>Phone</label><input type="text" x-model="form.phone" class="form-input" style="width:120px;"></div>
                    <div class="fg"><label>Email</label><input type="email" x-model="form.email" class="form-input" style="width:160px;"></div>
                    <div class="fg"><label>Type</label>
                        <select x-model="form.contact_type" class="form-input">
                            <option value="GENERAL">General</option><option value="BILLING">Billing</option>
                            <option value="SHIPPING">Shipping</option><option value="QUALITY">Quality</option>
                            <option value="DECISION_MAKER">Decision Maker</option><option value="INFLUENCER">Influencer</option>
                        </select>
                    </div>
                    <div class="fg"><label>&nbsp;</label><label style="display:flex; align-items:center; gap:4px; font-size:12px; cursor:pointer;"><input type="checkbox" x-model="form.is_primary"> Primary</label></div>
                    <button class="btn btn-primary btn-sm" @click="saveContact()">Save</button>
                    <button class="btn btn-secondary btn-sm" @click="showForm=false">Cancel</button>
                </div>
            </div>
            <table class="data-table">
                <thead><tr><th>Name</th><th>Title</th><th>Type</th><th>Phone</th><th>Email</th><th>Primary</th><th>Active</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($contacts)): ?>
                        <tr><td colspan="8" class="empty-state">No contacts.</td></tr>
                    <?php else: foreach ($contacts as $c): ?>
                    <tr class="<?= !$c['active'] ? 'inactive-row' : '' ?>">
                        <td><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></td>
                        <td><?= $c['title'] ? htmlspecialchars($c['title']) : '<span style="color:#9ca3af;">—</span>' ?></td>
                        <td><?php
                            $tb = match($c['contact_type']) { 'BILLING'=>'badge-warning','SHIPPING'=>'badge-info','QUALITY'=>'badge-active','DECISION_MAKER'=>'badge-danger','INFLUENCER'=>'', default=>'badge-inactive' };
                        ?><span class="badge <?= $tb ?>"><?= str_replace('_',' ',$c['contact_type']) ?></span></td>
                        <td><?= $c['phone'] ? htmlspecialchars($c['phone']) : '' ?></td>
                        <td><?= $c['email'] ? '<a href="mailto:'.htmlspecialchars($c['email']).'" style="color:#2563eb;">'.htmlspecialchars($c['email']).'</a>' : '' ?></td>
                        <td><?= $c['is_primary'] ? '<span style="color:#f59e0b; font-size:16px;">&#9733;</span>' : '' ?></td>
                        <td><span class="badge <?= $c['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $c['active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td class="actions-cell">
                            <?php if ($c['active']): ?>
                            <button class="btn btn-sm btn-secondary" @click="editContact(<?= htmlspecialchars(json_encode($c)) ?>)">Edit</button>
                            <button class="btn btn-sm btn-warning" @click="deactivateContact(<?= $c['id'] ?>)" onclick="return confirm('Deactivate?')">Deactivate</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ═══ Ship-To Tab ═══ -->
        <div id="tab-ship-to" class="tab-panel">
            <div style="margin-bottom:12px; text-align:right;">
                <a href="/customers/<?= $customer['id'] ?>/ship-to/create" class="btn btn-primary btn-sm">+ Add Ship-To Location</a>
            </div>
            <table class="data-table">
                <thead><tr><th>Location Name</th><th>City / State</th><th>Phone</th><th>Contact</th><th>Active</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($shipTos)): ?>
                        <tr><td colspan="6" class="empty-state">No ship-to locations.</td></tr>
                    <?php else: foreach ($shipTos as $st): ?>
                    <tr class="<?= !$st['active'] ? 'inactive-row' : '' ?>">
                        <td style="font-weight:500;"><?= htmlspecialchars($st['location_name']) ?></td>
                        <td><?php $cs = array_filter([$st['city'],$st['state']]); echo $cs ? htmlspecialchars(implode(', ',$cs)) : '<span style="color:#9ca3af;">—</span>'; ?></td>
                        <td><?= $st['phone'] ? htmlspecialchars($st['phone']) : '' ?></td>
                        <td><?= $st['contact_name'] ? htmlspecialchars($st['contact_name']) : '' ?></td>
                        <td><span class="badge <?= $st['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $st['active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td class="actions-cell">
                            <a href="/customers/<?= $customer['id'] ?>/ship-to/<?= $st['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                            <?php if ($st['active']): ?>
                            <form method="POST" action="/customers/<?= $customer['id'] ?>/ship-to/<?= $st['id'] ?>/deactivate" style="display:inline;">
                                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate?')">Deactivate</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ═══ CRM Profile Tab ═══ -->
        <div id="tab-crm" class="tab-panel" x-data="{ saving: false }">
            <form @submit.prevent="
                saving = true;
                fetch('/customers/<?= $customer['id'] ?>/crm-profile', {
                    method: 'POST', body: new FormData($el),
                    headers: {'X-Requested-With':'XMLHttpRequest'}
                }).then(r => { saving = false; location.reload(); })
            ">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Industry Segment</label>
                        <select name="industry_segment_id" class="form-input" style="width:100%;">
                            <option value="">— None —</option>
                            <?php foreach ($segments as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($crmProfile['industry_segment_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Annual Volume</label>
                        <input type="number" step="0.01" name="annual_volume" value="<?= htmlspecialchars($crmProfile['annual_volume'] ?? '') ?>" class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Customer Since</label>
                        <input type="date" name="customer_since" value="<?= htmlspecialchars($crmProfile['customer_since'] ?? '') ?>" class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Last Rep Visit</label>
                        <input type="date" name="last_rep_visit" value="<?= htmlspecialchars($crmProfile['last_rep_visit'] ?? '') ?>" class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Next Contact Date <?php
                            $ncd = $crmProfile['next_contact_date'] ?? null;
                            if ($ncd && $ncd < date('Y-m-d')): ?><span style="color:#dc2626; font-size:11px;">(OVERDUE)</span><?php endif; ?>
                        </label>
                        <input type="date" name="next_contact_date" value="<?= htmlspecialchars($ncd ?? '') ?>" class="form-input" style="width:100%; <?= ($ncd && $ncd < date('Y-m-d')) ? 'border-color:#dc2626;' : '' ?>">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Equipment on Site</label><textarea name="equipment_on_site" rows="3" class="form-input" style="width:100%;"><?= htmlspecialchars($crmProfile['equipment_on_site'] ?? '') ?></textarea></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Products of Interest</label><textarea name="products_of_interest" rows="3" class="form-input" style="width:100%;"><?= htmlspecialchars($crmProfile['products_of_interest'] ?? '') ?></textarea></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Competition</label><textarea name="competition" rows="3" class="form-input" style="width:100%;"><?= htmlspecialchars($crmProfile['competition'] ?? '') ?></textarea></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Key Decision Makers</label><textarea name="key_decision_makers" rows="3" class="form-input" style="width:100%;"><?= htmlspecialchars($crmProfile['key_decision_makers'] ?? '') ?></textarea></div>
                </div>
                <div style="margin-top:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Profile Notes</label>
                    <textarea name="profile_notes" rows="3" class="form-input" style="width:100%;"><?= htmlspecialchars($crmProfile['profile_notes'] ?? '') ?></textarea>
                </div>
                <div style="margin-top:12px;">
                    <button type="submit" class="btn btn-primary" :disabled="saving" x-text="saving ? 'Saving...' : 'Save CRM Profile'"></button>
                </div>
            </form>
        </div>

        <!-- ═══ Activity Log Tab ═══ -->
        <div id="tab-activities" class="tab-panel" x-data="activityManager()">
            <div style="margin-bottom:12px; text-align:right;">
                <button class="btn btn-primary btn-sm" @click="showForm = !showForm">+ Log Activity</button>
            </div>
            <div x-show="showForm" x-cloak class="inline-form" style="flex-direction:column; align-items:stretch;">
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:end;">
                    <div class="fg"><label>Type</label>
                        <select x-model="form.activity_type" class="form-input">
                            <option value="GENERAL">General</option><option value="CALL">Call</option>
                            <option value="EMAIL">Email</option><option value="MEETING">Meeting</option>
                            <option value="SITE_VISIT">Site Visit</option><option value="DEMO">Demo</option>
                            <option value="COMPLAINT">Complaint</option><option value="PRICING_DISCUSSION">Pricing Discussion</option>
                            <option value="PROPOSAL_SENT">Proposal Sent</option>
                        </select>
                    </div>
                    <div class="fg" style="flex:1;"><label>Subject *</label><input type="text" x-model="form.subject" class="form-input" style="width:100%;" required></div>
                </div>
                <div style="margin-top:8px;"><label style="font-size:12px;">Notes</label><textarea x-model="form.notes" rows="2" class="form-input" style="width:100%;"></textarea></div>
                <?php if (!empty($contacts)): ?>
                <div style="margin-top:8px;"><label style="font-size:12px;">Contacts Involved</label>
                    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:4px;">
                        <?php foreach ($contacts as $c): if (!$c['active']) continue; ?>
                        <label style="font-size:12px; display:flex; align-items:center; gap:4px; cursor:pointer;">
                            <input type="checkbox" value="<?= $c['id'] ?>" x-model="form.contact_ids"> <?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div style="margin-top:8px; display:flex; gap:8px;">
                    <button class="btn btn-primary btn-sm" @click="saveActivity()">Save Activity</button>
                    <button class="btn btn-secondary btn-sm" @click="showForm=false">Cancel</button>
                </div>
            </div>
            <table class="data-table">
                <thead><tr><th>Type</th><th>Subject</th><th>Notes</th><th>By</th><th>Date</th></tr></thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr><td colspan="5" class="empty-state">No activities logged.</td></tr>
                    <?php else: foreach ($activities as $a): ?>
                    <tr>
                        <td><?php
                            $ab = match($a['activity_type']) { 'CALL'=>'badge-info','EMAIL'=>'badge-active','MEETING'=>'badge-warning','SITE_VISIT'=>'badge-active','COMPLAINT'=>'badge-danger', default=>'' };
                        ?><span class="badge <?= $ab ?>"><?= str_replace('_',' ',$a['activity_type']) ?></span></td>
                        <td style="font-weight:500;"><?= htmlspecialchars($a['subject']) ?></td>
                        <td style="max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($a['notes'] ?? '') ?>"><?= htmlspecialchars(mb_substr($a['notes'] ?? '', 0, 80)) ?><?= mb_strlen($a['notes'] ?? '') > 80 ? '...' : '' ?></td>
                        <td><?= htmlspecialchars($a['created_by_name'] ?? '') ?></td>
                        <td style="white-space:nowrap;"><?= date('M j, Y g:ia', strtotime($a['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ═══ Tasks Tab ═══ -->
        <div id="tab-tasks" class="tab-panel" x-data="taskManager()">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <label style="font-size:13px; display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" x-model="showAll" class="form-checkbox"> Show completed/cancelled
                </label>
                <button class="btn btn-primary btn-sm" @click="showForm = !showForm">+ Add Task</button>
            </div>
            <div x-show="showForm" x-cloak class="inline-form" style="flex-direction:column; align-items:stretch;">
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:end;">
                    <div class="fg" style="flex:1;"><label>Title *</label><input type="text" x-model="form.title" class="form-input" style="width:100%;" required></div>
                    <div class="fg"><label>Due Date *</label><input type="date" x-model="form.due_date" class="form-input" required></div>
                    <div class="fg"><label>Assigned To *</label>
                        <select x-model="form.assigned_to" class="form-input">
                            <option value="">— Select —</option>
                            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg"><label>Priority</label>
                        <select x-model="form.priority" class="form-input">
                            <option value="LOW">Low</option><option value="NORMAL" selected>Normal</option>
                            <option value="HIGH">High</option><option value="URGENT">Urgent</option>
                        </select>
                    </div>
                    <div class="fg"><label>Contact</label>
                        <select x-model="form.contact_id" class="form-input">
                            <option value="">— None —</option>
                            <?php foreach ($contacts as $c): if (!$c['active']) continue; ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-top:8px;"><label style="font-size:12px;">Description</label><textarea x-model="form.description" rows="2" class="form-input" style="width:100%;"></textarea></div>
                <div style="margin-top:8px; display:flex; gap:8px;">
                    <button class="btn btn-primary btn-sm" @click="saveTask()">Save Task</button>
                    <button class="btn btn-secondary btn-sm" @click="showForm=false">Cancel</button>
                </div>
            </div>
            <table class="data-table">
                <thead><tr><th>Title</th><th>Assigned To</th><th>Due Date</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                        <tr><td colspan="6" class="empty-state">No tasks.</td></tr>
                    <?php else: foreach ($tasks as $t):
                        $isOverdue = $t['status'] === 'OPEN' && $t['due_date'] < date('Y-m-d');
                        $isDone = $t['status'] !== 'OPEN';
                    ?>
                    <tr class="<?= $isOverdue ? 'overdue-row' : '' ?>" x-show="showAll || '<?= $t['status'] ?>' === 'OPEN'">
                        <td style="font-weight:500;"><?= htmlspecialchars($t['title']) ?>
                            <?php if ($t['contact_first']): ?><br><span style="font-size:11px; color:#6b7280;">Contact: <?= htmlspecialchars($t['contact_first'].' '.$t['contact_last']) ?></span><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($t['assigned_name'] ?? '') ?></td>
                        <td style="white-space:nowrap; <?= $isOverdue ? 'color:#dc2626; font-weight:600;' : '' ?>"><?= date('M j, Y', strtotime($t['due_date'])) ?></td>
                        <td><?php
                            $pb = match($t['priority']) { 'LOW'=>'badge-inactive','NORMAL'=>'','HIGH'=>'badge-warning','URGENT'=>'badge-danger', default=>'' };
                        ?><span class="badge <?= $pb ?>"><?= $t['priority'] ?></span></td>
                        <td><?php
                            $sb = match($t['status']) { 'OPEN'=>'badge-info','COMPLETED'=>'badge-active','CANCELLED'=>'badge-inactive', default=>'' };
                        ?><span class="badge <?= $sb ?>"><?= $t['status'] ?></span>
                        <?php if ($t['completed_at']): ?><br><span style="font-size:10px; color:#6b7280;"><?= date('M j g:ia', strtotime($t['completed_at'])) ?></span><?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <?php if ($t['status'] === 'OPEN'): ?>
                            <button class="btn btn-sm btn-primary" @click="completeTask(<?= $t['id'] ?>)">&#10003;</button>
                            <button class="btn btn-sm btn-warning" @click="cancelTask(<?= $t['id'] ?>)">&#10005;</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ═══ Pricing Tab ═══ -->
        <div id="tab-pricing" class="tab-panel">
            <!-- Assigned Price Lists -->
            <h3 style="font-size:14px; margin:0 0 8px;">Assigned Price Lists</h3>
            <form method="POST" action="/customers/<?= $customer['id'] ?>/price-lists" class="inline-form" style="margin-bottom:8px;">
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
            <table class="data-table" style="margin-bottom:24px;">
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
                        <form method="POST" action="/customers/<?= $customer['id'] ?>/price-lists/<?= $pla['id'] ?>" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <input type="number" name="priority_override" value="<?= $pla['priority_override'] ?? '' ?>" placeholder="Default" style="width:60px;padding:2px 4px;font-size:12px;border:1px solid #d1d5db;border-radius:3px;">
                            <button type="submit" class="btn btn-sm btn-secondary">Update</button>
                        </form>
                        <form method="POST" action="/customers/<?= $customer['id'] ?>/price-lists/<?= $pla['id'] ?>/remove" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Remove this price list?')">Remove</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <!-- Customer Price Overrides (legacy) -->
            <h3 style="font-size:14px; margin:0 0 8px;">Price Overrides</h3>
            <form method="POST" action="/customers/<?= $customer['id'] ?>/prices" class="inline-form" id="priceForm">
                <input type="hidden" name="price_id" id="priceId" value="">
                <div class="fg item-search-wrap">
                    <label>Item *</label>
                    <input type="text" id="priceItemSearch" placeholder="Search items..." class="form-input" style="width:200px;" autocomplete="off">
                    <input type="hidden" name="item_id" id="priceItemId" required>
                    <div class="item-suggestions" id="priceSuggestions"></div>
                </div>
                <div class="fg"><label>Min Qty</label><input type="number" step="0.0001" name="min_quantity" id="priceMinQty" value="0" class="form-input" style="width:100px;"></div>
                <div class="fg"><label>Unit Price *</label><input type="number" step="0.0001" name="unit_price" id="priceUnitPrice" class="form-input" style="width:100px;" required></div>
                <div class="fg"><label>Effective Date</label><input type="date" name="effective_date" id="priceEffDate" value="<?= date('Y-m-d') ?>" class="form-input"></div>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="resetPriceForm()" id="priceCancelBtn" style="display:none;">Cancel</button>
            </form>
            <table class="data-table" style="margin-bottom:24px;">
                <thead><tr><th>Item</th><th style="text-align:right;">Min Qty</th><th style="text-align:right;">Unit Price</th><th>Effective</th><th>Active</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($prices)): ?>
                        <tr><td colspan="6" class="empty-state">No price overrides.</td></tr>
                    <?php else: foreach ($prices as $p): ?>
                    <tr class="<?= !$p['active'] ? 'inactive-row' : '' ?>">
                        <td><a href="/items/<?= $p['item_id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($p['item_code']) ?></a> <span style="color:#6b7280; font-size:12px;"><?= htmlspecialchars($p['item_description']) ?></span></td>
                        <td style="text-align:right;"><?= number_format((float)$p['min_quantity'], 4) ?></td>
                        <td style="text-align:right;">$<?= number_format((float)$p['unit_price'], 4) ?></td>
                        <td><?= date('M j, Y', strtotime($p['effective_date'])) ?></td>
                        <td><span class="badge <?= $p['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $p['active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td class="actions-cell">
                            <?php if ($p['active']): ?>
                            <button class="btn btn-sm btn-secondary" onclick="editPrice(<?= htmlspecialchars(json_encode($p)) ?>)">Edit</button>
                            <form method="POST" action="/customers/<?= $customer['id'] ?>/prices/<?= $p['id'] ?>/deactivate" style="display:inline;"><button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate?')">Deactivate</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <!-- MOQ -->
            <h3 style="font-size:14px; margin:0 0 8px;">Minimum Order Quantities</h3>
            <form method="POST" action="/customers/<?= $customer['id'] ?>/moq" class="inline-form" id="moqForm">
                <input type="hidden" name="moq_id" id="moqId" value="">
                <div class="fg item-search-wrap">
                    <label>Item *</label>
                    <input type="text" id="moqItemSearch" placeholder="Search items..." class="form-input" style="width:200px;" autocomplete="off">
                    <input type="hidden" name="item_id" id="moqItemId" required>
                    <div class="item-suggestions" id="moqSuggestions"></div>
                </div>
                <div class="fg"><label>Min Quantity *</label><input type="number" step="0.0001" name="min_quantity" id="moqMinQty" class="form-input" style="width:120px;" required></div>
                <div class="fg"><label>Effective Date</label><input type="date" name="effective_date" id="moqEffDate" value="<?= date('Y-m-d') ?>" class="form-input"></div>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="resetMoqForm()" id="moqCancelBtn" style="display:none;">Cancel</button>
            </form>
            <table class="data-table">
                <thead><tr><th>Item</th><th style="text-align:right;">Min Quantity</th><th>Effective</th><th>Active</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($moqs)): ?>
                        <tr><td colspan="5" class="empty-state">No MOQ rules.</td></tr>
                    <?php else: foreach ($moqs as $m): ?>
                    <tr class="<?= !$m['active'] ? 'inactive-row' : '' ?>">
                        <td><a href="/items/<?= $m['item_id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($m['item_code']) ?></a> <span style="color:#6b7280; font-size:12px;"><?= htmlspecialchars($m['item_description']) ?></span></td>
                        <td style="text-align:right;"><?= number_format((float)$m['min_quantity'], 4) ?></td>
                        <td><?= date('M j, Y', strtotime($m['effective_date'])) ?></td>
                        <td><span class="badge <?= $m['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $m['active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td class="actions-cell">
                            <?php if ($m['active']): ?>
                            <button class="btn btn-sm btn-secondary" onclick="editMoq(<?= htmlspecialchars(json_encode($m)) ?>)">Edit</button>
                            <form method="POST" action="/customers/<?= $customer['id'] ?>/moq/<?= $m['id'] ?>/deactivate" style="display:inline;"><button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate?')">Deactivate</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ═══ Consignment Tab ═══ -->
        <div id="tab-consignment" class="tab-panel">
            <div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
                <a href="/consignment/create" class="btn btn-primary btn-sm">+ New Placement</a>
            </div>
            <?php
            try {
                $conStmt = $this->db ? null : null;
                $conDb = \PrecisionInk\Controllers\BaseController::getSharedDb();
                if ($conDb) {
                    $conStmt = $conDb->prepare("
                        SELECT cp.*, i.item_code, i.description as item_description,
                               COALESCE((SELECT SUM(cc.quantity_consumed) FROM consignment_consumption cc WHERE cc.placement_id = cp.id), 0) as total_consumed,
                               (SELECT MAX(cc.consumption_date) FROM consignment_consumption cc WHERE cc.placement_id = cp.id) as last_consumption
                        FROM consignment_placements cp
                        JOIN items i ON cp.item_id = i.id
                        WHERE cp.customer_id = ? AND cp.status = 'ACTIVE'
                        ORDER BY cp.placement_date DESC
                    ");
                    $conStmt->execute([$customer['id']]);
                    $conPlacements = $conStmt->fetchAll();
                } else { $conPlacements = []; }
            } catch (\Throwable $e) { $conPlacements = []; }
            ?>
            <?php if(empty($conPlacements)):?>
                <p style="color:#9ca3af;padding:16px;">No active consignment placements.</p>
            <?php else:?>
                <table class="data-table">
                    <thead><tr><th>CON #</th><th>Item</th><th style="text-align:right;">Placed</th><th style="text-align:right;">Consumed</th><th style="text-align:right;">Balance</th><th>Last Consumption</th></tr></thead>
                    <tbody>
                    <?php foreach($conPlacements as $cp):$bal=(float)$cp['quantity_placed']-(float)$cp['total_consumed'];?>
                    <tr>
                        <td><a href="/consignment/<?=$cp['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($cp['con_number'])?></a></td>
                        <td><?=htmlspecialchars($cp['item_code'])?> <span style="color:#6b7280;font-size:12px;"><?=htmlspecialchars($cp['item_description'])?></span></td>
                        <td style="text-align:right;"><?=number_format((float)$cp['quantity_placed'],4)?></td>
                        <td style="text-align:right;"><?=number_format((float)$cp['total_consumed'],4)?></td>
                        <td style="text-align:right;font-weight:600;"><?=number_format($bal,4)?></td>
                        <td><?=$cp['last_consumption']?date('M j, Y',strtotime($cp['last_consumption'])):'—'?></td>
                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            <?php endif;?>
        </div>

        <!-- ═══ Custom Fields Tab ═══ -->
        <div id="tab-custom-fields" class="tab-panel">
            <?php
            $cfRecordType = 'customers';
            $cfRecordId = $customer['id'] ?? 0;
            if (isset($customFieldService)) {
                require __DIR__ . '/../partials/custom_fields_view.php';
            } else {
                echo '<p style="color:#9ca3af; padding:16px;">No custom fields configured.</p>';
            }
            ?>
        </div>

        <!-- ═══ Orders Tab ═══ -->
        <div id="tab-orders" class="tab-panel">
            <?php if (empty($recentSOs)): ?>
                <p style="color:#9ca3af; padding:16px;">No sales orders on record.</p>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>SO #</th><th>Order Date</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($recentSOs as $so): ?>
                    <tr>
                        <td><a href="/sales-orders/<?= $so['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($so['so_number']) ?></a></td>
                        <td><?= date('M j, Y', strtotime($so['order_date'])) ?></td>
                        <td><?php
                            $sob = match($so['status']) { 'DRAFT'=>'badge-inactive','CONFIRMED'=>'badge-info','ON_HOLD'=>'badge-warning','PARTIAL'=>'badge-warning','SHIPPED'=>'badge-active','CANCELLED'=>'badge-danger', default=>'' };
                        ?><span class="badge <?= $sob ?>"><?= $so['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:12px; color:#9ca3af; margin-top:8px;">Showing 10 most recent. <a href="/sales-orders?customer_id=<?= $customer['id'] ?>" style="color:#2563eb;">View all orders</a></p>
            <?php endif; ?>
        </div>

        <!-- ═══ Invoices Tab ═══ -->
        <div id="tab-invoices" class="tab-panel">
            <?php if (empty($recentInvoices)): ?>
                <p style="color:#9ca3af; padding:16px;">No invoices on record.</p>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Invoice #</th><th>Date</th><th>Due Date</th><th style="text-align:right;">Amount</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($recentInvoices as $inv): ?>
                    <tr>
                        <td><a href="/invoices/<?= $inv['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($inv['invoice_number']) ?></a></td>
                        <td><?= date('M j, Y', strtotime($inv['invoice_date'])) ?></td>
                        <td><?= date('M j, Y', strtotime($inv['due_date'])) ?></td>
                        <td style="text-align:right;">$<?= number_format((float)$inv['total_due'], 2) ?></td>
                        <td><?php
                            $ib = match($inv['status']) { 'OPEN'=>'badge-warning','PAID'=>'badge-active','VOID'=>'badge-inactive', default=>'' };
                        ?><span class="badge <?= $ib ?>"><?= $inv['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:12px; color:#9ca3af; margin-top:8px;">Showing 10 most recent. <a href="/invoices?customer_id=<?= $customer['id'] ?>" style="color:#2563eb;">View all invoices</a></p>
            <?php endif; ?>
        </div>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    var custId = <?= (int)$customer['id'] ?>;
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);

    function switchTab(name) {
        document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
        document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.getElementById('tab-' + name).classList.add('active');
        event.target.classList.add('active');
        history.replaceState(null, '', '#' + name);
    }
    (function() {
        var hash = location.hash.replace('#', '');
        if (hash && document.getElementById('tab-' + hash)) {
            document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            document.getElementById('tab-' + hash).classList.add('active');
            var btns = document.querySelectorAll('.tab-btn');
            var tabNames = ['details','contacts','ship-to','crm','activities','tasks','pricing','consignment','custom-fields','orders','invoices'];
            var idx = tabNames.indexOf(hash);
            if (idx >= 0 && btns[idx]) btns[idx].classList.add('active');
        }
    })();

    function postJson(url, data) {
        var fd = new FormData();
        Object.keys(data).forEach(function(k) {
            var v = data[k];
            if (Array.isArray(v)) v.forEach(function(i) { fd.append(k + '[]', i); });
            else if (v !== null && v !== undefined) fd.append(k, v);
        });
        return fetch(url, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
    }

    /* ── Contacts ── */
    function contactManager() {
        return {
            showForm: false,
            form: { contact_id:'', first_name:'', last_name:'', title:'', phone:'', email:'', contact_type:'GENERAL', is_primary:false },
            resetForm() { this.form = { contact_id:'', first_name:'', last_name:'', title:'', phone:'', email:'', contact_type:'GENERAL', is_primary:false }; },
            editContact(c) {
                this.form = { contact_id:c.id, first_name:c.first_name, last_name:c.last_name, title:c.title||'', phone:c.phone||'', email:c.email||'', contact_type:c.contact_type, is_primary:!!c.is_primary };
                this.showForm = true;
            },
            saveContact() {
                if (!this.form.first_name || !this.form.last_name) { alert('First and last name required.'); return; }
                var data = Object.assign({}, this.form);
                data.is_primary = data.is_primary ? '1' : '';
                postJson('/customers/' + custId + '/contacts', data).then(function() { location.reload(); });
            },
            deactivateContact(cid) {
                postJson('/customers/' + custId + '/contacts/' + cid + '/deactivate', {}).then(function() { location.reload(); });
            }
        };
    }

    /* ── Activity Log ── */
    function activityManager() {
        return {
            showForm: false,
            form: { activity_type:'GENERAL', subject:'', notes:'', contact_ids:[] },
            saveActivity() {
                if (!this.form.subject) { alert('Subject is required.'); return; }
                postJson('/customers/' + custId + '/activities', this.form).then(function() { location.reload(); });
            }
        };
    }

    /* ── Tasks ── */
    function taskManager() {
        return {
            showForm: false, showAll: false,
            form: { title:'', description:'', due_date:'', assigned_to:'', priority:'NORMAL', contact_id:'' },
            saveTask() {
                if (!this.form.title || !this.form.due_date || !this.form.assigned_to) { alert('Title, due date, and assigned user required.'); return; }
                postJson('/customers/' + custId + '/tasks', this.form).then(function() { location.reload(); });
            },
            completeTask(tid) { postJson('/customers/' + custId + '/tasks/' + tid + '/complete', {}).then(function() { location.reload(); }); },
            cancelTask(tid) { if (confirm('Cancel this task?')) postJson('/customers/' + custId + '/tasks/' + tid + '/cancel', {}).then(function() { location.reload(); }); }
        };
    }

    /* ── Pricing item search ── */
    function bindItemTypeahead(inputId, suggestionsId, hiddenId) {
        var input = document.getElementById(inputId);
        var sugs = document.getElementById(suggestionsId);
        var hidden = document.getElementById(hiddenId);
        if (!input) return;
        var timer;
        input.addEventListener('input', function() {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 1) { sugs.style.display = 'none'; return; }
            timer = setTimeout(function() {
                fetch('/items/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        sugs.innerHTML = '';
                        data.forEach(function(item) {
                            var d = document.createElement('div');
                            d.textContent = item.display;
                            d.addEventListener('click', function() {
                                input.value = item.item_code + ' — ' + item.description;
                                hidden.value = item.id;
                                sugs.style.display = 'none';
                            });
                            sugs.appendChild(d);
                        });
                        sugs.style.display = data.length ? 'block' : 'none';
                    });
            }, 300);
        });
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !sugs.contains(e.target)) sugs.style.display = 'none';
        });
    }
    bindItemTypeahead('priceItemSearch', 'priceSuggestions', 'priceItemId');
    bindItemTypeahead('moqItemSearch', 'moqSuggestions', 'moqItemId');

    function editPrice(p) {
        document.getElementById('priceId').value = p.id;
        document.getElementById('priceItemId').value = p.item_id;
        document.getElementById('priceItemSearch').value = p.item_code + ' — ' + p.item_description;
        document.getElementById('priceMinQty').value = p.min_quantity;
        document.getElementById('priceUnitPrice').value = p.unit_price;
        document.getElementById('priceEffDate').value = p.effective_date;
        document.getElementById('priceCancelBtn').style.display = '';
    }
    function resetPriceForm() {
        document.getElementById('priceId').value = '';
        document.getElementById('priceItemId').value = '';
        document.getElementById('priceItemSearch').value = '';
        document.getElementById('priceMinQty').value = '0';
        document.getElementById('priceUnitPrice').value = '';
        document.getElementById('priceCancelBtn').style.display = 'none';
    }
    function editMoq(m) {
        document.getElementById('moqId').value = m.id;
        document.getElementById('moqItemId').value = m.item_id;
        document.getElementById('moqItemSearch').value = m.item_code + ' — ' + m.item_description;
        document.getElementById('moqMinQty').value = m.min_quantity;
        document.getElementById('moqEffDate').value = m.effective_date;
        document.getElementById('moqCancelBtn').style.display = '';
    }
    function resetMoqForm() {
        document.getElementById('moqId').value = '';
        document.getElementById('moqItemId').value = '';
        document.getElementById('moqItemSearch').value = '';
        document.getElementById('moqMinQty').value = '';
        document.getElementById('moqCancelBtn').style.display = 'none';
    }
    </script>
</body>
</html>
