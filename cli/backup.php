<?php
/**
 * Precision Ink ERP – Database backup script
 * Usage: php cli/backup.php
 * Can be called from cron or via the System Health "Run Backup Now" button.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

// Determine backup path and retention from config or system_settings
$backupPath = $config['BACKUP_PATH'] ?? __DIR__ . '/../storage/backups';
$retention = 7;

// Try to read from system_settings if DB is available
try {
    $pdo = new PDO(
        "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4",
        $config['DB_USER'],
        $config['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('backup_path', 'backup_retention_count')");
    $stmt->execute();
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    if (!empty($settings['backup_path'])) {
        $backupPath = $settings['backup_path'];
    }
    if (!empty($settings['backup_retention_count'])) {
        $retention = (int) $settings['backup_retention_count'];
    }
} catch (Exception $e) {
    echo "Warning: Could not read system_settings: {$e->getMessage()}\n";
}

// Create backup directory if needed
if (!is_dir($backupPath)) {
    if (!mkdir($backupPath, 0755, true)) {
        echo "ERROR: Cannot create backup directory: {$backupPath}\n";
        exit(1);
    }
}

$filename = 'backup_' . date('Y-m-d_His') . '.sql.gz';
$filepath = $backupPath . '/' . $filename;

echo "Starting backup to {$filepath}...\n";

$cmd = sprintf(
    'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s 2>&1 | gzip > %s',
    escapeshellarg($config['DB_HOST']),
    escapeshellarg($config['DB_USER']),
    escapeshellarg($config['DB_PASS']),
    escapeshellarg($config['DB_NAME']),
    escapeshellarg($filepath)
);

exec($cmd, $output, $exitCode);

if ($exitCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
    $sizeMb = round(filesize($filepath) / 1024 / 1024, 2);
    echo "Backup created: {$filename} ({$sizeMb} MB)\n";

    // Rotate old backups
    $files = glob($backupPath . '/backup_*.sql.gz');
    if ($files && count($files) > $retention) {
        usort($files, fn($a, $b) => filemtime($a) - filemtime($b)); // oldest first
        $toDelete = array_slice($files, 0, count($files) - $retention);
        foreach ($toDelete as $file) {
            unlink($file);
            echo "Deleted old backup: " . basename($file) . "\n";
        }
    }

    // Update last run timestamp
    try {
        if (isset($pdo)) {
            $pdo->prepare(
                "INSERT INTO system_settings (setting_key, setting_value) VALUES ('cron_backup_last_run', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            )->execute([date('Y-m-d H:i:s')]);
        }
    } catch (Exception $e) {
        echo "Warning: Could not update cron timestamp: {$e->getMessage()}\n";
    }

    echo "Done.\n";
} else {
    echo "Backup FAILED (exit code {$exitCode})\n";
    if ($output) {
        echo implode("\n", $output) . "\n";
    }
    // Clean up empty/failed file
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    exit(1);
}
