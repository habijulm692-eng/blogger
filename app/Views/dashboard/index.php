<?php
use App\Helpers\Sanitizer;

use App\Helpers\Url;
$pageTitle = 'Dashboard';
ob_start();
?>

<!-- Stats Cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 gap-3 lg:gap-4 mb-6">
    <?php
    $cards = [
        ['Total Posts', $stats['total'], 'bg-blue-50 text-blue-700', 'border-blue-200'],
        ['Draft', $stats['draft'], 'bg-slate-50 text-slate-700', 'border-slate-200'],
        ['Human Review', $stats['human_review'], 'bg-yellow-50 text-yellow-800', 'border-yellow-200'],
        ['Approved', $stats['approved'], 'bg-emerald-50 text-emerald-700', 'border-emerald-200'],
        ['Scheduled', $stats['scheduled'], 'bg-cyan-50 text-cyan-700', 'border-cyan-200'],
        ['Published', $stats['published'], 'bg-green-50 text-green-700', 'border-green-200'],
        ['Failed', $stats['failed'], 'bg-red-50 text-red-700', 'border-red-200'],
        ['AI Reviewing', $stats['ai_reviewing'], 'bg-purple-50 text-purple-700', 'border-purple-200'],
    ];
    foreach ($cards as [$label, $value, $bg, $border]):
    ?>
    <div class="bg-white rounded-xl border <?= $border ?> p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide"><?= $label ?></p>
        <p class="text-2xl font-bold mt-1 <?= explode(' ', $bg)[1] ?>"><?= (int) $value ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Secondary Stats -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 font-medium">Today Generated</p>
        <p class="text-xl font-bold text-slate-800 mt-1"><?= (int) $stats['today_generated'] ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 font-medium">Today Published</p>
        <p class="text-xl font-bold text-slate-800 mt-1"><?= (int) $stats['today_published'] ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 font-medium">API Calls Today</p>
        <p class="text-xl font-bold text-slate-800 mt-1"><?= (int) $apiStats['total'] ?>
            <span class="text-sm font-normal text-slate-400">(<?= (int) $apiStats['failed'] ?> failed)</span>
        </p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 font-medium">Next Scheduled</p>
        <?php if ($nextScheduled): ?>
        <p class="text-sm font-semibold text-slate-800 mt-1 truncate"><?= Sanitizer::escape($nextScheduled['title'] ?? 'Untitled') ?></p>
        <p class="text-xs text-slate-400"><?= Sanitizer::escape($nextScheduled['scheduled_at'] ?? '') ?></p>
        <?php else: ?>
        <p class="text-sm text-slate-400 mt-1">None</p>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions + Blogger Status -->
<div class="flex flex-wrap gap-3 mb-6">
    <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/create'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Create New Post
    </a>
    <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/posts?status=HUMAN_REVIEW'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-medium rounded-lg transition">
        Review Queue (<?= (int) $stats['human_review'] ?>)
    </a>
    <span class="inline-flex items-center gap-1.5 px-3 py-2 text-sm rounded-lg <?= $bloggerConnected ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-orange-50 text-orange-700 border border-orange-200' ?>">
        <span class="w-2 h-2 rounded-full <?= $bloggerConnected ? 'bg-green-500' : 'bg-orange-500' ?>"></span>
        Blogger: <?= $bloggerConnected ? 'Connected' : 'Not Connected' ?>
    </span>
</div>

<!-- Recent Posts -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
        <h2 class="font-semibold text-slate-800">Recent Posts</h2>
        <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/posts'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm text-blue-600 hover:text-blue-700">View all →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-5 py-3 font-medium">Title</th>
                    <th class="text-left px-3 py-3 font-medium">Status</th>
                    <th class="text-left px-3 py-3 font-medium">AI Score</th>
                    <th class="text-left px-3 py-3 font-medium">Created</th>
                    <th class="text-left px-3 py-3 font-medium">Scheduled</th>
                    <th class="text-right px-5 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($recentPosts)): ?>
                <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No posts yet. Create your first post!</td></tr>
                <?php else: foreach ($recentPosts as $p): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$p['id']), ENT_QUOTES, 'UTF-8') ?>" class="font-medium text-slate-800 hover:text-blue-600 truncate block max-w-xs">
                            <?= Sanitizer::escape($p['title'] ?: $p['topic'] ?: 'Untitled') ?>
                        </a>
                    </td>
                    <td class="px-3 py-3">
                        <?php
                        $statusColors = [
                            'DRAFT' => 'bg-slate-100 text-slate-700',
                            'GENERATING' => 'bg-blue-100 text-blue-700',
                            'GENERATED' => 'bg-indigo-100 text-indigo-700',
                            'AI_REVIEWING' => 'bg-purple-100 text-purple-700',
                            'HUMAN_REVIEW' => 'bg-yellow-100 text-yellow-800',
                            'REJECTED' => 'bg-red-100 text-red-700',
                            'APPROVED' => 'bg-emerald-100 text-emerald-700',
                            'SCHEDULED' => 'bg-cyan-100 text-cyan-700',
                            'PUBLISHING' => 'bg-orange-100 text-orange-700',
                            'PUBLISHED' => 'bg-green-100 text-green-700',
                            'FAILED' => 'bg-red-100 text-red-700',
                        ];
                        $sc = $statusColors[$p['status']] ?? 'bg-slate-100 text-slate-600';
                        ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $sc ?>"><?= str_replace('_', ' ', $p['status']) ?></span>
                    </td>
                    <td class="px-3 py-3 text-slate-600"><?= $p['ai_score'] !== null ? number_format((float)$p['ai_score'], 1) : '—' ?></td>
                    <td class="px-3 py-3 text-slate-500 text-xs"><?= $p['created_at'] ? date('M j, H:i', strtotime($p['created_at'])) : '—' ?></td>
                    <td class="px-3 py-3 text-slate-500 text-xs"><?= $p['scheduled_at'] ? date('M j, H:i', strtotime($p['scheduled_at'])) : '—' ?></td>
                    <td class="px-5 py-3 text-right">
                        <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$p['id']), ENT_QUOTES, 'UTF-8') ?>" class="text-blue-600 hover:text-blue-700 text-xs font-medium">View</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
