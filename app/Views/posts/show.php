<?php
use App\Helpers\Sanitizer;
use App\Helpers\Csrf;
use App\Helpers\Url;

$pageTitle = $post['title'] ?: $post['topic'] ?: 'Post Details';
ob_start();

$imgSvc = new \App\Services\ImageService();
$postImages = !empty($post['content_html']) ? $imgSvc->listImageSrcsIndexed((string)$post['content_html']) : [];

$statusColors = [
    'DRAFT' => 'bg-slate-100 text-slate-700', 'GENERATING' => 'bg-blue-100 text-blue-700',
    'GENERATED' => 'bg-indigo-100 text-indigo-700', 'AI_REVIEWING' => 'bg-purple-100 text-purple-700',
    'HUMAN_REVIEW' => 'bg-yellow-100 text-yellow-800', 'REJECTED' => 'bg-red-100 text-red-700',
    'APPROVED' => 'bg-emerald-100 text-emerald-700', 'SCHEDULED' => 'bg-cyan-100 text-cyan-700',
    'PUBLISHING' => 'bg-orange-100 text-orange-700', 'PUBLISHED' => 'bg-green-100 text-green-700',
    'FAILED' => 'bg-red-100 text-red-700',
];
$sc = $statusColors[$post['status']] ?? 'bg-slate-100 text-slate-600';
?>

