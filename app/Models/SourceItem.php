<?php
declare(strict_types=1);
namespace App\Models;
use App\Helpers\Database;

class SourceItem
{
    public static function createIgnoreDuplicate(array $data): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT IGNORE INTO source_items (source_id, title, url, summary, published_at, content_hash)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        return $stmt->execute([
            $data['source_id'],
            $data['title'],
            $data['url'],
            $data['summary'] ?? null,
            $data['published_at'] ?? null,
            $data['content_hash'],
        ]);
    }

    public static function recent(int $limit = 30, ?int $createdBy = null): array
    {
        $db = Database::getInstance();
        if ($createdBy !== null) {
            $stmt = $db->prepare(
                'SELECT si.*, sw.name AS source_name, sw.site_type
                 FROM source_items si
                 JOIN source_websites sw ON sw.id = si.source_id
                 WHERE sw.created_by = ?
                 ORDER BY si.fetched_at DESC LIMIT ?'
            );
            $stmt->bindValue(1, $createdBy, \PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        } else {
            $stmt = $db->prepare(
                'SELECT si.*, sw.name AS source_name, sw.site_type
                 FROM source_items si
                 JOIN source_websites sw ON sw.id = si.source_id
                 ORDER BY si.fetched_at DESC LIMIT ?'
            );
            $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM source_items WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
