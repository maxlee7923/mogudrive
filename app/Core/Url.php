<?php

declare(strict_types=1);

namespace App\Core;

final class Url
{
    public static function scriptDir(): string
    {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        if ($dir === '/' || $dir === '.') {
            return '';
        }
        return rtrim($dir, '/');
    }

    public static function entry(): string
    {
        return self::scriptDir() . '/index.php';
    }

    public static function route(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return self::entry() . '?r=' . rawurlencode($path);
    }

    public static function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $direct = $docRoot . '/' . $path;
        if ($docRoot !== '' && is_file($direct)) {
            return '/' . $path;
        }
        $publicPrefixed = $docRoot . '/public/' . $path;
        if ($docRoot !== '' && is_file($publicPrefixed)) {
            return '/public/' . $path;
        }
        $dir = self::scriptDir();
        return ($dir ? $dir : '') . '/' . $path;
    }
}
