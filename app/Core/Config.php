<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Config
{
    private static array $config = [];

    public static function load(string $basePath): void
    {
        $file = $basePath . '/storage/config.php';
        if (!is_file($file)) {
            throw new RuntimeException('Not installed. Open /install.php first.');
        }
        self::$config = require $file;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = self::$config;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
