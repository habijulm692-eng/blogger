<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO Database singleton with automatic connection health-check & reconnect.
 *
 * Handles "MySQL server has gone away" (error 2006 / 2002) which commonly
 * occurs on shared hosting when the connection sits idle during long AI
 * API calls (5–30+ seconds).
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Return a live PDO instance. If the previous connection has dropped,
     * it is automatically re-created.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
            return self::$instance;
        }

        // Health-check: run a cheap query. If it fails with "gone away", reconnect.
        try {
            self::$instance->query('SELECT 1');
        } catch (PDOException $e) {
            if (self::isGoneAway($e)) {
                self::$instance = null;
                self::$instance = self::createConnection();
            } else {
                throw $e;
            }
        }

        return self::$instance;
    }

    public static function disconnect(): void
    {
        self::$instance = null;
    }

    public static function reconnect(): PDO
    {
        self::disconnect();
        return self::getInstance();
    }

    private static function createConnection(): PDO
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );

            // Force MySQL session timezone to match app timezone (critical for scheduled posts)
            $tz = $_ENV['APP_TIMEZONE'] ?? 'Asia/Dhaka';
            try {
                // Convert PHP timezone name to MySQL offset, e.g. +06:00
                $dt = new \DateTime('now', new \DateTimeZone($tz));
                $offset = $dt->format('P'); // e.g. +06:00
                $pdo->exec("SET time_zone = '{$offset}'");
            } catch (\Throwable $e) {
                // Non-fatal — fall back to server default
            }

            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private static function isGoneAway(PDOException $e): bool
    {
        $code = (int) $e->getCode();
        $msg  = $e->getMessage();

        return $code === 2006
            || $code === 2002
            || $code === 2013
            || str_contains($msg, 'server has gone away')
            || str_contains($msg, 'Lost connection')
            || str_contains($msg, 'Error while sending');
    }
}
