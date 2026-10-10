<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Session;
use App\Helpers\Response;

/**
 * Require authenticated admin
 */
class AuthMiddleware
{
    public function handle(): void
    {
        if (!Session::isLoggedIn()) {
            if ($this->isAjax()) {
                Response::error('Unauthorized. Please login.', 401);
            }
            Response::redirect('/login');
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
