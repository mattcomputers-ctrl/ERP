<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($po['po_number']) ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .tab-bar { display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:16px; overflow-x:auto; }
        .tab-btn { padding:8px 14px; font-size:13px; font-weight:500; cursor:pointer; border:none; background:none; color:#6b7280; border-bottom:2px solid transparent; margin-bottom:-2px; white-space:nowrap; }
        .tab-btn.active { color:#2563eb; border-bottom-color:#2563eb; }
        .tab-btn:hover { color:#1d4ed8; }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center; }
        .modal-overlay.active { display:flex; }
        .modal-box { background:#fff; border-radius:8px; padding:24px; max-width:500px; width:90%; box-shadow:0 10px 25px rgba(0,0,0,0.15); }
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
    <div style="max-width:1100px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast"><?= htmlspecialchars($_SESSION['toast']['message']) ?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <?php
        $sb = match($po['status']) { 'DRAFT'=>'badge-inactive','SENT'=>'badge-info','PARTIAL'=>'badge-warning','RECEIVED'=>'badge-active','CANCELLED'=>'badge-danger', default=>'' };
        $poValue = 0;
        foreach ($lines as $l) { if ($l['line_status'] !== 'CANCELLED') $poValue += (float)$l['ordered_quantity'] * (float)$l['unit_cost']; }
        ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">
                <?= htmlspecialchars($po['po_number']) ?>
                <?php if ($po['revision_number'] > 0): ?><span style="font-size:14px; color:#6b7280;">Rev <?= $po['revision_number'] ?></span><?php endif; ?>
                <span class="badge <?= $sb ?>" style="font-size:14px; vertical-align:middle; <?= $po['status']==='CANCELLED' ? 'text-decoration:line-through;' : '' ?>"><?= $po['status'] ?></span>
                <span class="badge <?= $po['po_type']==='BLANKET' ? 'badge-warning' : 'badge-inactive' ?>" style="font-size:12px; vertical-align:middle;"><?= $po['po_type'] ?></span>
            </h1>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="/purchase-orders" class="btn btn-secondary">&larr; Back</a>
                <?php if (in_array($po['status'], ['DRAFT','SENT'])): ?>
                    <a href="/purchase-orders/<?= $po['id'] ?>/edit" class="btn btn-secondary">Edit</a>
                <?php endif; ?>
                <?php if ($po['status'] === 'DRAFT'): ?>
                    <form method="POST" action="/purchase-orders/<?= $po['id'] ?>/send" style="display:inline;">
                        <button type="submit" class="btn btn-primary">Send to Supplier</button>
                    </form>
                <?php endif; ?>
                <?php if (in_array($po['status'], ['SENT','PARTIAL'])): ?>
                    <a href="/purchase-orders/<?= $po['id'] ?>/receive" class="btn btn-primary">Receive</a>
                <?php endif; ?>
                <form method="POST" action="/purchase-orders/<?= $po['id'] ?>/clone" style="display:inline;">
                    <button type="submit" class="btn btn-secondary">Clone</button>
                </form>
                <?php if (!in_array($po['status'], ['RECEIVED','CANCELLED'])): ?>
                    <button class="btn btn-warning" onclick="document.getElementById('cancelModal').classList.add('active')">Cancel PO</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('details')">Details</button>
            <button class="tab-btn" onclick="switchTab('receipts')">Receipts</button>
            <button class="tab-btn" onclick="switchTab('landed')">Landed Costs</button>
            <button class="tab-btn" onclick="switchTab('revisions')">Revision History</button>
            <button class="tab-btn" onclick="switchTab('attachments')">Attachments</button>
            <button class="tab-btn" onclick="switchTab('email')">Email History</button>
        </div>

        <!-- Details Tab -->
        <div id="tab-details" class="tab-panel active">
            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Supplier</strong>
                        <p style="margin:2px 0;"><a href="/suppliers/<?= $po['supplier_id'] ?>" style="color:#2563eb; text-decoration:none;"><?= htmlspecialchars($po['supplier_code'] . ' — ' . $po['supplier_name']) ?></a></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Facility</strong>
                        <p style="margin:2px 0;"><?= htmlspecialchars($po['facility_name']) ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Order Date</strong>
                        <p style="margin:2px 0;"><?= date('M j, Y', strtotime($po['order_date'])) ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Expected Delivery</strong>
                        <?php $pastDue = $po['expected_delivery_date'] && $po['expected_delivery_date'] < date('Y-m-d') && !in_array($po['status'], ['RECEIVED','CANCELLED']); ?>
                        <p style="margin:2px 0; <?= $pastDue ? 'color:#dc2626; font-weight:600;' : '' ?>"><?= $po['expected_delivery_date'] ? date('M j, Y', strtotime($po['expected_delivery_date'])) : '—' ?></p>
                    </div>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">PO Value</strong>
                        <p style="margin:2px 0; font-weight:600;">$<?= number_format($poValue, 2) ?></p>
                    </div>
                    <?php if ($po['created_by_name']): ?>
                    <div>
                        <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Created By</strong>
                        <p style="margin:2px 0;"><?= htmlspecialchars($po['created_by_name']) ?> on <?= date('M j, Y', strtotime($po['created_at'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($po['po_type'] === 'BLANKET'): ?>
                <div style="margin-top:12px; padding:10px; background:#eff6ff; border-radius:6px;">
                    <strong style="font-size:12px; color:#1d4ed8;">Blanket Contract:</strong>
                    <?= $po['contract_start_date'] ? date('M j, Y', strtotime($po['contract_start_date'])) : '?' ?> — <?= $po['contract_end_date'] ? date('M j, Y', strtotime($po['contract_end_date'])) : '?' ?>
                    | Qty: <?= $po['contracted_quantity'] ? number_format((float)$po['contracted_quantity'], 4) : '—' ?>
                    | Value: <?= $po['contracted_value'] ? '$' . number_format((float)$po['contracted_value'], 2) : '—' ?>
                </div>
                <?php endif; ?>
                <?php if ($po['notes']): ?>
                <div style="margin-top:8px;"><strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Notes</strong><p style="margin:2px 0;"><?= nl2br(htmlspecialchars($po['notes'])) ?></p></div>
                <?php endif; ?>
                <?php if ($po['shipping_instructions']): ?>
                <div style="margin-top:8px;"><strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Shipping Instructions</strong><p style="margin:2px 0;"><?= nl2br(htmlspecialchars($po['shipping_instructions'])) ?></p></div>
                <?php endif; ?>
            </div>

            <!-- Lines Table -->
            <h3 style="margin-bottom:8px;">Line Items</h3>
            <table class="data-table">
                <thead><tr><th>#</th><th>Item</th><th>Pack</th><th style="text-align:right;">Ordered</th><th>UOM</th><th style="text-align:right;">Unit Cost</th><th style="text-align:right;">Line Total</th><th style="text-align:right;">Received</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php $n=1; foreach ($lines as $l):
                        $lineTotal = (float)$l['ordered_quantity'] * (float)$l['unit_cost'];
                    ?>
                    <tr class="<?= $l['line_status'] === 'CANCELLED' ? 'inactive-row' : '' ?>">
                        <td style="color:#9ca3af;"><?= $n++ ?></td>
                        <td><a href="/items/<?= $l['item_id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($l['item_code']) ?></a> <span style="color:#6b7280; font-size:12px;"><?= htmlspecialchars($l['item_description']) ?></span></td>
                        <td><?= $l['pack_name'] ? htmlspecialchars($l['pack_name']) : '' ?></td>
                        <td style="text-align:right;"><?= number_format((float)$l['ordered_quantity'], 4) ?></td>
                        <td><?= htmlspecialchars($l['uom_abbr'] ?? '') ?></td>
                        <td style="text-align:right;">$<?= number_format((float)$l['unit_cost'], 4) ?></td>
                        <td style="text-align:right;">$<?= number_format($lineTotal, 2) ?></td>
                        <td style="text-align:right;"><?= number_format((float)$l['received_quantity'], 4) ?></td>
                        <td><?php
                            $lsb = match($l['line_status']) { 'OPEN'=>'badge-info','PARTIAL'=>'badge-warning','RECEIVED'=>'badge-active','CANCELLED'=>'badge-danger', default=>'' };
                        ?><span class="badge <?= $lsb ?>"><?= $l['line_status'] ?></span></td>
                        <td class="actions-cell">
                            <?php if ($l['line_status'] === 'OPEN' && !in_array($po['status'], ['RECEIVED','CANCELLED'])): ?>
                            <button class="btn btn-sm btn-warning" onclick="cancelLineModal(<?= $l['id'] ?>)">Cancel</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($l['line_status'] === 'CANCELLED' && $l['cancellation_reason']): ?>
                    <tr class="inactive-row"><td></td><td colspan="9" style="font-size:12px; color:#991b1b; padding:2px 8px;">Reason: <?= htmlspecialchars($l['cancellation_reason']) ?></td></tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <tr style="font-weight:600; border-top:2px solid #d1d5db;">
                        <td colspan="6" style="text-align:right;">Total</td>
                        <td style="text-align:right;">$<?= number_format($poValue, 2) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tbody>
            </table>

            <!-- Custom Fields -->
            <?php
            $cfRecordType = 'purchase_orders';
            $cfRecordId = $po['id'] ?? 0;
            if (isset($customFieldService)) require __DIR__ . '/../partials/custom_fields_view.php';
            ?>
        </div>

        <!-- Receipts Tab (stub for Pass 2) -->
        <div id="tab-receipts" class="tab-panel">
            <?php if (empty($receipts)): ?>
                <p style="color:#9ca3af; padding:16px;">No receipts recorded yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Receipt Date</th><th>Supplier Invoice</th><th>Lines</th><th>Received By</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($receipts as $r): ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($r['receipt_date'])) ?></td>
                            <td><?= htmlspecialchars($r['supplier_invoice_number']) ?></td>
                            <td><?= (int)$r['line_count'] ?></td>
                            <td><?= htmlspecialchars($r['received_by_name'] ?? '') ?></td>
                            <td class="actions-cell"><span style="color:#9ca3af; font-size:12px;">Details in Pass 2</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php if (in_array($po['status'], ['SENT','PARTIAL'])): ?>
                <div style="margin-top:12px;"><a href="/purchase-orders/<?= $po['id'] ?>/receive" class="btn btn-primary">Receive Shipment</a></div>
            <?php endif; ?>
        </div>

        <!-- Landed Costs Tab (stub for Pass 3) -->
        <div id="tab-landed" class="tab-panel">
            <p style="color:#9ca3af; padding:16px;">Built in Pass 3.</p>
        </div>

        <!-- Revision History Tab -->
        <div id="tab-revisions" class="tab-panel">
            <?php if (empty($revisions)): ?>
                <p style="color:#9ca3af; padding:16px;">No revisions — this PO has not been modified after sending.</p>
            <?php else: ?>
                <?php foreach ($revisions as $rev): ?>
                <div style="padding:12px; background:#f9fafb; border-radius:6px; margin-bottom:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong>Revision <?= (int)$rev['revision_number'] ?></strong>
                        <span style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($rev['changed_by_name'] ?? '') ?> — <?= date('M j, Y g:ia', strtotime($rev['created_at'])) ?></span>
                    </div>
                    <?php $changes = json_decode($rev['field_changes'], true) ?: []; ?>
                    <?php if (!empty($changes)): ?>
                    <table style="width:100%; margin-top:8px; font-size:12px;">
                        <tr style="color:#6b7280;"><th style="text-align:left; padding:2px 6px;">Field</th><th style="text-align:left; padding:2px 6px;">Old</th><th style="text-align:left; padding:2px 6px;">New</th></tr>
                        <?php foreach ($changes as $c): ?>
                        <tr><td style="padding:2px 6px;"><?= htmlspecialchars($c['field']) ?></td><td style="padding:2px 6px; color:#dc2626;"><?= htmlspecialchars($c['old'] ?? '—') ?></td><td style="padding:2px 6px; color:#16a34a;"><?= htmlspecialchars($c['new'] ?? '—') ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Attachments Tab -->
        <div id="tab-attachments" class="tab-panel">
            <?php
            $recordType = 'purchase_order';
            $recordId = $po['id'];
            require __DIR__ . '/../partials/attachments.php';
            ?>
        </div>

        <!-- Email History Tab -->
        <div id="tab-email" class="tab-panel">
            <?php
            $emailReferenceType = 'purchase_order';
            $emailReferenceId = $po['id'];
            require __DIR__ . '/../partials/email_history_tab.php';
            ?>
        </div>
    </div>

    <!-- Cancel PO Modal -->
    <div id="cancelModal" class="modal-overlay" onclick="if(event.target===this) this.classList.remove('active')">
        <div class="modal-box">
            <h3 style="margin:0 0 16px;">Cancel Purchase Order</h3>
            <form method="POST" action="/purchase-orders/<?= $po['id'] ?>/cancel">
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Cancellation Reason <span style="color:red;">*</span></label>
                    <textarea name="cancellation_reason" rows="3" class="form-input" style="width:100%;" required></textarea>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn btn-danger">Cancel PO</button>
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Back</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel Line Modal -->
    <div id="cancelLineModal" class="modal-overlay" onclick="if(event.target===this) this.classList.remove('active')">
        <div class="modal-box">
            <h3 style="margin:0 0 16px;">Cancel Line</h3>
            <form method="POST" id="cancelLineForm" action="">
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Cancellation Reason <span style="color:red;">*</span></label>
                    <textarea name="cancellation_reason" rows="3" class="form-input" style="width:100%;" required></textarea>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn btn-danger">Cancel Line</button>
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Back</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    var toast = document.getElementById('toast'); if (toast) setTimeout(function(){toast.style.display='none';}, 4000);

    function switchTab(name) {
        document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});
        document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});
        document.getElementById('tab-'+name).classList.add('active');
        event.target.classList.add('active');
        history.replaceState(null,'','#'+name);
    }
    (function(){
        var hash = location.hash.replace('#','');
        if (hash && document.getElementById('tab-'+hash)) {
            document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});
            document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});
            document.getElementById('tab-'+hash).classList.add('active');
            var btns = document.querySelectorAll('.tab-btn');
            var names = ['details','receipts','landed','revisions','attachments','email'];
            var idx = names.indexOf(hash);
            if (idx >= 0 && btns[idx]) btns[idx].classList.add('active');
        }
    })();

    function cancelLineModal(lineId) {
        document.getElementById('cancelLineForm').action = '/purchase-orders/<?= $po['id'] ?>/cancel-line/' + lineId;
        document.getElementById('cancelLineModal').classList.add('active');
    }
    </script>
</body>
</html>
