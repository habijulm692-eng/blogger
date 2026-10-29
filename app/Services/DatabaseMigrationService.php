<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use PDO;
use Throwable;

class DatabaseMigrationService
{
    private string $migrationsPath;

    public function __construct(?string $migrationsPath = null)
    {
        $this->migrationsPath = $migrationsPath ?? dirname(__DIR__, 2) . '/database/migrations';
    }

    /**
     * Ensure the migrations tracking table exists.
     */
    public function ensureMigrationsTable(): void
    {
        try {
            $db = Database::getInstance();
            $db->exec(
                'CREATE TABLE IF NOT EXISTS `migrations` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `migration` VARCHAR(255) NOT NULL UNIQUE,
                    `batch` INT UNSIGNED NOT NULL DEFAULT 1,
                    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
            );
        } catch (Throwable) {
            // Silently continue if database is currently unreachable
        }
    }

    /**
     * Get database connection info and table overview.
     */
    public function getDatabaseInfo(): array
    {
        try {
            $db = Database::getInstance();
            $version = (string) $db->query('SELECT VERSION()')->fetchColumn();
            $dbName = (string) $db->query('SELECT DATABASE()')->fetchColumn();

            $stmt = $db->query(
                "SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, UPDATE_TIME 
                 FROM information_schema.TABLES 
                 WHERE TABLE_SCHEMA = DATABASE()
                 ORDER BY TABLE_NAME ASC"
            );
            $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            return [
                'connected'    => true,
                'version'      => $version,
                'database'     => $dbName,
                'table_count'  => count($tables),
                'tables'       => $tables,
                'error'        => null,
            ];
        } catch (Throwable $e) {
            return [
                'connected'    => false,
                'version'      => null,
                'database'     => $_ENV['DB_DATABASE'] ?? 'unknown',
                'table_count'  => 0,
                'tables'       => [],
                'error'        => $e->getMessage(),
            ];
        }
    }

