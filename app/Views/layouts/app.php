<?php
use App\Helpers\Csrf;
use App\Helpers\Sanitizer;
use App\Helpers\Session;
use App\Helpers\Url;

$adminName = Session::get('admin_name', 'Admin');
$currentPath = \App\Helpers\Url::pathForRouting($_SERVER['REQUEST_URI'] ?? '/');
$u = static fn(string $path): string => Url::to($path);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= Sanitizer::escape(Csrf::token()) ?>">
    <meta name="app-base" content="<?= Sanitizer::escape(Url::base()) ?>">
    <meta name="app-url" content="<?= Sanitizer::escape(Url::base()) ?>">
    <title><?= Sanitizer::escape($pageTitle ?? 'Dashboard') ?> — AI Blogger</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .sidebar-link.active { background: #eff6ff; color: #1d4ed8; border-right: 3px solid #2563eb; }
        .toast { animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .prose h2 { font-size: 1.4em; font-weight: 700; margin: 1.2em 0 0.5em; }
        .prose h3 { font-size: 1.15em; font-weight: 600; margin: 1em 0 0.4em; }
        .prose p { margin: 0.6em 0; line-height: 1.7; }
        .prose ul { list-style: disc; padding-left: 1.5em; margin: 0.6em 0; }
        .prose li { margin: 0.3em 0; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
<div class="flex min-h-screen">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-slate-200 transform -translate-x-full lg:translate-x-0 transition-transform duration-200">
        <div class="flex items-center gap-2 px-5 h-16 border-b border-slate-200">
            <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-sm">AI</div>
            <span class="font-semibold text-lg text-slate-800">AI Blogger</span>
        </div>
        <nav class="p-3 space-y-0.5 overflow-y-auto" style="height: calc(100vh - 4rem)">
            <?php
            $links = [
                ['/dashboard', 'Dashboard'],
                ['/posts', 'Posts'],
                ['/posts/create', 'Create Post'],
                ['/sources', 'Sources'],
                ['/chat', 'AI Chat'],
                ['/blogger', 'Blogger'],
                ['/users', 'Users'],
                ['/ai-usage', 'AI Usage'],
                ['/database', 'Database & SQL'],
                ['/settings', 'Settings'],
                ['/activity-logs', 'Activity Logs'],
            ];
            foreach ($links as [$href, $label]):
                $active = ($currentPath === $href || ($href !== '/dashboard' && $href !== '/' && str_starts_with($currentPath, $href)));
                if ($href === '/dashboard' && ($currentPath === '/' || $currentPath === '/dashboard')) $active = true;
                if ($href === '/posts' && str_starts_with($currentPath, '/posts/create')) $active = false;
            ?>
            <a href="<?= Sanitizer::escape($u($href)) ?>" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition <?= $active ? 'active' : '' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
            <div class="pt-4 mt-4 border-t border-slate-200">
                <form method="POST" action="<?= Sanitizer::escape($u('/logout')) ?>">
                    <?= Csrf::field() ?>
                    <button type="submit" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 w-full transition">Logout</button>
                </form>
            </div>
        </nav>
    </aside>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-30 hidden lg:hidden" onclick="toggleSidebar()"></div>
    <div class="flex-1 lg:ml-64">
        <header class="sticky top-0 z-20 bg-white border-b border-slate-200 h-16 flex items-center px-4 lg:px-6 gap-4">
            <button type="button" onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-slate-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="text-lg font-semibold text-slate-800 flex-1"><?= Sanitizer::escape($pageTitle ?? 'Dashboard') ?></h1>
            <span class="text-sm text-slate-500 hidden sm:inline"><?= Sanitizer::escape($adminName) ?></span>
        </header>
        <main class="p-4 lg:p-6">
            <?php if ($flashSuccess = Session::flash('success')): ?>
            <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm"><?= Sanitizer::escape($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError = Session::flash('error')): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= Sanitizer::escape($flashError) ?></div>
            <?php endif; ?>
            <?= $content ?? '' ?>
        </main>
    </div>
</div>
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
<script src="<?= Sanitizer::escape(Url::asset('js/app.js')) ?>"></script>
<script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebar-overlay').classList.toggle('hidden');}</script>
</body>
</html>
