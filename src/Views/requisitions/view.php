<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($req['req_number']) ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center; }
        .modal-overlay.active { display:flex; }
        .modal-box { background:#fff; border-radius:8px; padding:24px; max-width:500px; width:90%; box-shadow:0 10px 25px rgba(0,0,0,0.15); }
        .supp-search-wrap { position:relative; }
        .supp-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none; }
        .supp-suggestions div { padding:6px 10px; font-size:13px; cursor:pointer; }
        .supp-suggestions div:hover { background:#eff6ff; }
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
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast"><?= htmlspecialchars($_SESSION['toast']['message']) ?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <?php
        $sb = match($req['status']) { 'DRAFT'=>'badge-inactive','SUBMITTED'=>'badge-info','APPROVED'=>'badge-active','REJECTED'=>'badge-danger','CONVERTED'=>'badge-active','CANCELLED'=>'badge-inactive', default=>'' };
        ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">
                <?= htmlspecialchars($req['req_number']) ?>
                <span class="badge <?= $sb ?>" style="font-size:14px; vertical-align:middle; <?= $req['status'] === 'CANCELLED' ? 'text-decoration:line-through;' : '' ?>">
                    <?= $req['status'] ?>
                </span>
            </h1>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="/purchase-requisitions" class="btn btn-secondary">&larr; Back</a>
                <?php if ($req['status'] === 'DRAFT'): ?>
                    <a href="/purchase-requisitions/<?= $req['id'] ?>/edit" class="btn btn-secondary">Edit</a>
                    <form method="POST" action="/purchase-requisitions/<?= $req['id'] ?>/submit" style="display:inline;">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Submit for approval?')">Submit</button>
                    </form>
                <?php elseif ($req['status'] === 'SUBMITTED' && $canApprove): ?>
                    <button class="btn btn-primary" onclick="document.getElementById('approveModal').classList.add('active')">Approve</button>
                    <button class="btn btn-danger" onclick="document.getElementById('rejectModal').classList.add('active')">Reject</button>
                <?php elseif ($req['status'] === 'APPROVED'): ?>
                    <button class="btn btn-primary" onclick="document.getElementById('convertModal').classList.add('active')">Convert to PO</button>
                <?php elseif ($req['status'] === 'REJECTED'): ?>
                    <a href="/purchase-requisitions/<?= $req['id'] ?>/edit" class="btn btn-secondary">Edit &amp; Resubmit</a>
                <?php endif; ?>
                <?php if (!in_array($req['status'], ['CONVERTED', 'CANCELLED'])): ?>
                    <form method="POST" action="/purchase-requisitions/<?= $req['id'] ?>/cancel" style="display:inline;">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Cancel this requisition?')">Cancel</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Header Info -->
        <div class="form-section" style="margin-bottom:16px;">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                <div>
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Requested By</strong>
                    <p style="margin:2px 0;"><?= htmlspecialchars($req['requested_by_name']) ?></p>
                </div>
                <div>
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Request Date</strong>
                    <p style="margin:2px 0;"><?= date('M j, Y', strtotime($req['request_date'])) ?></p>
                </div>
                <div>
                    <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Required By</strong>
                    <p style="margin:2px 0; <?= $req['required_by_date'] && $req['required_by_date'] < date('Y-m-d') && !in_array($req['status'], ['CONVERTED','CANCELLED']) ? 'color:#dc2626; font-weight:600;' : '' ?>">
                        <?= $req['required_by_date'] ? date('M j, Y', strtotime($req['required_by_date'])) : '—' ?>
                    </p>
                </div>
            </div>
            <?php if ($req['justification']): ?>
            <div style="margin-top:8px;">
                <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Justification</strong>
                <p style="margin:2px 0;"><?= nl2br(htmlspecialchars($req['justification'])) ?></p>
            </div>
            <?php endif; ?>
            <?php if ($req['notes']): ?>
            <div style="margin-top:8px;">
                <strong style="font-size:12px; color:#6b7280; text-transform:uppercase;">Notes</strong>
                <p style="margin:2px 0;"><?= nl2br(htmlspecialchars($req['notes'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Timeline -->
            <div style="margin-top:12px; padding-top:12px; border-top:1px solid #e5e7eb; font-size:12px; color:#6b7280;">
                Created <?= date('M j, Y g:ia', strtotime($req['created_at'])) ?>
                <?php if ($req['approved_by_name']): ?>
                    &nbsp;&rarr;&nbsp; <?= $req['status'] === 'REJECTED' ? 'Rejected' : 'Approved' ?> by <?= htmlspecialchars($req['approved_by_name']) ?> on <?= date('M j, Y g:ia', strtotime($req['approved_at'])) ?>
                <?php endif; ?>
            </div>
            <?php if ($req['approval_notes']): ?>
            <div style="margin-top:4px; padding:8px 12px; background:<?= $req['status'] === 'REJECTED' ? '#fef2f2' : '#f0fdf4' ?>; border-radius:4px; font-size:13px;">
                <strong><?= $req['status'] === 'REJECTED' ? 'Rejection Reason' : 'Approval Notes' ?>:</strong> <?= htmlspecialchars($req['approval_notes']) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Lines -->
        <h3 style="margin-bottom:8px;">Line Items</h3>
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Item</th><th>Pack</th><th style="text-align:right;">Qty</th><th>UOM</th><th style="text-align:right;">Est. Cost</th><th>Preferred Supplier</th><th>Converted PO</th><th>Notes</th></tr>
            </thead>
            <tbody>
                <?php if (empty($lines)): ?>
                    <tr><td colspan="9" class="empty-state">No line items.</td></tr>
                <?php else: $n = 1; foreach ($lines as $l): ?>
                <tr>
                    <td style="color:#9ca3af;"><?= $n++ ?></td>
                    <td>
                        <a href="/items/<?= $l['item_id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($l['item_code']) ?></a>
                        <span style="color:#6b7280; font-size:12px;"> — <?= htmlspecialchars($l['item_description']) ?></span>
                    </td>
                    <td><?= $l['pack_name'] ? htmlspecialchars($l['pack_name']) : '' ?></td>
                    <td style="text-align:right;"><?= number_format((float)$l['quantity'], 4) ?></td>
                    <td><?= htmlspecialchars($l['uom_abbr'] ?? '') ?></td>
                    <td style="text-align:right;"><?= $l['estimated_unit_cost'] !== null ? '$' . number_format((float)$l['estimated_unit_cost'], 4) : '—' ?></td>
                    <td><?= $l['supplier_code'] ? htmlspecialchars($l['supplier_code'] . ' — ' . $l['supplier_name']) : '<span style="color:#9ca3af;">—</span>' ?></td>
                    <td>
                        <?php if ($l['converted_po_number']): ?>
                            <a href="/purchase-orders/<?= $l['converted_po_id'] ?>" style="color:#2563eb; text-decoration:none;"><?= htmlspecialchars($l['converted_po_number']) ?></a>
                        <?php else: ?>
                            <span style="color:#9ca3af;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($l['notes'] ?? '') ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal-overlay" onclick="if(event.target===this) this.classList.remove('active')">
        <div class="modal-box">
            <h3 style="margin:0 0 16px;">Approve Requisition</h3>
            <form method="POST" action="/purchase-requisitions/<?= $req['id'] ?>/approve">
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Approval Notes (optional)</label>
                    <textarea name="approval_notes" rows="3" class="form-input" style="width:100%;"></textarea>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn btn-primary">Approve</button>
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal-overlay" onclick="if(event.target===this) this.classList.remove('active')">
        <div class="modal-box">
            <h3 style="margin:0 0 16px;">Reject Requisition</h3>
            <form method="POST" action="/purchase-requisitions/<?= $req['id'] ?>/reject">
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Rejection Reason <span style="color:red;">*</span></label>
                    <textarea name="approval_notes" rows="3" class="form-input" style="width:100%;" required></textarea>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn btn-danger">Reject</button>
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Convert to PO Modal -->
    <?php if ($req['status'] === 'APPROVED'):
        // Group unconverted lines by supplier
        $unconverted = array_filter($lines, fn($l) => $l['converted_po_id'] === null);
        $groups = [];
        foreach ($unconverted as $l) {
            $key = $l['preferred_supplier_id'] ?: 'none';
            $groups[$key][] = $l;
        }
    ?>
    <div id="convertModal" class="modal-overlay" onclick="if(event.target===this) this.classList.remove('active')">
        <div class="modal-box" style="max-width:700px;">
            <h3 style="margin:0 0 16px;">Convert to Purchase Order(s)</h3>
            <form method="POST" action="/purchase-requisitions/<?= $req['id'] ?>/convert">
                <?php if (empty($unconverted)): ?>
                    <p style="color:#9ca3af;">All lines have been converted.</p>
                <?php else: ?>
                    <p style="font-size:13px; color:#6b7280; margin-bottom:12px;">One PO will be created per supplier group. Assign suppliers to lines without one.</p>
                    <table class="data-table" style="font-size:13px;">
                        <thead><tr><th>Item</th><th style="text-align:right;">Qty</th><th>Supplier</th></tr></thead>
                        <tbody>
                            <?php foreach ($unconverted as $l): ?>
                            <tr>
                                <td><?= htmlspecialchars($l['item_code']) ?></td>
                                <td style="text-align:right;"><?= number_format((float)$l['quantity'], 4) ?></td>
                                <td>
                                    <?php if ($l['preferred_supplier_id']): ?>
                                        <?= htmlspecialchars($l['supplier_code'] . ' — ' . $l['supplier_name']) ?>
                                        <input type="hidden" name="supplier[<?= $l['id'] ?>]" value="<?= $l['preferred_supplier_id'] ?>">
                                    <?php else: ?>
                                        <div class="supp-search-wrap">
                                            <input type="hidden" name="supplier[<?= $l['id'] ?>]" value="" class="conv-supp-id">
                                            <input type="text" class="form-input conv-supp-search" placeholder="Assign supplier..." autocomplete="off" style="width:100%; font-size:12px; padding:3px 6px;">
                                            <div class="supp-suggestions"></div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="display:flex; gap:8px; margin-top:12px;">
                        <button type="submit" class="btn btn-primary">Convert All</button>
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="/assets/js/settings.js"></script>
    <script>
    var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display='none'; }, 4000);

    // Supplier search in convert modal
    document.querySelectorAll('.conv-supp-search').forEach(function(input) {
        var timer;
        input.addEventListener('input', function() {
            var self = this; clearTimeout(timer);
            timer = setTimeout(function() {
                var q = self.value.trim();
                var box = self.parentNode.querySelector('.supp-suggestions');
                if (q.length < 1) { box.style.display = 'none'; return; }
                fetch('/suppliers/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r) { return r.json(); })
                    .then(function(items) {
                        box.innerHTML = '';
                        items.forEach(function(item) {
                            var d = document.createElement('div');
                            d.textContent = item.display;
                            d.addEventListener('click', function() {
                                self.value = item.display;
                                self.parentNode.querySelector('.conv-supp-id').value = item.id;
                                box.style.display = 'none';
                            });
                            box.appendChild(d);
                        });
                        box.style.display = items.length ? 'block' : 'none';
                    });
            }, 300);
        });
    });
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.supp-suggestions').forEach(function(box) {
            if (!box.parentNode.contains(e.target)) box.style.display = 'none';
        });
    });
    </script>
</body>
</html>
