<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';
    if (!str_starts_with($class, $prefix)) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});
require BASE_PATH . '/app/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH);
\App\Helpers\Logger::init(BASE_PATH . '/storage/logs');
$svc = new \App\Services\SourceFetchService();
$stats = $svc->fetchAll();
echo date('c') . ' fetch_sources: ' . json_encode($stats) . PHP_EOL;
