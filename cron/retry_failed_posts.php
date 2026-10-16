<?php

declare(strict_types=1);

/**
 * Cron: Retry failed publish attempts
 * Recommended: every 15 min — * /15 (no space) * * * * php /path/to/cron/retry_failed_posts.php
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

echo '[' . date('Y-m-d H:i:s') . '] Starting retry...' . PHP_EOL;

try {
    $scheduler = new \App\Services\SchedulerService();
    $results = $scheduler->retryFailedPosts();
    echo "Retried: {$results['retried']}, Success: {$results['success']}" . PHP_EOL;
} catch (Throwable $e) {
    \App\Helpers\Logger::error('Retry cron failed', ['error' => $e->getMessage()]);
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo '[' . date('Y-m-d H:i:s') . '] Done.' . PHP_EOL;
