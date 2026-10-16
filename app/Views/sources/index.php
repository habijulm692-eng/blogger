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

    <!-- ════════════════════════════════════════════════════════
         BLUEPRINT – Content Strategy & Idea Generator
         ════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl border border-blue-200 p-6 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-slate-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Content Blueprint &amp; Idea Generator
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Save your full content strategy. AI reads it daily and generates 3–5 ready-to-use topic ideas.</p>
            </div>
            <div class="flex gap-2 items-center">
                <select id="bp-lang" class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none">
                    <option value="bn">বাংলা</option>
                    <option value="en">International (Tier-1)</option>
                </select>
                <button type="button" id="btn-gen-ideas"
                        onclick="generateBlueprintIdeas()"
                        class="px-4 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium whitespace-nowrap">
                    ✦ Generate Ideas
                </button>
            </div>
        </div>

        <!-- Blueprint editor -->
        <form method="POST" action="<?= Sanitizer::escape($u('/sources/blueprint')) ?>" id="blueprint-form">
            <?= Csrf::field() ?>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Your Content Strategy Blueprint</label>
            <textarea name="blueprint" id="blueprint-text" rows="8"
                      class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
                      placeholder="Paste your full content strategy here. e.g.:&#10;- Blog niche: Tech tutorials for Bangladeshi developers&#10;- Target audience: Junior devs 18-30, learning Laravel / Next.js&#10;- Tone: Friendly, beginner-friendly, with code examples&#10;- Publishing frequency: 3 posts/week&#10;- SEO focus: long-tail Bengali + English keywords&#10;- Avoid: promotional content, outbound links&#10;..."><?= Sanitizer::escape($blueprint ?? '') ?></textarea>
            <div class="flex justify-end mt-2">
                <button type="submit" id="btn-save-blueprint"
                        class="px-4 py-2 text-sm rounded-lg bg-slate-700 hover:bg-slate-800 text-white font-medium">
                    Save Blueprint
                </button>
            </div>
        </form>

        <!-- Blueprint Ideas List -->
        <div id="blueprint-ideas-wrap" class="space-y-3">
            <?php if (!empty($blueprintIdeas)): ?>
            <h3 class="text-sm font-semibold text-slate-700 pt-1 border-t border-slate-100">💡 AI-Generated Topic Ideas</h3>
            <div id="blueprint-ideas-list" class="space-y-3">
                <?php foreach ($blueprintIdeas as $idea):
                    $tags = json_decode((string)($idea['tags'] ?? '[]'), true) ?? [];
                ?>
                <div class="idea-card border border-slate-200 rounded-xl p-4 bg-slate-50 hover:border-blue-300 transition-colors" data-id="<?= (int)$idea['id'] ?>">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 leading-snug"><?= Sanitizer::escape($idea['topic']) ?></p>
                            <?php if (!empty($idea['angle'])): ?>
                            <p class="text-xs text-slate-500 mt-1"><?= Sanitizer::escape($idea['angle']) ?></p>
                            <?php endif; ?>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <?php if (!empty($idea['primary_keyword'])): ?>
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">🔑 <?= Sanitizer::escape($idea['primary_keyword']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($idea['tone'])): ?>
                                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">✏️ <?= Sanitizer::escape($idea['tone']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($idea['category'])): ?>
                                <span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">📁 <?= Sanitizer::escape($idea['category']) ?></span>
                                <?php endif; ?>
                                <?php foreach ($tags as $tag): ?>
                                <span class="text-xs bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full">#<?= Sanitizer::escape((string)$tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5 shrink-0">
                            <button type="button"
                                    onclick="useBlueprintIdea(this)"
                                    data-topic="<?= Sanitizer::escape($idea['topic']) ?>"
                                    data-keyword="<?= Sanitizer::escape($idea['primary_keyword'] ?? '') ?>"
                                    data-tone="<?= Sanitizer::escape($idea['tone'] ?? '') ?>"
                                    data-tags="<?= Sanitizer::escape(implode(', ', $tags)) ?>"
                                    data-id="<?= (int)$idea['id'] ?>"
                                    data-lang="<?= Sanitizer::escape($idea['language'] ?? 'bn') ?>"
                                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition whitespace-nowrap">
                                Use This Topic →
                            </button>
                            <button type="button"
                                    onclick="dismissBlueprintIdea(<?= (int)$idea['id'] ?>, this)"
                                    class="px-3 py-1.5 border border-slate-300 text-slate-500 hover:text-red-500 hover:border-red-300 text-xs rounded-lg transition">
                                Dismiss
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div id="blueprint-ideas-list" class="hidden space-y-3"></div>
            <p id="blueprint-no-ideas" class="text-sm text-slate-400 italic">No ideas yet. Save your blueprint then click "✦ Generate Ideas".</p>
            <?php endif; ?>
        </div>
    </div>
    <!-- /Blueprint -->

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
// ── Existing loaders ───────────────────────────────────────────────────────
document.getElementById('suggest-form').addEventListener('submit', function() {
    App.ProgressLoader.show('AI is generating topic ideas...');
});

document.querySelectorAll('.accept-suggestion-form').forEach(function(form) {
    form.addEventListener('submit', function() {
        const lang = form.querySelector('.suggestion-lang')?.value;
        const langLabel = lang === 'en' ? 'Generating International post...' : 'পোস্ট তৈরি হচ্ছে...';
        App.ProgressLoader.show(langLabel);
    });
});

// ── Blueprint — Save form loader ───────────────────────────────────────────
document.getElementById('blueprint-form').addEventListener('submit', function() {
    App.ProgressLoader.show('Saving blueprint...');
});

