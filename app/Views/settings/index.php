<?php
use App\Helpers\Sanitizer;
use App\Helpers\Csrf;

use App\Helpers\Url;
$pageTitle = 'Settings';
ob_start();

function settingVal(array $grouped, string $group, string $key, $default = '') {
    if (!isset($grouped[$group])) return $default;
    foreach ($grouped[$group] as $s) {
        if ($s['setting_key'] === $key) return $s['setting_value'];
    }
    return $default;
}
?>

<form method="POST" action="<?= htmlspecialchars(\App\Helpers\Url::to('/settings'), ENT_QUOTES, 'UTF-8') ?>" class="max-w-3xl mx-auto space-y-6">
    <?= Csrf::field() ?>

    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold text-slate-800 mb-4">General</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Site Name</label>
                <input type="text" name="site_name" value="<?= Sanitizer::escape(settingVal($grouped, 'general', 'site_name', 'AI Blogger')) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Timezone</label>
                <input type="text" name="timezone" value="<?= Sanitizer::escape(settingVal($grouped, 'general', 'timezone', 'Asia/Dhaka')) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Default Language</label>
                <select name="default_language" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="bn" <?= settingVal($grouped, 'general', 'default_language') === 'bn' ? 'selected' : '' ?>>Bengali</option>
                    <option value="en" <?= settingVal($grouped, 'general', 'default_language') === 'en' ? 'selected' : '' ?>>English</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Default Word Count</label>
                <input type="number" name="default_word_count" value="<?= Sanitizer::escape(settingVal($grouped, 'general', 'default_word_count', '1000')) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold text-slate-800 mb-4">AI Settings</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Temperature</label>
                <input type="text" name="ai_temperature" value="<?= Sanitizer::escape(settingVal($grouped, 'ai', 'ai_temperature', '0.7')) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Max Tokens</label>
                <input type="number" name="ai_max_tokens" value="<?= Sanitizer::escape(settingVal($grouped, 'ai', 'ai_max_tokens', '4096')) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Timeout (sec)</label>
                <input type="number" name="ai_timeout" value="<?= Sanitizer::escape(settingVal($grouped, 'ai', 'ai_timeout', '60')) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
        </div>
        <p class="text-xs text-slate-500 mb-2">API keys are configured in <code>.env</code> file (never exposed in the UI).</p>
        <div class="space-y-2">
            <?php if (empty($providers)): ?>
            <p class="text-sm text-orange-600">No AI providers configured. Add API keys to .env</p>
            <?php else: foreach ($providers as $p):
                $provTitle = match ($p['name']) {
                    'gemini_primary'   => 'Google Gemini Flash 3.5',
                    'gemini_secondary' => 'Google Gemini Flash 3',
                    'gemini'           => 'Google Gemini',
                    default            => ucwords(str_replace('_', ' ', (string)$p['name'])),
                };
            ?>
            <div class="flex items-center gap-2 text-sm">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                <span class="font-medium"><?= Sanitizer::escape($provTitle) ?></span>
                <span class="text-slate-400"><?= Sanitizer::escape($p['model']) ?></span>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold text-slate-800 mb-4">Publishing</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Frequency</label>
                <select name="publish_frequency" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="daily" <?= settingVal($grouped, 'publishing', 'publish_frequency') === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="every_2_days" <?= settingVal($grouped, 'publishing', 'publish_frequency') === 'every_2_days' ? 'selected' : '' ?>>Every 2 Days</option>
                    <option value="custom" <?= settingVal($grouped, 'publishing', 'publish_frequency') === 'custom' ? 'selected' : '' ?>>Custom</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Default Schedule Time</label>
                <input type="time" name="default_schedule_time" value="<?= Sanitizer::escape(settingVal($grouped, 'publishing', 'default_schedule_time', '10:00')) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Retry Limit</label>
                <input type="number" name="publish_retry_limit" value="<?= Sanitizer::escape(settingVal($grouped, 'publishing', 'publish_retry_limit', '3')) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
        </div>
        <div class="mt-4">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="auto_approval_mode" value="1" <?= settingVal($grouped, 'publishing', 'auto_approval_mode') === '1' ? 'checked' : '' ?>
                       class="rounded border-slate-300">
                Auto Approval Mode (OFF by default — human approval is mandatory)
            </label>
        </div>
    </div>

    <!-- Ads & Monetization Settings -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h2 class="font-semibold text-slate-800 text-base flex items-center gap-2">
                    <span>📢</span> বিজ্ঞাপন ও মনেটাইজেশন সেটিংস (Ad Integration)
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">পোস্ট তৈরির সময় স্বয়ংক্রিয় ব্যানার অ্যাড, সোশ্যাল বার এবং কপি বাটন স্মার্ট লিঙ্ক অ্যাড নিয়ন্ত্রণ করুন।</p>
            </div>
            <div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="ads_enabled" value="1" <?= settingVal($grouped, 'ads', 'ads_enabled', '0') === '1' ? 'checked' : '' ?>
                           class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    <span class="ml-2 text-xs font-semibold text-slate-700">বিজ্ঞাপন চালু</span>
                </label>
            </div>
        </div>

        <!-- Banner Ad Section -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <label class="block text-sm font-semibold text-slate-700">ব্যানার অ্যাড কোড (Banner Ad Script / HTML)</label>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500">ব্যানারের অবস্থান:</span>
                    <select name="ad_banner_position" class="px-2.5 py-1 border border-slate-300 rounded-md text-xs bg-slate-50">
                        <?php $pos = settingVal($grouped, 'ads', 'ad_banner_position', 'smart'); ?>
                        <option value="smart" <?= $pos === 'smart' ? 'selected' : '' ?>>স্বয়ংক্রিয় ব্যালান্সড পজিশন (Smart)</option>
                        <option value="after_intro" <?= $pos === 'after_intro' ? 'selected' : '' ?>>ভূমিকার ঠিক পর (After Intro)</option>
                        <option value="middle" <?= $pos === 'middle' ? 'selected' : '' ?>>পোস্টের মাঝে (Middle)</option>
                        <option value="bottom" <?= $pos === 'bottom' ? 'selected' : '' ?>>পোস্টের নিচে (Bottom)</option>
                        <option value="both" <?= $pos === 'both' ? 'selected' : '' ?>>শুরু ও শেষে উভয় জায়গায় (Both)</option>
                    </select>
                </div>
            </div>
            <textarea name="ad_banner_code" rows="3"
                      class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="এখানে ব্যানার বিজ্ঞাপনের কোড বা স্ক্রিপ্ট পেস্ট করুন (e.g. AdSense, Monetag, Adsterra Banner)..."><?= Sanitizer::escape(settingVal($grouped, 'ads', 'ad_banner_code', '')) ?></textarea>
        </div>

        <!-- Social Bar Ad Section -->
        <div class="space-y-2">
            <label class="block text-sm font-semibold text-slate-700">সোশ্যাল বার অ্যাড কোড (Social Bar / Floating Ad Script)</label>
            <textarea name="ad_social_bar_code" rows="2"
                      class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="এখানে সোশ্যাল বার বা ফ্লোটিং বিজ্ঞাপনের কোড পেস্ট করুন..."><?= Sanitizer::escape(settingVal($grouped, 'ads', 'ad_social_bar_code', '')) ?></textarea>
            <p class="text-[11px] text-slate-400">এই কোডটি পোস্টের নিচে বা উপযুক্ত জায়গায় নিজে থেকেই লোড হবে।</p>
        </div>

        <!-- Direct Links / Smart Links for Copy Button -->
        <div class="border-t border-slate-100 pt-4 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-1.5">
                    <span>🔗</span> ডিরেক্ট / স্মার্ট লিঙ্ক সেটিংস (কপি বাটনে অ্যাড গেট)
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">পোস্টে থাকা মাস্টার প্রম্পট বা সোর্স কোডের উপরের কপি বাটনে ক্লিক করলে পাঠক এই স্মার্ট লিঙ্কগুলোতে রিডাইরেক্ট হবে এবং লিঙ্ক দেখার পর কপি আনলক হবে। কোনো থার্ড-পার্টি ওয়াটারমার্ক বা লোগো থাকবে না।</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">স্মার্ট লিঙ্ক ১ (Smart Link 1 URL)</label>
                    <input type="url" name="ad_direct_link_1" value="<?= Sanitizer::escape(settingVal($grouped, 'ads', 'ad_direct_link_1', '')) ?>"
                           placeholder="https://example.com/direct-link-1"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">স্মার্ট লিঙ্ক ২ (Smart Link 2 URL)</label>
                    <input type="url" name="ad_direct_link_2" value="<?= Sanitizer::escape(settingVal($grouped, 'ads', 'ad_direct_link_2', '')) ?>"
                           placeholder="https://example.com/direct-link-2"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">কপি হতে কতটি লিঙ্ক দেখা লাগবে?</label>
                    <select name="ad_smart_link_count" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs bg-slate-50">
                        <?php $sc = (int) settingVal($grouped, 'ads', 'ad_smart_link_count', '2'); ?>
                        <option value="2" <?= $sc === 2 ? 'selected' : '' ?>>২ টি লিঙ্ক দেখতে হবে (Default)</option>
                        <option value="1" <?= $sc === 1 ? 'selected' : '' ?>>১ টি লিঙ্ক দেখলেই কপি হবে</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">কপি বাটনের লেখা</label>
                    <input type="text" name="ad_copy_button_text" value="<?= Sanitizer::escape(settingVal($grouped, 'ads', 'ad_copy_button_text', '📋 কপি করুন')) ?>"
                           placeholder="📋 কপি করুন"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">১ম ক্লিকের পর নোটিশ লেখা</label>
                    <input type="text" name="ad_copy_unlock_text" value="<?= Sanitizer::escape(settingVal($grouped, 'ads', 'ad_copy_unlock_text', 'আরেকটা লিঙ্ক দেখলে কপি হবে')) ?>"
                           placeholder="আরেকটা লিঙ্ক দেখলে কপি হবে"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                    <input type="checkbox" name="ad_prompt_box_enabled" value="1" <?= settingVal($grouped, 'ads', 'ad_prompt_box_enabled', '1') === '1' ? 'checked' : '' ?>
                           class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span>পোস্টে প্রম্পট/কোড পাওয়া গেলে স্বয়ংক্রিয়ভাবে ছোট কপি বক্স ও স্মার্ট লিঙ্ক অ্যাড যুক্ত করুন (Default: On)</span>
                </label>
            </div>
        </div>
    </div>

    <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">Save Settings</button>
</form>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
