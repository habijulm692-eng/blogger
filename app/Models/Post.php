<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Database;
use PDO;

class Post
{
    public const STATUSES = [
        'DRAFT',
        'GENERATING',
        'GENERATED',
        'AI_REVIEWING',
        'HUMAN_REVIEW',
        'REJECTED',
        'APPROVED',
        'SCHEDULED',
        'PUBLISHING',
        'PUBLISHED',
        'FAILED',
    ];

    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM posts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? self::decodeJsonFields($row) : null;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO posts (
                topic, primary_keyword, secondary_keywords, category, language,
                word_count, target_audience, writing_tone, status, created_by, tags
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['topic'],
            $data['primary_keyword'] ?? null,
            isset($data['secondary_keywords']) ? json_encode($data['secondary_keywords'], JSON_UNESCAPED_UNICODE) : null,
            $data['category'] ?? null,
            $data['language'] ?? 'bn',
            $data['word_count'] ?? 1000,
            $data['target_audience'] ?? null,
            $data['writing_tone'] ?? null,
            $data['status'] ?? 'DRAFT',
            $data['created_by'] ?? null,
            isset($data['tags']) ? json_encode($data['tags'], JSON_UNESCAPED_UNICODE) : null,
        ]);

        $id = (int) $db->lastInsertId();
        self::logStatusChange($id, null, $data['status'] ?? 'DRAFT', $data['created_by'] ?? null);
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $allowed = [
            'topic', 'primary_keyword', 'secondary_keywords', 'title', 'meta_description',
            'excerpt', 'content_html', 'tags', 'category', 'language', 'word_count',
            'target_audience', 'writing_tone', 'status', 'ai_score', 'ai_provider_used',
            'ai_model_used', 'generation_attempts', 'blogger_post_id', 'blogger_url',
            'publish_attempt_id', 'error_message', 'error_code', 'retry_count',
            'last_attempt_at', 'approved_by', 'approved_at', 'scheduled_at', 'published_at',
        ];

        $sets = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }

            if (in_array($key, ['secondary_keywords', 'tags'], true) && is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $sets[] = "`{$key}` = ?";
            $params[] = $value;
        }

        if ($sets === []) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE posts SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    public static function updateStatus(int $id, string $newStatus, ?int $adminId = null, ?string $note = null): bool
    {
        $post = self::findById($id);
        if (!$post) {
            return false;
        }

        $oldStatus = $post['status'];
        $ok = self::update($id, ['status' => $newStatus]);

        if ($ok) {
            self::logStatusChange($id, $oldStatus, $newStatus, $adminId, $note);
        }

        return $ok;
    }

    public static function logStatusChange(
        int $postId,
        ?string $from,
        string $to,
        ?int $adminId = null,
        ?string $note = null
    ): void {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO post_status_logs (post_id, from_status, to_status, changed_by, note) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$postId, $from, $to, $adminId, $note]);
    }

    public static function countByStatus(?string $status = null, ?int $createdBy = null): int
    {
        $db = Database::getInstance();
        $where = [];
        $params = [];
        if ($status !== null) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($createdBy !== null) {
            $where[] = 'created_by = ?';
            $params[] = $createdBy;
        }
        $sql = 'SELECT COUNT(*) FROM posts';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function countToday(string $field = 'created_at'): int
    {
        $db = Database::getInstance();
        $allowed = ['created_at', 'published_at'];
        if (!in_array($field, $allowed, true)) {
            $field = 'created_at';
        }
        $stmt = $db->query("SELECT COUNT(*) FROM posts WHERE DATE({$field}) = CURDATE()");
        return (int) $stmt->fetchColumn();
    }

    public static function getRecent(int $limit = 10): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT id, title, topic, status, ai_score, created_at, scheduled_at, published_at, blogger_url
             FROM posts ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function search(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $db = Database::getInstance();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(title LIKE ? OR topic LIKE ? OR primary_keyword LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['created_by'])) {
            $where[] = 'created_by = ?';
            $params[] = (int) $filters['created_by'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $countStmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM posts WHERE {$whereClause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'data' => array_map([self::class, 'decodeJsonFields'], $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public static function getNextScheduled(): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT id, title, scheduled_at FROM posts
             WHERE status = 'SCHEDULED' AND scheduled_at > NOW()
             ORDER BY scheduled_at ASC LIMIT 1"
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getDueForPublishing(): array
    {
        $db = Database::getInstance();
        // Use PHP time in app timezone so comparison is reliable even if MySQL TZ differs
        $now = (new \DateTime('now', new \DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'Asia/Dhaka')))->format('Y-m-d H:i:s');
        $stmt = $db->prepare(
            "SELECT p.* FROM posts p
             INNER JOIN scheduled_posts sp ON sp.post_id = p.id
             WHERE p.status = 'SCHEDULED'
               AND sp.status IN ('PENDING', 'FAILED')
               AND sp.scheduled_at <= ?
               AND (sp.locked_at IS NULL OR sp.locked_at < DATE_SUB(?, INTERVAL 10 MINUTE))
             ORDER BY sp.scheduled_at ASC
             LIMIT 5"
        );
        $stmt->execute([$now, $now]);
        return array_map([self::class, 'decodeJsonFields'], $stmt->fetchAll());
    }

    private static function decodeJsonFields(array $row): array
    {
        foreach (['secondary_keywords', 'tags'] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $decoded = json_decode($row[$field], true);
                $row[$field] = is_array($decoded) ? $decoded : [];
            }
        }
        return $row;
    }


    public static function belongsToUser(array $post, int $adminId): bool
    {
        return (int) ($post['created_by'] ?? 0) === $adminId;
    }

    /** Recent posts for one user */
    public static function recentForUser(int $adminId, int $limit = 10): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT * FROM posts WHERE created_by = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $adminId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return array_map([self::class, 'decodeJsonFields'], $stmt->fetchAll());
    }
}
