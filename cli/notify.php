<?php
// Precision Ink ERP — Notification dispatcher (cron)
// Run every 15 minutes: crontab -e -> */15 * * * * php /path/to/cli/notify.php

require_once __DIR__ . '/../vendor/autoload.php';

// Load config
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    echo "Config file not found. Copy config.php.template to config.php.\n";
    exit(1);
}
$config = require $configFile;

try {
    $pdo = new PDO(
        "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4",
        $config['DB_USER'], $config['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

$emailService = new \App\Services\EmailService($pdo, null);
$notificationService = new \App\Services\NotificationService($pdo, $emailService);

echo "[" . date('Y-m-d H:i:s') . "] Starting notification sweep...\n";

// 1. Run all scheduled notification checks
$notificationService->runScheduledChecks();
echo "  Scheduled checks complete.\n";

// 2. Quote expiry sweep
$expired = $pdo->exec("UPDATE quotes SET status = 'EXPIRED', updated_at = NOW() WHERE status IN ('DRAFT','SENT') AND expiration_date < CURDATE()");
echo "  Expired {$expired} quotes.\n";

// Release reservations for recently expired quotes
$stmt = $pdo->query("SELECT id FROM quotes WHERE status = 'EXPIRED' AND updated_at >= DATE_SUB(NOW(), INTERVAL 20 MINUTE)");
$recentlyExpired = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($recentlyExpired as $quoteId) {
    $pdo->prepare("DELETE FROM inventory_reservations WHERE reservation_type = 'QUOTE' AND reference_id = ?")->execute([$quoteId]);
}
if (!empty($recentlyExpired)) echo "  Released reservations for " . count($recentlyExpired) . " expired quote(s).\n";

// 3. Month-end inventory snapshot
if (date('d') === date('t')) {
    $snapshotDate = date('Y-m-d');
    $existing = $pdo->prepare("SELECT COUNT(*) FROM inventory_snapshots WHERE snapshot_date = ?");
    $existing->execute([$snapshotDate]);
    if ((int)$existing->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO inventory_snapshots (snapshot_date, item_id, facility_id, quantity_on_hand, unit_cost, total_value)
            SELECT CURDATE(), fl.item_id, fl.facility_id,
                   SUM(fl.remaining_quantity),
                   CASE WHEN SUM(fl.remaining_quantity) > 0 THEN SUM(fl.remaining_quantity * fl.unit_cost) / SUM(fl.remaining_quantity) ELSE 0 END,
                   SUM(fl.remaining_quantity * fl.unit_cost)
            FROM fifo_lots fl
            WHERE fl.status = 'AVAILABLE' AND fl.remaining_quantity > 0
            GROUP BY fl.item_id, fl.facility_id
        ");
        echo "  Month-end snapshot taken for {$snapshotDate}.\n";
    }
}

// 4. Update last run timestamp
$pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES ('cron_notify_last_run', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()")
    ->execute([date('Y-m-d H:i:s')]);

echo "[" . date('Y-m-d H:i:s') . "] Notification sweep complete.\n";
