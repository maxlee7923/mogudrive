<?php

declare(strict_types=1);

namespace App\Core;

final class Settings
{
    private static bool $loaded = false;

    /**
     * @var array<string, string|null>
     */
    private static array $values = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();

        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        if ($key === 'site_name') {
            return Config::get('app.name', $default);
        }

        return $default;
    }

    public static function siteName(): string
    {
        $name = trim((string)self::get('site_name', Config::get('app.name', '蘑菇网盘')));
        return $name !== '' ? $name : '蘑菇网盘';
    }

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        try {
            $rows = Db::pdo()->query('SELECT `key`, `value` FROM settings')->fetchAll();
            foreach ($rows as $row) {
                $key = (string)($row['key'] ?? '');
                if ($key === '') {
                    continue;
                }
                self::$values[$key] = isset($row['value']) ? (string)$row['value'] : null;
            }
        } catch (\Throwable $e) {
            self::$values = [];
        }
    }
}
