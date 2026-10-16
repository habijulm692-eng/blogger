<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Database;

class PostReview
{
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO post_reviews (
                post_id, provider, model, score, grammar_score, seo_score,
                readability_score, structure_score, problems, suggestions,
                required_changes, verdict, raw_response
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['post_id'],
            $data['provider'],
            $data['model'] ?? null,
            $data['score'] ?? null,
            $data['grammar_score'] ?? null,
            $data['seo_score'] ?? null,
            $data['readability_score'] ?? null,
            $data['structure_score'] ?? null,
            isset($data['problems']) ? json_encode($data['problems'], JSON_UNESCAPED_UNICODE) : null,
            isset($data['suggestions']) ? json_encode($data['suggestions'], JSON_UNESCAPED_UNICODE) : null,
            isset($data['required_changes']) ? json_encode($data['required_changes'], JSON_UNESCAPED_UNICODE) : null,
            $data['verdict'] ?? null,
            $data['raw_response'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    public static function getLatestForPost(int $postId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT * FROM post_reviews WHERE post_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$postId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        foreach (['problems', 'suggestions', 'required_changes'] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = json_decode($row[$field], true) ?: [];
            }
        }

        return $row;
    }

    public static function getAllForPost(int $postId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT * FROM post_reviews WHERE post_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$postId]);
        $rows = $stmt->fetchAll();

        return array_map(static function (array $row): array {
            foreach (['problems', 'suggestions', 'required_changes'] as $field) {
                if (isset($row[$field]) && is_string($row[$field])) {
                    $row[$field] = json_decode($row[$field], true) ?: [];
                }
            }
            return $row;
        }, $rows);
    }
}
