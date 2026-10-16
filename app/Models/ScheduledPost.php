<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Database;

class ScheduledPost
{
    public static function create(int $postId, string $scheduledAt, string $timezone = 'Asia/Dhaka'): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO scheduled_posts (post_id, scheduled_at, timezone, status)
             VALUES (?, ?, ?, \'PENDING\')
             ON DUPLICATE KEY UPDATE scheduled_at = VALUES(scheduled_at), timezone = VALUES(timezone),
             status = \'PENDING\', attempts = 0, error_message = NULL, locked_at = NULL, locked_by = NULL'
        );
        $stmt->execute([$postId, $scheduledAt, $timezone]);
        return (int) $db->lastInsertId();
    }

    public static function findByPostId(int $postId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM scheduled_posts WHERE post_id = ? LIMIT 1');
        $stmt->execute([$postId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Acquire lock for PENDING or FAILED scheduled rows.
     * FAILED is allowed so retry cron can re-process.
     */
    public static function acquireLock(int $id, string $lockId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE scheduled_posts
             SET locked_at = NOW(), locked_by = ?, status = 'PROCESSING', attempts = attempts + 1, last_attempt_at = NOW()
             WHERE id = ?
               AND status IN ('PENDING', 'FAILED')
               AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE))"
        );
        $stmt->execute([$lockId, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function markCompleted(int $id): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE scheduled_posts SET status = 'COMPLETED', locked_at = NULL, locked_by = NULL WHERE id = ?"
        );
        $stmt->execute([$id]);
    }

    public static function markFailed(int $id, string $error): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE scheduled_posts SET status = 'FAILED', error_message = ?, locked_at = NULL, locked_by = NULL WHERE id = ?"
        );
        $stmt->execute([$error, $id]);
    }

    public static function releaseLock(int $id): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE scheduled_posts SET status = 'PENDING', locked_at = NULL, locked_by = NULL WHERE id = ?"
        );
        $stmt->execute([$id]);
    }

    /**
     * Reset a FAILED scheduled row back to PENDING so it can be picked up again.
     */
    public static function resetToPending(int $id): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE scheduled_posts SET status = 'PENDING', locked_at = NULL, locked_by = NULL, error_message = NULL WHERE id = ?"
        );
        $stmt->execute([$id]);
    }
}
