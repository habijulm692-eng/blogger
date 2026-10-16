<?php

declare(strict_types=1);

/**
 * Cron: Cleanup old logs
 * Recommended: 0 3 * * * php /path/to/cron/cleanup_logs.php
 */

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

require BASE_PATH . '/app/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH);

$appConfig = require BASE_PATH . '/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Dhaka');

\App\Helpers\Logger::init(BASE_PATH . '/storage/logs');

$retentionDays = $appConfig['logging']['retention_days'] ?? 30;

echo '[' . date('Y-m-d H:i:s') . "] Cleaning logs older than {$retentionDays} days..." . PHP_EOL;

try {
    $db = \App\Helpers\Database::getInstance();

    $stmt = $db->prepare('DELETE FROM ai_usage_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
    $stmt->execute([$retentionDays]);
    echo "AI usage logs deleted: " . $stmt->rowCount() . PHP_EOL;

    $stmt = $db->prepare('DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
    $stmt->execute([$retentionDays]);
    echo "Activity logs deleted: " . $stmt->rowCount() . PHP_EOL;

    $stmt = $db->prepare('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
    $stmt->execute([7]);
    echo "Login attempts deleted: " . $stmt->rowCount() . PHP_EOL;

    // Clean old log files
    $logPath = BASE_PATH . '/storage/logs';
    $cutoff = time() - ($retentionDays * 86400);
    $deleted = 0;
    foreach (glob($logPath . '/app-*.log') as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
            $deleted++;
        }
    }
    echo "Log files deleted: {$deleted}" . PHP_EOL;
} catch (Throwable $e) {
    \App\Helpers\Logger::error('Cleanup cron failed', ['error' => $e->getMessage()]);
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo '[' . date('Y-m-d H:i:s') . '] Done.' . PHP_EOL;
