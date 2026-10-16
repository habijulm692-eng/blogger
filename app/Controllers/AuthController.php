<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Helpers\Validator;
use App\Models\ActivityLog;
use App\Models\Admin;

class AuthController
{
    public function showLogin(): void
    {
        Response::view('auth.login', [
            'csrf' => Csrf::token(),
            'error' => Session::flash('error'),
            'success' => Session::flash('success'),
        ]);
    }

    public function login(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';

        $validator = Validator::make($_POST)
            ->required('email', 'Email/Username')
            ->required('password', 'Password');

        if ($validator->fails()) {
            Session::flash('error', $validator->firstError());
            Response::redirect('/login');
        }

        $identifier = trim((string) $validator->get('email'));
        $password = (string) $validator->get('password');
        $remember = !empty($_POST['remember']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($this->isIpLocked($ip)) {
            Session::flash('error', 'Too many login attempts. Please try again later.');
            Response::redirect('/login');
        }

        $admin = Admin::findByEmailOrUsername($identifier);

        if (!$admin || Admin::isLocked($admin)) {
            $this->logLoginAttempt($ip, $identifier, false);

            if ($admin && Admin::isLocked($admin)) {
                Session::flash('error', 'Account is temporarily locked. Please try again later.');
            } else {
                Session::flash('error', 'Invalid credentials.');
            }
            Response::redirect('/login');
        }

        if (!password_verify($password, $admin['password'])) {
            $this->logLoginAttempt($ip, $identifier, false);
            Admin::incrementLoginAttempts((int) $admin['id']);

            $maxAttempts = $config['security']['login_max_attempts'] ?? 5;
            $lockoutMinutes = $config['security']['login_lockout_minutes'] ?? 15;

            $admin = Admin::findById((int) $admin['id']);
            if ($admin && (int) $admin['login_attempts'] >= $maxAttempts) {
                Admin::lockAccount((int) $admin['id'], $lockoutMinutes);
                Session::flash('error', "Account locked for {$lockoutMinutes} minutes due to too many failed attempts.");
            } else {
                Session::flash('error', 'Invalid credentials.');
            }
            Response::redirect('/login');
        }

        // Success
        $this->logLoginAttempt($ip, $identifier, true);
        Admin::updateLastLogin((int) $admin['id'], $ip);

        Session::regenerate();
        Session::set('admin_id', (int) $admin['id']);
        Session::set('admin_name', $admin['name']);
        Session::set('admin_email', $admin['email']);

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            Admin::setRememberToken((int) $admin['id'], hash('sha256', $token));
            setcookie('remember_token', $token, [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => (bool) ($config['session']['secure'] ?? false),
            ]);
        }

        ActivityLog::log('admin_login', (int) $admin['id'], null, null, 'Admin logged in');

        Response::redirect('/dashboard');
    }

    public function logout(): void
    {
        $adminId = Session::getAdminId();

        if ($adminId) {
            Admin::setRememberToken($adminId, null);
            ActivityLog::log('admin_logout', $adminId, null, null, 'Admin logged out');
        }

        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        Session::destroy();
        Response::redirect('/login');
    }

    private function isIpLocked(string $ip): bool
    {
        $db = Database::getInstance();
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $maxAttempts = $config['security']['login_max_attempts'] ?? 5;
        $lockoutMinutes = $config['security']['login_lockout_minutes'] ?? 15;

        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ? AND success = 0
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$ip, $lockoutMinutes]);

        return (int) $stmt->fetchColumn() >= ($maxAttempts * 2);
    }

    private function logLoginAttempt(string $ip, string $identifier, bool $success): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO login_attempts (ip_address, email_or_username, success) VALUES (?, ?, ?)'
        );
        $stmt->execute([$ip, $identifier, $success ? 1 : 0]);
    }

    public function showRegister(): void
    {
        Response::view('auth.register', [
            'csrf' => Csrf::token(),
            'error' => Session::flash('error'),
            'success' => Session::flash('success'),
        ]);
    }

    public function register(): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password_confirmation'] ?? '');

        if ($name === '' || $email === '' || $username === '' || strlen($password) < 6) {
            Session::flash('error', 'সব ঘর পূরণ করুন। পাসওয়ার্ড কমপক্ষে ৬ অক্ষর।');
            Response::redirect('/register');
        }
        if ($password !== $password2) {
            Session::flash('error', 'পাসওয়ার্ড মিলছে না।');
            Response::redirect('/register');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'সঠিক ইমেইল দিন।');
            Response::redirect('/register');
        }
        if (Admin::emailExists($email) || Admin::usernameExists($username)) {
            Session::flash('error', 'ইমেইল বা ইউজারনেম ইতিমধ্যে আছে।');
            Response::redirect('/register');
        }

        try {
            // First user becomes admin, others editor
            $isFirst = count(Admin::all()) === 0;
            Admin::createWithRole([
                'name' => $name,
                'email' => $email,
                'username' => $username,
                'password' => $password,
                'role' => $isFirst ? 'admin' : 'editor',
            ]);
            Session::flash('success', 'অ্যাকাউন্ট তৈরি হয়েছে। এখন লগইন করুন।');
            Response::redirect('/login');
        } catch (\Throwable $e) {
            Session::flash('error', 'রেজিস্ট্রেশন ব্যর্থ: ' . $e->getMessage());
            Response::redirect('/register');
        }
    }
}
