<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Dashboard') ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .dashboard-content { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .ann-banner { border-left: 4px solid; padding: 14px 18px; margin-bottom: 10px; border-radius: 4px; display: flex; justify-content: space-between; align-items: flex-start; }
        .ann-banner-info { background: #eff6ff; border-color: #3b82f6; color: #1e40af; }
        .ann-banner-warning { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
        .ann-banner-urgent { background: #fef2f2; border-color: #ef4444; color: #991b1b; }
        .ann-banner-title { font-weight: 700; margin-bottom: 4px; }
        .ann-banner-message { font-size: 13px; line-height: 1.5; }
        .ann-dismiss { background: none; border: none; font-size: 20px; cursor: pointer; opacity: 0.5; padding: 0 4px; line-height: 1; }
        .ann-dismiss:hover { opacity: 1; }
        .widget-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .widget-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        .widget-card h3 { margin: 0 0 8px; font-size: 14px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .widget-card .widget-value { font-size: 28px; font-weight: 700; color: #1a1a2e; }
        <?php $pqCount = count($_SESSION['print_queue'] ?? []); ?>
    </style>
</head>
<body>
    <header class="app-header">
        <div class="header-left">
            <a href="/" class="app-logo">Precision Ink ERP</a>
        </div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php if ($pqCount > 0): ?>
                <a href="/settings/print-queue" style="color:#fff; text-decoration:none; font-size:14px;">&#128424; (<?= $pqCount ?>)</a>
            <?php endif; ?>
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?>
                <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span>
            <?php endif; ?>
            <a href="/settings" style="color:#fff; text-decoration:none; font-size:13px; opacity:0.8;">Settings</a>
        </div>
    </header>

    <div class="dashboard-content">
        <!-- Announcement Banners -->
        <?php if (!empty($announcements)): ?>
            <div id="announcement-banners" style="margin-bottom:20px;">
                <?php foreach ($announcements as $ann): ?>
                    <?php
                    $bannerClass = match($ann['priority']) {
                        'URGENT'  => 'ann-banner-urgent',
                        'WARNING' => 'ann-banner-warning',
                        default   => 'ann-banner-info',
                    };
                    ?>
                    <div class="ann-banner <?= $bannerClass ?>" id="ann-<?= $ann['id'] ?>">
                        <div>
                            <div class="ann-banner-title"><?= htmlspecialchars($ann['title']) ?></div>
                            <div class="ann-banner-message"><?= $ann['message'] ?></div>
                        </div>
                        <button class="ann-dismiss" onclick="dismissAnnouncement(<?= $ann['id'] ?>)" title="Dismiss">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h1 style="margin-bottom:20px;">Dashboard</h1>

        <!-- Widget Grid (shell — populated by future sessions) -->
        <div class="widget-grid">
            <div class="widget-card">
                <h3>Open Sales Orders</h3>
                <div class="widget-value">—</div>
            </div>
            <div class="widget-card">
                <h3>Open Purchase Orders</h3>
                <div class="widget-value">—</div>
            </div>
            <div class="widget-card">
                <h3>Low Stock Items</h3>
                <div class="widget-value">—</div>
            </div>
            <div class="widget-card">
                <h3>Pending QC</h3>
                <div class="widget-value">—</div>
            </div>
        </div>
    </div>

    <script>
    function dismissAnnouncement(id) {
        var banner = document.getElementById('ann-' + id);
        if (banner) {
            banner.style.transition = 'opacity 0.3s ease, max-height 0.3s ease';
            banner.style.opacity = '0';
            banner.style.maxHeight = '0';
            banner.style.overflow = 'hidden';
            banner.style.padding = '0 18px';
            banner.style.marginBottom = '0';
            setTimeout(function() { banner.remove(); }, 300);
        }
        fetch('/announcements/dismiss/' + id, {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
    }
    </script>
</body>
</html>
