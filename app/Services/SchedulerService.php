<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\ScheduledPost;

class SchedulerService
{
    private BloggerService $blogger;
    private int $retryLimit;

    public function __construct()
    {
        $this->blogger = new BloggerService();
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $this->retryLimit = $config['publishing']['retry_limit'] ?? 3;
    }

    /**
     * Process all due scheduled posts.
     * Called by cron every 5 minutes.
     */
    public function processDuePosts(): array
    {
        $results = ['processed' => 0, 'success' => 0, 'failed' => 0, 'skipped' => 0];
        $posts = Post::getDueForPublishing();

        foreach ($posts as $post) {
            $results['processed']++;
            $outcome = $this->publishPost((int) $post['id']);

            if ($outcome === 'success') {
                $results['success']++;
            } elseif ($outcome === 'failed') {
                $results['failed']++;
            } else {
                $results['skipped']++;
            }
        }

        Logger::info('Scheduler run completed', $results);
        return $results;
    }

    /**
     * Publish a single post with locking and idempotency.
     */
    public function publishPost(int $postId): string
    {
        $post = Post::findById($postId);
        if (!$post) {
            return 'skipped';
        }

        // Already published — idempotency
        if ($post['status'] === 'PUBLISHED' || !empty($post['blogger_post_id'])) {
            Logger::info('Post already published, skipping', ['post_id' => $postId]);
            return 'skipped';
        }

        if ($post['status'] !== 'SCHEDULED' && $post['status'] !== 'FAILED') {
            Logger::warning('Post not in publishable status', [
                'post_id' => $postId,
                'status' => $post['status'],
            ]);
            return 'skipped';
        }

        $scheduled = ScheduledPost::findByPostId($postId);
        if (!$scheduled) {
            return 'skipped';
        }

        $lockId = bin2hex(random_bytes(16));
        if (!ScheduledPost::acquireLock((int) $scheduled['id'], $lockId)) {
            Logger::info('Could not acquire lock for post', ['post_id' => $postId]);
            return 'skipped';
        }

        // Generate unique publish attempt ID for idempotency
        $attemptId = $post['publish_attempt_id'] ?? bin2hex(random_bytes(16));

        Post::update($postId, [
            'status' => 'PUBLISHING',
            'publish_attempt_id' => $attemptId,
            'last_attempt_at' => date('Y-m-d H:i:s'),
        ]);
        Post::logStatusChange($postId, $post['status'], 'PUBLISHING', null, 'Scheduler started publishing');

        try {
            // Re-fetch to get latest data
            $post = Post::findById($postId);
            $result = $this->blogger->publish($post);

            if ($result['success']) {
                Post::update($postId, [
                    'status' => 'PUBLISHED',
                    'blogger_post_id' => $result['blogger_post_id'],
                    'blogger_url' => $result['blogger_url'],
                    'published_at' => date('Y-m-d H:i:s'),
                    'error_message' => null,
                    'error_code' => null,
                ]);
                Post::logStatusChange($postId, 'PUBLISHING', 'PUBLISHED', null, 'Saved as Draft to Blogger');
                ScheduledPost::markCompleted((int) $scheduled['id']);

                ActivityLog::log('post_published', null, 'post', $postId, 'Post saved as Draft in Blogger', [
                    'blogger_post_id' => $result['blogger_post_id'],
                    'url' => $result['blogger_url'],
                ]);

                Logger::info('Post saved as Blogger draft successfully', [
                    'post_id' => $postId,
                    'blogger_url' => $result['blogger_url'],
                ]);

                return 'success';
            }

            // Publish failed
            $retryCount = (int) ($post['retry_count'] ?? 0) + 1;
            $errorMsg = $result['error'] ?? 'Unknown publish error';
            $errorCode = $result['error_code'] ?? 'UNKNOWN';

            if ($retryCount >= $this->retryLimit) {
                Post::update($postId, [
                    'status' => 'FAILED',
                    'error_message' => $errorMsg,
                    'error_code' => $errorCode,
                    'retry_count' => $retryCount,
                    'last_attempt_at' => date('Y-m-d H:i:s'),
                ]);
                Post::logStatusChange($postId, 'PUBLISHING', 'FAILED', null, "Failed after {$retryCount} attempts: {$errorMsg}");
                ScheduledPost::markFailed((int) $scheduled['id'], $errorMsg);

                ActivityLog::log('post_publish_failed', null, 'post', $postId, $errorMsg);
                return 'failed';
            }

            // Schedule retry with exponential backoff
            $delayMinutes = match ($retryCount) {
                1 => 5,
                2 => 15,
                default => 60,
            };

            $nextAttempt = date('Y-m-d H:i:s', time() + ($delayMinutes * 60));

            Post::update($postId, [
                'status' => 'SCHEDULED',
                'error_message' => $errorMsg,
                'error_code' => $errorCode,
                'retry_count' => $retryCount,
                'last_attempt_at' => date('Y-m-d H:i:s'),
                'scheduled_at' => $nextAttempt,
            ]);
            Post::logStatusChange($postId, 'PUBLISHING', 'SCHEDULED', null, "Retry #{$retryCount} scheduled in {$delayMinutes} min");

            // Update scheduled_posts
            $db = Database::getInstance();
            $stmt = $db->prepare(
                "UPDATE scheduled_posts SET status = 'PENDING', scheduled_at = ?, error_message = ?, locked_at = NULL, locked_by = NULL WHERE id = ?"
            );
            $stmt->execute([$nextAttempt, $errorMsg, $scheduled['id']]);

            Logger::warning('Publish failed, retry scheduled', [
                'post_id' => $postId,
                'retry' => $retryCount,
                'next_attempt' => $nextAttempt,
                'error' => $errorMsg,
            ]);

            return 'failed';
        } catch (\Throwable $e) {
            Logger::error('Exception during publish', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            Post::update($postId, [
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
                'last_attempt_at' => date('Y-m-d H:i:s'),
            ]);
            ScheduledPost::markFailed((int) $scheduled['id'], $e->getMessage());

            return 'failed';
        }
    }

    /**
     * Retry failed posts that still have retries left.
     */
    public function retryFailedPosts(): array
    {
        $db = Database::getInstance();
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $limit = $config['publishing']['retry_limit'] ?? 3;

        $stmt = $db->prepare(
            "SELECT id FROM posts
             WHERE status = 'FAILED'
               AND retry_count < ?
               AND (last_attempt_at IS NULL OR last_attempt_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
             ORDER BY last_attempt_at ASC
             LIMIT 5"
        );
        $stmt->execute([$limit]);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $results = ['retried' => 0, 'success' => 0];

        foreach ($ids as $id) {
            // Reset to SCHEDULED for retry
            Post::update((int) $id, [
                'status' => 'SCHEDULED',
                'scheduled_at' => date('Y-m-d H:i:s'),
            ]);

            $scheduled = ScheduledPost::findByPostId((int) $id);
            if ($scheduled) {
                $db->prepare(
                    "UPDATE scheduled_posts SET status = 'PENDING', scheduled_at = NOW(), locked_at = NULL WHERE id = ?"
                )->execute([$scheduled['id']]);
            }

            $outcome = $this->publishPost((int) $id);
            $results['retried']++;
            if ($outcome === 'success') {
                $results['success']++;
            }
        }

        return $results;
    }
}
