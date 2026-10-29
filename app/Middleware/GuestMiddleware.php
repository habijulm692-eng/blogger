<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Session;
use App\Helpers\Response;

/**
 * Redirect authenticated users away from guest pages
 */
class GuestMiddleware
{
    public function handle(): void
    {
        if (Session::isLoggedIn()) {
            Response::redirect('/dashboard');
        }
    }
}
