<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'edit' ? 'Edit Supplier' : 'New Supplier' ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
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

    <div style="max-width:800px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast">
                <?= htmlspecialchars($_SESSION['toast']['message']) ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">
                <?= $mode === 'edit' ? 'Edit Supplier: ' . htmlspecialchars($supplier['supplier_code'] ?? '') : 'New Supplier' ?>
            </h1>
            <a href="/suppliers" class="btn btn-secondary">&larr; Back to Suppliers</a>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="toast toast-error" style="margin-bottom:16px;">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= $mode === 'edit' ? '/suppliers/' . $supplier['id'] . '/edit' : '/suppliers/create' ?>">
            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">
                            Supplier Code <span style="color:red;">*</span>
                        </label>
                        <input type="text" name="supplier_code" value="<?= htmlspecialchars($supplier['supplier_code'] ?? '') ?>"
                               required class="form-input" style="width:100%; text-transform:uppercase;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">
                            Company Name <span style="color:red;">*</span>
                        </label>
                        <input type="text" name="company_name" value="<?= htmlspecialchars($supplier['company_name'] ?? '') ?>"
                               required class="form-input" style="width:100%;">
                    </div>
                </div>

                <h4 style="margin:16px 0 8px; font-size:13px; color:#6b7280; text-transform:uppercase;">Address</h4>
                <div>
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Street</label>
                    <input type="text" name="street" value="<?= htmlspecialchars($supplier['street'] ?? '') ?>" class="form-input" style="width:100%;">
                </div>
                <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:12px; margin-top:8px;">
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">City</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($supplier['city'] ?? '') ?>" class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">State</label>
                        <input type="text" name="state" value="<?= htmlspecialchars($supplier['state'] ?? '') ?>" class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">ZIP</label>
                        <input type="text" name="zip" value="<?= htmlspecialchars($supplier['zip'] ?? '') ?>" class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Country</label>
                        <input type="text" name="country" value="<?= htmlspecialchars($supplier['country'] ?? '') ?>" class="form-input" style="width:100%;">
                    </div>
                </div>

                <h4 style="margin:16px 0 8px; font-size:13px; color:#6b7280; text-transform:uppercase;">Contact &amp; Terms</h4>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>" class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Fax</label>
                        <input type="text" name="fax" value="<?= htmlspecialchars($supplier['fax'] ?? '') ?>" class="form-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Payment Terms</label>
                        <select name="payment_terms_id" class="form-input" style="width:100%;">
                            <option value="">— None —</option>
                            <?php foreach ($paymentTerms as $pt): ?>
                            <option value="<?= $pt['id'] ?>" <?= ($supplier['payment_terms_id'] ?? 0) == $pt['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pt['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-top:12px;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="active" value="1"
                               <?= ($supplier === null || !empty($supplier['active'])) ? 'checked' : '' ?> class="form-checkbox">
                        Active
                    </label>
                </div>

                <div style="margin-top:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Notes</label>
                    <textarea name="notes" rows="3" class="form-input" style="width:100%;"><?= htmlspecialchars($supplier['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Custom Fields -->
            <?php
            $cfRecordType = 'suppliers';
            $cfRecordId = $supplier['id'] ?? 0;
            if (isset($customFieldService)) {
                require __DIR__ . '/../partials/custom_fields_edit.php';
            }
            ?>

            <div style="display:flex; gap:8px; margin-top:16px;">
                <button type="submit" class="btn btn-primary"><?= $mode === 'edit' ? 'Save Changes' : 'Create Supplier' ?></button>
                <a href="/suppliers" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);
    </script>
</body>
</html>