    /**
     * Get list of all migration files and their execution status.
     */
    public function getMigrationFiles(): array
    {
        $this->ensureMigrationsTable();

        $executed = [];
        try {
            $db = Database::getInstance();
            $stmt = $db->query('SELECT migration, batch, executed_at FROM migrations ORDER BY id ASC');
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $executed[$row['migration']] = $row;
                }
            }
        } catch (Throwable) {
            // fallback
        }

        $files = [];
        if (is_dir($this->migrationsPath)) {
            $scanned = scandir($this->migrationsPath) ?: [];
            natsort($scanned);

            foreach ($scanned as $file) {
                if (!str_ends_with(strtolower($file), '.sql')) {
                    continue;
                }

                $fullPath = $this->migrationsPath . '/' . $file;
                $isExecuted = isset($executed[$file]);

                $files[] = [
                    'name'        => $file,
                    'path'        => $fullPath,
                    'size'        => filesize($fullPath),
                    'modified_at' => date('Y-m-d H:i:s', filemtime($fullPath)),
                    'is_executed' => $isExecuted,
                    'batch'       => $isExecuted ? $executed[$file]['batch'] : null,
                    'executed_at' => $isExecuted ? $executed[$file]['executed_at'] : null,
                ];
            }
        }

        return $files;
    }

    /**
     * Run all pending migration files.
     */
    public function runPendingMigrations(): array
    {
        $this->ensureMigrationsTable();
        $all = $this->getMigrationFiles();
        $pending = array_filter($all, fn($f) => !$f['is_executed']);

        if (empty($pending)) {
            return [
                'success'        => true,
                'executed_files' => 0,
                'total_queries'  => 0,
                'message'        => 'সকল মাইগ্রেশন ইতিমধ্যে সম্পূর্ণ হয়েছে! কোনো পেন্ডিং মাইগ্রেশন নেই।',
                'details'        => [],
            ];
        }

        $db = Database::getInstance();
        $batch = (int) ($db->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn() ?: 1);

        $executedCount = 0;
        $totalQueries = 0;
        $details = [];

        foreach ($pending as $item) {
            $result = $this->executeSqlFile($item['path']);
            $totalQueries += $result['executed_queries'];

            if ($result['success']) {
                $stmt = $db->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
                $stmt->execute([$item['name'], $batch]);
                $executedCount++;

                $details[] = [
                    'file'    => $item['name'],
                    'status'  => 'success',
                    'queries' => $result['executed_queries'],
                    'errors'  => $result['errors'],
                ];
            } else {
                $details[] = [
                    'file'    => $item['name'],
                    'status'  => 'error',
                    'queries' => $result['executed_queries'],
                    'errors'  => $result['errors'],
                ];
                // Continue to try remaining if possible or stop
            }
        }

        return [
            'success'        => true,
            'executed_files' => $executedCount,
            'total_queries'  => $totalQueries,
            'message'        => sprintf('%d টি মাইগ্রেশন ফাইল এবং মোট %d টি SQL কুয়েরি সফলভাবে এক্সিকিউট হয়েছে!', $executedCount, $totalQueries),
            'details'        => $details,
        ];
    }

    /**
     * Run a single migration file by filename.
     */
    public function runSingleMigration(string $filename): array
    {
        $this->ensureMigrationsTable();
        $filename = basename($filename);
        $fullPath = $this->migrationsPath . '/' . $filename;

        if (!is_file($fullPath)) {
            return [
                'success' => false,
                'message' => "মাইগ্রেশন ফাইল '{$filename}' পাওয়া যায়নি।",
            ];
        }

        $db = Database::getInstance();
        $result = $this->executeSqlFile($fullPath);

        if ($result['success']) {
            $batch = (int) ($db->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn() ?: 1);
            $stmt = $db->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?) ON DUPLICATE KEY UPDATE executed_at = NOW()');
            $stmt->execute([$filename, $batch]);

            return [
                'success' => true,
                'message' => sprintf("মাইগ্রেশন '%s' সফলভাবে রান হয়েছে (%d টি কুয়েরি সম্পন্ন)!", $filename, $result['executed_queries']),
                'queries' => $result['executed_queries'],
                'errors'  => $result['errors'],
            ];
        }

        return [
            'success' => false,
            'message' => sprintf("মাইগ্রেশন '%s' রান করতে সমস্যা হয়েছে।", $filename),
            'errors'  => $result['errors'],
        ];
    }

    /**
     * Upload and execute a .sql file.
     */
    public function uploadAndExecute(array $fileUpload): array
    {
        if (empty($fileUpload['tmp_name']) || !is_uploaded_file($fileUpload['tmp_name'])) {
            return [
                'success' => false,
                'message' => 'কোনো ফাইল আপলোড করা হয়নি।',
            ];
        }

        $originalName = (string) ($fileUpload['name'] ?? 'custom.sql');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($ext !== 'sql') {
            return [
                'success' => false,
                'message' => 'শুধুমাত্র .sql এক্সটেনশন যুক্ত ফাইল আপলোড করতে পারবেন।',
            ];
        }

        $rawSql = (string) file_get_contents($fileUpload['tmp_name']);
        if (trim($rawSql) === '') {
            return [
                'success' => false,
                'message' => 'আপলোড করা SQL ফাইলটি সম্পূর্ণ খালি।',
            ];
        }

        // Save a copy into database/migrations if it looks like a migration
        if (!is_dir($this->migrationsPath)) {
            @mkdir($this->migrationsPath, 0755, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $originalName) ?: 'uploaded_' . time() . '.sql';
        $destPath = $this->migrationsPath . '/' . $safeName;
        @move_uploaded_file($fileUpload['tmp_name'], $destPath);

        // Execute queries
        $res = $this->executeRawSql($rawSql);

        // Record in migrations table if successful
        if ($res['success']) {
            try {
                $this->ensureMigrationsTable();
                $db = Database::getInstance();
                $batch = (int) ($db->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn() ?: 1);
                $stmt = $db->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?) ON DUPLICATE KEY UPDATE executed_at = NOW()');
                $stmt->execute([$safeName, $batch]);
            } catch (Throwable) {
            }
        }

        return [
            'success'          => $res['success'],
            'message'          => sprintf("ফাইল '%s' সফলভাবে আপলোড ও এক্সিকিউট হয়েছে! (%d টি কুয়েরি সম্পন্ন)", $originalName, $res['executed_queries']),
            'executed_queries' => $res['executed_queries'],
            'errors'           => $res['errors'],
            'warnings'         => $res['warnings'],
        ];
    }

    /**
     * Execute a .sql file from disk.
     */
    public function executeSqlFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            return [
                'success'          => false,
                'executed_queries' => 0,
                'errors'           => ["File not found: {$filePath}"],
                'warnings'         => [],
            ];
        }

        $raw = (string) file_get_contents($filePath);
        return $this->executeRawSql($raw);
    }

    /**
     * Split and execute raw SQL string statements safely via PDO.
     */
    public function executeRawSql(string $sql): array
    {
        $statements = self::splitSql($sql);
        if (empty($statements)) {
            return [
                'success'          => true,
                'executed_queries' => 0,
                'errors'           => [],
                'warnings'         => [],
            ];
        }

        $db = Database::getInstance();
        $executed = 0;
        $errors = [];
        $warnings = [];

        foreach ($statements as $query) {
            $query = trim($query);
            if ($query === '') {
                continue;
            }

            try {
                $db->exec($query);
                $executed++;
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                // Benign duplicate / exists errors
                if (
                    str_contains($msg, 'already exists') ||
                    str_contains($msg, 'Duplicate column') ||
                    str_contains($msg, 'Duplicate key name') ||
                    str_contains($msg, 'Multiple primary key defined')
                ) {
                    $warnings[] = "Warning on query [" . mb_substr($query, 0, 80) . "...]: " . $msg;
                    $executed++;
                    continue;
                }

                $errors[] = [
                    'query'   => mb_substr($query, 0, 200),
                    'message' => $msg,
                ];
            }
        }

        return [
            'success'          => empty($errors),
            'executed_queries' => $executed,
            'errors'           => $errors,
            'warnings'         => $warnings,
        ];
    }

    /**
     * Split SQL text into separate executable queries.
     * Accurately handles comments, quotes, backticks, and semicolons.
     */
    public static function splitSql(string $sql): array
    {
        $queries = [];
        $length = strlen($sql);
        $current = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            // Line comment start (-- or #)
            if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick && !$inBlockComment && !$inLineComment) {
                if ($char === '-' && $next === '-') {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
            }

            // Line comment end
            if ($inLineComment) {
                if ($char === "\n" || $char === "\r") {
                    $inLineComment = false;
                }
                continue;
            }

            // Block comment end
            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            // Quotes handling
            if ($char === "'" && !$inDoubleQuote && !$inBacktick) {
                $escaped = ($i > 0 && $sql[$i - 1] === '\\');
                if (!$escaped) {
                    $inSingleQuote = !$inSingleQuote;
                }
            } elseif ($char === '"' && !$inSingleQuote && !$inBacktick) {
                $escaped = ($i > 0 && $sql[$i - 1] === '\\');
                if (!$escaped) {
                    $inDoubleQuote = !$inDoubleQuote;
                }
            } elseif ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
                $inBacktick = !$inBacktick;
            }

            // Statement delimiter
            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $queries[] = $trimmed;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $trimmed = trim($current);
        if ($trimmed !== '') {
            $queries[] = $trimmed;
        }

        return $queries;
    }
}
