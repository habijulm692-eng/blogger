<?php
use App\Helpers\Sanitizer;

$pageTitle = 'Activity Logs';
ob_start();
?>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
            <tr>
                <th class="text-left px-5 py-3">Time</th>
                <th class="text-left px-3 py-3">Admin</th>
                <th class="text-left px-3 py-3">Action</th>
                <th class="text-left px-3 py-3">Description</th>
                <th class="text-left px-5 py-3">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($logs)): ?>
            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">No activity yet.</td></tr>
            <?php else: foreach ($logs as $log): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-5 py-3 text-xs text-slate-500 whitespace-nowrap"><?= Sanitizer::escape($log['created_at']) ?></td>
                <td class="px-3 py-3"><?= Sanitizer::escape($log['admin_name'] ?? 'System') ?></td>
                <td class="px-3 py-3"><span class="px-2 py-0.5 bg-slate-100 rounded text-xs font-medium"><?= Sanitizer::escape($log['action']) ?></span></td>
                <td class="px-3 py-3 text-slate-600 max-w-xs truncate"><?= Sanitizer::escape($log['description'] ?? '') ?></td>
                <td class="px-5 py-3 text-xs text-slate-400"><?= Sanitizer::escape($log['ip_address'] ?? '') ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
