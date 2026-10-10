<?php
use App\Helpers\Sanitizer;
use App\Helpers\Url;
use App\Helpers\Csrf;

$pageTitle = 'AI Usage & Provider Pipeline';
$u = static fn(string $p) => Url::to($p);
ob_start();
?>

<!-- Metric Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-xs text-slate-500 uppercase tracking-wide font-medium">আজকের মোট রিকোয়েস্ট (Today Total)</p>
        <p class="text-3xl font-bold text-slate-800 mt-1"><?= (int)($totals['total'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-xs text-slate-500 uppercase tracking-wide font-medium">সফল (Success)</p>
        <p class="text-3xl font-bold text-emerald-600 mt-1"><?= (int)($totals['success'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-xs text-slate-500 uppercase tracking-wide font-medium">ব্যর্থ (Failed)</p>
        <p class="text-3xl font-bold text-rose-600 mt-1"><?= (int)($totals['failed'] ?? 0) ?></p>
    </div>
</div>

<!-- AI Model Pipeline Management (চালু/বন্ধ ও সিরিয়াল/অগ্রাধিকার) -->
<div class="bg-white rounded-xl border border-slate-200 p-6 mb-6 shadow-sm">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-4 border-b border-slate-100">
        <div>
            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                AI মডেল ব্যবস্থাপনা ও সিরিয়াল (Provider Pipeline)
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
                মডেল চালু (On) বা বন্ধ (Off) করুন এবং কোনটার পর কোনটা কাজ করবে (Primary, Secondary ইত্যাদি) নির্বাচন করুন।
            </p>
        </div>
        <div class="text-xs text-slate-500 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
            মোট মডেল: <span class="font-semibold text-slate-700"><?= count($providers ?? []) ?></span> |
            সক্রিয়: <span class="font-semibold text-emerald-600">
                <?= count(array_filter($providers ?? [], static fn($p) => !empty($p['is_enabled']) && !empty($p['is_configured']))) ?>
            </span>
        </div>
    </div>

    <!-- Active Execution Flow Preview -->
    <div class="my-4 p-3.5 bg-blue-50/70 border border-blue-100 rounded-xl">
        <p class="text-xs font-semibold text-blue-900 uppercase tracking-wide mb-2">বর্তমান সক্রিয় এক্সিকিউশন সিরিয়াল (Active Chain):</p>
        <div class="flex flex-wrap items-center gap-2">
            <?php
            $activeList = array_values(array_filter($providers ?? [], static fn($p) => !empty($p['is_enabled'])));
            if (empty($activeList)):
            ?>
                <span class="text-xs text-amber-700 font-medium bg-amber-100 px-2.5 py-1 rounded">সতর্কতা: কোন AI মডেল চালু নেই! অন্তত একটি মডেল চালু করুন।</span>
            <?php
            else:
                foreach ($activeList as $idx => $p):
                    $isFirst = ($idx === 0);
                    $badgeStyle = $isFirst
                        ? 'bg-blue-600 text-white font-semibold shadow-xs'
                        : 'bg-white text-slate-700 border border-slate-200';
            ?>
                    <div class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-lg <?= $badgeStyle ?>">
                        <span class="w-4 h-4 rounded-full <?= $isFirst ? 'bg-white/20' : 'bg-slate-100' ?> flex items-center justify-center text-[10px]">
                            <?= $idx + 1 ?>
                        </span>
                        <span><?= Sanitizer::escape($p['display_name']) ?></span>
                        <?php if ($isFirst): ?>
                            <span class="text-[10px] bg-blue-500 text-white px-1.5 py-0.2 rounded font-normal">Primary (তৈরি করবে)</span>
                        <?php elseif ($idx === 1): ?>
                            <span class="text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.2 rounded font-normal">Secondary (রিভিউ করবে)</span>
                        <?php else: ?>
                            <span class="text-[10px] text-slate-400 font-normal">Fallback</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($idx < count($activeList) - 1): ?>
                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    <?php endif; ?>
            <?php
                endforeach;
            endif;
            ?>
        </div>
    </div>

    <!-- Providers Table & Serial Configuration Form -->
    <form method="POST" action="<?= Sanitizer::escape($u('/ai-usage/reorder')) ?>" id="providers-order-form">
        <?= Csrf::field() ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead>
                    <tr class="text-xs uppercase text-slate-500 border-b border-slate-200 bg-slate-50/50">
                        <th class="py-3 px-3 w-16">সিরিয়াল</th>
                        <th class="py-3 px-3">মডেলের নাম (Provider)</th>
                        <th class="py-3 px-3">কনফিগারেশন মডেল</th>
                        <th class="py-3 px-3">API Key স্ট্যাটাস</th>
                        <th class="py-3 px-3 text-center">চালু / বন্ধ (On/Off)</th>
                        <th class="py-3 px-3 text-right">অগ্রাধিকার পরিবর্তন</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="providers-tbody">
                    <?php
                    $ranks = [
                        1 => ['label' => 'Primary (১ম)', 'class' => 'bg-blue-100 text-blue-800 border-blue-200'],
                        2 => ['label' => 'Secondary (২য়)', 'class' => 'bg-emerald-100 text-emerald-800 border-emerald-200'],
                        3 => ['label' => 'Tertiary (৩য়)', 'class' => 'bg-purple-100 text-purple-800 border-purple-200'],
                        4 => ['label' => '4th (৪র্থ)', 'class' => 'bg-slate-100 text-slate-700 border-slate-200'],
                        5 => ['label' => '5th (৫ম)', 'class' => 'bg-slate-100 text-slate-700 border-slate-200'],
                        6 => ['label' => '6th (৬ষ্ঠ)', 'class' => 'bg-slate-100 text-slate-700 border-slate-200'],
                    ];
                    $maxRank = max(count($providers ?? []), 6);
                    $rankLabels = [
                        1 => '১ম (Primary)',
                        2 => '২য় (Secondary)',
                        3 => '৩য় (Tertiary)',
                        4 => '৪র্থ (4th)',
                        5 => '৫ম (5th)',
                        6 => '৬ষ্ঠ (6th)',
                        7 => '৭ম (7th)',
                        8 => '৮ম (8th)',
                    ];
                    foreach ($providers as $idx => $p):
                        $pName = (string)$p['name'];
                        $pPriority = (int)($p['priority'] ?? ($idx + 1));
                        $isEnabled = !empty($p['is_enabled']);
                        $isConfigured = !empty($p['is_configured']);
                        $rankMeta = $ranks[$pPriority] ?? ['label' => $pPriority . 'th', 'class' => 'bg-slate-100 text-slate-700 border-slate-200'];
                    ?>
                    <tr class="hover:bg-slate-50/60 transition provider-row" data-provider="<?= Sanitizer::escape($pName) ?>" data-index="<?= $idx ?>">
                        <!-- Priority Badge -->
                        <td class="py-3.5 px-3">
                            <span class="inline-flex items-center justify-center font-bold text-xs px-2.5 py-1 rounded-md border <?= $rankMeta['class'] ?>">
                                <?= $rankMeta['label'] ?>
                            </span>
                        </td>

                        <!-- Display Name -->
                        <td class="py-3.5 px-3">
                            <div class="font-semibold text-slate-800"><?= Sanitizer::escape($p['display_name']) ?></div>
                            <div class="text-xs text-slate-400 font-mono"><?= Sanitizer::escape($pName) ?></div>
                        </td>

                        <!-- Configured Model -->
                        <td class="py-3.5 px-3">
                            <span class="font-mono text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded border border-slate-200">
                                <?= Sanitizer::escape($p['configured_model'] ?: 'default') ?>
                            </span>
                        </td>

                        <!-- API Key Status -->
                        <td class="py-3.5 px-3">
                            <?php if ($isConfigured): ?>
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Key Configured
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full" title="Set in .env">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    No Key in .env
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Status Toggle (চালু / বন্ধ) -->
                        <td class="py-3.5 px-3 text-center">
                            <button type="button"
                                    class="toggle-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer shadow-xs <?= $isEnabled ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-slate-200 text-slate-600 hover:bg-slate-300' ?>"
                                    data-provider="<?= Sanitizer::escape($pName) ?>"
                                    data-state="<?= $isEnabled ? '1' : '0' ?>"
                                    onclick="toggleProviderStatus(this)">
                                <span class="toggle-dot w-2 h-2 rounded-full <?= $isEnabled ? 'bg-white' : 'bg-slate-400' ?>"></span>
                                <span class="toggle-text"><?= $isEnabled ? 'চালু (Active)' : 'বন্ধ (Disabled)' ?></span>
                            </button>
                        </td>

                        <!-- Priority Selector & Move Controls -->
                        <td class="py-3.5 px-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <select name="priorities[<?= Sanitizer::escape($pName) ?>]"
                                        class="priority-select text-xs font-medium border border-slate-300 rounded-lg px-2.5 py-1.5 bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                        data-provider="<?= Sanitizer::escape($pName) ?>">
                                    <?php for ($r = 1; $r <= $maxRank; $r++):
                                        $optLabel = $rankLabels[$r] ?? ($r . 'th');
                                    ?>
                                        <option value="<?= $r ?>" <?= $pPriority === $r ? 'selected' : '' ?>><?= $optLabel ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-5 mt-4 border-t border-slate-100">
            <p class="text-xs text-slate-500">
                💡 ড্রপডাউন থেকে ১ থেকে <?= count($providers ?? []) ?> নম্বর সিরিয়াল সিলেক্ট করে নিচের বাটনে ক্লিক করুন।
            </p>
            <button type="submit" id="btn-save-order"
                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                সিরিয়াল সংরক্ষণ করুন (Save Priorities)
            </button>
        </div>
    </form>
</div>

<!-- Workflow Explanation Box -->
<div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-xl p-5 mb-6 shadow-sm">
    <div class="flex items-start gap-3">
        <div class="w-8 h-8 rounded-lg bg-blue-500/20 text-blue-300 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div class="space-y-1.5 text-xs leading-relaxed">
            <h3 class="font-bold text-sm text-white">মাল্টি-মডেল অটোমেশন ও হ্যান্ডঅফ নিয়ম (Multi-Model Sequential System):</h3>
            <ul class="list-disc list-inside space-y-1 text-slate-300">
                <li><strong class="text-white">১. কনটেন্ট তৈরি (Generate):</strong> তালিকার ১ম সক্রিয় মডেল দিয়ে কনটেন্ট তৈরি শুরু হবে। ১ম মডেল ফেইল করলে পরবর্তী মডেল চেষ্টা করবে।</li>
                <li><strong class="text-white">২. রিভিউ (Review):</strong> কনটেন্ট তৈরি শেষ হওয়া মাত্র স্বয়ংক্রিয়ভাবে ২য় মডেলে রিভিউ ও কোয়ালিটি চেকের জন্য চলে যাবে। <span class="text-amber-300">যে মডেল তৈরি করেছে সে রিভিউ করবে না!</span></li>
                <li><strong class="text-white">৩. অটো ফলব্যাক (Fallback):</strong> ২য় মডেল রিভিউতে ফেইল বা সমস্যা করলে ৩য় মডেল রিভিউ করবে। যদি ৩য় মডেলও ফেইল করে, পরবর্তী মডেল ট্রাই করবে।</li>
                <li><strong class="text-white">৪. শেষ চেষ্টা (Creator Fallback):</strong> সব ব্যাকআপ মডেল ফেইল করলেই কেবল যে মডেল কনটেন্ট তৈরি করেছিল সে আবার রিভিউ করবে।</li>
                <li><strong class="text-white">৫. ইমপ্রুভমেন্ট (Improvement):</strong> স্কোর ৯.০ এর নিচে থাকলে রিভিউয়ার মডেল বাদে ৩য় মডেল দিয়ে আর্টিকেল উন্নত করা হবে।</li>
            </ul>
        </div>
    </div>
</div>

<!-- Today by Provider Statistics Table -->
<div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm overflow-x-auto">
    <div class="flex items-center justify-between mb-3">
        <h2 class="font-bold text-slate-800 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            আজকের রিকোয়েস্ট লগ পরিসংখ্যান (Today Usage by Provider)
        </h2>
    </div>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-500 border-b border-slate-100 bg-slate-50/50">
            <tr>
                <th class="py-2.5 px-3">Provider</th>
                <th class="py-2.5 px-3">Total Calls</th>
                <th class="py-2.5 px-3 text-emerald-600">Success</th>
                <th class="py-2.5 px-3 text-rose-600">Failed</th>
                <th class="py-2.5 px-3">Last Used Time</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <?php if (empty($stats)): ?>
            <tr><td colspan="5" class="py-6 text-center text-slate-400">আজকে এখনো কোনো রিকোয়েস্ট হয়নি।</td></tr>
            <?php else: foreach ($stats as $row): ?>
            <tr class="hover:bg-slate-50/50">
                <td class="py-2.5 px-3 font-semibold text-slate-700 capitalize"><?= Sanitizer::escape($row['provider'] ?? '') ?></td>
                <td class="py-2.5 px-3 font-medium text-slate-800"><?= (int)($row['total'] ?? 0) ?></td>
                <td class="py-2.5 px-3 text-emerald-600 font-medium"><?= (int)($row['success'] ?? 0) ?></td>
                <td class="py-2.5 px-3 text-rose-600 font-medium"><?= (int)($row['failed'] ?? 0) ?></td>
                <td class="py-2.5 px-3 text-slate-400 text-xs"><?= Sanitizer::escape($row['last_used'] ?? '') ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<p class="text-xs text-slate-400 mt-4">
    API কী কনফিগারেশন <code>.env</code> ফাইলে নির্ধারিত (Gemini Flash 3.5, Gemini Flash 3, SambaNova, Xkero, Apinex, Groq)।
</p>

<!-- JavaScript for Live Toggle and Priority Order -->
<script>
async function toggleProviderStatus(btn) {
    const provider = btn.getAttribute('data-provider');
    const currentState = btn.getAttribute('data-state') === '1';
    const newState = !currentState;

    btn.disabled = true;
    btn.style.opacity = '0.6';

    try {
        const form = new FormData();
        form.append('provider', provider);
        form.append('state', newState ? '1' : '0');
        form.append('_csrf_token', App.csrfToken());

        const res = await App.fetch('/ai-usage/toggle', {
            method: 'POST',
            body: form,
        });

        if (res.success) {
            btn.setAttribute('data-state', newState ? '1' : '0');
            const dot = btn.querySelector('.toggle-dot');
            const txt = btn.querySelector('.toggle-text');

            if (newState) {
                btn.className = btn.className
                    .replace('bg-slate-200 text-slate-600 hover:bg-slate-300', 'bg-emerald-600 text-white hover:bg-emerald-700');
                dot.className = dot.className.replace('bg-slate-400', 'bg-white');
                txt.textContent = 'চালু (Active)';
            } else {
                btn.className = btn.className
                    .replace('bg-emerald-600 text-white hover:bg-emerald-700', 'bg-slate-200 text-slate-600 hover:bg-slate-300');
                dot.className = dot.className.replace('bg-white', 'bg-slate-400');
                txt.textContent = 'বন্ধ (Disabled)';
            }
            App.toast(res.message || 'Status updated', 'success');
            // Refresh preview after short delay
            setTimeout(() => window.location.reload(), 800);
        } else {
            App.toast(res.message || 'Failed to update', 'error');
        }
    } catch (err) {
        App.toast(err.message || 'Failed to update status', 'error');
    } finally {
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

// Reorder Form Submission
document.getElementById('providers-order-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-save-order');
    App.setLoading(btn, true, 'সংরক্ষণ হচ্ছে...');

    try {
        const formData = new FormData(this);
        formData.append('_csrf_token', App.csrfToken());

        const res = await App.fetch('/ai-usage/reorder', {
            method: 'POST',
            body: formData,
        });

        if (res.success) {
            App.toast(res.message || 'সিরিয়াল সফলভাবে সংরক্ষিত হয়েছে', 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            App.toast(res.message || 'সংরক্ষণ ব্যর্থ হয়েছে', 'error');
            App.setLoading(btn, false, 'সিরিয়াল সংরক্ষণ করুন (Save Priorities)');
        }
    } catch (err) {
        App.toast(err.message || 'ত্রুটি ঘটেছে', 'error');
        App.setLoading(btn, false, 'সিরিয়াল সংরক্ষণ করুন (Save Priorities)');
    }
});
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
