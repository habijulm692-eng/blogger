<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\ActivityLog;
use App\Models\AiProviderConfig;
use App\Models\AiUsageLog;
use App\Models\SystemSetting;
use App\Services\AIService;

class SettingsController
{
    public function index(): void
    {
        $settings = SystemSetting::getAll();
        $grouped = [];
        foreach ($settings as $s) {
            $grouped[$s['group_name']][] = $s;
        }

        $aiService = new AIService();
        $providers = $aiService->getConfiguredProviders();
        $providerStats = AiUsageLog::getTodayStats();

        Response::view('settings.index', [
            'grouped' => $grouped,
            'providers' => $providers,
            'providerStats' => $providerStats,
            'csrf' => Csrf::token(),
        ]);
    }

    public function update(): void
    {
        $allowed = [
            'site_name', 'timezone', 'default_language', 'default_word_count',
            'default_audience', 'default_tone',
            'ai_temperature', 'ai_max_tokens', 'ai_timeout',
            'publish_frequency', 'default_schedule_time', 'publish_retry_limit',
            'auto_approval_mode', 'login_max_attempts', 'login_lockout_minutes',
            'log_retention_days',
        ];

        $types = [
            'default_word_count' => 'integer',
            'ai_max_tokens' => 'integer',
            'ai_timeout' => 'integer',
            'publish_retry_limit' => 'integer',
            'auto_approval_mode' => 'boolean',
            'login_max_attempts' => 'integer',
            'login_lockout_minutes' => 'integer',
            'log_retention_days' => 'integer',
        ];

        $groups = [
            'site_name' => 'general', 'timezone' => 'general',
            'default_language' => 'general', 'default_word_count' => 'general',
            'default_audience' => 'general', 'default_tone' => 'general',
            'ai_temperature' => 'ai', 'ai_max_tokens' => 'ai', 'ai_timeout' => 'ai',
            'publish_frequency' => 'publishing', 'default_schedule_time' => 'publishing',
            'publish_retry_limit' => 'publishing', 'auto_approval_mode' => 'publishing',
            'login_max_attempts' => 'security', 'login_lockout_minutes' => 'security',
            'log_retention_days' => 'maintenance',
        ];

        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $type = $types[$key] ?? 'string';
                $group = $groups[$key] ?? 'general';
                $value = $_POST[$key];

                if ($type === 'boolean') {
                    $value = !empty($value) ? '1' : '0';
                }

                SystemSetting::set($key, $value, $type, $group);
            }
        }

        ActivityLog::log('settings_updated', Session::getAdminId(), null, null, 'System settings updated');

        if ($this->isAjax()) {
            Response::success(null, 'Settings saved');
        }

        Session::flash('success', 'Settings saved successfully.');
        Response::redirect('/settings');
    }

    public function aiUsage(): void
    {
        $stats = AiUsageLog::getTodayStats();
        $totals = AiUsageLog::getTodayTotals();
        $providers = AiProviderConfig::getAllWithConfig();

        Response::view('ai.usage', [
            'stats'     => $stats,
            'totals'    => $totals,
            'providers' => $providers,
            'csrf'      => Csrf::token(),
        ]);
    }

    public function toggleProvider(): void
    {
        $name = trim((string) ($_POST['provider'] ?? ''));
        if ($name === '' || !isset(AiProviderConfig::DEFAULTS[$name])) {
            if ($this->isAjax()) {
                Response::error('Invalid provider', 422);
            }
            Session::flash('error', 'Invalid provider');
            Response::redirect('/ai-usage');
        }

        $row = AiProviderConfig::findByName($name);
        $currentState = (bool) ($row['is_enabled'] ?? true);
        $newState = isset($_POST['state']) ? (bool) $_POST['state'] : !$currentState;

        AiProviderConfig::setEnabled($name, $newState);
        ActivityLog::log(
            'ai_provider_toggle',
            Session::getAdminId(),
            null,
            null,
            "Provider {$name} " . ($newState ? 'enabled' : 'disabled')
        );

        $msg = ($row['display_name'] ?? $name) . ' ' . ($newState ? 'চালু করা হয়েছে' : 'বন্ধ করা হয়েছে');

        if ($this->isAjax()) {
            Response::success([
                'provider'   => $name,
                'is_enabled' => $newState,
            ], $msg);
        }

        Session::flash('success', $msg);
        Response::redirect('/ai-usage');
    }

    public function reorderProviders(): void
    {
        $priorities = $_POST['priorities'] ?? [];
        if (!is_array($priorities)) {
            if ($this->isAjax()) {
                Response::error('Invalid data', 422);
            }
            Session::flash('error', 'Invalid priority data');
            Response::redirect('/ai-usage');
        }

        $cleaned = [];
        foreach ($priorities as $name => $p) {
            $name = trim((string) $name);
            if (isset(AiProviderConfig::DEFAULTS[$name])) {
                $cleaned[$name] = max(1, min(99, (int) $p));
            }
        }

        if ($cleaned !== []) {
            AiProviderConfig::updatePriorities($cleaned);
            ActivityLog::log('ai_provider_reorder', Session::getAdminId(), null, null, 'AI provider priorities updated');
        }

        $msg = 'মডেলের সিরিয়াল ও অগ্রাধিকার সফলভাবে আপডেট হয়েছে।';

        if ($this->isAjax()) {
            Response::success(null, $msg);
        }

        Session::flash('success', $msg);
        Response::redirect('/ai-usage');
    }

    private function isAjax(): bool
    {
        return (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        );
    }
}
