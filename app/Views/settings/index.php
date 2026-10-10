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

    <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">Save Settings</button>
</form>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