<div class="flex flex-wrap items-center gap-3 mb-6">
    <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/posts'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm text-slate-500 hover:text-slate-700">&larr; Back</a>
    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium <?= $sc ?>"><?= str_replace('_', ' ', $post['status']) ?></span>
    <?php if ($post['ai_score'] !== null): ?>
    <span class="text-sm text-slate-500">AI Score: <strong><?= number_format((float)$post['ai_score'], 1) ?>/10</strong></span>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <!-- Content -->
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h1 class="text-2xl font-bold text-slate-900 mb-2"><?= Sanitizer::escape($post['title'] ?: 'No title yet') ?></h1>
            <?php if ($post['meta_description']): ?>
            <p class="text-sm text-slate-500 mb-4 italic"><?= Sanitizer::escape($post['meta_description']) ?></p>
            <?php endif; ?>
            <?php if (!empty($post['content_html'])): ?>
            <div id="post-content-container" class="prose max-w-none text-slate-700"><?= $post['content_html'] ?></div>
            <?php else: ?>
            <div id="post-content-container">
                <p class="text-slate-400 py-8 text-center">No content generated yet.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- AI Review -->
        <?php if ($review): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="font-semibold text-slate-800 mb-4">AI Review</h2>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-4">
                <?php
                $scores = [
                    'Overall' => $review['score'], 'Grammar' => $review['grammar_score'],
                    'SEO' => $review['seo_score'], 'Readability' => $review['readability_score'],
                    'Structure' => $review['structure_score'],
                ];
                foreach ($scores as $label => $val):
                ?>
                <div class="text-center p-3 bg-slate-50 rounded-lg">
                    <p class="text-xs text-slate-500"><?= $label ?></p>
                    <p class="text-lg font-bold text-slate-800"><?= number_format((float)$val, 1) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="text-sm mb-3">Verdict: <strong class="<?= $review['verdict'] === 'APPROVE' ? 'text-green-600' : 'text-orange-600' ?>"><?= Sanitizer::escape($review['verdict'] ?? '') ?></strong></p>
            <?php
                $rawExtra = [];
                if (!empty($review['raw_response'])) {
                    $decoded = is_string($review['raw_response']) ? json_decode($review['raw_response'], true) : $review['raw_response'];
                    if (is_array($decoded)) {
                        $rawExtra = $decoded;
                    }
                }
                $detailedNotes = $rawExtra['detailed_notes'] ?? '';
                $rounds = $rawExtra['rounds'] ?? null;
                $allReviews = $rawExtra['all_reviews'] ?? [];
            ?>
            <?php if ($rounds): ?>
            <p class="text-xs text-slate-500 mb-2">Improve rounds: <strong><?= (int)$rounds ?></strong> (পাস মার্ক ≥ ৮.০ | ইমপ্রুভমেন্ট টার্গেট ৯.০+)</p>
            <?php endif; ?>

            <?php if (count($allReviews) > 1): ?>
            <!-- Review progression when multiple rounds occurred -->
            <div class="mb-4 p-3 bg-slate-50 rounded-lg border border-slate-200">
                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">রিভিউ ও ইমপ্রুভমেন্ট অগ্রগতি (Review Progression):</h4>
                <div class="space-y-2 text-xs">
                    <?php foreach ($allReviews as $rIdx => $rItem): 
                        $rScore = (float)($rItem['score'] ?? 0);
                        $isR1 = ($rIdx === 0);
                        $isPass = $rScore >= 8.0;
                    ?>
                    <div class="p-2.5 rounded-lg bg-white border <?= $isPass ? 'border-emerald-200' : 'border-amber-200' ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full <?= $isPass ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?> flex items-center justify-center font-bold text-[10px]">
                                    <?= $rIdx + 1 ?>
                                </span>
                                <div>
                                    <span class="font-semibold text-slate-800"><?= $isR1 ? '১ম রাউন্ড রিভিউ (Initial)' : 'পরবর্তী রাউন্ড রিভিউ (After Improve)' ?></span>
                                    <?php if (!empty($rItem['_provider'])): ?>
                                    <span class="text-slate-400 font-mono text-[11px]">(<?= Sanitizer::escape($rItem['_provider']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-sm <?= $isPass ? 'text-emerald-600' : 'text-amber-600' ?>">
                                    <?= number_format($rScore, 1) ?> / 10
                                </span>
                                <span class="text-[10px] block font-medium <?= $isPass ? 'text-emerald-600' : 'text-amber-700' ?>">
                                    <?= $isPass ? '✓ পাস (≥ ৮.০)' : '✗ ফেল (৮.০ থেকে ' . number_format(8.0 - $rScore, 1) . ' কম)' ?>
                                </span>
                            </div>
                        </div>
                        <?php if (!$isPass && !empty($rItem['problems'])): ?>
                        <div class="mt-2 pt-2 border-t border-slate-100 text-[11px] text-slate-600">
                            <span class="text-amber-700 font-semibold">ফেল হওয়ার মূল কারণ:</span>
                            <ul class="list-disc pl-4 mt-0.5 space-y-0.5">
                                <?php foreach (array_slice((array)$rItem['problems'], 0, 2) as $prob): ?>
                                <li><?= Sanitizer::escape(is_string($prob) ? $prob : json_encode($prob)) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($isR1 && count($allReviews) > 1): ?>
                    <div class="text-center text-blue-600 text-[11px] font-medium py-0.5">
                        ↓ ৩য় মডেল দিয়ে ৯.০+ স্কোর অর্জনের লক্ষ্যে পরিমার্জিত (Improved) করা হয়েছে ↓
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($detailedNotes !== ''): ?>
            <div class="mb-3 p-3 bg-slate-50 rounded-lg text-sm text-slate-700">
                <p class="text-xs font-medium text-slate-500 mb-1">Detailed notes</p>
                <?= nl2br(Sanitizer::escape($detailedNotes)) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($review['problems'])): ?>
            <div class="mb-3"><p class="text-xs font-medium text-red-600 mb-1">Problems</p>
            <ul class="text-sm text-slate-600 list-disc pl-5"><?php foreach ($review['problems'] as $p): ?><li><?= Sanitizer::escape(is_string($p) ? $p : json_encode($p)) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <?php if (!empty($review['required_changes'])): ?>
            <div class="mb-3"><p class="text-xs font-medium text-orange-600 mb-1">Required changes</p>
            <ul class="text-sm text-slate-600 list-disc pl-5"><?php foreach ($review['required_changes'] as $c): ?><li><?= Sanitizer::escape(is_string($c) ? $c : json_encode($c)) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <?php if (!empty($review['suggestions'])): ?>
            <div class="mb-3"><p class="text-xs font-medium text-blue-600 mb-1">Suggestions</p>
            <ul class="text-sm text-slate-600 list-disc pl-5"><?php foreach ($review['suggestions'] as $s): ?><li><?= Sanitizer::escape(is_string($s) ? $s : json_encode($s)) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar Actions -->
    <div class="space-y-4">
        <!-- Actions Card -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
            <h3 class="font-semibold text-slate-800 text-sm">Actions</h3>

            <?php if (in_array($post['status'], ['DRAFT', 'GENERATED', 'HUMAN_REVIEW', 'REJECTED', 'FAILED'])): ?>
            <form method="POST" action="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$post['id'] . '/generate'), ENT_QUOTES, 'UTF-8') ?>" id="gen-form">
                <?= Csrf::field() ?>
                <button type="submit" id="btn-gen" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
                    onclick="this.disabled=true; this.innerText='Generating...'; this.form.submit();">
                    <?= empty($post['content_html']) ? 'Generate Content' : 'Regenerate' ?>
                </button>
            </form>
            <?php endif; ?>

            <?php if (!empty($post['content_html']) && in_array($post['status'], ['GENERATED', 'HUMAN_REVIEW'])): ?>
            <form method="POST" action="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$post['id'] . '/review'), ENT_QUOTES, 'UTF-8') ?>">
                <?= Csrf::field() ?>
                <button type="submit" class="w-full py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition"
                    onclick="this.disabled=true; this.innerText='Reviewing...'; this.form.submit();">Run AI Review</button>
            </form>
            <?php endif; ?>

            <a href="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$post['id'] . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="block w-full py-2 text-center bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium rounded-lg transition">Edit Content</a>

            <?php if (in_array($post['status'], ['HUMAN_REVIEW', 'GENERATED', 'REJECTED'])): ?>
            <form method="POST" action="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$post['id'] . '/approve'), ENT_QUOTES, 'UTF-8') ?>">
                <?= Csrf::field() ?>
                <button type="submit" class="w-full py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">Approve</button>
            </form>
            <form method="POST" action="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$post['id'] . '/reject'), ENT_QUOTES, 'UTF-8') ?>">
                <?= Csrf::field() ?>
                <button type="submit" class="w-full py-2 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium rounded-lg border border-red-200 transition">Reject</button>
            </form>
            <?php endif; ?>

            <?php if (in_array($post['status'], ['APPROVED', 'SCHEDULED', 'FAILED'], true)): ?>
            <div class="border-t border-slate-200 pt-3 space-y-3">
                <form method="POST" action="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$post['id'] . '/publish-now'), ENT_QUOTES, 'UTF-8') ?>"
                      onsubmit="return confirm('এখনই Blogger-এ পাবলিশ করবেন?');">
                    <?= Csrf::field() ?>
                    <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition"
                        onclick="if(window.App && App.ProgressLoader) App.ProgressLoader.show('Blogger-এ পোস্ট পাবলিশ হচ্ছে...');">
                        🚀 Publish Now
                    </button>
                </form>

                <h4 class="text-xs font-medium text-slate-500 mb-1">অথবা সময় নির্ধারণ করুন</h4>
                <form method="POST" action="<?= htmlspecialchars(\App\Helpers\Url::to('/posts/' . (int)$post['id'] . '/schedule'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-2">
                    <?= Csrf::field() ?>
                    <input type="date" name="publish_date" required value="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <input type="time" name="publish_time" value="10:00"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <input type="hidden" name="timezone" value="Asia/Dhaka">
                    <button type="submit" class="w-full py-2 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-medium rounded-lg transition">Schedule</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Image Manager Card -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4" id="image-manager-card">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-lg">📸</span>
                    <h3 class="font-semibold text-slate-800 text-sm">ছবি পরিবর্তন (Image Manager)</h3>
                </div>
                <span id="img-count-badge" class="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">
                    <?= count($postImages) ?> টি ছবি
                </span>
            </div>

            <!-- Search input & options -->
            <div class="space-y-2 bg-slate-50 p-3 rounded-lg border border-slate-200 text-xs">
                <div>
                    <label class="block text-slate-700 font-medium mb-1">সার্চ কিওয়ার্ড (Search Keyword):</label>
                    <input type="text" id="img-search-query"
                           placeholder="<?= htmlspecialchars($post['primary_keyword'] ?: $post['topic'] ?: 'tech, modern workspace...', ENT_QUOTES, 'UTF-8') ?>"
                           value="<?= htmlspecialchars($post['primary_keyword'] ?: '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs focus:ring-1 focus:ring-blue-500 bg-white">
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="inline-flex items-center gap-1.5 cursor-pointer text-slate-700 select-none">
                        <input type="checkbox" id="img-random-toggle" class="rounded text-blue-600 focus:ring-0">
                        <span class="font-medium text-slate-800">Search with Random Query</span>
                    </label>
                    <button type="button" onclick="quickRandomQuery()" class="text-blue-600 hover:text-blue-800 font-medium hover:underline flex items-center gap-0.5">
                        🎲 নতুন কিওয়ার্ড
                    </button>
                </div>
            </div>

            <!-- Selection controls -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-2 text-xs">
                <label class="inline-flex items-center gap-1.5 cursor-pointer font-medium text-slate-700 select-none">
                    <input type="checkbox" id="img-select-all" class="rounded text-blue-600 focus:ring-0" onchange="toggleSelectAll(this)">
                    <span>সবগুলো সিলেক্ট করুন</span>
                </label>
                <span id="img-selected-info" class="text-slate-500 font-medium">০ টি সিলেক্টেড</span>
            </div>

            <!-- Image list with numbers -->
            <div id="img-items-list" class="space-y-2.5 max-h-80 overflow-y-auto pr-1">
                <?php if (!empty($postImages)): ?>
                    <?php foreach ($postImages as $item): ?>
                    <div class="img-item-row flex items-center justify-between gap-2.5 p-2 rounded-lg border border-slate-200 bg-white hover:border-blue-400 hover:shadow-xs transition" data-index="<?= $item['index'] ?>">
                        <div class="flex items-center gap-2 min-w-0">
                            <input type="checkbox" class="img-checkbox rounded text-blue-600 focus:ring-0 cursor-pointer" value="<?= $item['index'] ?>" onchange="updateSelectedCount()">
                            <a href="<?= htmlspecialchars($item['src'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="flex-shrink-0" title="বড় আকারে দেখুন">
                                <img src="<?= htmlspecialchars($item['src'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="w-14 h-11 object-cover rounded border border-slate-200 bg-slate-100 hover:opacity-90">
                            </a>
                            <div class="min-w-0">
                                <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                    ছবি <?= $item['number'] ?>
                                </span>
                                <?php if (!empty($item['credit'])): ?>
                                <p class="text-[10px] text-slate-400 truncate max-w-[110px]" title="<?= htmlspecialchars($item['credit'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($item['credit'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="button" onclick="replaceSpecificImage(<?= $item['index'] ?>)" class="flex-shrink-0 text-xs px-2.5 py-1 bg-slate-50 hover:bg-blue-600 hover:text-white text-slate-700 rounded font-medium border border-slate-200 transition">
                            🔄 চেঞ্জ
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-xs text-slate-400 text-center py-4">এই পোস্টে এখনও কোনো ছবি যোগ করা হয়নি।</p>
                <?php endif; ?>
            </div>

            <!-- Action buttons -->
            <div class="space-y-2 pt-1 border-t border-slate-100">
                <button type="button" id="btn-replace-selected" onclick="replaceSelectedImages()" class="w-full py-2.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                    🔄 সিলেক্টেড ছবি পরিবর্তন করুন
                </button>
                <button type="button" id="btn-replace-all" onclick="replaceAllImages()" class="w-full py-2 px-3 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-medium rounded-lg transition">
                    ⚡ সবগুলো ছবি একসাথে নতুন করে রিপ্লেস করুন
                </button>
            </div>
        </div>

        <?php if (!empty($aiAttempts)): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-800 text-sm mb-3">AI Model Attempts</h3>
            <ul class="space-y-2 text-xs">
                <?php foreach ($aiAttempts as $att): ?>
                <li class="flex flex-col gap-0.5 border-b border-slate-100 pb-2 last:border-0">
                    <div class="flex justify-between items-center">
                        <span class="font-medium text-slate-700"><?= Sanitizer::escape($att['provider'] ?? '') ?>
                            <?php if (!empty($att['model'])): ?>
                            <span class="text-slate-400 font-normal">(<?= Sanitizer::escape($att['model']) ?>)</span>
                            <?php endif; ?>
                        </span>
                        <?php
                        $st = $att['status'] ?? '';
                        $badge = match ($st) {
                            'SUCCESS' => 'bg-emerald-100 text-emerald-700',
                            'FAILED' => 'bg-red-100 text-red-700',
                            'INVALID_RESPONSE' => 'bg-amber-100 text-amber-700',
                            default => 'bg-slate-100 text-slate-600',
                        };
                        ?>
                        <span class="px-1.5 py-0.5 rounded <?= $badge ?>"><?= Sanitizer::escape($st) ?></span>
                    </div>
                    <div class="text-slate-400"><?= Sanitizer::escape($att['operation'] ?? '') ?>
                        <?php if (!empty($att['duration_ms'])): ?> · <?= (int)$att['duration_ms'] ?>ms<?php endif; ?>
                        <?php if (!empty($att['created_at'])): ?> · <?= Sanitizer::escape($att['created_at']) ?><?php endif; ?>
                    </div>
                    <?php if (!empty($att['error_message'])): ?>
                    <?php
                        $msg = (string)$att['error_message'];
                        $isReviewPass = str_contains($msg, 'Review Pass');
                        $isReviewFail = str_contains($msg, 'Review Fail');
                        $isImprove    = str_contains($msg, 'Content Improved');
                        if ($isReviewPass):
                    ?>
                        <div class="mt-1 text-[11px] font-semibold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                            <?= Sanitizer::escape($msg) ?>
                        </div>
                    <?php elseif ($isReviewFail): ?>
                        <div class="mt-1 text-[11px] font-semibold text-amber-900 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                            <?= Sanitizer::escape($msg) ?>
                        </div>
                    <?php elseif ($isImprove): ?>
                        <div class="mt-1 text-[11px] font-semibold text-blue-800 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
                            <?= Sanitizer::escape($msg) ?>
                        </div>
                    <?php else: ?>
                        <div class="text-red-600 break-words"><?= Sanitizer::escape($msg) ?></div>
                    <?php endif; ?>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Meta Info -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-2 text-sm">
            <h3 class="font-semibold text-slate-800 text-sm mb-2">Details</h3>
            <div class="flex justify-between"><span class="text-slate-500">Topic</span><span class="text-slate-700 text-right max-w-[60%] truncate"><?= Sanitizer::escape($post['topic']) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Keyword</span><span class="text-slate-700"><?= Sanitizer::escape($post['primary_keyword'] ?? '—') ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Language</span><span class="text-slate-700"><?= $post['language'] === 'bn' ? 'Bengali' : 'English' ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Created</span><span class="text-slate-700"><?= $post['created_at'] ?></span></div>
            <?php if ($post['approved_at']): ?><div class="flex justify-between"><span class="text-slate-500">Approved</span><span class="text-slate-700"><?= $post['approved_at'] ?></span></div><?php endif; ?>
            <?php if ($post['scheduled_at']): ?><div class="flex justify-between"><span class="text-slate-500">Scheduled</span><span class="text-slate-700"><?= $post['scheduled_at'] ?></span></div><?php endif; ?>
            <?php if ($post['published_at']): ?><div class="flex justify-between"><span class="text-slate-500">Published</span><span class="text-slate-700"><?= $post['published_at'] ?></span></div><?php endif; ?>
            <?php if ($post['blogger_url']): ?>
            <div class="pt-2 border-t border-slate-100">
                <a href="<?= Sanitizer::escape($post['blogger_url']) ?>" target="_blank" rel="noopener" class="text-blue-600 hover:underline text-sm">View on Blogger →</a>
            </div>
            <?php endif; ?>
            <?php if ($post['error_message']): ?>
            <div class="pt-2 border-t border-slate-100">
                <p class="text-xs text-red-600"><?= Sanitizer::escape($post['error_message']) ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($post['tags']) && is_array($post['tags'])): ?>
            <div class="pt-2 flex flex-wrap gap-1">
                <?php foreach ($post['tags'] as $tag): ?>
                <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-xs"><?= Sanitizer::escape($tag) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const POST_ID = <?= (int)$post['id'] ?>;
