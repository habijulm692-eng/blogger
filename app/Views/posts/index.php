<?php
use App\Helpers\Sanitizer;

use App\Helpers\Url;
$pageTitle = 'Posts';
ob_start();

$statusColors = [
    'DRAFT' => 'bg-slate-100 text-slate-700', 'GENERATING' => 'bg-blue-100 text-blue-700',
    'GENERATED' => 'bg-indigo-100 text-indigo-700', 'AI_REVIEWING' => 'bg-purple-100 text-purple-700',
    'HUMAN_REVIEW' => 'bg-yellow-100 text-yellow-800', 'REJECTED' => 'bg-red-100 text-red-700',
    'APPROVED' => 'bg-emerald-100 text-emerald-700', 'SCHEDULED' => 'bg-cyan-100 text-cyan-700',
    'PUBLISHING' => 'bg-orange-100 text-orange-700', 'PUBLISHED' => 'bg-green-100 text-green-700',
    'FAILED' => 'bg-red-100 text-red-700',
];
?>

<div class="flex flex-wrap items-center gap-3 mb-6">
    <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/create'), ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">+ Create Post</a>
    <form method="GET" action="<?= htmlspecialchars(\App\Helpers\Url::to('/posts'), ENT_QUOTES, 'UTF-8') ?>" class="flex flex-wrap gap-2 flex-1">
        <input type="text" name="search" value="<?= Sanitizer::escape($filters['search'] ?? '') ?>" placeholder="Search..."
               class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All Status</option>
            <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= str_replace('_', ' ', $s) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg">Filter</button>
    </form>
</div>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-5 py-3 font-medium">Title / Topic</th>
                    <th class="text-left px-3 py-3 font-medium">Status</th>
                    <th class="text-left px-3 py-3 font-medium">AI Score</th>
                    <th class="text-left px-3 py-3 font-medium">Created</th>
                    <th class="text-left px-3 py-3 font-medium">Scheduled</th>
                    <th class="text-right px-5 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($posts)): ?>
                <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No posts found.</td></tr>
                <?php else: foreach ($posts as $p): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$p['id']), ENT_QUOTES, 'UTF-8') ?>" class="font-medium text-slate-800 hover:text-blue-600 block max-w-xs truncate">
                            <?= Sanitizer::escape($p['title'] ?: $p['topic'] ?: 'Untitled') ?>
                        </a>
                    </td>
                    <td class="px-3 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $statusColors[$p['status']] ?? 'bg-slate-100' ?>">
                            <?= str_replace('_', ' ', $p['status']) ?>
                        </span>
                    </td>
                    <td class="px-3 py-3"><?= $p['ai_score'] !== null ? number_format((float)$p['ai_score'], 1) : '—' ?></td>
                    <td class="px-3 py-3 text-xs text-slate-500"><?= $p['created_at'] ? date('M j, Y H:i', strtotime($p['created_at'])) : '—' ?></td>
                    <td class="px-3 py-3 text-xs text-slate-500"><?= $p['scheduled_at'] ? date('M j, H:i', strtotime($p['scheduled_at'])) : '—' ?></td>
                    <td class="px-5 py-3 text-right space-x-2">
                        <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$p['id']), ENT_QUOTES, 'UTF-8') ?>" class="text-blue-600 hover:underline text-xs">View</a>
                        <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$p['id'] . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:underline text-xs">Edit</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
    <div class="px-5 py-3 border-t border-slate-200 flex items-center justify-between text-sm">
        <span class="text-slate-500">Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?> (<?= $pagination['total'] ?> total)</span>
        <div class="flex gap-2">
            <?php if ($pagination['page'] > 1): ?>
            <a href="?page=<?= $pagination['page'] - 1 ?>&status=<?= urlencode($filters['status'] ?? '') ?>&search=<?= urlencode($filters['search'] ?? '') ?>" class="px-3 py-1 border rounded hover:bg-slate-50">Prev</a>
            <?php endif; ?>
            <?php if ($pagination['page'] < $pagination['total_pages']): ?>
            <a href="?page=<?= $pagination['page'] + 1 ?>&status=<?= urlencode($filters['status'] ?? '') ?>&search=<?= urlencode($filters['search'] ?? '') ?>" class="px-3 py-1 border rounded hover:bg-slate-50">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
