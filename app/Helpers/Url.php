<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Base URL helpers — works at domain root OR subdirectory
 * (e.g. http://localhost/blogger/)
 */
class Url
{
    private static ?string $base = null;

    public static function detectBase(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }

        // 1. Auto-detect from SCRIPT_NAME (e.g. /blogger/index.php => /blogger, /index.php => '')
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = str_replace('\\', '/', dirname($scriptName));
        $scriptBase = ($dir === '/' || $dir === '.' || $dir === '\\' || $dir === '')
            ? ''
            : rtrim($dir, '/');

        // 2. Check configured base path from environment
        $configured = trim((string) ($_ENV['APP_BASE_PATH'] ?? ''));
        if ($configured !== '') {
            $configured = '/' . trim($configured, '/');
            if ($configured === '/') {
                $configured = '';
            }
        }

        // 3. Smart resolution:
        // - If SCRIPT_NAME already contains a subfolder (e.g. /blogger), that is our true base.
        // - Else if SCRIPT_NAME is at root, but REQUEST_URI actually starts with configured base, use configured.
        // - Otherwise, the request is being served at domain root, so base must be empty to avoid 404 errors.
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($scriptBase !== '') {
            self::$base = $scriptBase;
        } elseif ($configured !== '' && (str_starts_with($requestUri, $configured . '/') || $requestUri === $configured)) {
            self::$base = $configured;
        } else {
            self::$base = '';
        }

        return self::$base;
    }

    public static function setBase(string $base): void
    {
        $base = rtrim(str_replace('\\', '/', $base), '/');
        self::$base = ($base === '/' || $base === '') ? '' : $base;
    }

    public static function base(): string
    {
        return self::detectBase();
    }

    /**
     * Build internal app URL with base path prefix.
     * Examples (APP_BASE_PATH=/blogger):
     *   to('/login')      => /blogger/login
     *   to('/posts/1')    => /blogger/posts/1
     *   to('/')           => /blogger/
     */
    public static function to(string $path = '/'): string
    {
        $base = self::base();
        $query = '';

        if (str_contains($path, '?')) {
            [$path, $qs] = explode('?', $path, 2);
            $query = $qs !== '' ? '?' . $qs : '';
        }

        $path = '/' . trim($path, '/');
        if ($path === '/') {
            return ($base === '' ? '/' : $base . '/') . ltrim($query, '');
        }

        return $base . $path . $query;
    }

    /**
     * Strip base path from REQUEST_URI so router sees /login not /blogger/login
     */
    public static function pathForRouting(string $requestUri): string
    {
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        $path = rawurldecode($path);
        $base = self::base();

        if ($base !== '' && (str_starts_with($path, $base . '/') || $path === $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        // Fallback for broken rewrite: ?r=/login
        if (!empty($_GET['r'])) {
            $path = '/' . ltrim((string) $_GET['r'], '/');
        }

        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    public static function asset(string $path): string
    {
        return self::to('assets/' . ltrim($path, '/'));
    }
}
