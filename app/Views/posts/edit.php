<?php
use App\Helpers\Sanitizer;
use App\Helpers\Csrf;

use App\Helpers\Url;
$pageTitle = 'Edit Post';
ob_start();
?>

<div class="max-w-4xl mx-auto">
    <form method="POST" action="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$post['id']), ENT_QUOTES, 'UTF-8') ?>" id="edit-form" class="space-y-5">
        <?= Csrf::field() ?>

        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Title</label>
                <input type="text" name="title" value="<?= Sanitizer::escape($post['title'] ?? '') ?>"
                       class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Meta Description</label>
                <textarea name="meta_description" rows="2"
                          class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= Sanitizer::escape($post['meta_description'] ?? '') ?></textarea>
                <p class="text-xs text-slate-400 mt-1">Recommended: 150-160 characters</p>
            </div>
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-sm font-medium text-slate-700">Content (HTML)</label>
                    <div class="flex gap-2">
                        <button type="button" onclick="showPreview()" class="text-xs text-blue-600 hover:underline">Preview</button>
                        <button type="button" onclick="showHtml()" class="text-xs text-blue-600 hover:underline">HTML</button>
                    </div>
                </div>
                <textarea name="content_html" id="content-html" rows="20"
                          class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($post['content_html'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <div id="content-preview" class="hidden prose max-w-none p-4 border border-slate-200 rounded-lg mt-2 bg-slate-50"></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Tags (comma separated)</label>
                    <input type="text" name="tags" value="<?= Sanitizer::escape(is_array($post['tags'] ?? null) ? implode(', ', $post['tags']) : '') ?>"
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Category</label>
                    <input type="text" name="category" value="<?= Sanitizer::escape($post['category'] ?? '') ?>"
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <?php if ($review): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold text-slate-800 mb-2">AI Review (Score: <?= number_format((float)$review['score'], 1) ?>/10)</h3>
            <?php if (!empty($review['problems'])): ?>
            <p class="text-xs font-medium text-red-600 mb-1">Problems</p>
            <ul class="text-sm text-slate-600 list-disc pl-5 mb-3"><?php foreach ($review['problems'] as $p): ?><li><?= Sanitizer::escape(is_string($p) ? $p : json_encode($p)) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?php if (!empty($review['suggestions'])): ?>
            <p class="text-xs font-medium text-blue-600 mb-1">Suggestions</p>
            <ul class="text-sm text-slate-600 list-disc pl-5"><?php foreach ($review['suggestions'] as $s): ?><li><?= Sanitizer::escape(is_string($s) ? $s : json_encode($s)) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">Save Draft</button>
            <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$post['id']), ENT_QUOTES, 'UTF-8') ?>" class="px-5 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium rounded-lg transition">Cancel</a>
            <button type="button" onclick="optimizeSeo()" id="btn-seo" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-lg transition">Optimize SEO</button>
            <button type="button" onclick="injectAdsIntoEditor()" id="btn-inject-ads" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition flex items-center gap-1.5">
                <span>📢</span> <span>বিজ্ঞাপন ও প্রম্পট বক্স যোগ করুন</span>
            </button>
        </div>
    </form>

    <div id="seo-results" class="hidden mt-6 bg-white rounded-xl border border-slate-200 p-6"></div>
</div>

<input type="file" id="img-replace-input" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden">

<script>
let _replaceTargetImg = null;
let _longPressTimer = null;

function showPreview() {
    const html = document.getElementById('content-html');
    const prev = document.getElementById('content-preview');
    prev.innerHTML = html.value;
    prev.classList.remove('hidden');
    html.classList.add('hidden');
    bindImageReplace(prev);
}
function showHtml() {
    const html = document.getElementById('content-html');
    const prev = document.getElementById('content-preview');
    // Sync any replaced images back to textarea
    if (!prev.classList.contains('hidden')) {
        html.value = prev.innerHTML;
    }
    html.classList.remove('hidden');
    prev.classList.add('hidden');
}

function bindImageReplace(container) {
    container.querySelectorAll('img').forEach(img => {
        img.style.cursor = 'pointer';
        img.title = 'ডাবল-ক্লিক বা লং-প্রেস করে ছবি পরিবর্তন করুন';

        img.addEventListener('dblclick', (e) => {
            e.preventDefault();
            openImageReplace(img);
        });

        img.addEventListener('touchstart', (e) => {
            _longPressTimer = setTimeout(() => openImageReplace(img), 600);
        }, { passive: true });
        img.addEventListener('touchend', () => clearTimeout(_longPressTimer));
        img.addEventListener('touchmove', () => clearTimeout(_longPressTimer));
        img.addEventListener('mousedown', (e) => {
            if (e.button === 0) {
                _longPressTimer = setTimeout(() => openImageReplace(img), 600);
            }
        });
        img.addEventListener('mouseup', () => clearTimeout(_longPressTimer));
        img.addEventListener('mouseleave', () => clearTimeout(_longPressTimer));
    });
}

