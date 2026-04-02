<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'edit' ? 'Edit Ship-To' : 'New Ship-To' ?> — <?= htmlspecialchars($customer['customer_code']) ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
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
    <div style="max-width:800px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast"><?= htmlspecialchars($_SESSION['toast']['message']) ?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">
                <?= $mode === 'edit' ? 'Edit Ship-To: ' . htmlspecialchars($shipTo['location_name'] ?? '') : 'New Ship-To Location' ?>
                <span style="font-size:14px; font-weight:400; color:#6b7280;"> — <?= htmlspecialchars($customer['customer_code']) ?></span>
            </h1>
            <a href="/customers/<?= $customer['id'] ?>#ship-to" class="btn btn-secondary">&larr; Back</a>
        </div>

        <?php
        // Resolve inherited values for display
        $custRep = $customer['sales_rep_name'] ?? '—';
        $custShipVia = $customer['ship_via_name'] ?? '—';
        $custTerms = $customer['payment_terms_name'] ?? '—';
        $custCredit = $customer['credit_limit'] > 0 ? '$' . number_format((float)$customer['credit_limit'], 2) : 'No Limit';
        ?>

        <form method="POST" action="<?= $mode === 'edit'
            ? "/customers/{$customer['id']}/ship-to/{$shipTo['id']}/edit"
            : "/customers/{$customer['id']}/ship-to/create" ?>"
            x-data="{
                overrideRep: <?= ($shipTo['sales_rep_id'] ?? null) !== null ? 'true' : 'false' ?>,
                overrideShipVia: <?= ($shipTo['default_ship_via_id'] ?? null) !== null ? 'true' : 'false' ?>,
                overrideTerms: <?= ($shipTo['payment_terms_id'] ?? null) !== null ? 'true' : 'false' ?>,
                overrideCredit: <?= ($shipTo['credit_limit'] ?? null) !== null ? 'true' : 'false' ?>
            }">
            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div style="grid-column:span 2;">
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Location Name <span style="color:red;">*</span></label>
                        <input type="text" name="location_name" value="<?= htmlspecialchars($shipTo['location_name'] ?? '') ?>" required class="form-input" style="width:100%;">
                    </div>
                </div>

                <h4 style="margin:16px 0 8px; font-size:13px; color:#6b7280; text-transform:uppercase;">Address</h4>
                <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Street</label><input type="text" name="street" value="<?= htmlspecialchars($shipTo['street'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:12px; margin-top:8px;">
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">City</label><input type="text" name="city" value="<?= htmlspecialchars($shipTo['city'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">State</label><input type="text" name="state" value="<?= htmlspecialchars($shipTo['state'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">ZIP</label><input type="text" name="zip" value="<?= htmlspecialchars($shipTo['zip'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Country</label><input type="text" name="country" value="<?= htmlspecialchars($shipTo['country'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                </div>

                <h4 style="margin:16px 0 8px; font-size:13px; color:#6b7280; text-transform:uppercase;">Contact</h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Contact Name</label><input type="text" name="contact_name" value="<?= htmlspecialchars($shipTo['contact_name'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Email</label><input type="email" name="email" value="<?= htmlspecialchars($shipTo['email'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Phone</label><input type="text" name="phone" value="<?= htmlspecialchars($shipTo['phone'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Fax</label><input type="text" name="fax" value="<?= htmlspecialchars($shipTo['fax'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                </div>

                <h4 style="margin:16px 0 8px; font-size:13px; color:#6b7280; text-transform:uppercase;">Inheritable Fields</h4>
                <p style="font-size:12px; color:#9ca3af; margin:0 0 12px;">When using customer default, the value is inherited from the parent customer record at runtime.</p>

                <!-- Sales Rep -->
                <div style="padding:10px; background:#f9fafb; border-radius:6px; margin-bottom:8px;">
                    <label style="font-size:13px; font-weight:500; display:flex; align-items:center; gap:6px; cursor:pointer; margin-bottom:6px;">
                        <input type="checkbox" name="override_sales_rep" value="1" x-model="overrideRep" class="form-checkbox">
                        Override Sales Rep <span style="font-size:11px; color:#9ca3af;" x-show="!overrideRep">(using customer default: <?= htmlspecialchars($custRep) ?>)</span>
                    </label>
                    <div x-show="overrideRep" x-cloak>
                        <select name="sales_rep_id" class="form-input" style="width:100%;">
                            <option value="">— None —</option>
                            <?php foreach ($reps as $r): ?><option value="<?= $r['id'] ?>" <?= ($shipTo['sales_rep_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['full_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Ship Via -->
                <div style="padding:10px; background:#f9fafb; border-radius:6px; margin-bottom:8px;">
                    <label style="font-size:13px; font-weight:500; display:flex; align-items:center; gap:6px; cursor:pointer; margin-bottom:6px;">
                        <input type="checkbox" name="override_ship_via" value="1" x-model="overrideShipVia" class="form-checkbox">
                        Override Ship Via <span style="font-size:11px; color:#9ca3af;" x-show="!overrideShipVia">(using customer default: <?= htmlspecialchars($custShipVia) ?>)</span>
                    </label>
                    <div x-show="overrideShipVia" x-cloak>
                        <select name="default_ship_via_id" class="form-input" style="width:100%;">
                            <option value="">— None —</option>
                            <?php foreach ($shipVias as $sv): ?><option value="<?= $sv['id'] ?>" <?= ($shipTo['default_ship_via_id'] ?? 0) == $sv['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sv['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Payment Terms -->
                <div style="padding:10px; background:#f9fafb; border-radius:6px; margin-bottom:8px;">
                    <label style="font-size:13px; font-weight:500; display:flex; align-items:center; gap:6px; cursor:pointer; margin-bottom:6px;">
                        <input type="checkbox" name="override_payment_terms" value="1" x-model="overrideTerms" class="form-checkbox">
                        Override Payment Terms <span style="font-size:11px; color:#9ca3af;" x-show="!overrideTerms">(using customer default: <?= htmlspecialchars($custTerms) ?>)</span>
                    </label>
                    <div x-show="overrideTerms" x-cloak>
                        <select name="payment_terms_id" class="form-input" style="width:100%;">
                            <option value="">— None —</option>
                            <?php foreach ($paymentTerms as $pt): ?><option value="<?= $pt['id'] ?>" <?= ($shipTo['payment_terms_id'] ?? 0) == $pt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pt['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Credit Limit -->
                <div style="padding:10px; background:#f9fafb; border-radius:6px; margin-bottom:8px;">
                    <label style="font-size:13px; font-weight:500; display:flex; align-items:center; gap:6px; cursor:pointer; margin-bottom:6px;">
                        <input type="checkbox" name="override_credit_limit" value="1" x-model="overrideCredit" class="form-checkbox">
                        Override Credit Limit <span style="font-size:11px; color:#9ca3af;" x-show="!overrideCredit">(using customer default: <?= htmlspecialchars($custCredit) ?>)</span>
                    </label>
                    <div x-show="overrideCredit" x-cloak>
                        <input type="number" step="0.01" name="credit_limit" value="<?= htmlspecialchars($shipTo['credit_limit'] ?? '0') ?>" class="form-input" style="width:200px;">
                    </div>
                </div>

                <div style="margin-top:12px;"><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Default Internal Notes</label><textarea name="default_internal_notes" rows="2" class="form-input" style="width:100%;"><?= htmlspecialchars($shipTo['default_internal_notes'] ?? '') ?></textarea></div>
                <div style="margin-top:8px;"><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Notes</label><textarea name="notes" rows="2" class="form-input" style="width:100%;"><?= htmlspecialchars($shipTo['notes'] ?? '') ?></textarea></div>

                <div style="margin-top:12px;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="active" value="1" <?= ($shipTo === null || !empty($shipTo['active'])) ? 'checked' : '' ?> class="form-checkbox"> Active
                    </label>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-top:16px;">
                <button type="submit" class="btn btn-primary"><?= $mode === 'edit' ? 'Save Changes' : 'Create Ship-To Location' ?></button>
                <a href="/customers/<?= $customer['id'] ?>#ship-to" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);</script>
</body>
</html>
