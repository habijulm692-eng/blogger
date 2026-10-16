<?php
use App\Helpers\Url;
use App\Helpers\Csrf;
use App\Helpers\Sanitizer;
$u = static fn(string $p) => Url::to($p);
$pageTitle = $pageTitle ?? 'Content Sources';
ob_start();
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Add competitor blogs/news sites → fetch → AI suggests original post ideas.</p>
        <div class="flex flex-wrap gap-2 items-center">
            <form method="POST" action="<?= Sanitizer::escape($u('/sources/fetch')) ?>"><?= Csrf::field() ?>
                <button class="px-3 py-2 text-sm rounded-lg bg-slate-800 text-white">Fetch All</button>
            </form>

            <!-- Language selector + AI Suggest Topics -->
            <form method="POST" action="<?= Sanitizer::escape($u('/sources/suggest')) ?>" id="suggest-form" class="flex gap-2 items-center">
                <?= Csrf::field() ?>
                <select name="suggest_language" id="suggest-lang" class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="bn">বাংলা</option>
                    <option value="en">International (Tier-1)</option>
                </select>
                <button type="submit" id="btn-suggest-topics"
                        class="px-3 py-2 text-sm rounded-lg bg-blue-600 text-white whitespace-nowrap">
                    AI Suggest Topics
                </button>
            </form>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <h2 class="font-medium text-slate-800">Add Website</h2>
            <form method="POST" action="<?= Sanitizer::escape($u('/sources')) ?>" class="space-y-3">
                <?= Csrf::field() ?>
                <input name="name" required placeholder="Site name" class="w-full border rounded-lg px-3 py-2 text-sm">
                <input name="url" required placeholder="https://example.com" class="w-full border rounded-lg px-3 py-2 text-sm">
                <input name="rss_url" placeholder="RSS URL (optional)" class="w-full border rounded-lg px-3 py-2 text-sm">
                <div class="flex gap-2">
                    <select name="site_type" class="border rounded-lg px-3 py-2 text-sm">
                        <option value="blog">Blog</option>
                        <option value="news">News</option>
                        <option value="other">Other</option>
                    </select>
                    <input name="category" placeholder="Category" class="flex-1 border rounded-lg px-3 py-2 text-sm">
                </div>
                <button class="w-full py-2 bg-blue-600 text-white rounded-lg text-sm">Add Source</button>
            </form>
            <h3 class="text-sm font-medium text-slate-600 pt-2">Saved sources</h3>
            <ul class="divide-y text-sm">
                <?php foreach ($sources as $s): ?>
                <li class="py-2 flex justify-between gap-2">
                    <div>
                        <div class="font-medium"><?= Sanitizer::escape($s['name']) ?>
                            <span class="text-xs text-slate-400">(<?= Sanitizer::escape($s['site_type']) ?>)</span>
                        </div>
                        <a class="text-blue-600 text-xs break-all" href="<?= Sanitizer::escape($s['url']) ?>" target="_blank" rel="noopener"><?= Sanitizer::escape($s['url']) ?></a>
                    </div>
                    <form method="POST" action="<?= Sanitizer::escape($u('/sources/' . (int)$s['id'] . '/delete')) ?>" onsubmit="return confirm('Delete?')">
                        <?= Csrf::field() ?>
                        <button class="text-red-600 text-xs">Delete</button>
                    </form>
                </li>
                <?php endforeach; ?>
                <?php if (empty($sources)): ?><li class="text-slate-400 py-4">No sources yet.</li><?php endif; ?>
            </ul>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <h2 class="font-medium text-slate-800">Pending Suggestions</h2>
            <ul class="space-y-3">
                <?php foreach ($suggestions as $sg): ?>
                <li class="border rounded-lg p-3">
                    <div class="font-medium text-sm"><?= Sanitizer::escape($sg['title']) ?></div>
                    <p class="text-xs text-slate-500 mt-1"><?= Sanitizer::escape($sg['angle'] ?? '') ?></p>
                    <div class="text-xs text-slate-400 mt-1"><?= Sanitizer::escape($sg['source_name'] ?? '') ?></div>
                    <div class="flex gap-2 mt-2">
                        <form method="POST" action="<?= Sanitizer::escape($u('/sources/suggestions/' . (int)$sg['id'] . '/accept')) ?>"
                              class="flex flex-wrap items-center gap-1 accept-suggestion-form">
                            <?= Csrf::field() ?>
                            <select name="language" class="text-xs border border-slate-300 rounded px-1 py-1 suggestion-lang">
                                <option value="bn">বাংলা</option>
                                <option value="en">International (Tier-1)</option>
                            </select>
                            <button type="submit" class="px-2 py-1 text-xs bg-emerald-600 text-white rounded accept-btn">
                                Accept → Generate
                            </button>
                        </form>
                        <form method="POST" action="<?= Sanitizer::escape($u('/sources/suggestions/' . (int)$sg['id'] . '/reject')) ?>">
                            <?= Csrf::field() ?>
                            <button class="px-2 py-1 text-xs border rounded">Reject</button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
                <?php if (empty($suggestions)): ?><li class="text-slate-400 text-sm">No suggestions yet.</li><?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="font-medium text-slate-800 mb-3">Recent Source Items</h2>
        <div class="overflow-x-auto text-sm">
            <table class="w-full">
                <thead class="text-left text-slate-500 border-b"><tr><th class="py-2">Title</th><th>Source</th><th>Fetched</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-2"><a class="text-blue-600" target="_blank" rel="noopener" href="<?= Sanitizer::escape($it['url']) ?>"><?= Sanitizer::escape(mb_substr($it['title'],0,90)) ?></a></td>
                        <td class="text-slate-500"><?= Sanitizer::escape($it['source_name'] ?? '') ?></td>
                        <td class="text-slate-400 text-xs"><?= Sanitizer::escape($it['fetched_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// AI Suggest Topics — progress loader
document.getElementById('suggest-form').addEventListener('submit', function() {
    App.ProgressLoader.show('AI is generating topic ideas...');
});

// Accept → Generate — progress loader for each suggestion
document.querySelectorAll('.accept-suggestion-form').forEach(function(form) {
    form.addEventListener('submit', function() {
        const lang = form.querySelector('.suggestion-lang')?.value;
        const langLabel = lang === 'en' ? 'Generating International post...' : 'পোস্ট তৈরি হচ্ছে...';
        App.ProgressLoader.show(langLabel);
    });
});
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
