<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\ActivityLog;
use App\Models\BloggerConnection;
use App\Services\BloggerService;

class BloggerController
{
    public function index(): void
    {
        $connection = BloggerConnection::getActive((int) Session::getAdminId());
        $service = new BloggerService();

        Response::view('blogger.index', [
            'connection' => $connection,
            'isConfigured' => $service->isConfigured(),
            'csrf' => Csrf::token(),
        ]);
    }

    public function connect(): void
    {
        $service = new BloggerService();

        if (!$service->isConfigured()) {
            Session::flash('error', 'Google OAuth is not configured. Add Client ID and Secret in .env');
            Response::redirect('/blogger');
        }

        $state = bin2hex(random_bytes(16));
        Session::set('oauth_state', $state);

        $url = $service->getAuthUrl($state);
        Response::redirect($url);
    }

    public function callback(): void
    {
        $code = $_GET['code'] ?? null;
        $state = $_GET['state'] ?? null;
        $error = $_GET['error'] ?? null;

        if ($error) {
            Session::flash('error', 'Google authorization denied: ' . $error);
            Response::redirect('/blogger');
        }

        if (!$code || !$state || $state !== Session::get('oauth_state')) {
            Session::flash('error', 'Invalid OAuth callback.');
            Response::redirect('/blogger');
        }

        Session::remove('oauth_state');

        $service = new BloggerService();
        $adminId = Session::getAdminId();

        $result = $service->handleCallback($code, $adminId);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Failed to connect Blogger');
            Response::redirect('/blogger');
        }

        ActivityLog::log('blogger_connected', $adminId, null, null, 'Connected blog: ' . ($result['blog_name'] ?? ''));
        Session::flash('success', 'Blogger connected successfully: ' . ($result['blog_name'] ?? 'Blog'));
        Response::redirect('/blogger');
    }

    public function disconnect(): void
    {
        $adminId = Session::getAdminId();
        $service = new BloggerService();
        $service->disconnect($adminId);

        ActivityLog::log('blogger_disconnected', $adminId);
        Session::flash('success', 'Blogger disconnected.');
        Response::redirect('/blogger');
    }

    public function test(): void
    {
        $service = new BloggerService();
        $result = $service->testConnection();

        if ($this->isAjax()) {
            if ($result['success']) {
                Response::success($result, 'Connection successful');
            }
            Response::error($result['error'] ?? 'Connection test failed', 400);
        }

        if ($result['success']) {
            Session::flash('success', 'Blogger connection is working. Found ' . count($result['blogs'] ?? []) . ' blog(s).');
        } else {
            Session::flash('error', $result['error'] ?? 'Connection test failed');
        }
        Response::redirect('/blogger');
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
