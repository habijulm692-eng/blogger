<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Database;
use PDO;
use PDOException;

class AiUsageLog
{
    /**
     * Insert a usage log entry.
     * Automatically retries once on "MySQL server has gone away" (2006)
     * because AI calls can take longer than the idle timeout of shared-hosting MySQL.
     */
    public static function create(array $data): int
    {
        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $db = Database::getInstance();
                $stmt = $db->prepare(
                    'INSERT INTO ai_usage_logs (
                        provider, model, operation, post_id, request_time, response_time,
                        duration_ms, status, http_status, error_code, error_message,
                        prompt_tokens, completion_tokens, total_tokens
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $data['provider'],
                    $data['model'] ?? null,
                    $data['operation'],
                    $data['post_id'] ?? null,
                    $data['request_time'],
                    $data['response_time'] ?? null,
                    $data['duration_ms'] ?? null,
                    $data['status'],
                    $data['http_status'] ?? null,
                    $data['error_code'] ?? null,
                    $data['error_message'] ?? null,
                    $data['prompt_tokens'] ?? null,
                    $data['completion_tokens'] ?? null,
                    $data['total_tokens'] ?? null,
                ]);

                return (int) $db->lastInsertId();
            } catch (PDOException $e) {
                $isGoneAway = self::isGoneAwayError($e);

                // On last attempt or non-reconnectable error → rethrow
                if ($attempt === $maxAttempts || !$isGoneAway) {
                    throw $e;
                }

                // Force a fresh connection and retry once
                Database::reconnect();
            }
        }

        // Should never reach here
        throw new \RuntimeException('AiUsageLog::create failed after retries');
    }

    public static function getTodayStats(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT
                provider,
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) AS success,
                SUM(CASE WHEN status != 'SUCCESS' THEN 1 ELSE 0 END) AS failed,
                MAX(created_at) AS last_used
             FROM ai_usage_logs
             WHERE DATE(created_at) = CURDATE()
             GROUP BY provider
             ORDER BY total DESC"
        );
        return $stmt->fetchAll();
    }

    public static function getTodayTotals(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) AS success,
                SUM(CASE WHEN status != 'SUCCESS' THEN 1 ELSE 0 END) AS failed
             FROM ai_usage_logs
             WHERE DATE(created_at) = CURDATE()"
        );
        $row = $stmt->fetch();
        return [
            'total'   => (int) ($row['total'] ?? 0),
            'success' => (int) ($row['success'] ?? 0),
            'failed'  => (int) ($row['failed'] ?? 0),
        ];
    }


    public static function getByPostId(int $postId, int $limit = 20): array
    {
        $db = Database::getInstance();
        $limit = max(1, min(100, $limit));
        $stmt = $db->prepare(
            "SELECT provider, model, operation, status, error_message, duration_ms, created_at
             FROM ai_usage_logs
             WHERE post_id = ?
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$postId]);
        return $stmt->fetchAll();
    }

    private static function isGoneAwayError(PDOException $e): bool
    {
        $code = (int) $e->getCode();
        $msg  = $e->getMessage();

        return $code === 2006
            || $code === 2002
            || $code === 2013
            || str_contains($msg, 'server has gone away')
            || str_contains($msg, 'Lost connection')
            || str_contains($msg, 'Error while sending');
    }
}
