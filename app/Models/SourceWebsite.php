<?php
declare(strict_types=1);
namespace App\Models;
use App\Helpers\Database;
use PDO;

class SourceWebsite
{
    public static function all(bool $activeOnly = false, ?int $createdBy = null): array
    {
        $db = Database::getInstance();
        $where  = ["site_type != 'blueprint'"]; // exclude internal blueprint row
        $params = [];
        if ($activeOnly) {
            $where[] = 'is_active = 1';
        }
        if ($createdBy !== null) {
            $where[] = 'created_by = ?';
            $params[] = $createdBy;
        }
        $sql = 'SELECT * FROM source_websites';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM source_websites WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO source_websites (name, url, rss_url, site_type, category, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['url'],
            $data['rss_url'] ?? null,
            $data['site_type'] ?? 'blog',
            $data['category'] ?? null,
            $data['is_active'] ?? 1,
            $data['created_by'] ?? null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $allowed = ['name', 'url', 'rss_url', 'site_type', 'category', 'is_active', 'last_fetched_at', 'blueprint', 'blueprint_updated_at'];
        $sets = [];
        $vals = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $sets[] = "$k = ?";
                $vals[] = $data[$k];
            }
        }
        if ($sets === []) {
            return false;
        }
        $vals[] = $id;
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE source_websites SET ' . implode(', ', $sets) . ' WHERE id = ?');
        return $stmt->execute($vals);
    }

    public static function delete(int $id, ?int $createdBy = null): bool
    {
        $db = Database::getInstance();
        if ($createdBy !== null) {
            $stmt = $db->prepare('DELETE FROM source_websites WHERE id = ? AND created_by = ?');
            return $stmt->execute([$id, $createdBy]);
        }
        $stmt = $db->prepare('DELETE FROM source_websites WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Retrieve the blueprint text for a given admin.
     * Blueprint is stored on the first source_website row of type 'blueprint'.
     */
    public static function getBlueprint(int $adminId): string
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT blueprint FROM source_websites
              WHERE site_type = 'blueprint' AND created_by = ?
              ORDER BY id ASC LIMIT 1"
        );
        $stmt->execute([$adminId]);
        $row = $stmt->fetch();
        return (string) ($row['blueprint'] ?? '');
    }

    /**
     * Upsert the blueprint row for a given admin.
     */
    public static function saveBlueprint(int $adminId, string $text): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id FROM source_websites
              WHERE site_type = 'blueprint' AND created_by = ?
              ORDER BY id ASC LIMIT 1"
        );
        $stmt->execute([$adminId]);
        $row = $stmt->fetch();

        if ($row) {
            $db->prepare(
                "UPDATE source_websites
                    SET blueprint = ?, blueprint_updated_at = NOW()
                  WHERE id = ?"
            )->execute([$text, $row['id']]);
        } else {
            $db->prepare(
                "INSERT INTO source_websites
                    (name, url, site_type, blueprint, blueprint_updated_at, created_by)
                 VALUES (?, ?, 'blueprint', ?, NOW(), ?)"
            )->execute(['__blueprint__', 'internal://blueprint', $text, $adminId]);
        }
    }
}
