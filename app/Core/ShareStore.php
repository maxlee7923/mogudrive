<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class ShareStore
{
    private const STORE_DIR_RELATIVE_PATH = '/storage/runtime/share-manifests';
    private const LEGACY_FILE_RELATIVE_PATH = '/storage/runtime/shares.json';

    /**
     * @param array{
     *   code:string,
     *   title:?string,
     *   password_hash:?string,
     *   expires_at:?string,
     *   allow_folder:int,
     *   items:array<int, array<string, mixed>>
     * } $payload
     * @return array{
     *   id:int,code:string,title:?string,password_hash:?string,expires_at:?string,allow_folder:int,created_at:string,
     *   file_ids:array<int,int>,items:array<int, array<string, mixed>>
     * }
     */
    public static function create(array $payload): array
    {
        self::ensureStoreBootstrapped();

        $items = [];
        foreach ((array)($payload['items'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = self::normalizeItem($row);
            if ($normalized === null) {
                continue;
            }
            $items[] = $normalized;
        }
        if (!$items) {
            throw new RuntimeException('Cannot create share without files.');
        }

        $createdAt = date('Y-m-d H:i:s');
        $id = self::nextId();
        $record = [
            'id' => $id,
            'code' => (string)$payload['code'],
            'title' => $payload['title'] ?? null,
            'password_hash' => $payload['password_hash'] ?? null,
            'expires_at' => $payload['expires_at'] ?? null,
            'allow_folder' => (int)($payload['allow_folder'] ?? 1),
            'created_at' => $createdAt,
            'file_ids' => array_values(array_unique(array_map(static fn (array $it): int => (int)$it['id'], $items))),
            'items' => $items,
        ];

        $groups = [];
        foreach ($items as $item) {
            $storageId = (int)$item['storage_id'];
            $groups[$storageId][] = $item;
        }

        foreach ($groups as $storageId => $storageItems) {
            self::mutateStorageFile((int)$storageId, static function (array $data) use ($record, $storageItems): array {
                $newRow = $record;
                $newRow['items'] = $storageItems;
                $data['shares'][] = $newRow;
                return [$data, true];
            });
        }

        return $record;
    }

    /**
     * @return array<int, array{
     *   id:int,code:string,title:?string,password_hash:?string,expires_at:?string,allow_folder:int,created_at:string,
     *   file_ids:array<int,int>,items:array<int, array<string, mixed>>
     * }>
     */
    public static function all(): array
    {
        self::ensureStoreBootstrapped();
        $all = [];

        foreach (self::storageFiles() as $storageId => $path) {
            $data = self::readStorageData($path, $storageId);
            foreach ((array)($data['shares'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $norm = self::normalizeStorageShareRecord($row, $storageId);
                if ($norm === null) {
                    continue;
                }

                $code = (string)$norm['code'];
                if ($code === '') {
                    continue;
                }
                if (!isset($all[$code])) {
                    $all[$code] = $norm;
                    continue;
                }

                $mergedItems = self::mergeItems((array)$all[$code]['items'], (array)$norm['items']);
                $all[$code]['items'] = $mergedItems;
                $all[$code]['file_ids'] = array_values(array_unique(array_map(static fn (array $it): int => (int)$it['id'], $mergedItems)));
            }
        }

        $rows = array_values($all);
        usort($rows, static fn (array $a, array $b): int => (int)$a['id'] <=> (int)$b['id']);
        return $rows;
    }

    /**
     * @return array{
     *   id:int,code:string,title:?string,password_hash:?string,expires_at:?string,allow_folder:int,created_at:string,
     *   file_ids:array<int,int>,items:array<int, array<string, mixed>>
     * }|null
     */
    public static function findByCode(string $code): ?array
    {
        $target = trim($code);
        if ($target === '') {
            return null;
        }
        foreach (self::all() as $row) {
            if ((string)$row['code'] === $target) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @return array{
     *   id:int,storage_id:int,driver:string,config_json:string,object_key:string,original_name:string,mime_type:string,
     *   size:int,md5:?string,folder_path:?string,created_at:string
     * }|null
     */
    public static function findItemByCodeAndFileId(string $code, int $fileId): ?array
    {
        $share = self::findByCode($code);
        if (!$share) {
            return null;
        }
        foreach ((array)($share['items'] ?? []) as $item) {
            if ((int)($item['id'] ?? 0) === $fileId) {
                return $item;
            }
        }
        return null;
    }

    /**
     * @return array<int, array{id:int,code:string,title:?string,expires_at:?string,created_at:string,item_count:int}>
     */
    public static function listSummary(int $limit = 200): array
    {
        $rows = self::all();
        usort($rows, static fn (array $a, array $b): int => (int)$b['id'] <=> (int)$a['id']);
        if ($limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int)$row['id'],
                'code' => (string)$row['code'],
                'title' => $row['title'] ?? null,
                'expires_at' => $row['expires_at'] ?? null,
                'created_at' => (string)$row['created_at'],
                'item_count' => count((array)($row['items'] ?? [])),
            ];
        }
        return $out;
    }

    public static function deleteById(int $id): bool
    {
        self::ensureStoreBootstrapped();
        $deleted = false;

        foreach (self::storageFiles() as $storageId => $path) {
            $changed = self::mutateStorageFile($storageId, static function (array $data) use ($id): array {
                $shares = is_array($data['shares'] ?? null) ? $data['shares'] : [];
                $kept = [];
                $found = false;
                foreach ($shares as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $norm = self::normalizeStorageShareRecord($row, (int)($data['storage_id'] ?? 0));
                    if ($norm === null) {
                        continue;
                    }
                    if ((int)$norm['id'] === $id) {
                        $found = true;
                        continue;
                    }
                    $kept[] = $norm;
                }
                $data['shares'] = $kept;
                return [$data, $found];
            });
            if ($changed) {
                $deleted = true;
            }
        }

        return $deleted;
    }

    public static function deleteByCode(string $code): bool
    {
        self::ensureStoreBootstrapped();
        $target = trim($code);
        if ($target === '') {
            return false;
        }

        $deleted = false;
        foreach (self::storageFiles() as $storageId => $path) {
            $changed = self::mutateStorageFile($storageId, static function (array $data) use ($target): array {
                $shares = is_array($data['shares'] ?? null) ? $data['shares'] : [];
                $kept = [];
                $found = false;
                foreach ($shares as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $norm = self::normalizeStorageShareRecord($row, (int)($data['storage_id'] ?? 0));
                    if ($norm === null) {
                        continue;
                    }
                    if ((string)$norm['code'] === $target) {
                        $found = true;
                        continue;
                    }
                    $kept[] = $norm;
                }
                $data['shares'] = $kept;
                return [$data, $found];
            });
            if ($changed) {
                $deleted = true;
            }
        }

        return $deleted;
    }

    private static function ensureStoreBootstrapped(): void
    {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }

        self::ensureStoreDir();

        if (!self::storageFiles()) {
            if (is_file(self::legacyFilePath())) {
                self::importLegacyStore();
            } else {
                self::bootstrapFromDatabase();
            }
        }

        $bootstrapped = true;
    }

    private static function ensureStoreDir(): void
    {
        $dir = self::storeDir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create share store directory.');
        }
    }

    private static function storeDir(): string
    {
        return dirname(__DIR__, 2) . self::STORE_DIR_RELATIVE_PATH;
    }

    private static function legacyFilePath(): string
    {
        return dirname(__DIR__, 2) . self::LEGACY_FILE_RELATIVE_PATH;
    }

    /**
     * @return array<int, string>
     */
    private static function storageFiles(): array
    {
        self::ensureStoreDir();
        $files = glob(self::storeDir() . '/storage-*.json');
        if (!is_array($files)) {
            return [];
        }

        $out = [];
        foreach ($files as $path) {
            if (!preg_match('/storage-(\d+)\.json$/', str_replace('\\', '/', $path), $m)) {
                continue;
            }
            $out[(int)$m[1]] = $path;
        }
        ksort($out);
        return $out;
    }

    private static function storageFilePath(int $storageId): string
    {
        return self::storeDir() . '/storage-' . $storageId . '.json';
    }

    /**
     * @return array{storage_id:int,shares:array<int, array<string, mixed>>}
     */
    private static function readStorageData(string $path, int $storageId): array
    {
        if (!is_file($path)) {
            return ['storage_id' => $storageId, 'shares' => []];
        }
        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return ['storage_id' => $storageId, 'shares' => []];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid share store format.');
        }
        return [
            'storage_id' => (int)($decoded['storage_id'] ?? $storageId),
            'shares' => is_array($decoded['shares'] ?? null) ? $decoded['shares'] : [],
        ];
    }

    /**
     * @template T
     * @param callable(array{storage_id:int,shares:array<int, array<string, mixed>>}): array{0:array{storage_id:int,shares:array<int, array<string, mixed>>},1:T} $fn
     * @return T
     */
    private static function mutateStorageFile(int $storageId, callable $fn): mixed
    {
        $path = self::storageFilePath($storageId);
        self::ensureStoreDir();
        $fh = @fopen($path, 'c+');
        if (!is_resource($fh)) {
            throw new RuntimeException('Cannot open share store file.');
        }

        try {
            if (!@flock($fh, LOCK_EX)) {
                throw new RuntimeException('Cannot lock share store file.');
            }

            $raw = stream_get_contents($fh);
            if (!is_string($raw) || trim($raw) === '') {
                $data = ['storage_id' => $storageId, 'shares' => []];
            } else {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    throw new RuntimeException('Invalid share store format.');
                }
                $data = [
                    'storage_id' => (int)($decoded['storage_id'] ?? $storageId),
                    'shares' => is_array($decoded['shares'] ?? null) ? $decoded['shares'] : [],
                ];
            }

            [$newData, $result] = $fn($data);
            self::writeToHandle($fh, $newData);
            @flock($fh, LOCK_UN);
            return $result;
        } finally {
            fclose($fh);
        }
    }

    /**
     * @param resource $fh
     * @param array{storage_id:int,shares:array<int, array<string, mixed>>} $data
     */
    private static function writeToHandle($fh, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            throw new RuntimeException('Failed to encode share store.');
        }
        rewind($fh);
        if (!@ftruncate($fh, 0)) {
            throw new RuntimeException('Failed to truncate share store.');
        }
        if (@fwrite($fh, $json . PHP_EOL) === false) {
            throw new RuntimeException('Failed to write share store.');
        }
        fflush($fh);
    }

    private static function nextId(): int
    {
        $max = 0;
        foreach (self::all() as $row) {
            $max = max($max, (int)$row['id']);
        }
        return $max + 1;
    }

    /**
     * @return array{
     *   id:int,code:string,title:?string,password_hash:?string,expires_at:?string,allow_folder:int,created_at:string,
     *   file_ids:array<int,int>,items:array<int, array<string, mixed>>
     * }|null
     */
    private static function normalizeStorageShareRecord(array $row, int $storageId): ?array
    {
        $id = (int)($row['id'] ?? 0);
        $code = trim((string)($row['code'] ?? ''));
        if ($id <= 0 || $code === '') {
            return null;
        }

        $items = [];
        foreach ((array)($row['items'] ?? []) as $itemRow) {
            if (!is_array($itemRow)) {
                continue;
            }
            $normalized = self::normalizeItem($itemRow, $storageId);
            if ($normalized === null) {
                continue;
            }
            $items[] = $normalized;
        }

        return [
            'id' => $id,
            'code' => $code,
            'title' => isset($row['title']) ? (string)$row['title'] : null,
            'password_hash' => isset($row['password_hash']) ? (string)$row['password_hash'] : null,
            'expires_at' => isset($row['expires_at']) ? (string)$row['expires_at'] : null,
            'allow_folder' => (int)($row['allow_folder'] ?? 1),
            'created_at' => (string)($row['created_at'] ?? date('Y-m-d H:i:s')),
            'file_ids' => array_values(array_unique(array_map(static fn (array $it): int => (int)$it['id'], $items))),
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
     *   id:int,storage_id:int,driver:string,config_json:string,object_key:string,original_name:string,mime_type:string,
     *   size:int,md5:?string,folder_path:?string,created_at:string
     * }|null
     */
    private static function normalizeItem(array $row, ?int $defaultStorageId = null): ?array
    {
        $id = (int)($row['id'] ?? 0);
        $storageId = (int)($row['storage_id'] ?? ($defaultStorageId ?? 0));
        $driver = trim((string)($row['driver'] ?? ''));
        $configJson = (string)($row['config_json'] ?? '');
        $objectKey = (string)($row['object_key'] ?? '');
        if ($id <= 0 || $storageId <= 0 || $driver === '' || $configJson === '' || $objectKey === '') {
            return null;
        }

        return [
            'id' => $id,
            'storage_id' => $storageId,
            'driver' => $driver,
            'config_json' => $configJson,
            'object_key' => $objectKey,
            'original_name' => (string)($row['original_name'] ?? ''),
            'mime_type' => (string)($row['mime_type'] ?? 'application/octet-stream'),
            'size' => (int)($row['size'] ?? 0),
            'md5' => isset($row['md5']) && $row['md5'] !== '' ? (string)$row['md5'] : null,
            'folder_path' => isset($row['folder_path']) && $row['folder_path'] !== '' ? (string)$row['folder_path'] : null,
            'created_at' => (string)($row['created_at'] ?? date('Y-m-d H:i:s')),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $a
     * @param array<int, array<string, mixed>> $b
     * @return array<int, array<string, mixed>>
     */
    private static function mergeItems(array $a, array $b): array
    {
        $seen = [];
        $out = [];
        foreach (array_merge($a, $b) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $norm = self::normalizeItem($row);
            if ($norm === null) {
                continue;
            }
            $key = (int)$norm['id'] . '|' . (int)$norm['storage_id'] . '|' . (string)$norm['object_key'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $norm;
        }
        return $out;
    }

    private static function importLegacyStore(): void
    {
        $raw = @file_get_contents(self::legacyFilePath());
        if ($raw === false || trim($raw) === '') {
            self::bootstrapFromDatabase();
            return;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            self::bootstrapFromDatabase();
            return;
        }

        $legacyShares = is_array($decoded['shares'] ?? null) ? $decoded['shares'] : [];
        if (!$legacyShares) {
            return;
        }

        $allIds = [];
        foreach ($legacyShares as $share) {
            if (!is_array($share)) {
                continue;
            }
            foreach ((array)($share['file_ids'] ?? []) as $fid) {
                $id = (int)$fid;
                if ($id > 0) {
                    $allIds[$id] = true;
                }
            }
        }
        $fileMap = self::loadFileSnapshotsByIds(array_keys($allIds));

        $grouped = [];
        foreach ($legacyShares as $share) {
            if (!is_array($share)) {
                continue;
            }
            $normId = (int)($share['id'] ?? 0);
            $normCode = trim((string)($share['code'] ?? ''));
            if ($normId <= 0 || $normCode === '') {
                continue;
            }

            $items = [];
            foreach ((array)($share['file_ids'] ?? []) as $fid) {
                $id = (int)$fid;
                if ($id > 0 && isset($fileMap[$id])) {
                    $items[] = $fileMap[$id];
                }
            }
            if (!$items) {
                continue;
            }

            $meta = [
                'id' => $normId,
                'code' => $normCode,
                'title' => isset($share['title']) ? (string)$share['title'] : null,
                'password_hash' => isset($share['password_hash']) ? (string)$share['password_hash'] : null,
                'expires_at' => isset($share['expires_at']) ? (string)$share['expires_at'] : null,
                'allow_folder' => (int)($share['allow_folder'] ?? 1),
                'created_at' => (string)($share['created_at'] ?? date('Y-m-d H:i:s')),
            ];

            foreach ($items as $item) {
                $sid = (int)$item['storage_id'];
                if (!isset($grouped[$sid][$normCode])) {
                    $grouped[$sid][$normCode] = $meta + ['items' => []];
                }
                $grouped[$sid][$normCode]['items'][] = $item;
            }
        }

        self::writeGroupedShares($grouped);
    }

    private static function bootstrapFromDatabase(): void
    {
        try {
            $pdo = Db::pdo();
            $shareRows = $pdo->query('SELECT id, code, title, password_hash, expires_at, allow_folder, created_at FROM shares ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
            if (!$shareRows) {
                return;
            }

            $itemRows = $pdo->query('SELECT share_id, file_id FROM share_items ORDER BY share_id ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
            $ids = [];
            $shareFileMap = [];
            foreach ($itemRows as $row) {
                $sid = (int)($row['share_id'] ?? 0);
                $fid = (int)($row['file_id'] ?? 0);
                if ($sid <= 0 || $fid <= 0) {
                    continue;
                }
                $shareFileMap[$sid][] = $fid;
                $ids[$fid] = true;
            }
            $fileMap = self::loadFileSnapshotsByIds(array_keys($ids));

            $grouped = [];
            foreach ($shareRows as $share) {
                $shareId = (int)$share['id'];
                $code = trim((string)$share['code']);
                if ($shareId <= 0 || $code === '') {
                    continue;
                }
                $meta = [
                    'id' => $shareId,
                    'code' => $code,
                    'title' => $share['title'] !== null ? (string)$share['title'] : null,
                    'password_hash' => $share['password_hash'] !== null ? (string)$share['password_hash'] : null,
                    'expires_at' => $share['expires_at'] !== null ? (string)$share['expires_at'] : null,
                    'allow_folder' => (int)($share['allow_folder'] ?? 1),
                    'created_at' => (string)$share['created_at'],
                ];
                foreach ((array)($shareFileMap[$shareId] ?? []) as $fid) {
                    $file = $fileMap[(int)$fid] ?? null;
                    if ($file === null) {
                        continue;
                    }
                    $storageId = (int)$file['storage_id'];
                    if (!isset($grouped[$storageId][$code])) {
                        $grouped[$storageId][$code] = $meta + ['items' => []];
                    }
                    $grouped[$storageId][$code]['items'][] = $file;
                }
            }

            self::writeGroupedShares($grouped);
        } catch (\Throwable $e) {
            return;
        }
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array{
     *   id:int,storage_id:int,driver:string,config_json:string,object_key:string,original_name:string,mime_type:string,
     *   size:int,md5:?string,folder_path:?string,created_at:string
     * }>
     */
    private static function loadFileSnapshotsByIds(array $ids): array
    {
        $out = [];
        $normIds = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if (!$normIds) {
            return $out;
        }

        $in = implode(',', array_fill(0, count($normIds), '?'));
        $sql = "SELECT f.id, f.storage_id, f.object_key, f.original_name, f.mime_type, f.size, f.md5, f.folder_path, f.created_at, sl.driver, sl.config_json
                FROM files f
                JOIN storage_locations sl ON sl.id = f.storage_id
                WHERE f.id IN ($in)";
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($normIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $norm = self::normalizeItem($row);
            if ($norm === null) {
                continue;
            }
            $out[(int)$norm['id']] = $norm;
        }
        return $out;
    }

    /**
     * @param array<int, array<int|string, array<string, mixed>>> $grouped
     */
    private static function writeGroupedShares(array $grouped): void
    {
        foreach ($grouped as $storageId => $sharesByCode) {
            $rows = [];
            foreach ($sharesByCode as $share) {
                if (!is_array($share)) {
                    continue;
                }
                $norm = self::normalizeStorageShareRecord($share, (int)$storageId);
                if ($norm === null || !$norm['items']) {
                    continue;
                }
                $rows[] = $norm;
            }
            usort($rows, static fn (array $a, array $b): int => (int)$a['id'] <=> (int)$b['id']);
            $path = self::storageFilePath((int)$storageId);
            $json = json_encode(['storage_id' => (int)$storageId, 'shares' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if (!is_string($json)) {
                throw new RuntimeException('Failed to encode share store.');
            }
            if (@file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Failed to write share store.');
            }
        }
    }
}
