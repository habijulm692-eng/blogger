<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Database;

class ChatMessage
{
    public static function add(int $adminId, string $role, string $message): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO chat_messages (admin_id, role, message) VALUES (?, ?, ?)'
        );
        $stmt->execute([$adminId, $role, $message]);
        return (int) $db->lastInsertId();
    }

    public static function history(int $adminId, int $limit = 40): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT * FROM (
                SELECT * FROM chat_messages WHERE admin_id = ? ORDER BY id DESC LIMIT ?
             ) t ORDER BY id ASC'
        );
        $stmt->bindValue(1, $adminId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function clear(int $adminId): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM chat_messages WHERE admin_id = ?');
        $stmt->execute([$adminId]);
    }
}
