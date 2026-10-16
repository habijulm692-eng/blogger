<?php
use App\Helpers\Url;
use App\Helpers\Csrf;
use App\Helpers\Sanitizer;
$u = static fn(string $p) => Url::to($p);
$pageTitle = $pageTitle ?? 'AI Chat';
ob_start();
?>
<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-slate-500">Content strategy, SEO, scheduling — ask anything about your blog.</p>
        <form method="POST" action="<?= Sanitizer::escape($u('/chat/clear')) ?>"><?= Csrf::field() ?>
            <button class="text-xs text-red-600 border rounded px-2 py-1">Clear chat</button>
        </form>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl flex flex-col h-[70vh]">
        <div id="chat-box" class="flex-1 overflow-y-auto p-4 space-y-3">
            <?php if (empty($messages)): ?>
                <p class="text-slate-400 text-sm text-center py-8">Start a conversation…</p>
            <?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <?php $isUser = ($m['role'] ?? '') === 'user'; ?>
                <div class="flex <?= $isUser ? 'justify-end' : 'justify-start' ?>">
                    <div class="max-w-[85%] rounded-2xl px-3 py-2 text-sm <?= $isUser ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-800' ?>">
                        <?= nl2br(Sanitizer::escape($m['message'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="POST" action="<?= Sanitizer::escape($u('/chat')) ?>" class="border-t p-3 flex gap-2">
            <?= Csrf::field() ?>
            <input name="message" required autocomplete="off" placeholder="Type your message…" class="flex-1 border rounded-lg px-3 py-2 text-sm">
            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">Send</button>
        </form>
    </div>
</div>
<script>const b=document.getElementById('chat-box');if(b)b.scrollTop=b.scrollHeight;</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
