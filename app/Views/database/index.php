<?php

use App\Helpers\Sanitizer;
use App\Helpers\Csrf;
use App\Helpers\Url;

$pageTitle = 'Database & SQL Manager';
ob_start();

$pendingCount = count(array_filter($files, fn($f) => !$f['is_executed']));
?>

<div class="space-y-6 max-w-6xl mx-auto">
    <!-- Top Welcome & Status Banner -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <span>🗄️</span> ডেটাবেজ ও SQL ফাইল ম্যানেজার (Database & Migrations)
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    phpMyAdmin-এ না গিয়ে সরাসরি এই প্যানেল থেকে SQL ফাইল আপলোড করুন এবং এক ক্লিকে ডেটাবেজ মাইগ্রেশন আপডেট করুন।
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <form method="POST" action="<?= htmlspecialchars(Url::to('/database/migrate'), ENT_QUOTES, 'UTF-8') ?>">
                    <?= Csrf::field() ?>
                    <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition flex items-center gap-2">
                        <span>⚡</span>
                        <span>পেন্ডিং মাইগ্রেশন রান করুন (<?= $pendingCount ?>)</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Database Info Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-5 border-t border-slate-100">
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-100">
                <span class="text-xs text-slate-400 block mb-0.5">কানেকশন স্ট্যাটাস</span>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full <?= $info['connected'] ? 'bg-green-500 animate-pulse' : 'bg-red-500' ?>"></span>
                    <span class="font-semibold text-sm text-slate-800"><?= $info['connected'] ? 'সফলভাবে যুক্ত (Connected)' : 'কানেকশন সমস্যা' ?></span>
                </div>
            </div>

            <div class="p-3 bg-slate-50 rounded-lg border border-slate-100">
                <span class="text-xs text-slate-400 block mb-0.5">ডেটাবেজ নাম</span>
                <span class="font-semibold text-sm text-slate-800 font-mono"><?= Sanitizer::escape($info['database'] ?? 'N/A') ?></span>
            </div>

            <div class="p-3 bg-slate-50 rounded-lg border border-slate-100">
                <span class="text-xs text-slate-400 block mb-0.5">মোট টেবিল সংখ্যা</span>
                <span class="font-semibold text-sm text-slate-800"><?= (int)($info['table_count'] ?? 0) ?> টি টেবিল</span>
            </div>

            <div class="p-3 bg-slate-50 rounded-lg border border-slate-100">
                <span class="text-xs text-slate-400 block mb-0.5">MySQL সংস্করণ</span>
                <span class="font-semibold text-sm text-slate-800 font-mono"><?= Sanitizer::escape(explode('-', (string)($info['version'] ?? 'N/A'))[0]) ?></span>
            </div>
        </div>
    </div>

    <!-- Main 2-Column Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left Column: Migration Files List -->
        <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="font-semibold text-slate-800 text-base flex items-center gap-2">
                    <span>📁</span> মাইগ্রেশন ফাইলসমূহ (Migration Files)
                </h2>
                <span class="text-xs text-slate-500">মোট <?= count($files) ?> টি ফাইল</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-slate-700 uppercase font-semibold text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">মাইগ্রেশন ফাইলের নাম</th>
                            <th class="py-2.5 px-3">সাইজ</th>
                            <th class="py-2.5 px-3">স্ট্যাটাস</th>
                            <th class="py-2.5 px-3 text-right">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($files)): ?>
                        <tr>
                            <td colspan="4" class="py-6 text-center text-slate-400">কোনো মাইগ্রেশন ফাইল পাওয়া যায়নি।</td>
                        </tr>
                        <?php else: foreach ($files as $f): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3 px-3 font-mono font-medium text-slate-800 flex items-center gap-2">
                                <span class="text-slate-400">📄</span>
                                <span title="<?= Sanitizer::escape($f['name']) ?>"><?= Sanitizer::escape($f['name']) ?></span>
                            </td>
                            <td class="py-3 px-3 text-slate-500 whitespace-nowrap">
                                <?= round($f['size'] / 1024, 1) ?> KB
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap">
                                <?php if ($f['is_executed']): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-700">
                                    <span>✓</span> সম্পূর্ণ (Executed)
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">
                                    <span>⏳</span> পেন্ডিং (Pending)
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-right whitespace-nowrap">
                                <form method="POST" action="<?= htmlspecialchars(Url::to('/database/migrate-one'), ENT_QUOTES, 'UTF-8') ?>" class="inline-block">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="migration" value="<?= Sanitizer::escape($f['name']) ?>">
                                    <button type="submit" class="px-2.5 py-1 text-[11px] font-medium rounded border <?= $f['is_executed'] ? 'border-slate-300 text-slate-600 hover:bg-slate-100' : 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100' ?> transition">
                                        <?= $f['is_executed'] ? 'পুনরায় রান' : 'এখন রান করুন' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="text-[11px] text-slate-400 mt-2">
                * মাইগ্রেশন ফাইলগুলো <code>database/migrations/</code> ফোল্ডারে সংরক্ষিত থাকে।
            </p>
        </div>

        <!-- Right Column: Upload SQL & Run Custom Queries -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Upload .sql Box -->
            <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="font-semibold text-slate-800 text-base flex items-center gap-2">
                        <span>📤</span> SQL ফাইল আপলোড করুন (Upload .sql)
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">যেকোনো .sql ফাইল ব্রাউজ করে আপলোড দিলে স্বয়ংক্রিয়ভাবে এক্সিকিউট হবে।</p>
                </div>

                <form method="POST" action="<?= htmlspecialchars(Url::to('/database/upload'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="space-y-4">
                    <?= Csrf::field() ?>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">কম্পিউটার বা মোবাইল থেকে .sql ফাইল বেছে নিন:</label>
                        <input type="file" name="sql_file" accept=".sql" required
                               class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer border border-slate-300 rounded-lg p-1.5">
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center justify-center gap-1.5">
                        <span>🚀</span> <span>আপলোড ও এক্সিকিউট করুন (Run SQL File)</span>
                    </button>
                </form>
            </div>

            <!-- Run Raw SQL Query Box -->
            <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="font-semibold text-slate-800 text-base flex items-center gap-2">
                        <span>💻</span> সরাসরি SQL কোড রান করুন
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">এখানে যেকোনো CREATE, ALTER বা INSERT কুয়েরি পেস্ট করে রান করতে পারেন।</p>
                </div>

                <form method="POST" action="<?= htmlspecialchars(Url::to('/database/run-query'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
                    <?= Csrf::field() ?>
                    <textarea name="sql_query" rows="5" required
                              placeholder="e.g. ALTER TABLE posts ADD COLUMN test_col VARCHAR(100) NULL;&#10;CREATE TABLE IF NOT EXISTS test (id INT);"
                              class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>

                    <button type="submit" onclick="return confirm('আপনি কি নিশ্চিত যে এই SQL স্টেটমেন্টগুলো রান করতে চান?');"
                            class="w-full py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg transition flex items-center justify-center gap-1.5">
                        <span>⚡</span> <span>SQL কুয়েরি চালান (Execute SQL)</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Collapsible Database Tables List -->
    <?php if (!empty($info['tables'])): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <details class="cursor-pointer group">
            <summary class="font-semibold text-slate-800 text-sm flex items-center justify-between list-none">
                <span class="flex items-center gap-2">
                    <span>📊</span> বর্তমান ডেটাবেজের সকল টেবিল ও রো সংখ্যা (<?= count($info['tables']) ?> টি টেবিল)
                </span>
                <span class="text-xs text-blue-600 group-open:rotate-180 transition-transform">▼</span>
            </summary>
            <div class="mt-4 pt-3 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                <?php foreach ($info['tables'] as $t): ?>
                <div class="p-2.5 bg-slate-50 rounded border border-slate-100 flex items-center justify-between font-mono">
                    <span class="text-slate-700 font-medium"><?= Sanitizer::escape($t['TABLE_NAME']) ?></span>
                    <span class="text-[11px] px-2 py-0.5 bg-white border border-slate-200 rounded text-slate-500">
                        <?= (int)($t['TABLE_ROWS'] ?? 0) ?> rows
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </details>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
