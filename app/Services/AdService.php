<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;

class AdService
{
    /**
     * Check if ads are globally enabled in system settings.
     */
    public function isEnabled(): bool
    {
        try {
            return (bool) SystemSetting::get('ads_enabled', false);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get all ad and smart-link settings with safe defaults.
     */
    public function getSettings(): array
    {
        try {
            return [
                'enabled'            => (bool) SystemSetting::get('ads_enabled', false),
                'social_bar_code'    => (string) SystemSetting::get('ad_social_bar_code', ''),
                'banner_code'        => (string) SystemSetting::get('ad_banner_code', ''),
                'banner_position'    => (string) SystemSetting::get('ad_banner_position', 'smart'),
                'direct_link_1'      => (string) SystemSetting::get('ad_direct_link_1', ''),
                'direct_link_2'      => (string) SystemSetting::get('ad_direct_link_2', ''),
                'smart_link_count'   => max(1, min(2, (int) SystemSetting::get('ad_smart_link_count', 2))),
                'copy_button_text'   => (string) SystemSetting::get('ad_copy_button_text', '📋 কপি করুন'),
                'copy_unlock_text'   => (string) SystemSetting::get('ad_copy_unlock_text', 'আরেকটা লিঙ্ক দেখলে কপি হবে'),
                'prompt_box_enabled' => (bool) SystemSetting::get('ad_prompt_box_enabled', true),
            ];
        } catch (\Throwable) {
            return [
                'enabled'            => false,
                'social_bar_code'    => '',
                'banner_code'        => '',
                'banner_position'    => 'smart',
                'direct_link_1'      => '',
                'direct_link_2'      => '',
                'smart_link_count'   => 2,
                'copy_button_text'   => '📋 কপি করুন',
                'copy_unlock_text'   => 'আরেকটা লিঙ্ক দেখলে কপি হবে',
                'prompt_box_enabled' => true,
            ];
        }
    }

    /**
     * Process post HTML content:
     * 1. Transform any master-prompt / code blocks into the compact ad-gated copy box.
     * 2. Insert custom prompt/code if provided and not yet embedded.
     * 3. Inject banner ads and social bar ads if ads are enabled.
     *
     * @param string $html Original post HTML
     * @param array $options Options:
     *   - enable_ads: ?bool (defaults to system setting)
     *   - custom_prompt_code: ?string (manual prompt or source code)
     *   - prompt_type: 'prompt'|'code'
     *   - include_prompt_box: bool
     */
    public function processPostContent(string $html, array $options = []): string
    {
        $settings = $this->getSettings();
        $adsActive = $options['enable_ads'] ?? $settings['enabled'];

        // 1. Process custom prompt/code if provided directly
        $customContent = trim((string) ($options['custom_prompt_code'] ?? ''));
        $promptType = ($options['prompt_type'] ?? 'prompt') === 'code' ? 'code' : 'prompt';
        $includePromptBox = !empty($options['include_prompt_box']) || $customContent !== '';

        if ($customContent !== '') {
            $promptBox = $this->buildCopyBox($customContent, $promptType, $settings);
            // Insert custom box after intro or at the top of content
            $html = $this->insertPromptBoxIntoHtml($html, $promptBox);
        }

        // 2. Detect any existing AI generated prompt blocks (e.g. language-master-prompt)
        $html = $this->convertPromptBlocks($html, $settings);

        // 3. Inject ads if enabled
        if ($adsActive) {
            if (!empty($settings['banner_code'])) {
                $html = $this->injectBannerAd($html, $settings['banner_code'], $settings['banner_position']);
            }
            if (!empty($settings['social_bar_code'])) {
                $html = $this->injectSocialBar($html, $settings['social_bar_code']);
            }
        }

        return $html;
    }

    /**
     * Convert code blocks matching language-master-prompt or [MASTER_PROMPT] tags
     * into the interactive compact ad-gated Copy Box.
     */
    public function convertPromptBlocks(string $html, ?array $settings = null): string
    {
        $settings = $settings ?? $this->getSettings();

        // Pattern 1: <pre><code class="language-master-prompt">...</code></pre>
        $pattern1 = '/<pre[^>]*><code[^>]*class=["\'][^"\']*language-master-prompt[^"\']*["\'][^>]*>(.*?)<\/code><\/pre>/is';
        $html = preg_replace_callback($pattern1, function ($matches) use ($settings) {
            $raw = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $this->buildCopyBox(trim($raw), 'prompt', $settings);
        }, $html) ?? $html;

        // Pattern 2: <pre><code class="language-prompt">...</code></pre>
        $pattern2 = '/<pre[^>]*><code[^>]*class=["\'][^"\']*language-prompt[^"\']*["\'][^>]*>(.*?)<\/code><\/pre>/is';
        $html = preg_replace_callback($pattern2, function ($matches) use ($settings) {
            $raw = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $this->buildCopyBox(trim($raw), 'prompt', $settings);
        }, $html) ?? $html;

        // Pattern 3: Explicit markers like <!-- MASTER_PROMPT_START -->...<!-- MASTER_PROMPT_END -->
        $pattern3 = '/<!--\s*MASTER_PROMPT_START\s*-->(.*?)<!--\s*MASTER_PROMPT_END\s*-->/is';
        $html = preg_replace_callback($pattern3, function ($matches) use ($settings) {
            $raw = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $this->buildCopyBox(trim($raw), 'prompt', $settings);
        }, $html) ?? $html;

        return $html;
    }

    /**
     * Generate a self-contained, responsive, compact Prompt / Source Code Box
     * with smart-link ad gating and a copy button at the top.
     * Completely free of third-party logos or watermarks.
     *
     * @param string $content The text to display and copy
     * @param string $type 'prompt' or 'code'
     * @param array|null $settings Custom or system ad settings
     */
    public function buildCopyBox(string $content, string $type = 'prompt', ?array $settings = null): string
    {
        $settings = $settings ?? $this->getSettings();
        $uid = bin2hex(random_bytes(5));

        $isCode = ($type === 'code');
        $title = $isCode ? 'Source Code / স্ক্রিপ্ট' : 'Master Prompt (মাস্টার প্রম্পট)';
        $icon = $isCode ? '💻' : '🤖';
        $badgeText = $isCode ? 'Code Snippet' : 'Universal AI Prompt';

        $link1 = trim((string) ($settings['direct_link_1'] ?? ''));
        $link2 = trim((string) ($settings['direct_link_2'] ?? ''));
        $reqClicks = (int) ($settings['smart_link_count'] ?? 2);
        if ($link2 === '' && $link1 !== '') {
            $reqClicks = 1; // Fallback if second link is missing
        }

        $btnText = !empty($settings['copy_button_text']) ? $settings['copy_button_text'] : '📋 কপি করুন';
        $unlockMsg = !empty($settings['copy_unlock_text']) ? $settings['copy_unlock_text'] : 'আরেকটা লিঙ্ক দেখলে কপি হবে';

        $escapedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $jsonContent = json_encode($content, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $jsonLink1 = json_encode($link1);
        $jsonLink2 = json_encode($link2);
        $jsonBtnText = json_encode($btnText, JSON_UNESCAPED_UNICODE);
        $jsonUnlockMsg = json_encode($unlockMsg, JSON_UNESCAPED_UNICODE);

        return <<<HTML
<div id="cbox_wrap_{$uid}" class="ai-copy-box" style="margin: 26px 0; border: 1px solid #334155; border-radius: 10px; background: #0f172a; color: #f8fafc; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.18); font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; clear: both;">
    <!-- Header with Title & Copy Button -->
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #1e293b; border-bottom: 1px solid #334155; font-size: 13px; font-weight: 600;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 16px;">{$icon}</span>
            <span style="color: #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">{$title}</span>
            <span style="background: rgba(59, 130, 246, 0.2); color: #93c5fd; font-size: 10.5px; padding: 2px 8px; border-radius: 4px; font-weight: 500; font-family: sans-serif;">{$badgeText}</span>
        </div>
        <button type="button" id="cbtn_{$uid}" onclick="window.runCopyAction_{$uid}()" style="background: #2563eb; color: #ffffff; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px; outline: none;">
            <span id="cicon_{$uid}">📋</span>
            <span id="ctxt_{$uid}">{$btnText}</span>
        </button>
    </div>

    <!-- Alert status banner shown on ad-step progression -->
    <div id="cstatus_{$uid}" style="display: none; padding: 7px 12px; background: #fef3c7; color: #92400e; font-size: 11.5px; font-weight: 500; text-align: center; border-bottom: 1px solid #fde68a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;"></div>

    <!-- Compact Code / Prompt Area -->
    <div style="max-height: 220px; overflow-y: auto; padding: 14px 16px; background: #0b1120; font-size: 12.5px; line-height: 1.6; color: #e2e8f0; white-space: pre-wrap; word-break: break-word;">{$escapedContent}</div>
</div>

<script>
(function() {
    var step = 0;
    var l1 = {$jsonLink1};
    var l2 = {$jsonLink2};
    var req = {$reqClicks};
    var defaultText = {$jsonBtnText};
    var unlockText = {$jsonUnlockMsg};
    var textToCopy = {$jsonContent};

    window.runCopyAction_{$uid} = function() {
        var btn = document.getElementById('cbtn_{$uid}');
        var txt = document.getElementById('ctxt_{$uid}');
        var status = document.getElementById('cstatus_{$uid}');

        // Smart link ad gating logic
        if (req >= 2) {
            if (step === 0 && l1) {
                step = 1;
                window.open(l1, '_blank');
                if (status) {
                    status.style.display = 'block';
                    status.style.background = '#fef3c7';
                    status.style.color = '#92400e';
                    status.textContent = '⚠️ ' + unlockText + ' (ধাপ ১/২ সম্পন্ন)';
                }
                if (txt) txt.textContent = unlockText;
                if (btn) btn.style.background = '#d97706';
                return;
            } else if (step === 1 && l2) {
                step = 2;
                window.open(l2, '_blank');
            }
        } else if (req === 1 && l1 && step === 0) {
            step = 1;
            window.open(l1, '_blank');
        }

        // Perform clipboard copy
        function doCopy() {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(textToCopy);
            }
            var ta = document.createElement('textarea');
            ta.value = textToCopy;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            return Promise.resolve();
        }

        doCopy().then(function() {
            if (txt) txt.textContent = '✅ কপি সফল হয়েছে!';
            if (btn) btn.style.background = '#16a34a';
            if (status) {
                status.style.display = 'block';
                status.style.background = '#dcfce7';
                status.style.color = '#166534';
                status.textContent = '🎉 সম্পূর্ণ প্রম্পট ক্লিপবোর্ডে কপি সম্পন্ন হয়েছে!';
            }
            setTimeout(function() {
                step = 0;
                if (txt) txt.textContent = defaultText;
                if (btn) btn.style.background = '#2563eb';
                if (status) status.style.display = 'none';
            }, 4000);
        }).catch(function() {
            if (txt) txt.textContent = 'ম্যানুয়ালি সিলেক্ট করুন';
        });
    };
})();
</script>
HTML;
    }

    /**
     * Insert banner ad snippet into post content according to configured position.
     */
    public function injectBannerAd(string $html, string $bannerCode, string $position = 'smart'): string
    {
        $bannerCode = trim($bannerCode);
        if ($bannerCode === '') {
            return $html;
        }

        $bannerWrapper = <<<HTML
<div class="ai-blogger-ad-banner" style="text-align: center; margin: 30px auto; max-width: 100%; overflow: hidden; clear: both;">
    {$bannerCode}
</div>
HTML;

        // Count paragraphs
        $pCount = preg_match_all('/<\/p>/i', $html, $matches, PREG_OFFSET_CAPTURE);

        if ($pCount < 2) {
            // Short content: append at end
            return $html . "\n" . $bannerWrapper;
        }

        if ($position === 'after_intro') {
            // After 2nd paragraph
            $pos = $matches[0][1][1] + 4; // length of </p>
            return substr($html, 0, $pos) . "\n" . $bannerWrapper . "\n" . substr($html, $pos);
        }

        if ($position === 'bottom') {
            // Before conclusion or at end
            return $html . "\n" . $bannerWrapper;
        }

        if ($position === 'both' && $pCount >= 4) {
            // After 2nd paragraph AND before last paragraph
            $pos1 = $matches[0][1][1] + 4;
            $html = substr($html, 0, $pos1) . "\n" . $bannerWrapper . "\n" . substr($html, $pos1);

            // Re-match after first insertion
            preg_match_all('/<\/p>/i', $html, $matches2, PREG_OFFSET_CAPTURE);
            $lastIdx = max(2, count($matches2[0]) - 2);
            $pos2 = $matches2[0][$lastIdx][1] + 4;
            return substr($html, 0, $pos2) . "\n" . $bannerWrapper . "\n" . substr($html, $pos2);
        }

        // 'smart' or 'middle': Insert approximately at 40-50% mark
        $targetP = max(1, (int) floor($pCount / 2));
        $pos = $matches[0][$targetP - 1][1] + 4;
        return substr($html, 0, $pos) . "\n" . $bannerWrapper . "\n" . substr($html, $pos);
    }

    /**
     * Inject Social Bar ad code at the end of content.
     */
    public function injectSocialBar(string $html, string $socialBarCode): string
    {
        $socialBarCode = trim($socialBarCode);
        if ($socialBarCode === '') {
            return $html;
        }

        // Append cleanly at the end
        return $html . "\n" . '<!-- Social Bar Ad -->' . "\n" . $socialBarCode . "\n";
    }

    /**
     * Insert a prompt box into post HTML at an optimal position
     * (after the introduction paragraphs, before other main sections).
     */
    private function insertPromptBoxIntoHtml(string $html, string $promptBox): string
    {
        // Try to insert after the 2nd paragraph
        $pCount = preg_match_all('/<\/p>/i', $html, $matches, PREG_OFFSET_CAPTURE);
        if ($pCount >= 2) {
            $pos = $matches[0][1][1] + 4;
            return substr($html, 0, $pos) . "\n" . $promptBox . "\n" . substr($html, $pos);
        }

        // Fallback: prepend before content
        return $promptBox . "\n" . $html;
    }
}