function openImageReplace(img) {
    _replaceTargetImg = img;
    // Floating Replace button near image
    let btn = document.getElementById('img-replace-btn');
    if (!btn) {
        btn = document.createElement('button');
        btn.id = 'img-replace-btn';
        btn.type = 'button';
        btn.textContent = 'Replace Image';
        btn.className = 'fixed z-50 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-lg';
        btn.onclick = () => document.getElementById('img-replace-input').click();
        document.body.appendChild(btn);
    }
    const rect = img.getBoundingClientRect();
    btn.style.top = (rect.top + window.scrollY + 8) + 'px';
    btn.style.left = (rect.left + window.scrollX + 8) + 'px';
    btn.style.display = 'block';
    // Auto-hide after 5s
    clearTimeout(btn._hideTimer);
    btn._hideTimer = setTimeout(() => { btn.style.display = 'none'; }, 5000);
}

document.getElementById('img-replace-input').addEventListener('change', async function () {
    const file = this.files && this.files[0];
    if (!file || !_replaceTargetImg) return;

    const btn = document.getElementById('img-replace-btn');
    if (btn) btn.textContent = 'Uploading...';

    try {
        const form = new FormData();
        form.append('image', file);
        form.append('_csrf_token', App.csrfToken());
        const res = await App.fetch('/posts/<?= (int)$post['id'] ?>/upload-image', {
            method: 'POST',
            body: form,
        });
        const url = res.data && res.data.url;
        if (!url) throw new Error('No URL returned');

        const oldSrc = _replaceTargetImg.getAttribute('src');
        _replaceTargetImg.setAttribute('src', url);
        // Also update the HTML textarea so save keeps the change
        const ta = document.getElementById('content-html');
        if (oldSrc && ta.value.includes(oldSrc)) {
            ta.value = ta.value.split(oldSrc).join(url);
        } else {
            // fallback: sync whole preview
            const prev = document.getElementById('content-preview');
            if (prev && !prev.classList.contains('hidden')) {
                ta.value = prev.innerHTML;
            }
        }
        App.toast('ছবি পরিবর্তন হয়েছে', 'success');
    } catch (e) {
        App.toast(e.message || 'Image upload failed', 'error');
    } finally {
        if (btn) {
            btn.textContent = 'Replace Image';
            btn.style.display = 'none';
        }
        this.value = '';
        _replaceTargetImg = null;
    }
});

async function optimizeSeo() {
    const btn = document.getElementById('btn-seo');
    App.setLoading(btn, true, 'Optimizing...');
    try {
        const form = new FormData();
        form.append('_csrf_token', App.csrfToken());
        const res = await App.fetch('/posts/<?= (int)$post['id'] ?>/optimize-seo', { method: 'POST', body: form });
        const d = res.data;
        const el = document.getElementById('seo-results');
        el.classList.remove('hidden');
        el.innerHTML = `<h3 class="font-semibold mb-3">SEO Suggestions</h3>
            <p class="text-xs text-slate-500 mb-1">Alternative Titles</p>
            <ul class="text-sm mb-3 space-y-1">${(d.alternative_titles||[]).map(t => `<li class="flex justify-between gap-2"><span>${App.escape(t)}</span><button type="button" class="text-xs text-blue-600" onclick="document.querySelector('[name=title]').value='${App.escape(t).replace(/'/g,"\'")}'">Use</button></li>`).join('')}</ul>
            <p class="text-xs text-slate-500 mb-1">Meta Description</p>
            <p class="text-sm mb-2">${App.escape(d.meta_description||'')} <button type="button" class="text-xs text-blue-600" onclick="document.querySelector('[name=meta_description]').value='${App.escape(d.meta_description||'').replace(/'/g,"\'")}'">Use</button></p>
            <p class="text-xs text-slate-500">Related Keywords: ${(d.related_keywords||[]).map(k=>App.escape(k)).join(', ')}</p>`;
        App.toast('SEO suggestions ready', 'success');
    } catch(e) {
        App.toast(e.message || 'SEO optimization failed', 'error');
    } finally {
        App.setLoading(btn, false, 'Optimize SEO');
    }
}

async function injectAdsIntoEditor() {
    const btn = document.getElementById('btn-inject-ads');
    App.setLoading(btn, true, 'যুক্ত হচ্ছে...');
    try {
        const form = new FormData();
        form.append('_csrf_token', App.csrfToken());
        const res = await App.fetch('/posts/<?= (int)$post['id'] ?>/inject-ads', {
            method: 'POST',
            body: form
        });
        if (res.data && res.data.content_html) {
            document.getElementById('content-html').value = res.data.content_html;
            const prev = document.getElementById('content-preview');
            if (prev && !prev.classList.contains('hidden')) {
                prev.innerHTML = res.data.content_html;
                if (typeof bindImageReplace === 'function') bindImageReplace(prev);
            }
            App.toast(res.message || 'বিজ্ঞাপন ও প্রম্পট বক্স যুক্ত হয়েছে!', 'success');
        }
    } catch(err) {
        App.toast(err.message || 'বিজ্ঞাপন যুক্ত করতে সমস্যা হয়েছে', 'error');
    } finally {
        App.setLoading(btn, false, '📢 বিজ্ঞাপন ও প্রম্পট বক্স যোগ করুন');
    }
}
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