// ── Blueprint — Generate Ideas (AJAX) ─────────────────────────────────────
async function generateBlueprintIdeas() {
    const blueprint = document.getElementById('blueprint-text').value.trim();
    if (!blueprint) {
        App.toast('Please write your blueprint first, then save it.', 'warning');
        return;
    }

    const btn  = document.getElementById('btn-gen-ideas');
    const lang = document.getElementById('bp-lang').value;
    App.setLoading(btn, true, 'Generating...');
    App.ProgressLoader.show('AI is reading your blueprint and generating ideas...');

    try {
        const form = new FormData();
        form.append('language', lang);
        form.append('_csrf_token', App.csrfToken());

        const res = await App.fetch('/sources/blueprint/generate', { method: 'POST', body: form });

        App.toast(`Generated ${res.data?.count ?? 0} new topic ideas!`, 'success');

        // Reload ideas list via AJAX
        await refreshBlueprintIdeas();
    } catch (err) {
        App.toast(err.message || 'Failed to generate ideas', 'error');
    } finally {
        App.setLoading(btn, false, '✦ Generate Ideas');
        App.ProgressLoader.hide?.();
    }
}

// ── Refresh ideas list from server ────────────────────────────────────────
async function refreshBlueprintIdeas() {
    try {
        const res = await App.fetch('/sources/blueprint/ideas', { method: 'GET' });
        const ideas = res.data || [];
        renderBlueprintIdeas(ideas);
    } catch (_) {
        // non-fatal
    }
}

function renderBlueprintIdeas(ideas) {
    const list   = document.getElementById('blueprint-ideas-list');
    const noIdea = document.getElementById('blueprint-no-ideas');

    if (!ideas.length) {
        list.innerHTML = '';
        list.classList.add('hidden');
        if (noIdea) noIdea.classList.remove('hidden');
        return;
    }
    if (noIdea) noIdea.classList.add('hidden');
    list.classList.remove('hidden');

    list.innerHTML = ideas.map(idea => {
        const tags = Array.isArray(idea.tags_array) ? idea.tags_array : [];
        const tagHtml = tags.map(t => `<span class="text-xs bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full">#${App.escape(String(t))}</span>`).join('');
        return `
        <div class="idea-card border border-slate-200 rounded-xl p-4 bg-slate-50 hover:border-blue-300 transition-colors" data-id="${idea.id}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800 leading-snug">${App.escape(idea.topic)}</p>
                    ${idea.angle ? `<p class="text-xs text-slate-500 mt-1">${App.escape(idea.angle)}</p>` : ''}
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        ${idea.primary_keyword ? `<span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">🔑 ${App.escape(idea.primary_keyword)}</span>` : ''}
                        ${idea.tone ? `<span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">✏️ ${App.escape(idea.tone)}</span>` : ''}
                        ${idea.category ? `<span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">📁 ${App.escape(idea.category)}</span>` : ''}
                        ${tagHtml}
                    </div>
                </div>
                <div class="flex flex-col gap-1.5 shrink-0">
                    <button type="button"
                            onclick="useBlueprintIdea(this)"
                            data-topic="${App.escape(idea.topic)}"
                            data-keyword="${App.escape(idea.primary_keyword || '')}"
                            data-tone="${App.escape(idea.tone || '')}"
                            data-tags="${App.escape(tags.join(', '))}"
                            data-id="${idea.id}"
                            data-lang="${App.escape(idea.language || 'bn')}"
                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition whitespace-nowrap">
                        Use This Topic →
                    </button>
                    <button type="button"
                            onclick="dismissBlueprintIdea(${idea.id}, this)"
                            class="px-3 py-1.5 border border-slate-300 text-slate-500 hover:text-red-500 hover:border-red-300 text-xs rounded-lg transition">
                        Dismiss
                    </button>
                </div>
            </div>
        </div>`;
    }).join('');
}

// ── Use This Topic — fill Create Post form ────────────────────────────────
function useBlueprintIdea(btn) {
    const topic   = btn.dataset.topic   || '';
    const keyword = btn.dataset.keyword || '';
    const tone    = btn.dataset.tone    || '';
    const tags    = btn.dataset.tags    || '';
    const lang    = btn.dataset.lang    || 'bn';
    const ideaId  = parseInt(btn.dataset.id, 10);

    // Store in sessionStorage so /posts/create can pick it up
    sessionStorage.setItem('bp_topic',   topic);
    sessionStorage.setItem('bp_keyword', keyword);
    sessionStorage.setItem('bp_tone',    tone);
    sessionStorage.setItem('bp_tags',    tags);
    sessionStorage.setItem('bp_lang',    lang);
    sessionStorage.setItem('bp_idea_id', ideaId);

    App.toast('Opening Create Post form with this topic…', 'success');
    window.location.href = App.url('/posts/create');
}

// ── Dismiss ───────────────────────────────────────────────────────────────
async function dismissBlueprintIdea(id, btn) {
    try {
        const form = new FormData();
        form.append('_csrf_token', App.csrfToken());
        await App.fetch(`/sources/blueprint/ideas/${id}/dismiss`, { method: 'POST', body: form });

        // Remove card from DOM
        const card = document.querySelector(`.idea-card[data-id="${id}"]`);
        if (card) card.remove();

        // Show empty state if no cards left
        if (!document.querySelector('.idea-card')) {
            const list = document.getElementById('blueprint-ideas-list');
            if (list) list.classList.add('hidden');
            const noIdea = document.getElementById('blueprint-no-ideas');
            if (noIdea) noIdea.classList.remove('hidden');
        }
        App.toast('Idea dismissed.', 'success');
    } catch (err) {
        App.toast(err.message || 'Failed to dismiss', 'error');
    }
}
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