const RANDOM_TOPIC_POOL = [
    'technology modern workspace laptop',
    'digital innovation office computer',
    'creative workspace laptop desk',
    'artificial intelligence future tech',
    'programming code software monitor',
    'online education learning computer',
    'business productivity strategy laptop',
    'modern remote work setup desk',
    'cloud data computing technology',
    'minimal desk workspace technology'
];

function quickRandomQuery() {
    const q = RANDOM_TOPIC_POOL[Math.floor(Math.random() * RANDOM_TOPIC_POOL.length)];
    const input = document.getElementById('img-search-query');
    if (input) input.value = q;
    const toggle = document.getElementById('img-random-toggle');
    if (toggle) toggle.checked = true;
    App.toast('র্যান্ডম কিওয়ার্ড সিলেক্ট করা হয়েছে: ' + q, 'info');
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.img-checkbox');
    const checked = document.querySelectorAll('.img-checkbox:checked');
    const count = checked.length;

    const info = document.getElementById('img-selected-info');
    if (info) info.textContent = count + ' টি সিলেক্টেড';

    const selectAll = document.getElementById('img-select-all');
    if (selectAll) {
        selectAll.checked = (checkboxes.length > 0 && count === checkboxes.length);
    }

    const btnReplaceSelected = document.getElementById('btn-replace-selected');
    if (btnReplaceSelected) {
        if (count === 0) {
            btnReplaceSelected.textContent = '🔄 সিলেক্টেড ছবি পরিবর্তন করুন';
        } else {
            btnReplaceSelected.textContent = '🔄 সিলেক্টেড (' + count + 'টি) ছবি পরিবর্তন করুন';
        }
    }
}

