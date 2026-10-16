<?php
use App\Helpers\Url;
use App\Helpers\Sanitizer;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Not Found</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">
    <div class="text-center">
        <h1 class="text-6xl font-bold text-slate-300">404</h1>
        <p class="text-slate-600 mt-2 mb-6">Page not found</p>
        <a href="<?= Sanitizer::escape(Url::to('/dashboard')) ?>" class="px-5 py-2.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">Go to Dashboard</a>
        <p class="mt-4"><a href="<?= Sanitizer::escape(Url::to('/login')) ?>" class="text-sm text-blue-600 hover:underline">Login</a></p>
    </div>
</body>
</html>
