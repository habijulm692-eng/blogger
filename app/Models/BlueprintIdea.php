<?php
declare(strict_types=1);
namespace App\Models;
use App\Helpers\Database;
use PDO;

/**
 * Blueprint-generated AI topic ideas (independent of external source feeds).
 */
class BlueprintIdea
{
    public static function create(array $data): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO blueprint_ideas
                (admin_id, topic, primary_keyword, tone, tags, angle, category, language, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['admin_id'],
            $data['topic'],
            $data['primary_keyword'] ?? null,
            $data['tone']            ?? null,
            isset($data['tags'])     ? json_encode($data['tags'], JSON_UNESCAPED_UNICODE) : null,
            $data['angle']           ?? null,
            $data['category']        ?? null,
            $data['language']        ?? 'bn',
            $data['status']          ?? 'pending',
        ]);
        return (int) $db->lastInsertId();
    }

    /** @return list<array> */
    public static function listPending(int $adminId, int $limit = 10): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT * FROM blueprint_ideas
              WHERE admin_id = ? AND status = 'pending'
              ORDER BY id DESC LIMIT ?"
        );
        $stmt->bindValue(1, $adminId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function markUsed(int $id): void
    {
        $db = Database::getInstance();
        $db->prepare("UPDATE blueprint_ideas SET status = 'used' WHERE id = ?")
           ->execute([$id]);
    }

    public static function markDismissed(int $id, int $adminId): void
    {
        $db = Database::getInstance();
        $db->prepare("UPDATE blueprint_ideas SET status = 'dismissed' WHERE id = ? AND admin_id = ?")
           ->execute([$id, $adminId]);
    }

    /** Delete all pending ideas older than $days days for given admin */
    public static function pruneOld(int $adminId, int $days = 7): void
    {
        $db = Database::getInstance();
        $db->prepare(
            "DELETE FROM blueprint_ideas
              WHERE admin_id = ? AND status = 'pending'
                AND generated_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        )->execute([$adminId, $days]);
    }

    /** Decode the JSON tags field into an array */
    public static function decodeTags(array $idea): array
    {
        if (empty($idea['tags'])) {
            return [];
        }
        $decoded = json_decode((string) $idea['tags'], true);
        return is_array($decoded) ? $decoded : [];
    }
}
