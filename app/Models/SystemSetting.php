<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Database;

class SystemSetting
{
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            return $default;
        }

        $value = self::castValue($row['setting_value'], $row['setting_type']);
        self::$cache[$key] = $value;
        return $value;
    }

    public static function set(string $key, mixed $value, string $type = 'string', string $group = 'general'): void
    {
        $db = Database::getInstance();

        if ($type === 'json' && is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } elseif ($type === 'boolean') {
            $value = $value ? '1' : '0';
        } else {
            $value = (string) $value;
        }

        $stmt = $db->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, setting_type, group_name)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_at = NOW()'
        );
        $stmt->execute([$key, $value, $type, $group]);

        unset(self::$cache[$key]);
    }

    public static function getByGroup(string $group): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM system_settings WHERE group_name = ? ORDER BY setting_key');
        $stmt->execute([$group]);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = self::castValue($row['setting_value'], $row['setting_type']);
        }
        return $result;
    }

    public static function getAll(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query('SELECT * FROM system_settings ORDER BY group_name, setting_key');
        return $stmt->fetchAll();
    }

    private static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => $value === '1' || $value === 'true',
            'json' => json_decode($value, true) ?? [],
            default => $value,
        };
    }
}