function toggleSelectAll(master) {
    document.querySelectorAll('.img-checkbox').forEach(cb => {
        cb.checked = master.checked;
    });
    updateSelectedCount();
}

function getQueryOptions() {
    const input = document.getElementById('img-search-query');
    const toggle = document.getElementById('img-random-toggle');
    return {
        query: input ? input.value.trim() : '',
        random_query: toggle ? toggle.checked : false,
    };
}

function setButtonsLoading(isLoading, text = 'ছবি লোড হচ্ছে...') {
    const btnSel = document.getElementById('btn-replace-selected');
    const btnAll = document.getElementById('btn-replace-all');
    if (btnSel) {
        btnSel.disabled = isLoading;
        if (isLoading) btnSel.textContent = text;
        else updateSelectedCount();
    }
    if (btnAll) {
        btnAll.disabled = isLoading;
        if (isLoading) btnAll.textContent = text;
        else btnAll.textContent = '⚡ সবগুলো ছবি একসাথে নতুন করে রিপ্লেস করুন';
    }
}

async function executeImageReplace(payload) {
    setButtonsLoading(true, 'ছবি পরিবর্তন হচ্ছে...');
    try {
        const res = await App.fetch('/posts/' + POST_ID + '/replace-images', {
            method: 'POST',
            body: payload,
        });

        if (res.data) {
            // 1. Update article HTML
            if (res.data.content_html) {
                const container = document.getElementById('post-content-container');
                if (container) {
                    container.innerHTML = res.data.content_html;
                    tagArticleImages();
                }
            }

            // 2. Re-render image list in sidebar
            if (res.data.all_images) {
                renderImageList(res.data.all_images);
            }

            App.toast(res.message || 'ছবি সফলভাবে পরিবর্তন করা হয়েছে!', 'success');
        }
    } catch (e) {
        App.toast(e.message || 'ছবি পরিবর্তন করতে সমস্যা হয়েছে', 'error');
    } finally {
        setButtonsLoading(false);
    }
}

