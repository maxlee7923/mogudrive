<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Config::get('db.host'),
            Config::get('db.port', '3306'),
            Config::get('db.name')
        );
        self::$pdo = new PDO($dsn, Config::get('db.user'), Config::get('db.pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return self::$pdo;
    }
}
