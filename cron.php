<?php

declare(strict_types=1);

/**
 * Web Cron Entry Point (for shared hosting without CLI cron)
 *
 * Usage (external services like cron-job.org):
 *   https://yourdomain.com/cron.php?token=YOUR_SECRET&job=scheduler
 *   https://yourdomain.com/cron.php?token=YOUR_SECRET&job=all
 *
 * Available jobs:
 *   scheduler  → Process due scheduled posts
 *   retry      → Retry failed publish attempts
 *   sources    → Fetch new items from source websites
 *   cleanup    → Delete old logs
 *   all        → Run scheduler + retry + sources (recommended every 5–10 min)
 *
 * Security:
 *   - Requires CRON_SECRET from .env (or query param matching it)
 *   - Optional IP whitelist via CRON_ALLOWED_IPS
 *   - Returns JSON so you can monitor success/failure
 */

define('BASE_PATH', __DIR__);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH);

$appConfig = require BASE_PATH . '/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Dhaka');

\App\Helpers\Logger::init(BASE_PATH . '/storage/logs');

// ---------------------------------------------------------------------------
// Security: Token check
// ---------------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$secret = $_ENV['CRON_SECRET'] ?? '';
$providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';

if ($secret === '' || !hash_equals($secret, (string) $providedToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Forbidden: invalid or missing token',
        'time'    => date('c'),
    ]);
    exit;
}

// Optional IP whitelist (comma-separated in .env)
$allowedIps = array_filter(array_map('trim', explode(',', $_ENV['CRON_ALLOWED_IPS'] ?? '')));
if ($allowedIps !== []) {
    $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';
    // Take first IP if X-Forwarded-For has multiple
    $clientIp = trim(explode(',', $clientIp)[0]);
    if (!in_array($clientIp, $allowedIps, true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error'   => 'Forbidden: IP not allowed',
            'time'    => date('c'),
        ]);
        exit;
    }
}

// ---------------------------------------------------------------------------
// Job runner
// ---------------------------------------------------------------------------
$job = strtolower(trim($_GET['job'] ?? 'all'));
$startTime = microtime(true);
$results = [];
$errors  = [];

$run = function (string $name, callable $fn) use (&$results, &$errors): void {
    try {
        $results[$name] = $fn();
    } catch (Throwable $e) {
        $errors[$name] = $e->getMessage();
        \App\Helpers\Logger::error("Web cron [{$name}] failed", ['error' => $e->getMessage()]);
        $results[$name] = ['error' => $e->getMessage()];
    }
};

switch ($job) {
    case 'scheduler':
        $run('scheduler', function () {
            $svc = new \App\Services\SchedulerService();
            return $svc->processDuePosts();
        });
        break;

    case 'retry':
        $run('retry', function () {
            $svc = new \App\Services\SchedulerService();
            return $svc->retryFailedPosts();
        });
        break;

    case 'sources':
        $run('sources', function () {
            $svc = new \App\Services\SourceFetchService();
            return $svc->fetchAll();
        });
        break;

    case 'cleanup':
        $run('cleanup', function () use ($appConfig) {
            $retentionDays = $appConfig['logging']['retention_days'] ?? 30;
            $db = \App\Helpers\Database::getInstance();
            $out = [];

            $stmt = $db->prepare('DELETE FROM ai_usage_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
            $stmt->execute([$retentionDays]);
            $out['ai_usage_logs'] = $stmt->rowCount();

            $stmt = $db->prepare('DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
            $stmt->execute([$retentionDays]);
            $out['activity_logs'] = $stmt->rowCount();

            $stmt = $db->prepare('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
            $stmt->execute([7]);
            $out['login_attempts'] = $stmt->rowCount();

            // Old log files
            $logPath = BASE_PATH . '/storage/logs';
            $cutoff = time() - ($retentionDays * 86400);
            $deleted = 0;
            foreach (glob($logPath . '/app-*.log') ?: [] as $file) {
                if (filemtime($file) < $cutoff) {
                    unlink($file);
                    $deleted++;
                }
            }
            $out['log_files'] = $deleted;

            return $out;
        });
        break;

    case 'all':
        // Recommended for every 5–10 minutes
        $run('scheduler', function () {
            $svc = new \App\Services\SchedulerService();
            return $svc->processDuePosts();
        });
        $run('retry', function () {
            $svc = new \App\Services\SchedulerService();
            return $svc->retryFailedPosts();
        });
        $run('sources', function () {
            $svc = new \App\Services\SourceFetchService();
            return $svc->fetchAll();
        });
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => "Unknown job: {$job}. Allowed: scheduler, retry, sources, cleanup, all",
            'time'    => date('c'),
        ]);
        exit;
}

// ---------------------------------------------------------------------------
// Response
// ---------------------------------------------------------------------------
$durationMs = (int) round((microtime(true) - $startTime) * 1000);
$success = empty($errors);

http_response_code($success ? 200 : 500);

echo json_encode([
    'success'     => $success,
    'job'         => $job,
    'results'     => $results,
    'errors'      => $errors ?: null,
    'duration_ms' => $durationMs,
    'time'        => date('c'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