function replaceSpecificImage(index) {
    const opts = getQueryOptions();
    executeImageReplace({
        mode: 'index',
        index: index,
        query: opts.query,
        random_query: opts.random_query,
    });
}

function replaceSelectedImages() {
    const checked = Array.from(document.querySelectorAll('.img-checkbox:checked')).map(cb => parseInt(cb.value, 10));
    if (checked.length === 0) {
        App.toast('অনুগ্রহ করে কমপক্ষে একটি ছবি সিলেক্ট করুন', 'error');
        return;
    }

    const allCheckboxes = document.querySelectorAll('.img-checkbox');
    const opts = getQueryOptions();

    // If all are selected, use 'all' mode
    if (checked.length === allCheckboxes.length) {
        executeImageReplace({
            mode: 'all',
            query: opts.query,
            random_query: opts.random_query,
        });
    } else {
        // Replace only the chosen 1 or 2 images
        executeImageReplace({
            mode: 'indexes',
            indexes: checked,
            query: opts.query,
            random_query: opts.random_query,
        });
    }
}

function replaceAllImages() {
    const opts = getQueryOptions();
    executeImageReplace({
        mode: 'all',
        query: opts.query,
        random_query: opts.random_query,
    });
}

function renderImageList(images) {
    const list = document.getElementById('img-items-list');
    const badge = document.getElementById('img-count-badge');
    if (badge) badge.textContent = images.length + ' টি ছবি';

    if (!list) return;

    if (!images || images.length === 0) {
        list.innerHTML = '<p class="text-xs text-slate-400 text-center py-4">এই পোস্টে এখনও কোনো ছবি যোগ করা হয়নি।</p>';
        updateSelectedCount();
        return;
    }

    list.innerHTML = images.map(item => `
        <div class="img-item-row flex items-center justify-between gap-2.5 p-2 rounded-lg border border-slate-200 bg-white hover:border-blue-400 hover:shadow-xs transition" data-index="${item.index}">
            <div class="flex items-center gap-2 min-w-0">
                <input type="checkbox" class="img-checkbox rounded text-blue-600 focus:ring-0 cursor-pointer" value="${item.index}" onchange="updateSelectedCount()">
                <a href="${App.escape(item.src)}" target="_blank" rel="noopener" class="flex-shrink-0" title="বড় আকারে দেখুন">
                    <img src="${App.escape(item.src)}" alt="" class="w-14 h-11 object-cover rounded border border-slate-200 bg-slate-100 hover:opacity-90">
                </a>
                <div class="min-w-0">
                    <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                        ছবি ${item.number}
                    </span>
                    ${item.credit ? `<p class="text-[10px] text-slate-400 truncate max-w-[110px]" title="${App.escape(item.credit)}">${App.escape(item.credit)}</p>` : ''}
                </div>
            </div>
            <button type="button" onclick="replaceSpecificImage(${item.index})" class="flex-shrink-0 text-xs px-2.5 py-1 bg-slate-50 hover:bg-blue-600 hover:text-white text-slate-700 rounded font-medium border border-slate-200 transition">
                🔄 চেঞ্জ
            </button>
        </div>
    `).join('');

    updateSelectedCount();
}

