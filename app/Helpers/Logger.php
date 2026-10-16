<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Simple file-based logger
 */
class Logger
{
    private static string $logPath = '';

    public static function init(string $path): void
    {
        self::$logPath = rtrim($path, DIRECTORY_SEPARATOR);

        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        if (($config['debug'] ?? false) === true) {
            self::write('DEBUG', $message, $context);
        }
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        if (self::$logPath === '') {
            self::init(dirname(__DIR__, 2) . '/storage/logs');
        }

        $date = date('Y-m-d');
        $file = self::$logPath . DIRECTORY_SEPARATOR . "app-{$date}.log";
        $timestamp = date('Y-m-d H:i:s');

        $contextStr = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
