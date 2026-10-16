<?php
declare(strict_types=1);
namespace App\Models;
use App\Helpers\Database;
use PDO;

class TopicSuggestion
{
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO topic_suggestions (source_item_id, title, angle, primary_keyword, category, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['source_item_id'] ?? null,
            $data['title'],
            $data['angle'] ?? null,
            $data['primary_keyword'] ?? null,
            $data['category'] ?? null,
            $data['status'] ?? 'pending',
            $data['created_by'] ?? null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function listByStatus(string $status = 'pending', int $limit = 50, ?int $createdBy = null): array
    {
        $db = Database::getInstance();
        if ($createdBy !== null) {
            $stmt = $db->prepare(
                'SELECT ts.*, si.url AS source_url, si.title AS source_title, sw.name AS source_name
                 FROM topic_suggestions ts
                 LEFT JOIN source_items si ON si.id = ts.source_item_id
                 LEFT JOIN source_websites sw ON sw.id = si.source_id
                 WHERE ts.status = ? AND ts.created_by = ?
                 ORDER BY ts.id DESC LIMIT ?'
            );
            $stmt->bindValue(1, $status);
            $stmt->bindValue(2, $createdBy, PDO::PARAM_INT);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        } else {
            $stmt = $db->prepare(
                'SELECT ts.*, si.url AS source_url, si.title AS source_title, sw.name AS source_name
                 FROM topic_suggestions ts
                 LEFT JOIN source_items si ON si.id = ts.source_item_id
                 LEFT JOIN source_websites sw ON sw.id = si.source_id
                 WHERE ts.status = ?
                 ORDER BY ts.id DESC LIMIT ?'
            );
            $stmt->bindValue(1, $status);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM topic_suggestions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updateStatus(int $id, string $status, ?int $postId = null): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE topic_suggestions SET status = ?, created_post_id = COALESCE(?, created_post_id) WHERE id = ?'
        );
        return $stmt->execute([$status, $postId, $id]);
    }
}