// Automatically tag images in the post content with "ছবি ১", "ছবি ২" badges
function tagArticleImages() {
    const container = document.getElementById('post-content-container');
    if (!container) return;

    const figures = container.querySelectorAll('figure');
    if (figures.length > 0) {
        figures.forEach((fig, i) => {
            fig.style.position = 'relative';
            let badge = fig.querySelector('.article-img-badge');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'article-img-badge absolute top-2 left-2 z-10 px-2 py-0.5 bg-slate-900/80 text-white text-xs font-semibold rounded backdrop-blur-xs shadow-sm';
                fig.appendChild(badge);
            }
            badge.textContent = 'ছবি ' + (i + 1);
        });
    } else {
        const imgs = container.querySelectorAll('img');
        imgs.forEach((img, i) => {
            if (img.parentElement && !img.parentElement.querySelector('.article-img-badge')) {
                img.parentElement.style.position = 'relative';
                const badge = document.createElement('span');
                badge.className = 'article-img-badge absolute top-2 left-2 z-10 px-2 py-0.5 bg-slate-900/80 text-white text-xs font-semibold rounded backdrop-blur-xs shadow-sm';
                badge.textContent = 'ছবি ' + (i + 1);
                img.parentElement.appendChild(badge);
            }
        });
    }
}

// Initial tag and counter setup on page load
document.addEventListener('DOMContentLoaded', () => {
    updateSelectedCount();
    tagArticleImages();
});
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
