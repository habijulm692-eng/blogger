<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'AI Blogger',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
    'key' => $_ENV['APP_KEY'] ?? '',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Dhaka',

    'session' => [
        'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
        'secure' => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'httponly' => true,
        'samesite' => 'Lax',
        'name' => 'ai_blogger_session',
    ],

    'security' => [
        'login_max_attempts' => (int) ($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5),
        'login_lockout_minutes' => (int) ($_ENV['LOGIN_LOCKOUT_MINUTES'] ?? 15),
        'csrf_token_lifetime' => (int) ($_ENV['CSRF_TOKEN_LIFETIME'] ?? 3600),
    ],

    'ai' => [
        'temperature' => (float) ($_ENV['AI_TEMPERATURE'] ?? 0.7),
        'max_tokens' => (int) ($_ENV['AI_MAX_TOKENS'] ?? 4096),
        'default_language' => $_ENV['AI_DEFAULT_LANGUAGE'] ?? 'bn',
        'default_word_count' => (int) ($_ENV['AI_DEFAULT_WORD_COUNT'] ?? 1000),
        'providers' => [
            'gemini_primary' => [
                'name' => 'gemini_primary',
                'api_key' => $_ENV['GEMINI_API_KEY'] ?? '',
                'model' => $_ENV['GEMINI_MODEL_PRIMARY'] ?? $_ENV['GEMINI_MODEL'] ?? 'gemini-3.5-flash',
                'timeout' => (int) ($_ENV['GEMINI_TIMEOUT'] ?? 180),
            ],
            'gemini_secondary' => [
                'name' => 'gemini_secondary',
                'api_key' => $_ENV['GEMINI_API_KEY'] ?? '',
                'model' => $_ENV['GEMINI_MODEL_SECONDARY'] ?? 'gemini-3-flash',
                'timeout' => (int) ($_ENV['GEMINI_TIMEOUT'] ?? 180),
            ],
            'gemini' => [
                'name' => 'gemini_primary',
                'api_key' => $_ENV['GEMINI_API_KEY'] ?? '',
                'model' => $_ENV['GEMINI_MODEL_PRIMARY'] ?? $_ENV['GEMINI_MODEL'] ?? 'gemini-3.5-flash',
                'timeout' => (int) ($_ENV['GEMINI_TIMEOUT'] ?? 180),
            ],
            'sambanova' => [
                'api_key' => $_ENV['SAMBANOVA_API_KEY'] ?? '',
                'model' => $_ENV['SAMBANOVA_MODEL'] ?? 'Meta-Llama-3.3-70B-Instruct',
                'base_url' => $_ENV['SAMBANOVA_BASE_URL'] ?? 'https://api.sambanova.ai/v1',
                'timeout' => (int) ($_ENV['SAMBANOVA_TIMEOUT'] ?? 120),
            ],
            'xkero' => [
                'api_key' => $_ENV['XKERO_API_KEY'] ?? '',
                'model' => $_ENV['XKERO_MODEL'] ?? 'Qwen/Qwen2.5-72B-Instruct',
                'base_url' => $_ENV['XKERO_BASE_URL'] ?? 'https://api.xkiro.com/v1',
                'timeout' => (int) ($_ENV['XKERO_TIMEOUT'] ?? 120),
            ],
            'apinex' => [
                'api_key' => $_ENV['APINEX_API_KEY'] ?? '',
                'model' => $_ENV['APINEX_MODEL'] ?? 'gpt-4o-mini',
                'base_url' => $_ENV['APINEX_BASE_URL'] ?? 'https://api.apinex.com/v1',
                'timeout' => (int) ($_ENV['APINEX_TIMEOUT'] ?? 120),
            ],
            'groq' => [
                'api_key' => $_ENV['GROQ_API_KEY'] ?? '',
                'model' => $_ENV['GROQ_MODEL'] ?? 'llama-3.1-8b-instant',
                'timeout' => (int) ($_ENV['GROQ_TIMEOUT'] ?? 90),
            ],
        ],
    ],

    'google' => [
        'client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
        'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
        'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI'] ?? '',
        'blogger_blog_id' => $_ENV['BLOGGER_BLOG_ID'] ?? '',
    ],

    'publishing' => [
        'retry_limit' => (int) ($_ENV['PUBLISH_RETRY_LIMIT'] ?? 3),
        'frequency' => $_ENV['PUBLISH_FREQUENCY'] ?? 'daily',
        'default_schedule_time' => $_ENV['DEFAULT_SCHEDULE_TIME'] ?? '10:00',
    ],

    'logging' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'info',
        'retention_days' => (int) ($_ENV['LOG_RETENTION_DAYS'] ?? 30),
        'path' => dirname(__DIR__) . '/storage/logs',
    ],
];
