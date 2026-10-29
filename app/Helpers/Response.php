<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * HTTP Response helpers
 */
class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): never
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): never
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        self::json($payload, $status);
    }

    public static function redirect(string $url, int $status = 302): never
    {
        // Prefix app base path for relative paths
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $url = Url::to($url);
        }

        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function view(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        extract($data, EXTR_SKIP);

        $viewFile = dirname(__DIR__) . '/Views/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        require $viewFile;
    }
}
