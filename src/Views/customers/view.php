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
    </style>
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

        <!-- ═══ Contacts Tab (stub) ═══ -->
        <div id="tab-contacts" class="tab-panel">
            <p style="color:#9ca3af; padding:16px;">Coming soon.</p>
        </div>

        <!-- ═══ Ship-To Tab (stub) ═══ -->
        <div id="tab-ship-to" class="tab-panel">
            <p style="color:#9ca3af; padding:16px;">Coming soon.</p>
        </div>

        <!-- ═══ CRM Profile Tab (stub) ═══ -->
        <div id="tab-crm" class="tab-panel">
            <p style="color:#9ca3af; padding:16px;">Coming soon.</p>
        </div>

        <!-- ═══ Activity Log Tab (stub) ═══ -->
        <div id="tab-activities" class="tab-panel">
            <p style="color:#9ca3af; padding:16px;">Coming soon.</p>
        </div>

        <!-- ═══ Tasks Tab (stub) ═══ -->
        <div id="tab-tasks" class="tab-panel">
            <p style="color:#9ca3af; padding:16px;">Coming soon.</p>
        </div>

        <!-- ═══ Pricing Tab (stub) ═══ -->
        <div id="tab-pricing" class="tab-panel">
            <p style="color:#9ca3af; padding:16px;">Coming soon.</p>
        </div>

        <!-- ═══ Consignment Tab (stub) ═══ -->
        <div id="tab-consignment" class="tab-panel">
            <p style="color:#9ca3af; padding:16px;">Consignment module built in Session 22.</p>
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

        <!-- ═══ Orders Tab (stub) ═══ -->
        <div id="tab-orders" class="tab-panel">
            <p style="color:#9ca3af; padding:16px;">Coming soon.</p>
        </div>

        <!-- ═══ Invoices Tab (stub) ═══ -->
        <div id="tab-invoices" class="tab-panel">
            <p style="color:#9ca3af; padding:16px;">Coming soon.</p>
        </div>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
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
    </script>
</body>
</html>
