<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Settings') ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
</head>
<body>
    <header class="app-header">
        <div class="header-left">
            <a href="/" class="app-logo">Precision Ink ERP</a>
        </div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php
            // Facility selector
            $__facDb = \PrecisionInk\Controllers\BaseController::getSharedDb();
            $__facSvc = $__facDb ? new \App\Services\FacilityService($__facDb) : null;
            $__currentUser = $_SESSION['user'] ?? null;
            if ($__facSvc && $__currentUser):
                $__activeFacilityCount = $__facSvc->getActiveFacilityCount();
                if ($__activeFacilityCount > 1):
                    $__userFacilities = $__facSvc->getUserFacilities((int)$__currentUser['id']);
                    $__activeFacility = $__facSvc->getActiveFacility((int)$__currentUser['id']);
                    if (count($__userFacilities) > 1):
            ?>
            <form method="POST" action="/facility/switch" id="facility-form" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <select name="facility_id"
                        onchange="document.getElementById('facility-form').submit()"
                        style="padding:4px 8px; border-radius:4px; border:1px solid #555; background:#fff; font-size:13px;">
                    <?php foreach ($__userFacilities as $__f): ?>
                    <option value="<?= $__f['id'] ?>"
                            <?= $__f['id'] == $__activeFacility['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($__f['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php elseif (count($__userFacilities) === 1): ?>
            <span style="color:#fff; font-size:13px;"><?= htmlspecialchars($__activeFacility['name']) ?></span>
            <?php endif; endif; endif; ?>

            <?php $pqCount = count($_SESSION['print_queue'] ?? []); ?>
            <a href="/settings/print-queue" class="print-queue-badge-link" style="color:#fff; text-decoration:none; font-size:13px; <?= $pqCount > 0 ? '' : 'display:none;' ?>">
                &#128424; <span class="print-queue-badge"><?= $pqCount ?></span>
            </a>
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?>
                <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span>
            <?php endif; ?>
        </div>
    </header>

    <div class="settings-wrapper">
        <!-- Left Sub-Navigation -->
        <nav class="settings-nav">
            <h3 class="nav-heading">Settings</h3>

            <div class="nav-group">
                <h4 class="nav-group-title">System</h4>
                <a href="/settings/company" class="nav-link <?= ($section ?? '') === 'company' ? 'active' : '' ?>">Company</a>
                <a href="/settings/thresholds" class="nav-link <?= ($section ?? '') === 'thresholds' ? 'active' : '' ?>">Thresholds &amp; Defaults</a>
            </div>

            <div class="nav-group">
                <h4 class="nav-group-title">Dropdown Lists</h4>
                <a href="/settings/uom" class="nav-link <?= ($section ?? '') === 'uom' ? 'active' : '' ?>">Units of Measure</a>
                <a href="/settings/ship-via" class="nav-link <?= ($section ?? '') === 'ship-via' ? 'active' : '' ?>">Ship Via</a>
                <a href="/settings/payment-terms" class="nav-link <?= ($section ?? '') === 'payment-terms' ? 'active' : '' ?>">Payment Terms</a>
                <a href="/settings/surcharge-types" class="nav-link <?= ($section ?? '') === 'surcharge-types' ? 'active' : '' ?>">Surcharge Types</a>
                <a href="/settings/rma-reasons" class="nav-link <?= ($section ?? '') === 'rma-reasons' ? 'active' : '' ?>">RMA Return Reasons</a>
                <a href="/settings/lost-quote-reasons" class="nav-link <?= ($section ?? '') === 'lost-quote-reasons' ? 'active' : '' ?>">Lost Quote Reasons</a>
                <a href="/settings/landed-cost-types" class="nav-link <?= ($section ?? '') === 'landed-cost-types' ? 'active' : '' ?>">Landed Cost Types</a>
                <a href="/settings/reason-codes" class="nav-link <?= ($section ?? '') === 'reason-codes' ? 'active' : '' ?>">Reason Codes</a>
                <a href="/settings/industry-segments" class="nav-link <?= ($section ?? '') === 'industry-segments' ? 'active' : '' ?>">Industry Segments</a>
            </div>

            <div class="nav-group">
                <h4 class="nav-group-title">Items</h4>
                <a href="/settings/item-prototypes" class="nav-link <?= ($section ?? '') === 'item-prototypes' ? 'active' : '' ?>">Item Prototypes</a>
                <a href="/settings/batch-templates" class="nav-link <?= ($section ?? '') === 'batch-templates' ? 'active' : '' ?>">Batch Templates</a>
            </div>

            <div class="nav-group">
                <h4 class="nav-group-title">Document</h4>
                <a href="/settings/document-numbering" class="nav-link <?= ($section ?? '') === 'document-numbering' ? 'active' : '' ?>">Numbering Sequences</a>
                <a href="/settings/email-templates" class="nav-link <?= ($section ?? '') === 'email-templates' ? 'active' : '' ?>">Email Templates</a>
                <span class="nav-link disabled">Document Templates <em>(coming soon)</em></span>
            </div>

            <div class="nav-group">
                <h4 class="nav-group-title">Facilities</h4>
                <a href="/settings/facilities" class="nav-link <?= ($section ?? '') === 'facilities' ? 'active' : '' ?>">Facilities</a>
                <a href="/settings/equipment" class="nav-link <?= ($section ?? '') === 'equipment' ? 'active' : '' ?>">Equipment</a>
            </div>

            <div class="nav-group">
                <h4 class="nav-group-title">Users &amp; Security</h4>
                <a href="/users" class="nav-link <?= ($section ?? '') === 'users' ? 'active' : '' ?>">Users</a>
                <a href="/groups" class="nav-link <?= ($section ?? '') === 'groups' ? 'active' : '' ?>">Groups &amp; Permissions</a>
                <a href="/settings/password-policy" class="nav-link <?= ($section ?? '') === 'password-policy' ? 'active' : '' ?>">Password Policy</a>
                <a href="/settings/api-keys" class="nav-link <?= ($section ?? '') === 'api-keys' ? 'active' : '' ?>">API Keys</a>
                <a href="/settings/user-activity" class="nav-link <?= ($section ?? '') === 'user-activity' ? 'active' : '' ?>">User Activity</a>
            </div>

            <div class="nav-group">
                <h4 class="nav-group-title">Email &amp; Notifications</h4>
                <a href="/settings/smtp" class="nav-link <?= ($section ?? '') === 'smtp' ? 'active' : '' ?>">SMTP Settings</a>
                <a href="/settings/notifications" class="nav-link <?= ($section ?? '') === 'notifications' ? 'active' : '' ?>">Notifications</a>
            </div>

            <div class="nav-group">
                <h4 class="nav-group-title">Logs</h4>
                <a href="/settings/notification-log" class="nav-link <?= ($section ?? '') === 'notification-log' ? 'active' : '' ?>">Notification Log</a>
                <a href="/settings/audit-log" class="nav-link <?= ($section ?? '') === 'audit-log' ? 'active' : '' ?>">Audit Log</a>
                <a href="/settings/email-log" class="nav-link <?= ($section ?? '') === 'email-log' ? 'active' : '' ?>">Email Log</a>
            </div>

            <div class="nav-group">
                <h4 class="nav-group-title">Advanced</h4>
                <a href="/settings/custom-fields" class="nav-link <?= ($section ?? '') === 'custom-fields' ? 'active' : '' ?>">Custom Fields</a>
                <a href="/settings/announcements" class="nav-link <?= ($section ?? '') === 'announcements' ? 'active' : '' ?>">Announcements</a>
                <a href="/settings/scheduled-reports" class="nav-link <?= ($section ?? '') === 'scheduled-reports' ? 'active' : '' ?>">Scheduled Reports</a>
                <a href="/settings/import-export" class="nav-link <?= ($section ?? '') === 'import-export' ? 'active' : '' ?>">Import / Export</a>
                <a href="/settings/system-health" class="nav-link <?= ($section ?? '') === 'system-health' ? 'active' : '' ?>">System Health</a>
                <a href="/settings/print-queue" class="nav-link <?= ($section ?? '') === 'print-queue' ? 'active' : '' ?>">Print Queue</a>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="settings-content">
            <?php if (isset($_SESSION['toast'])): ?>
                <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast">
                    <?= htmlspecialchars($_SESSION['toast']['message']) ?>
                    <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php unset($_SESSION['toast']); ?>
            <?php endif; ?>

            <?php if (isset($content)):
                require __DIR__ . '/../' . $content . '.php';
            endif; ?>
        </main>
    </div>

    <script src="/assets/js/settings.js"></script>
</body>
</html>
