<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive — <?= htmlspecialchars($po['po_number']) ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .recv-line { padding:12px; background:#f9fafb; border-radius:6px; margin-bottom:12px; border-left:4px solid #2563eb; }
        .recv-line .line-header { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:8px; }
        .recv-line .lot-row { display:flex; gap:8px; align-items:center; padding:4px 0; margin-left:24px; }
        .recv-line .lot-row input { font-size:13px; padding:4px 8px; }
        .field-label { font-size:12px; color:#6b7280; display:block; margin-bottom:2px; }
        .discrepancy-warn { background:#fef9c3; border:1px solid #fde68a; padding:8px 12px; border-radius:4px; margin-top:8px; font-size:13px; color:#854d0e; }
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

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">Receive: <?= htmlspecialchars($po['po_number']) ?></h1>
            <a href="/purchase-orders/<?= $po['id'] ?>" class="btn btn-secondary">&larr; Back to PO</a>
        </div>

        <p style="font-size:13px; color:#6b7280; margin-bottom:16px;">
            Supplier: <strong><?= htmlspecialchars($po['supplier_code'] . ' — ' . $po['supplier_name']) ?></strong>
            | Facility: <strong><?= htmlspecialchars($po['facility_name']) ?></strong>
        </p>

        <form method="POST" action="/purchase-orders/<?= $po['id'] ?>/receive">
            <!-- Receipt Header -->
            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div>
                        <label class="field-label">Supplier Invoice # <span style="color:red;">*</span></label>
                        <input type="text" name="supplier_invoice_number" required class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label class="field-label">Receipt Date</label>
                        <input type="date" name="receipt_date" value="<?= date('Y-m-d') ?>" class="form-input" style="width:100%;" id="receiptDate">
                    </div>
                    <div>
                        <label class="field-label">Notes</label>
                        <input type="text" name="notes" class="form-input" style="width:100%;">
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <h3 style="margin-bottom:8px;">Line Items</h3>
            <?php if (empty($lines)): ?>
                <p style="color:#9ca3af;">No open lines to receive.</p>
            <?php else: ?>
                <?php foreach ($lines as $l): ?>
                <div class="recv-line" x-data="recvLine(<?= htmlspecialchars(json_encode([
                    'lineId' => $l['id'],
                    'orderedQty' => (float)$l['ordered_quantity'],
                    'receivedQty' => (float)$l['received_quantity'],
                    'remaining' => (float)$l['remaining'],
                    'unitCost' => (float)$l['unit_cost'],
                    'shelfLifeDays' => (int)($l['shelf_life_days'] ?? 0),
                ])) ?>)">
                    <div class="line-header">
                        <div style="flex:2; min-width:200px;">
                            <span style="font-weight:600;"><?= htmlspecialchars($l['item_code']) ?></span>
                            <span style="color:#6b7280; font-size:12px;"> — <?= htmlspecialchars($l['item_description']) ?></span>
                            <?php if ($l['pack_name']): ?><span class="badge badge-inactive" style="font-size:10px;"><?= htmlspecialchars($l['pack_name']) ?></span><?php endif; ?>
                            <?php if ($l['requires_inspection']): ?><span class="badge badge-warning" style="font-size:10px;">INSP. REQ</span><?php endif; ?>
                        </div>
                        <div style="text-align:right; font-size:12px; color:#6b7280;">
                            Ordered: <?= number_format((float)$l['ordered_quantity'], 4) ?> <?= htmlspecialchars($l['uom_abbr']) ?>
                            | Received: <?= number_format((float)$l['received_quantity'], 4) ?>
                            | Remaining: <strong><?= number_format((float)$l['remaining'], 4) ?></strong>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:8px;">
                        <div>
                            <label class="field-label">Receive Quantity</label>
                            <input type="number" step="0.0001" min="0" name="recv[<?= $l['id'] ?>][received_quantity]"
                                   x-model="recvQty" class="form-input" style="width:100%;"
                                   value="<?= $l['remaining'] ?>">
                        </div>
                        <div>
                            <label class="field-label">Unit Cost</label>
                            <input type="number" step="0.0001" name="recv[<?= $l['id'] ?>][unit_cost]"
                                   x-model="cost" class="form-input" style="width:100%;"
                                   value="<?= $l['unit_cost'] ?>">
                        </div>
                    </div>

                    <!-- COC -->
                    <div style="display:flex; gap:12px; align-items:end; margin-bottom:8px;">
                        <label style="display:flex; align-items:center; gap:4px; font-size:12px;">
                            <input type="checkbox" name="recv[<?= $l['id'] ?>][coc_received]" value="1" class="form-checkbox"> COC Received
                        </label>
                        <div>
                            <label class="field-label">COC Reference</label>
                            <input type="text" name="recv[<?= $l['id'] ?>][coc_reference]" class="form-input" style="width:150px; font-size:12px; padding:3px 6px;">
                        </div>
                        <div>
                            <label class="field-label">COC Date</label>
                            <input type="date" name="recv[<?= $l['id'] ?>][coc_date]" class="form-input" style="width:140px; font-size:12px; padding:3px 6px;">
                        </div>
                    </div>

                    <!-- Discrepancy -->
                    <template x-if="hasDiscrepancy">
                        <div class="discrepancy-warn">
                            <label class="field-label" style="color:#854d0e;">Discrepancy Note <span style="color:red;">*</span> (qty or cost differs from PO)</label>
                            <textarea name="recv[<?= $l['id'] ?>][discrepancy_note]" rows="2" class="form-input" style="width:100%;" required></textarea>
                        </div>
                    </template>

                    <!-- Lot Entries -->
                    <div style="margin-top:8px;">
                        <div style="font-size:12px; font-weight:600; color:#6b7280; margin-bottom:4px;">Lot Entries</div>
                        <template x-for="(lot, idx) in lots" :key="idx">
                            <div class="lot-row">
                                <div>
                                    <label class="field-label">Lot Number</label>
                                    <input type="text" :name="'recv[<?= $l['id'] ?>][lots][' + idx + '][supplier_lot_number]'"
                                           x-model="lot.number" class="form-input" style="width:160px;" required>
                                </div>
                                <div>
                                    <label class="field-label">Quantity</label>
                                    <input type="number" step="0.0001" min="0.0001"
                                           :name="'recv[<?= $l['id'] ?>][lots][' + idx + '][quantity]'"
                                           x-model="lot.qty" class="form-input" style="width:110px;" required>
                                </div>
                                <div>
                                    <label class="field-label">Expiration Date</label>
                                    <input type="date" :name="'recv[<?= $l['id'] ?>][lots][' + idx + '][expiration_date]'"
                                           x-model="lot.expDate" class="form-input" style="width:150px;">
                                </div>
                                <button type="button" class="btn btn-sm btn-warning" @click="removeLot(idx)" style="margin-top:16px;"
                                        x-show="lots.length > 1">&times;</button>
                            </div>
                        </template>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-left:24px; margin-top:4px;">
                            <button type="button" class="btn btn-sm btn-secondary" @click="addLot()">+ Add Lot</button>
                            <span x-show="lotQtyMismatch" style="font-size:12px; color:#dc2626; font-weight:600;">
                                Lot total (<span x-text="lotTotal.toFixed(4)"></span>) does not match receive qty (<span x-text="parseFloat(recvQty).toFixed(4)"></span>)
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($lines)): ?>
            <div style="margin-top:24px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Post Receipt</button>
                <a href="/purchase-orders/<?= $po['id'] ?>" class="btn btn-secondary">Cancel</a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    var toast = document.getElementById('toast'); if (toast) setTimeout(function(){toast.style.display='none';}, 4000);

    function recvLine(cfg) {
        // Calculate auto expiration from receipt date
        var autoExp = '';
        if (cfg.shelfLifeDays > 0) {
            var rd = document.getElementById('receiptDate');
            if (rd && rd.value) {
                var d = new Date(rd.value);
                d.setDate(d.getDate() + cfg.shelfLifeDays);
                autoExp = d.toISOString().split('T')[0];
            }
        }

        return {
            recvQty: cfg.remaining.toString(),
            cost: cfg.unitCost.toString(),
            originalCost: cfg.unitCost,
            remaining: cfg.remaining,
            orderedQty: cfg.orderedQty,
            lots: [{ number: '', qty: cfg.remaining.toString(), expDate: autoExp }],

            get hasDiscrepancy() {
                var q = parseFloat(this.recvQty) || 0;
                var c = parseFloat(this.cost) || 0;
                var qtyDiff = Math.abs(q - this.remaining) / (this.orderedQty || 1);
                var costDiff = this.originalCost > 0 ? Math.abs(c - this.originalCost) / this.originalCost : 0;
                return qtyDiff > 0.10 || costDiff > 0.05;
            },

            get lotTotal() {
                return this.lots.reduce(function(s, l) { return s + (parseFloat(l.qty) || 0); }, 0);
            },

            get lotQtyMismatch() {
                var rq = parseFloat(this.recvQty) || 0;
                return rq > 0 && Math.abs(this.lotTotal - rq) > 0.0001;
            },

            addLot() {
                this.lots.push({ number: '', qty: '', expDate: autoExp });
            },

            removeLot(idx) {
                this.lots.splice(idx, 1);
            }
        };
    }
    </script>
</body>
</html>
