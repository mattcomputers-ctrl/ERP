<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>QuickBooks Sync — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css"></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1000px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">QuickBooks Sync</h1>
            <div style="display:flex;gap:8px;">
                <a href="/qb-sync/log" class="btn btn-secondary">Sync Log</a>
                <a href="/settings/quickbooks" class="btn btn-secondary">Settings</a>
            </div>
        </div>

        <!-- Mode Tabs -->
        <div class="tab-bar" style="margin-bottom:16px;">
            <button class="tab-btn <?= $mode === 'DESKTOP' ? 'active' : '' ?>" onclick="switchMode('desktop')">QB Desktop (IIF Export)</button>
            <button class="tab-btn <?= $mode === 'ONLINE' ? 'active' : '' ?>" onclick="switchMode('online')">QB Online (API)</button>
        </div>

        <!-- Desktop Tab -->
        <div id="tab-desktop" style="<?= $mode === 'ONLINE' ? 'display:none;' : '' ?>">
            <p style="color:#6b7280;font-size:13px;margin-bottom:16px;">
                Export data as IIF files to import into QuickBooks Desktop via <strong>File &rarr; Utilities &rarr; Import &rarr; IIF Files</strong>.
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <!-- Invoices -->
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:16px;">
                    <h3 style="margin:0 0 8px;font-size:14px;">Invoices</h3>
                    <p style="font-size:24px;font-weight:700;color:#2563eb;margin:0;"><?= (int)$counts['invoices'] ?></p>
                    <p style="font-size:12px;color:#6b7280;margin:0 0 12px;">unsynced invoices</p>
                    <form method="POST" action="/qb-sync/export/invoices" style="display:flex;gap:8px;align-items:center;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit" class="btn btn-primary btn-sm" <?= $counts['invoices'] == 0 ? 'disabled' : '' ?>>Download IIF</button>
                        <label style="font-size:11px;display:inline-flex;align-items:center;gap:4px;cursor:pointer;">
                            <input type="checkbox" name="include_all" value="1"> Include synced
                        </label>
                    </form>
                </div>

                <!-- Vendor Bills -->
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:16px;">
                    <h3 style="margin:0 0 8px;font-size:14px;">Vendor Bills</h3>
                    <p style="font-size:24px;font-weight:700;color:#dc2626;margin:0;"><?= (int)$counts['bills'] ?></p>
                    <p style="font-size:12px;color:#6b7280;margin:0 0 12px;">unsynced PO receipts</p>
                    <form method="POST" action="/qb-sync/export/bills" style="display:flex;gap:8px;align-items:center;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit" class="btn btn-primary btn-sm" <?= $counts['bills'] == 0 ? 'disabled' : '' ?>>Download IIF</button>
                        <label style="font-size:11px;display:inline-flex;align-items:center;gap:4px;cursor:pointer;">
                            <input type="checkbox" name="include_all" value="1"> Include synced
                        </label>
                    </form>
                </div>

                <!-- Customers -->
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:16px;">
                    <h3 style="margin:0 0 8px;font-size:14px;">Customers</h3>
                    <p style="font-size:24px;font-weight:700;color:#166534;margin:0;"><?= (int)$counts['customers'] ?></p>
                    <p style="font-size:12px;color:#6b7280;margin:0 0 12px;">active customers</p>
                    <form method="POST" action="/qb-sync/export/customers">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Download IIF</button>
                    </form>
                </div>

                <!-- Vendors -->
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:16px;">
                    <h3 style="margin:0 0 8px;font-size:14px;">Vendors</h3>
                    <p style="font-size:24px;font-weight:700;color:#854d0e;margin:0;"><?= (int)$counts['vendors'] ?></p>
                    <p style="font-size:12px;color:#6b7280;margin:0 0 12px;">active vendors</p>
                    <form method="POST" action="/qb-sync/export/vendors">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Download IIF</button>
                    </form>
                </div>

                <!-- Items -->
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:16px;">
                    <h3 style="margin:0 0 8px;font-size:14px;">Items</h3>
                    <p style="font-size:24px;font-weight:700;color:#7c3aed;margin:0;"><?= (int)$counts['items'] ?></p>
                    <p style="font-size:12px;color:#6b7280;margin:0 0 12px;">active items</p>
                    <form method="POST" action="/qb-sync/export/items">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Download IIF</button>
                    </form>
                </div>

                <!-- Inventory Valuation -->
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:16px;">
                    <h3 style="margin:0 0 8px;font-size:14px;">Inventory Valuation</h3>
                    <p style="font-size:13px;color:#6b7280;margin:0 0 12px;">Journal entry with current FIFO inventory value by GL group</p>
                    <form method="POST" action="/qb-sync/export/inventory">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Download IIF</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Online Tab -->
        <div id="tab-online" style="<?= $mode === 'DESKTOP' ? 'display:none;' : '' ?>">
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:24px;margin-bottom:16px;">
                <h3 style="margin:0 0 12px;">Connection Status</h3>
                <?php if ($isConnected): ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                        <span style="display:inline-block;width:12px;height:12px;background:#16a34a;border-radius:50%;"></span>
                        <span style="color:#16a34a;font-weight:600;">Connected</span>
                        <?php if ($companyInfo): ?>
                            <span style="color:#6b7280;">— <?= htmlspecialchars($companyInfo['CompanyName'] ?? '') ?></span>
                        <?php endif; ?>
                    </div>
                    <form method="POST" action="/qb-sync/oauth/disconnect">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Disconnect from QuickBooks Online?')">Disconnect</button>
                    </form>
                <?php else: ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                        <span style="display:inline-block;width:12px;height:12px;background:#dc2626;border-radius:50%;"></span>
                        <span style="color:#dc2626;font-weight:600;">Not Connected</span>
                    </div>
                    <a href="/qb-sync/oauth/connect" class="btn btn-primary">Connect to QuickBooks Online</a>
                    <p style="font-size:12px;color:#6b7280;margin-top:8px;">Configure Client ID and Secret in <a href="/settings/quickbooks" style="color:#2563eb;">QuickBooks Settings</a> first.</p>
                <?php endif; ?>
            </div>

            <div style="background:#fefce8;border:1px solid #fde68a;border-radius:6px;padding:16px;">
                <h4 style="margin:0 0 8px;">QB Online Sync — Coming Soon</h4>
                <p style="font-size:13px;color:#6b7280;margin:0;">
                    Phase 2 will add automatic sync of invoices, bills, customers, vendors, and items to QuickBooks Online via REST API.
                    The OAuth connection framework is ready — configure your Intuit Developer app credentials in Settings to connect.
                </p>
            </div>
        </div>
    </div>
    <script>
    var toast=document.getElementById('toast');if(toast)setTimeout(function(){toast.style.display='none';},4000);
    function switchMode(mode) {
        document.getElementById('tab-desktop').style.display = mode === 'desktop' ? '' : 'none';
        document.getElementById('tab-online').style.display = mode === 'online' ? '' : 'none';
        document.querySelectorAll('.tab-btn').forEach(function(b,i) { b.classList.toggle('active', i === (mode==='desktop'?0:1)); });
    }
    </script>
</body>
</html>
