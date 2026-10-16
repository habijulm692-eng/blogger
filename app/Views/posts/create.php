<?php
use App\Helpers\Sanitizer;
use App\Helpers\Csrf;

use App\Helpers\Url;
$pageTitle = 'Create New Post';
ob_start();
?>

<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <form id="create-form" method="POST" action="<?= htmlspecialchars(\App\Helpers\Url::to('/posts'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-5">
            <?= Csrf::field() ?>
            <input type="hidden" name="generate_now" value="1" id="generate_now">

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Topic *</label>
                <textarea name="topic" id="topic" rows="2" required
                          class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="e.g. Best AI Tools for Small Businesses in 2025"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Primary Keyword</label>
                    <input type="text" name="primary_keyword" id="primary_keyword"
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="AI Tools">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Secondary Keywords</label>
                    <input type="text" name="secondary_keywords"
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="comma separated">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Language</label>
                    <select name="language" id="post-language"
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="bn" selected>Bengali (বাংলা)</option>
                        <option value="en">English (International / Tier-1)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Word Count</label>
                    <input type="number" name="word_count" value="1000" min="300" max="5000"
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Target Audience</label>
                    <input type="text" name="target_audience" id="target-audience"
                           value="General Bangladesh Readers"
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Writing Tone</label>
                    <input type="text" name="writing_tone" value="Professional + Easy"
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Category</label>
                    <input type="text" name="category"
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Technology">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Tags</label>
                    <input type="text" name="tags"
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="comma separated">
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" id="btn-generate"
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                    Generate with AI
                </button>
                <button type="button" id="btn-draft" onclick="document.getElementById('generate_now').value='0'; document.getElementById('create-form').submit();"
                        class="px-5 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium rounded-lg transition">
                    Save as Draft
                </button>
            </div>
        </form>
    </div>

    <!-- Topic Ideas -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 mt-6">
        <h3 class="font-semibold text-slate-800 mb-3">Generate Topic Ideas</h3>
        <div class="flex gap-2 mb-4">
            <input type="text" id="topic-keyword" placeholder="Enter keyword e.g. AI Tools"
                   class="flex-1 px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button type="button" id="btn-suggest" onclick="suggestTopics()"
                    class="px-4 py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-lg transition whitespace-nowrap">
                Get Ideas
            </button>
        </div>
        <div id="topic-ideas" class="space-y-2"></div>
    </div>
</div>

<script>
// Auto-update Target Audience when Language changes
(function () {
    const langSel = document.getElementById('post-language');
    const audienceInput = document.getElementById('target-audience');
    const audienceMap = {
        bn: 'General Bangladesh Readers',
        en: 'International Readers (US, UK, Canada, Australia)',
    };
    langSel.addEventListener('change', function () {
        audienceInput.value = audienceMap[this.value] || audienceMap.bn;
    });
})();

// Generate with AI — show progress loader
document.getElementById('create-form').addEventListener('submit', function(e) {
    if (document.getElementById('generate_now').value === '1') {
        const lang = document.getElementById('post-language').value;
        const label = lang === 'en'
            ? 'Generating your blog post...'
            : 'পোস্ট তৈরি হচ্ছে...';
        App.ProgressLoader.show(label);
    }
});

async function suggestTopics() {
    const keyword = document.getElementById('topic-keyword').value.trim();
    if (!keyword) { App.toast('Enter a keyword', 'warning'); return; }

    const btn = document.getElementById('btn-suggest');
    App.setLoading(btn, true, 'Thinking...');

    try {
        const form = new FormData();
        form.append('keyword', keyword);
        form.append('language', document.querySelector('[name=language]')?.value || 'bn');
        form.append('_csrf_token', App.csrfToken());

        const res = await App.fetch('/posts/suggest-topics', { method: 'POST', body: form });
        const container = document.getElementById('topic-ideas');
        container.innerHTML = '';

        (res.data || []).forEach((item, i) => {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between gap-3 p-3 bg-slate-50 rounded-lg border border-slate-100';
            div.innerHTML = `
                <span class="text-sm text-slate-700 flex-1">${App.escape(item.topic)}</span>
                <button type="button" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-md transition"
                    onclick="useTopic('${App.escape(item.topic).replace(/'/g, "\\'")}', '${App.escape(item.primary_keyword || keyword).replace(/'/g, "\\'")}')">
                    Use Topic
                </button>`;
            container.appendChild(div);
        });

        if (!(res.data || []).length) {
            container.innerHTML = '<p class="text-sm text-slate-400">No topics generated. Try again.</p>';
        }
    } catch (err) {
        App.toast(err.message || 'Failed to generate topics', 'error');
    } finally {
        App.setLoading(btn, false, 'Get Ideas');
    }
}

function useTopic(topic, keyword) {
    document.getElementById('topic').value = topic;
    document.getElementById('primary_keyword').value = keyword;
    App.toast('Topic applied', 'success');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Auto-fill from Blueprint "Use This Topic →" ────────────────────────────
(function applyBlueprintIdea() {
    const topic   = sessionStorage.getItem('bp_topic');
    if (!topic) return;

    const keyword = sessionStorage.getItem('bp_keyword') || '';
    const tone    = sessionStorage.getItem('bp_tone')    || '';
    const tags    = sessionStorage.getItem('bp_tags')    || '';
    const lang    = sessionStorage.getItem('bp_lang')    || 'bn';

    document.getElementById('topic').value           = topic;
    document.getElementById('primary_keyword').value = keyword;

    const toneEl = document.querySelector('[name=writing_tone]');
    if (toneEl && tone) toneEl.value = tone;

    const tagsEl = document.querySelector('[name=tags]');
    if (tagsEl && tags) tagsEl.value = tags;

    const langEl = document.getElementById('post-language');
    if (langEl && lang) {
        langEl.value = lang;
        langEl.dispatchEvent(new Event('change'));
    }

    // Clear so it doesn't re-apply on next visit
    ['bp_topic','bp_keyword','bp_tone','bp_tags','bp_lang','bp_idea_id'].forEach(k => sessionStorage.removeItem(k));

    App.toast('Blueprint topic applied to form ✓', 'success');
    window.scrollTo({ top: 0, behavior: 'smooth' });
})();
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
