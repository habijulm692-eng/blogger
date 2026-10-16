<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Database;
use App\Helpers\Logger;

class AiProviderConfig
{
    /** All known providers with their defaults */
    public const DEFAULTS = [
        'gemini_primary'   => ['display_name' => 'Google Gemini Flash 3.5', 'priority' => 1],
        'gemini_secondary' => ['display_name' => 'Google Gemini Flash 3',   'priority' => 2],
        'sambanova'        => ['display_name' => 'SambaNova',               'priority' => 3],
        'xkero'            => ['display_name' => 'Xkero (Qwen)',            'priority' => 4],
        'apinex'           => ['display_name' => 'Apinex',                  'priority' => 5],
        'groq'             => ['display_name' => 'Groq',                    'priority' => 6],
    ];

    private static function cacheFilePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/ai_providers.json';
    }

    private static function saveToCache(array $rows): void
    {
        try {
            $path = self::cacheFilePath();
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            // ignore cache write error
        }
    }

    private static function ensureAllProvidersPresent(array $rows): array
    {
        $names = [];
        foreach ($rows as &$r) {
            if ($r['name'] === 'gemini') {
                $r['name'] = 'gemini_primary';
                $r['display_name'] = self::DEFAULTS['gemini_primary']['display_name'];
            }
            $names[$r['name']] = true;
        }
        unset($r);

        $nextId = count($rows) + 1;
        foreach (self::DEFAULTS as $dName => $def) {
            if (!isset($names[$dName])) {
                $rows[] = [
                    'id' => $nextId++,
                    'name' => $dName,
                    'display_name' => $def['display_name'],
                    'is_enabled' => 1,
                    'priority' => $def['priority'],
                ];
            }
        }
        return $rows;
    }

    private static function readFromCache(): array
    {
        $path = self::cacheFilePath();
        if (is_file($path)) {
            $content = @file_get_contents($path);
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded) && !empty($decoded)) {
                    return self::ensureAllProvidersPresent($decoded);
                }
            }
        }

        // Fallback to in-memory defaults
        $rows = [];
        $id = 1;
        foreach (self::DEFAULTS as $name => $def) {
            $rows[] = [
                'id' => $id++,
                'name' => $name,
                'display_name' => $def['display_name'],
                'is_enabled' => 1,
                'priority' => $def['priority'],
            ];
        }
        return $rows;
    }

    /**
     * Get all provider configs, seeding defaults if missing.
     * Returns array of rows ordered by priority ASC.
     */
    public static function getAll(): array
    {
        try {
            $db = Database::getInstance();

            // Auto-migrate legacy 'gemini' to 'gemini_primary' if exists
            try {
                $db->exec("UPDATE ai_providers SET name = 'gemini_primary', display_name = 'Google Gemini Flash 3.5' WHERE name = 'gemini'");
            } catch (\Throwable $e) {
                // Ignore
            }

            // Clean up deprecated / legacy providers
            $allowedNames = "'" . implode("', '", array_keys(self::DEFAULTS)) . "'";
            try {
                $db->exec("DELETE FROM ai_providers WHERE name NOT IN ($allowedNames)");
            } catch (\Throwable $e) {
                // Ignore if delete fails
            }

            // Seed any missing providers
            foreach (self::DEFAULTS as $name => $def) {
                $stmt = $db->prepare('SELECT id FROM ai_providers WHERE name = ? LIMIT 1');
                $stmt->execute([$name]);
                if (!$stmt->fetch()) {
                    $db->prepare(
                        'INSERT INTO ai_providers (name, display_name, is_enabled, priority) VALUES (?, ?, 1, ?)'
                    )->execute([$name, $def['display_name'], $def['priority']]);
                }
            }

            $stmt = $db->query('SELECT * FROM ai_providers ORDER BY priority ASC, id ASC');
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                self::saveToCache($rows);
                return $rows;
            }
        } catch (\Throwable $e) {
            Logger::warning('AiProviderConfig::getAll DB error, falling back to cache', ['error' => $e->getMessage()]);
        }

        return self::readFromCache();
    }

    /**
     * Get all providers enriched with live configuration status from app config.
     */
    public static function getAllWithConfig(): array
    {
        $rows = self::getAll();
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $providerConfigs = $config['ai']['providers'] ?? [];

        $priorityLabels = [
            1 => '১ম (Primary)',
            2 => '২য় (Secondary)',
            3 => '৩য় (Tertiary)',
            4 => '৪র্থ (4th)',
            5 => '৫ম (5th)',
            6 => '৬ষ্ঠ (6th)',
            7 => '৭ম (7th)',
            8 => '৮ম (8th)',
            9 => '৯ম (9th)',
            10 => '১০ম (10th)',
        ];

        foreach ($rows as &$r) {
            $name = $r['name'];
            $cfg = $providerConfigs[$name] ?? [];
            $apiKey = trim((string)($cfg['api_key'] ?? ''));

            $r['configured_model'] = (string)($cfg['model'] ?? ($r['model'] ?? ''));
            $r['is_configured'] = ($apiKey !== '');
            $r['priority_label'] = $priorityLabels[(int)$r['priority']] ?? ((int)$r['priority'] . 'th');
            $r['is_enabled'] = (int)($r['is_enabled'] ?? 1);
        }
        unset($r);

        // Sort by priority ASC
        usort($rows, static fn($a, $b) => ((int)$a['priority']) <=> ((int)$b['priority']));

        return $rows;
    }

    public static function findByName(string $name): ?array
    {
        $all = self::getAll();
        foreach ($all as $row) {
            if ($row['name'] === $name) {
                return $row;
            }
        }
        if ($name === 'gemini') {
            foreach ($all as $row) {
                if ($row['name'] === 'gemini_primary') {
                    return $row;
                }
            }
        }
        return null;
    }

    public static function setEnabled(string $name, bool $enabled): void
    {
        try {
            $db = Database::getInstance();
            $db->prepare('UPDATE ai_providers SET is_enabled = ?, updated_at = NOW() WHERE name = ?')
               ->execute([$enabled ? 1 : 0, $name]);
        } catch (\Throwable $e) {
            Logger::warning('AiProviderConfig::setEnabled DB error', ['error' => $e->getMessage()]);
        }

        // Update cache file
        $cached = self::readFromCache();
        foreach ($cached as &$row) {
            if ($row['name'] === $name) {
                $row['is_enabled'] = $enabled ? 1 : 0;
            }
        }
        unset($row);
        self::saveToCache($cached);
    }

    public static function setPriority(string $name, int $priority): void
    {
        self::updatePriorities([$name => $priority]);
    }

    /**
     * Bulk update priorities from [name => priority] map.
     */
    public static function updatePriorities(array $priorities): void
    {
        try {
            $db = Database::getInstance();
            foreach ($priorities as $name => $priority) {
                $db->prepare('UPDATE ai_providers SET priority = ?, updated_at = NOW() WHERE name = ?')
                   ->execute([(int)$priority, (string)$name]);
            }
        } catch (\Throwable $e) {
            Logger::warning('AiProviderConfig::updatePriorities DB error', ['error' => $e->getMessage()]);
        }

        // Update cache file
        $cached = self::readFromCache();
        foreach ($cached as &$row) {
            if (isset($priorities[$row['name']])) {
                $row['priority'] = (int)$priorities[$row['name']];
            }
        }
        unset($row);
        usort($cached, static fn($a, $b) => ((int)$a['priority']) <=> ((int)$b['priority']));
        self::saveToCache($cached);
    }

    /**
     * Get enabled provider names in priority order.
     */
    public static function getEnabledOrder(): array
    {
        $allowed = array_keys(self::DEFAULTS);
        $allowedNames = "'" . implode("', '", $allowed) . "'";

        try {
            $db = Database::getInstance();
            $stmt = $db->query(
                "SELECT name FROM ai_providers WHERE is_enabled = 1 AND name IN ($allowedNames) ORDER BY priority ASC, id ASC"
            );
            $names = array_column($stmt->fetchAll(), 'name');
            if (!empty($names)) {
                return $names;
            }
        } catch (\Throwable $e) {
            Logger::warning('AiProviderConfig::getEnabledOrder DB error', ['error' => $e->getMessage()]);
        }

        // Fallback to cache file
        $cached = self::readFromCache();
        $enabled = [];
        foreach ($cached as $row) {
            if (!empty($row['is_enabled']) && in_array($row['name'], $allowed, true)) {
                $enabled[] = $row['name'];
            }
        }
        return !empty($enabled) ? $enabled : $allowed;
    }
}
