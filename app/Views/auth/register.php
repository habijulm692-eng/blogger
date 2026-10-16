<?php
use App\Helpers\Sanitizer;
use App\Helpers\Url;
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — AI Blogger</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-600 text-white text-xl font-bold mb-3">AI</div>
            <h1 class="text-2xl font-bold text-slate-800">Create Account</h1>
            <p class="text-slate-500 text-sm mt-1">Register a new admin/editor account</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
            <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= Sanitizer::escape($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
            <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"><?= Sanitizer::escape($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= Sanitizer::escape(Url::to('/register')) ?>" class="space-y-4">
                <input type="hidden" name="_csrf_token" value="<?= Sanitizer::escape($csrf ?? '') ?>">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Full name</label>
                    <input type="text" name="name" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Your name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" name="email" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="you@example.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                    <input type="text" name="username" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="username">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <input type="password" name="password" required minlength="6" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Confirm password</label>
                    <input type="password" name="password_confirmation" required minlength="6" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm">
                </div>
                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-sm">Register</button>
            </form>
        </div>
        <p class="text-center text-sm text-slate-600 mt-4">
            Already have an account?
            <a href="<?= Sanitizer::escape(Url::to('/login')) ?>" class="text-blue-600 hover:underline font-medium">Login</a>
        </p>
    </div>
</body>
</html>
