<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Csrf;
use App\Helpers\Response;

/**
 * Validate CSRF token on state-changing requests
 */
class CsrfMiddleware
{
    public function handle(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (!Csrf::validateRequest()) {
                if ($this->isAjax()) {
                    Response::error('Invalid CSRF token. Please refresh the page.', 403);
                }
                http_response_code(403);
                echo 'Invalid CSRF token. Please go back and try again.';
                exit;
            }
        }
    }

    private function isAjax(): bool
    {
        return (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        );
    }
}
