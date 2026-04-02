<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= $mode === 'edit' ? 'Edit' : 'New' ?> Price List — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:700px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div style="margin-bottom:16px;"><a href="/price-lists" style="color:#2563eb;">&larr; Back to Price Lists</a></div>
        <h1 style="margin:0 0 16px;"><?= $mode === 'edit' ? 'Edit Price List' : 'New Price List' ?></h1>

        <form method="POST" action="<?= $mode === 'edit' ? '/price-lists/'.$list['id'].'/edit' : '/price-lists/create' ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Name <span style="color:red;">*</span></label>
                <input type="text" name="name" value="<?= htmlspecialchars($list['name'] ?? '') ?>" required
                       style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
            </div>

            <div style="display:flex;gap:12px;margin-bottom:12px;">
                <div style="flex:1;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Type <span style="color:red;">*</span></label>
                    <select name="list_type" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
                        <option value="CUSTOMER" <?= ($list['list_type'] ?? '') === 'CUSTOMER' ? 'selected' : '' ?>>Customer</option>
                        <option value="SUPPLIER" <?= ($list['list_type'] ?? '') === 'SUPPLIER' ? 'selected' : '' ?>>Supplier</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Default Priority</label>
                    <input type="number" name="default_priority" value="<?= (int)($list['default_priority'] ?? 10) ?>"
                           style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
                    <small style="color:#6b7280;">Lower number = higher priority</small>
                </div>
            </div>

            <div style="display:flex;gap:12px;margin-bottom:12px;">
                <div style="flex:1;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Effective Date <span style="color:red;">*</span></label>
                    <input type="date" name="effective_date" value="<?= htmlspecialchars($list['effective_date'] ?? date('Y-m-d')) ?>" required
                           style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Expiration Date</label>
                    <input type="date" name="expiration_date" value="<?= htmlspecialchars($list['expiration_date'] ?? '') ?>"
                           style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
                    <small style="color:#6b7280;">Leave blank for no expiration</small>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Notes</label>
                <textarea name="notes" rows="3" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;"><?= htmlspecialchars($list['notes'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" name="active" value="1" <?= (!$list || $list['active']) ? 'checked' : '' ?>>
                    Active
                </label>
            </div>

            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $mode === 'edit' ? 'Save Changes' : 'Create Price List' ?></button>
                <a href="/price-lists" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
