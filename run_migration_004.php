<?php
/**
 * One-time migration runner for migration 004.
 * Upload to your server root and visit: https://your-domain/blogger/run_migration_004.php
 * DELETE this file after running it.
 */
declare(strict_types=1);

// Load env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v, '"\'');
        }
    }
}

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST'] ?? 'localhost',
        (int)($_ENV['DB_PORT'] ?? 3306),
        $_ENV['DB_DATABASE'] ?? ''
    );
    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'] ?? '', $_ENV['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    die('<b>DB Connection failed:</b> ' . htmlspecialchars($e->getMessage()));
}

$sqls = [
    // ① Add blueprint columns to source_websites (ignore if already exist)
    "ALTER TABLE source_websites ADD COLUMN blueprint TEXT NULL COMMENT 'User content strategy blueprint'",
    "ALTER TABLE source_websites ADD COLUMN blueprint_updated_at DATETIME NULL",

    // ② Create blueprint_ideas table
    "CREATE TABLE IF NOT EXISTS blueprint_ideas (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id        INT UNSIGNED NOT NULL,
        topic           VARCHAR(500)  NOT NULL,
        primary_keyword VARCHAR(200)  NULL,
        tone            VARCHAR(100)  NULL,
        tags            TEXT          NULL,
        angle           TEXT          NULL,
        category        VARCHAR(100)  NULL,
        language        VARCHAR(10)   NOT NULL DEFAULT 'bn',
        status          ENUM('pending','used','dismissed') NOT NULL DEFAULT 'pending',
        generated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_status (admin_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

echo "<pre>";
foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ OK: " . htmlspecialchars(substr($sql, 0, 80)) . "...\n";
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        // Column already exists = not an error
        if (str_contains($msg, 'Duplicate column name') || str_contains($msg, 'already exists')) {
            echo "⚠️  Already exists (skipped): " . htmlspecialchars(substr($sql, 0, 80)) . "\n";
        } else {
            echo "❌ ERROR: " . htmlspecialchars($msg) . "\n   SQL: " . htmlspecialchars(substr($sql, 0, 120)) . "\n";
        }
    }
}
echo "\n<b>Done. DELETE this file from your server now!</b>";
echo "</pre>";
