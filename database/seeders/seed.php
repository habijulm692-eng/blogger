<?php

declare(strict_types=1);

/**
 * Database Seeder
 * Usage: php database/seeders/seed.php
 */

define('BASE_PATH', dirname(__DIR__, 2));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

require BASE_PATH . '/app/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH);

echo "Seeding database...\n";

try {
    $db = \App\Helpers\Database::getInstance();

    // Create admin with proper password hash
    $password = 'Admin@12345';
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $db->prepare('SELECT id FROM admins WHERE email = ?');
    $stmt->execute(['admin@example.com']);

    if (!$stmt->fetch()) {
        $stmt = $db->prepare(
            'INSERT INTO admins (name, email, username, password, is_active) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute(['Administrator', 'admin@example.com', 'admin', $hash]);
        echo "Admin created: admin@example.com / Admin@12345\n";
    } else {
        // Update password
        $stmt = $db->prepare('UPDATE admins SET password = ? WHERE email = ?');
        $stmt->execute([$hash, 'admin@example.com']);
        echo "Admin password updated: admin@example.com / Admin@12345\n";
    }

    // AI Providers
    $providers = [
        ['gemini_primary', 'Google Gemini Flash 3.5', 1, 1, 'gemini-3.5-flash'],
        ['gemini_secondary', 'Google Gemini Flash 3', 1, 2, 'gemini-3-flash'],
        ['sambanova', 'SambaNova', 1, 3, 'Meta-Llama-3.3-70B-Instruct'],
        ['xkero', 'Xkero (Qwen)', 1, 4, 'Qwen/Qwen2.5-72B-Instruct'],
        ['apinex', 'Apinex', 1, 5, 'gpt-4o-mini'],
        ['groq', 'Groq', 1, 6, 'llama-3.1-8b-instant'],
    ];

    foreach ($providers as [$name, $display, $enabled, $priority, $model]) {
        $stmt = $db->prepare('SELECT id FROM ai_providers WHERE name = ?');
        $stmt->execute([$name]);
        if (!$stmt->fetch()) {
            $stmt = $db->prepare(
                'INSERT INTO ai_providers (name, display_name, is_enabled, priority, model, timeout, max_retries) VALUES (?, ?, ?, ?, ?, 60, 1)'
            );
            $stmt->execute([$name, $display, $enabled, $priority, $model]);
            echo "Provider added: {$display}\n";
        }
    }

    // System settings
    $settings = [
        ['site_name', 'AI Blogger', 'string', 'general'],
        ['timezone', 'Asia/Dhaka', 'string', 'general'],
        ['default_language', 'bn', 'string', 'general'],
        ['default_word_count', '1000', 'integer', 'general'],
        ['default_audience', 'General Bangladesh Readers', 'string', 'general'],
        ['default_tone', 'Professional + Easy', 'string', 'general'],
        ['ai_temperature', '0.7', 'string', 'ai'],
        ['ai_max_tokens', '4096', 'integer', 'ai'],
        ['ai_timeout', '60', 'integer', 'ai'],
        ['publish_frequency', 'daily', 'string', 'publishing'],
        ['default_schedule_time', '10:00', 'string', 'publishing'],
        ['publish_retry_limit', '3', 'integer', 'publishing'],
        ['auto_approval_mode', '0', 'boolean', 'publishing'],
        ['login_max_attempts', '5', 'integer', 'security'],
        ['login_lockout_minutes', '15', 'integer', 'security'],
        ['log_retention_days', '30', 'integer', 'maintenance'],
    ];

    foreach ($settings as [$key, $value, $type, $group]) {
        $stmt = $db->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, setting_type, group_name)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([$key, $value, $type, $group]);
    }
    echo "System settings seeded.\n";

    echo "\nSeeding complete!\n";
    echo "Login: admin@example.com / Admin@12345\n";
    echo "IMPORTANT: Change the default password after first login!\n";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
