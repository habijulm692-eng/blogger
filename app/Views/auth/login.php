<?php
use App\Helpers\Sanitizer;
use App\Helpers\Url;
$base = Url::base();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — AI Blogger</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-600 text-white text-xl font-bold mb-3">AI</div>
            <h1 class="text-2xl font-bold text-slate-800">AI Blogger</h1>
            <p class="text-slate-500 text-sm mt-1">Sign in to your admin account</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
            <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                <?= Sanitizer::escape($error) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
            <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                <?= Sanitizer::escape($success) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= Sanitizer::escape(Url::to('/login')) ?>" class="space-y-5">
                <input type="hidden" name="_csrf_token" value="<?= Sanitizer::escape($csrf) ?>">

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email or Username</label>
                    <input type="text" id="email" name="email" required autofocus
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="admin@example.com">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="••••••••">
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" value="1"
                           class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                    <label for="remember" class="ml-2 text-sm text-slate-600">Remember me</label>
                </div>

                <button type="submit"
                        class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-sm transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-sm text-slate-600 mt-4">
            No account?
            <a href="<?= Sanitizer::escape(Url::to('/register')) ?>" class="text-blue-600 hover:underline font-medium">Register</a>
        </p>
        <p class="text-center text-xs text-slate-400 mt-4">AI Blog Generator &amp; Blogger Auto Publisher</p>
    </div>
</body>
</html>
