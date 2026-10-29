<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Database;
use PDO;

class Admin
{
    private static ?bool $hasRoleColumn = null;

    public static function hasRoleColumn(): bool
    {
        if (self::$hasRoleColumn !== null) {
            return self::$hasRoleColumn;
        }
        try {
            $db = Database::getInstance();
            $stmt = $db->query("SHOW COLUMNS FROM admins LIKE 'role'");
            self::$hasRoleColumn = (bool) $stmt->fetch();
        } catch (\Throwable $e) {
            self::$hasRoleColumn = false;
        }
        return self::$hasRoleColumn;
    }

    /** Try to add role column (safe to call multiple times). */
    public static function ensureRoleColumn(): void
    {
        if (self::hasRoleColumn()) {
            return;
        }
        try {
            $db = Database::getInstance();
            $db->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER username");
            self::$hasRoleColumn = true;
        } catch (\Throwable $e) {
            // ignore if fails
            self::$hasRoleColumn = self::hasRoleColumn();
        }
    }

    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM admins WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row && !isset($row['role'])) {
            $row['role'] = 'admin';
        }
        return $row ?: null;
    }

    public static function findByEmailOrUsername(string $identifier): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT * FROM admins WHERE (email = ? OR username = ?) AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$identifier, $identifier]);
        $row = $stmt->fetch();
        if ($row && !isset($row['role'])) {
            $row['role'] = 'admin';
        }
        return $row ?: null;
    }

    public static function updateLastLogin(int $id, string $ip): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE admins SET last_login_at = NOW(), last_login_ip = ?, login_attempts = 0, locked_until = NULL WHERE id = ?'
        );
        $stmt->execute([$ip, $id]);
    }

    public static function incrementLoginAttempts(int $id): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE admins SET login_attempts = login_attempts + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function lockAccount(int $id, int $minutes): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE admins SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?'
        );
        $stmt->execute([$minutes, $id]);
    }

    public static function isLocked(array $admin): bool
    {
        if (empty($admin['locked_until'])) {
            return false;
        }
        return strtotime($admin['locked_until']) > time();
    }

    public static function setRememberToken(int $id, ?string $token): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE admins SET remember_token = ? WHERE id = ?');
        $stmt->execute([$token, $id]);
    }

    public static function findByRememberToken(string $token): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT * FROM admins WHERE remember_token = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        return self::createWithRole($data);
    }

    public static function all(): array
    {
        self::ensureRoleColumn();
        $db = Database::getInstance();
        if (self::hasRoleColumn()) {
            $rows = $db->query(
                'SELECT id, name, email, username, role, is_active, last_login_at, created_at FROM admins ORDER BY id ASC'
            )->fetchAll();
        } else {
            $rows = $db->query(
                'SELECT id, name, email, username, is_active, last_login_at, created_at FROM admins ORDER BY id ASC'
            )->fetchAll();
            foreach ($rows as &$r) {
                $r['role'] = 'admin';
            }
        }
        return $rows;
    }

    public static function createWithRole(array $data): int
    {
        self::ensureRoleColumn();
        $db = Database::getInstance();
        $role = $data['role'] ?? 'editor';
        if (!in_array($role, ['admin', 'editor'], true)) {
            $role = 'editor';
        }

        if (self::hasRoleColumn()) {
            $stmt = $db->prepare(
                'INSERT INTO admins (name, email, username, password, role, is_active) VALUES (?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['username'],
                password_hash($data['password'], PASSWORD_BCRYPT),
                $role,
            ]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO admins (name, email, username, password, is_active) VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['username'],
                password_hash($data['password'], PASSWORD_BCRYPT),
            ]);
        }
        return (int) $db->lastInsertId();
    }

    public static function setActive(int $id, bool $active): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE admins SET is_active = ? WHERE id = ?');
        return $stmt->execute([$active ? 1 : 0, $id]);
    }

    public static function updateRole(int $id, string $role): bool
    {
        self::ensureRoleColumn();
        if (!self::hasRoleColumn()) {
            return false;
        }
        if (!in_array($role, ['admin', 'editor'], true)) {
            return false;
        }
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE admins SET role = ? WHERE id = ?');
        return $stmt->execute([$role, $id]);
    }

    public static function emailExists(string $email): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return (bool) $stmt->fetch();
    }

    public static function usernameExists(string $username): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        return (bool) $stmt->fetch();
    }
}
