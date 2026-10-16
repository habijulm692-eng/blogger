<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * CSRF Token protection
 */
class Csrf
{
    private const TOKEN_KEY = '_csrf_token';
    private const TOKEN_TIME_KEY = '_csrf_token_time';

    public static function generate(): string
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $lifetime = $config['security']['csrf_token_lifetime'] ?? 3600;

        if (
            empty($_SESSION[self::TOKEN_KEY]) ||
            empty($_SESSION[self::TOKEN_TIME_KEY]) ||
            (time() - (int) $_SESSION[self::TOKEN_TIME_KEY]) > $lifetime
        ) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
            $_SESSION[self::TOKEN_TIME_KEY] = time();
        }

        return $_SESSION[self::TOKEN_KEY];
    }

    public static function token(): string
    {
        return self::generate();
    }

    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }

    public static function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        if (empty($_SESSION[self::TOKEN_KEY])) {
            return false;
        }

        return hash_equals($_SESSION[self::TOKEN_KEY], $token);
    }

    public static function validateRequest(): bool
    {
        $token = $_POST['_csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? null;

        return self::validate($token);
    }
}
