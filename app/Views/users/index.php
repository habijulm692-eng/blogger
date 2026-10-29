<?php
use App\Helpers\Url;
use App\Helpers\Csrf;
use App\Helpers\Sanitizer;
$u = static fn(string $p) => Url::to($p);
$pageTitle = $pageTitle ?? 'Users';
ob_start();
?>
<div class="space-y-6 max-w-3xl">
    <div class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="font-medium mb-3">Add User</h2>
        <form method="POST" action="<?= Sanitizer::escape($u('/users')) ?>" class="grid sm:grid-cols-2 gap-3">
            <?= Csrf::field() ?>
            <input name="name" required placeholder="Full name" class="border rounded-lg px-3 py-2 text-sm">
            <input name="email" type="email" required placeholder="Email" class="border rounded-lg px-3 py-2 text-sm">
            <input name="username" required placeholder="Username" class="border rounded-lg px-3 py-2 text-sm">
            <input name="password" type="password" required placeholder="Password (min 6)" class="border rounded-lg px-3 py-2 text-sm">
            <select name="role" class="border rounded-lg px-3 py-2 text-sm">
                <option value="editor">Editor</option>
                <option value="admin">Admin</option>
            </select>
            <button class="bg-blue-600 text-white rounded-lg text-sm py-2">Create User</button>
        </form>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500"><tr>
                <th class="px-4 py-2">Name</th><th>Username</th><th>Role</th><th>Status</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr class="border-t">
                    <td class="px-4 py-2"><?= Sanitizer::escape($user['name']) ?></td>
                    <td><?= Sanitizer::escape($user['username']) ?></td>
                    <td><?= Sanitizer::escape($user['role'] ?? 'admin') ?></td>
                    <td><?= !empty($user['is_active']) ? 'Active' : 'Off' ?></td>
                    <td class="px-4 py-2 text-right">
                        <form method="POST" action="<?= Sanitizer::escape($u('/users/' . (int)$user['id'] . '/toggle')) ?>" class="inline">
                            <?= Csrf::field() ?>
                            <button class="text-xs text-blue-600">Toggle</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
