<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Database;

class BloggerConnection
{
    public static function getActive(?int $adminId = null): ?array
    {
        $db = Database::getInstance();
        if ($adminId !== null && $adminId > 0) {
            $stmt = $db->prepare(
                'SELECT * FROM blogger_connections WHERE admin_id = ? AND is_active = 1 LIMIT 1'
            );
            $stmt->execute([$adminId]);
        } else {
            $stmt = $db->query(
                'SELECT * FROM blogger_connections WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1'
            );
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getByAdmin(int $adminId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT * FROM blogger_connections WHERE admin_id = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$adminId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function save(array $data): int
    {
        $db = Database::getInstance();

        // Deactivate existing
        $stmt = $db->prepare('UPDATE blogger_connections SET is_active = 0 WHERE admin_id = ?');
        $stmt->execute([$data['admin_id']]);

        $stmt = $db->prepare(
            'INSERT INTO blogger_connections (
                admin_id, google_account_email, access_token, refresh_token,
                token_expires_at, blog_id, blog_name, blog_url, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );

        $stmt->execute([
            $data['admin_id'],
            $data['google_account_email'] ?? null,
            $data['access_token'],
            $data['refresh_token'] ?? null,
            $data['token_expires_at'] ?? null,
            $data['blog_id'] ?? null,
            $data['blog_name'] ?? null,
            $data['blog_url'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    public static function updateTokens(int $id, string $accessToken, ?string $expiresAt = null): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE blogger_connections SET access_token = ?, token_expires_at = ?, last_used_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$accessToken, $expiresAt, $id]);
    }

    public static function updateBlogInfo(int $id, string $blogId, string $blogName, ?string $blogUrl = null): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE blogger_connections SET blog_id = ?, blog_name = ?, blog_url = ? WHERE id = ?'
        );
        $stmt->execute([$blogId, $blogName, $blogUrl, $id]);
    }

    public static function disconnect(int $adminId): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE blogger_connections SET is_active = 0, access_token = \'\', refresh_token = NULL WHERE admin_id = ?'
        );
        $stmt->execute([$adminId]);
    }

    public static function isConnected(): bool
    {
        $conn = self::getActive();
        return $conn !== null && !empty($conn['access_token']) && !empty($conn['blog_id']);
    }
}
