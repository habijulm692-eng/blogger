<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\AiUsageLog;
use App\Models\Post;
use App\Models\BloggerConnection;

class DashboardController
{
    public function index(): void
    {
        $uid = (int) Session::getAdminId();

        $stats = [
            'total' => Post::countByStatus(null, $uid),
            'draft' => Post::countByStatus('DRAFT', $uid),
            'ai_reviewing' => Post::countByStatus('AI_REVIEWING', $uid) + Post::countByStatus('GENERATING', $uid),
            'human_review' => Post::countByStatus('HUMAN_REVIEW', $uid) + Post::countByStatus('GENERATED', $uid),
            'approved' => Post::countByStatus('APPROVED', $uid),
            'scheduled' => Post::countByStatus('SCHEDULED', $uid),
            'published' => Post::countByStatus('PUBLISHED', $uid),
            'failed' => Post::countByStatus('FAILED', $uid),
            'today_generated' => Post::countToday('created_at'),
            'today_published' => Post::countToday('published_at'),
        ];

        $apiStats = AiUsageLog::getTodayTotals();
        $providerStats = AiUsageLog::getTodayStats();
        $recentPosts = method_exists(Post::class, 'recentForUser')
            ? Post::recentForUser($uid, 10)
            : Post::getRecent(10);
        $nextScheduled = Post::getNextScheduled();
        $bloggerConnected = BloggerConnection::getActive($uid) !== null;

        Response::view('dashboard.index', [
            'stats' => $stats,
            'apiStats' => $apiStats,
            'providerStats' => $providerStats,
            'recentPosts' => $recentPosts,
            'nextScheduled' => $nextScheduled,
            'bloggerConnected' => $bloggerConnected,
            'adminName' => Session::get('admin_name', 'Admin'),
        ]);
    }
}
