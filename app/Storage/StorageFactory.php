<?php

declare(strict_types=1);

namespace App\Storage;

use InvalidArgumentException;

final class StorageFactory
{
    public static function make(array $row): StorageAdapter
    {
        $driver = $row['driver'];
        $cfg = json_decode((string)$row['config_json'], true) ?: [];
        if ($driver === 'local') {
            return new LocalStorageAdapter((string)$cfg['base_path']);
        }
        if ($driver === 's3') {
            return new S3StorageAdapter($cfg);
        }
        throw new InvalidArgumentException('Unknown storage driver: ' . $driver);
    }
}
