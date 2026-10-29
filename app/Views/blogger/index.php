<?php
use App\Helpers\Sanitizer;
use App\Helpers\Csrf;

use App\Helpers\Url;
$pageTitle = 'Blogger Integration';
ob_start();
?>

<div class="max-w-2xl mx-auto space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold text-slate-800 mb-4">Connection Status</h2>

        <?php if (!$isConfigured): ?>
        <div class="p-4 bg-orange-50 border border-orange-200 rounded-lg text-sm text-orange-800 mb-4">
            Google OAuth is not configured. Add <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> to your <code>.env</code> file.
        </div>
        <?php endif; ?>

        <?php if ($connection && $connection['is_active']): ?>
        <div class="flex items-center gap-2 mb-4">
            <span class="w-3 h-3 rounded-full bg-green-500"></span>
            <span class="font-medium text-green-700">Connected</span>
        </div>
        <div class="space-y-2 text-sm mb-6">
            <div class="flex justify-between"><span class="text-slate-500">Blog Name</span><span class="font-medium"><?= Sanitizer::escape($connection['blog_name'] ?? '—') ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Blog ID</span><span class="font-mono text-xs"><?= Sanitizer::escape($connection['blog_id'] ?? '—') ?></span></div>
            <?php if ($connection['blog_url']): ?>
            <div class="flex justify-between"><span class="text-slate-500">URL</span><a href="<?= Sanitizer::escape($connection['blog_url']) ?>" target="_blank" class="text-blue-600 hover:underline"><?= Sanitizer::escape($connection['blog_url']) ?></a></div>
            <?php endif; ?>
            <div class="flex justify-between"><span class="text-slate-500">Last Used</span><span><?= Sanitizer::escape($connection['last_used_at'] ?? 'Never') ?></span></div>
        </div>
        <div class="flex gap-3">
            <form method="POST" action="<?= htmlspecialchars(\App\Helpers\Url::to('/blogger/test'), ENT_QUOTES, 'UTF-8') ?>">
                <?= Csrf::field() ?>
                <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm rounded-lg">Test Connection</button>
            </form>
            <form method="POST" action="<?= htmlspecialchars(\App\Helpers\Url::to('/blogger/disconnect'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Disconnect Blogger?')">
                <?= Csrf::field() ?>
                <button type="submit" class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-sm rounded-lg border border-red-200">Disconnect</button>
            </form>
        </div>
        <?php else: ?>
        <div class="flex items-center gap-2 mb-4">
            <span class="w-3 h-3 rounded-full bg-orange-500"></span>
            <span class="font-medium text-orange-700">Not Connected</span>
        </div>
        <p class="text-sm text-slate-500 mb-4">Connect your Google account to publish posts to Blogger automatically.</p>
        <?php if ($isConfigured): ?>
        <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/blogger/connect'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">Connect Blogger</a>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold text-slate-800 mb-2">Setup Instructions</h2>
        <ol class="text-sm text-slate-600 space-y-2 list-decimal pl-5">
            <li>Go to <a href="https://console.cloud.google.com/" target="_blank" class="text-blue-600 hover:underline">Google Cloud Console</a></li>
            <li>Create a project and enable the <strong>Blogger API v3</strong></li>
            <li>Create OAuth 2.0 credentials (Web application)</li>
            <li>Add authorized redirect URI: <code class="bg-slate-100 px-1 rounded text-xs"><?= Sanitizer::escape($_ENV['GOOGLE_REDIRECT_URI'] ?? 'http://localhost/auth/google/callback') ?></code></li>
            <li>Copy Client ID and Client Secret to <code>.env</code></li>
            <li>Optionally set <code>BLOGGER_BLOG_ID</code> if you have multiple blogs</li>
        </ol>
    </div>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
