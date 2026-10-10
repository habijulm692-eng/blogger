<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Database;
use PDO;

class ActivityLog
{
    public static function log(
        string $action,
        ?int $adminId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $metadata = null
    ): void {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO activity_logs (admin_id, action, entity_type, entity_id, description, ip_address, user_agent, metadata)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $adminId,
            $action,
            $entityType,
            $entityId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    public static function getRecent(int $limit = 50): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT al.*, a.name AS admin_name, a.email AS admin_email
             FROM activity_logs al
             LEFT JOIN admins a ON a.id = al.admin_id
             ORDER BY al.created_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
