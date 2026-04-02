<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'edit' ? 'Edit Customer' : 'New Customer' ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
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
            <h1 style="margin:0;"><?= $mode === 'edit' ? 'Edit Customer: ' . htmlspecialchars($customer['customer_code'] ?? '') : 'New Customer' ?></h1>
            <a href="/customers" class="btn btn-secondary">&larr; Back</a>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="toast toast-error" style="margin-bottom:16px;"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= $mode === 'edit' ? '/customers/' . $customer['id'] . '/edit' : '/customers/create' ?>">
            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Customer Code <span style="color:red;">*</span></label><input type="text" name="customer_code" value="<?= htmlspecialchars($customer['customer_code'] ?? '') ?>" required class="form-input" style="width:100%; text-transform:uppercase;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Company Name <span style="color:red;">*</span></label><input type="text" name="company_name" value="<?= htmlspecialchars($customer['company_name'] ?? '') ?>" required class="form-input" style="width:100%;"></div>
                </div>

                <h4 style="margin:16px 0 8px; font-size:13px; color:#6b7280; text-transform:uppercase;">Billing Address</h4>
                <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Street</label><input type="text" name="billing_street" value="<?= htmlspecialchars($customer['billing_street'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:12px; margin-top:8px;">
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">City</label><input type="text" name="billing_city" value="<?= htmlspecialchars($customer['billing_city'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">State</label><input type="text" name="billing_state" value="<?= htmlspecialchars($customer['billing_state'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">ZIP</label><input type="text" name="billing_zip" value="<?= htmlspecialchars($customer['billing_zip'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Country</label><input type="text" name="billing_country" value="<?= htmlspecialchars($customer['billing_country'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                </div>

                <h4 style="margin:16px 0 8px; font-size:13px; color:#6b7280; text-transform:uppercase;">Contact &amp; Terms</h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Phone</label><input type="text" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Fax</label><input type="text" name="fax" value="<?= htmlspecialchars($customer['fax'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Payment Terms</label><select name="payment_terms_id" class="form-input" style="width:100%;"><option value="">— None —</option><?php foreach ($paymentTerms as $pt): ?><option value="<?= $pt['id'] ?>" <?= ($customer['payment_terms_id'] ?? 0) == $pt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pt['name']) ?></option><?php endforeach; ?></select></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Sales Rep</label><select name="sales_rep_id" class="form-input" style="width:100%;"><option value="">— None —</option><?php foreach ($reps as $r): ?><option value="<?= $r['id'] ?>" <?= ($customer['sales_rep_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['full_name']) ?></option><?php endforeach; ?></select></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Default Ship Via</label><select name="default_ship_via_id" class="form-input" style="width:100%;"><option value="">— None —</option><?php foreach ($shipVias as $sv): ?><option value="<?= $sv['id'] ?>" <?= ($customer['default_ship_via_id'] ?? 0) == $sv['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sv['name']) ?></option><?php endforeach; ?></select></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Credit Limit ($0 = No Limit)</label><input type="number" step="0.01" name="credit_limit" value="<?= htmlspecialchars($customer['credit_limit'] ?? '0') ?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Lead Time (days)</label><input type="number" name="lead_time_days" value="<?= htmlspecialchars($customer['lead_time_days'] ?? '') ?>" class="form-input" style="width:100%;"></div>
                </div>

                <div style="margin-top:12px; display:flex; gap:24px; flex-wrap:wrap;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;"><input type="checkbox" name="tax_exempt" value="1" <?= !empty($customer['tax_exempt']) ? 'checked' : '' ?> class="form-checkbox"> Tax Exempt</label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;"><input type="checkbox" name="account_hold" value="1" id="holdCheck" <?= !empty($customer['account_hold']) ? 'checked' : '' ?> class="form-checkbox" onchange="document.getElementById('holdReason').style.display=this.checked?'block':'none'"> Account Hold</label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;"><input type="checkbox" name="active" value="1" <?= ($customer === null || !empty($customer['active'])) ? 'checked' : '' ?> class="form-checkbox"> Active</label>
                </div>

                <div id="holdReason" style="margin-top:8px; <?= empty($customer['account_hold']) ? 'display:none;' : '' ?>">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Hold Reason <span style="color:red;">*</span></label>
                    <textarea name="account_hold_reason" rows="2" class="form-input" style="width:100%;"><?= htmlspecialchars($customer['account_hold_reason'] ?? '') ?></textarea>
                </div>

                <div style="margin-top:12px;"><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Default Internal Notes</label><textarea name="default_internal_notes" rows="2" class="form-input" style="width:100%;"><?= htmlspecialchars($customer['default_internal_notes'] ?? '') ?></textarea></div>
                <div style="margin-top:8px;"><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Notes</label><textarea name="notes" rows="3" class="form-input" style="width:100%;"><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea></div>
            </div>

            <?php $cfRecordType = 'customers'; $cfRecordId = $customer['id'] ?? 0; if (isset($customFieldService)) require __DIR__ . '/../partials/custom_fields_edit.php'; ?>

            <div style="display:flex; gap:8px; margin-top:16px;">
                <button type="submit" class="btn btn-primary"><?= $mode === 'edit' ? 'Save Changes' : 'Create Customer' ?></button>
                <a href="/customers" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);</script>
</body>
</html>
