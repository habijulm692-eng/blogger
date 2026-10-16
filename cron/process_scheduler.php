<?php

declare(strict_types=1);

/**
 * Cron: Process scheduled posts for publishing
 * Recommended: every 5 min — * /5 (no space) * * * * php /path/to/cron/process_scheduler.php
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

echo '[' . date('Y-m-d H:i:s') . '] Starting scheduler...' . PHP_EOL;

try {
    $scheduler = new \App\Services\SchedulerService();
    $results = $scheduler->processDuePosts();
    echo "Processed: {$results['processed']}, Success: {$results['success']}, Failed: {$results['failed']}, Skipped: {$results['skipped']}" . PHP_EOL;
} catch (Throwable $e) {
    \App\Helpers\Logger::error('Scheduler cron failed', ['error' => $e->getMessage()]);
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo '[' . date('Y-m-d H:i:s') . '] Done.' . PHP_EOL;
