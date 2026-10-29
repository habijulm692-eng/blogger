<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\Admin;
use App\Models\ActivityLog;

class UserController
{
    public function index(): void
    {
        Admin::ensureRoleColumn();
        $this->requireAdmin();
        Response::view('users/index', [
            'users' => Admin::all(),
            'pageTitle' => 'Users',
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'editor'], true) ? $_POST['role'] : 'editor';

        if ($name === '' || $email === '' || $username === '' || strlen($password) < 6) {
            Session::flash('error', 'Fill all fields. Password min 6 chars.');
            Response::redirect('/users');
        }

        try {
            $id = Admin::createWithRole([
                'name' => $name,
                'email' => $email,
                'username' => $username,
                'password' => $password,
                'role' => $role,
            ]);
            ActivityLog::log('user_created', Session::getAdminId(), 'admin', $id, $username);
            Session::flash('success', 'User created.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not create user (email/username may exist).');
        }
        Response::redirect('/users');
    }

    public function toggle(int $id): void
    {
        $this->requireAdmin();
        if ($id === (int) Session::getAdminId()) {
            Session::flash('error', 'Cannot deactivate yourself.');
            Response::redirect('/users');
        }
        $users = Admin::all();
        $target = null;
        foreach ($users as $u) {
            if ((int) $u['id'] === $id) {
                $target = $u;
                break;
            }
        }
        if (!$target) {
            Session::flash('error', 'User not found.');
            Response::redirect('/users');
        }
        Admin::setActive($id, empty($target['is_active']));
        Session::flash('success', 'User status updated.');
        Response::redirect('/users');
    }

    private function requireAdmin(): void
    {
        $admin = Admin::findById((int) Session::getAdminId());
        $role = $admin['role'] ?? 'admin';
        if ($role !== 'admin') {
            Session::flash('error', 'Admin access required.');
            Response::redirect('/dashboard');
        }
    }
}
