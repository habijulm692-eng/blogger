<?php

declare(strict_types=1);

/**
 * AI Blogger — Front Controller
 * Works at domain root OR subdirectory (e.g. http://localhost/blogger/)
 */

define('BASE_PATH', __DIR__);

// Autoloader
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

// Environment
require BASE_PATH . '/app/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH);

// Detect base path for subdirectory installs (localhost/blogger)
\App\Helpers\Url::detectBase();

$appConfig = require BASE_PATH . '/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Dhaka');

if ($appConfig['debug'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
}

\App\Helpers\Logger::init(BASE_PATH . '/storage/logs');
\App\Helpers\Session::start();

// Remember-me
if (!\App\Helpers\Session::isLoggedIn() && !empty($_COOKIE['remember_token'])) {
    $token = (string) $_COOKIE['remember_token'];
    $admin = \App\Models\Admin::findByRememberToken(hash('sha256', $token));
    if ($admin) {
        \App\Helpers\Session::set('admin_id', (int) $admin['id']);
        \App\Helpers\Session::set('admin_name', $admin['name']);
        \App\Helpers\Session::set('admin_email', $admin['email']);
    }
}

// Route
$router = new \App\Helpers\Router();
$router->load(BASE_PATH . '/routes/web.php');

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = \App\Helpers\Url::pathForRouting($requestUri);

// Static asset handler fallback: if Apache rewrites /assets/... to index.php, serve file directly
if (str_starts_with($path, '/assets/')) {
    $assetRel = ltrim($path, '/');
    $assetFile = realpath(BASE_PATH . '/' . $assetRel);
    $assetsDir = realpath(BASE_PATH . '/assets');
    if ($assetFile && $assetsDir && str_starts_with($assetFile, $assetsDir) && is_file($assetFile)) {
        $ext = strtolower(pathinfo($assetFile, PATHINFO_EXTENSION));
        $mimes = [
            'js'    => 'application/javascript; charset=utf-8',
            'css'   => 'text/css; charset=utf-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($assetFile));
        header('Cache-Control: public, max-age=86400');
        readfile($assetFile);
        exit;
    }
}

$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $path
);
