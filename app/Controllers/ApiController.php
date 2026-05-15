<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Response;
use App\Core\ShareStore;
use App\Core\Url;
use App\Storage\MergeInProgressException;
use App\Storage\StorageAdapter;
use App\Storage\StorageFactory;
use PDO;

final class ApiController
{
    private const SIGN_TTL_SECONDS = 7200;
    private const CHUNK_SIZE_BYTES = 30 * 1024 * 1024;
    private const MERGING_STALE_SECONDS = 15 * 60;
    private const MERGING_HEARTBEAT_SECONDS = 10;
    private const SHARE_COOKIE_TTL_SECONDS = 30 * 24 * 60 * 60;
    private const CHUNK_CLEANUP_DELAY_SECONDS = 60;
    private static bool $folderTableEnsured = false;

    public function dispatch(string $endpoint): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($endpoint === 'share/meta' && $method === 'GET') {
            $this->shareMeta();
            return;
        }
        if ($endpoint === 'share/unlock' && $method === 'POST') {
            $this->shareUnlock();
            return;
        }
        if ($endpoint === 'file/chunk' && $method === 'GET') {
            $this->fileChunk();
            return;
        }

        $this->requireAdmin();

        switch ($endpoint) {
            case 'system/info':
                $this->systemInfo();
                break;
            case 'custom-buttons/get':
                $this->customButtonsGet();
                break;
            case 'custom-buttons/save':
                $this->customButtonsSave();
                break;
            case 'storage/list':
                $this->storageList();
                break;
            case 'storage/create':
                $this->storageCreate();
                break;
            case 'files/list':
                $this->filesList();
                break;
            case 'files/tree':
                $this->filesTree();
                break;
            case 'files/delete':
                $this->filesDelete();
                break;
            case 'files/rename':
                $this->filesRename();
                break;
            case 'files/copy':
                $this->filesCopy();
                break;
            case 'files/move':
                $this->filesMove();
                break;
            case 'files/folder/create':
                $this->filesFolderCreate();
                break;
            case 'upload/init':
                $this->uploadInit();
                break;
            case 'upload/status':
                $this->uploadStatus();
                break;
            case 'upload/chunk':
                $this->uploadChunk();
                break;
            case 'upload/complete':
                $this->uploadComplete();
                break;
            case 'share/create':
                $this->shareCreate();
                break;
            case 'share/list':
                $this->shareList();
                break;
            case 'share/delete':
                $this->shareDelete();
                break;
            default:
                Response::json(['ok' => false, 'message' => 'Not Found'], 404);
        }
    }

    private function requireAdmin(): void
    {
        if (empty($_SESSION['user'])) {
            Response::json(['ok' => false, 'message' => 'Unauthorized'], 401);
            exit;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return $_POST;
        }
        return json_decode($raw, true) ?: [];
    }

    private function storageList(): void
    {
        $rows = Db::pdo()->query('SELECT id, name, driver, config_json, is_active, created_at FROM storage_locations ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        Response::json(['ok' => true, 'items' => $rows]);
    }

    private function storageCreate(): void
    {
        $data = $this->jsonBody();
        $name = trim((string)($data['name'] ?? ''));
        $driver = trim((string)($data['driver'] ?? ''));
        $cfg = $data['config'] ?? [];
        if (!$name || !in_array($driver, ['local', 's3'], true)) {
            Response::json(['ok' => false, 'message' => 'Invalid storage data'], 422);
            return;
        }

        $stmt = Db::pdo()->prepare('INSERT INTO storage_locations (name, driver, config_json, is_active, created_at) VALUES (?, ?, ?, 1, NOW())');
        $stmt->execute([$name, $driver, json_encode($cfg, JSON_UNESCAPED_UNICODE)]);
        Response::json(['ok' => true]);
    }

    private function filesList(): void
    {
        $storageId = (int)($_GET['storage_id'] ?? 0);
        if ($storageId <= 0) {
            $rows = Db::pdo()->query('SELECT f.id, f.original_name, f.size, f.md5, f.folder_path, f.created_at, sl.name AS storage_name FROM files f JOIN storage_locations sl ON sl.id = f.storage_id ORDER BY f.id DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
            Response::json(['ok' => true, 'items' => $rows]);
            return;
        }

        $currentPath = $this->normalizeFolderPath((string)($_GET['folder_path'] ?? ''));
        $pdo = Db::pdo();
        $storageStmt = $pdo->prepare('SELECT id, name, driver FROM storage_locations WHERE id = ? LIMIT 1');
        $storageStmt->execute([$storageId]);
        $storage = $storageStmt->fetch(PDO::FETCH_ASSOC);
        if (!$storage) {
            Response::json(['ok' => false, 'message' => 'Storage not found'], 404);
            return;
        }

        if ($currentPath === '') {
            $fileStmt = $pdo->prepare(
                'SELECT f.id, f.storage_id, f.object_key, f.original_name, f.mime_type, f.size, f.md5, f.folder_path, f.created_at
                 FROM files f
                 WHERE f.storage_id = ? AND (f.folder_path IS NULL OR f.folder_path = ?)
                 ORDER BY f.original_name ASC, f.id DESC'
            );
            $fileStmt->execute([$storageId, '']);
        } else {
            $fileStmt = $pdo->prepare(
                'SELECT f.id, f.storage_id, f.object_key, f.original_name, f.mime_type, f.size, f.md5, f.folder_path, f.created_at
                 FROM files f
                 WHERE f.storage_id = ? AND f.folder_path = ?
                 ORDER BY f.original_name ASC, f.id DESC'
            );
            $fileStmt->execute([$storageId, $currentPath]);
        }
        $files = $fileStmt->fetchAll(PDO::FETCH_ASSOC);

        $folderCandidates = [];
        if ($currentPath === '') {
            $folderStmt = $pdo->prepare('SELECT DISTINCT folder_path FROM files WHERE storage_id = ? AND folder_path IS NOT NULL AND folder_path <> ?');
            $folderStmt->execute([$storageId, '']);
        } else {
            $folderStmt = $pdo->prepare('SELECT DISTINCT folder_path FROM files WHERE storage_id = ? AND folder_path LIKE ?');
            $folderStmt->execute([$storageId, $currentPath . '/%']);
        }
        foreach ($folderStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $folderCandidates[] = $this->normalizeFolderPath((string)($row['folder_path'] ?? ''));
        }

        $this->ensureFolderTable();
        if ($currentPath === '') {
            $markerStmt = $pdo->prepare('SELECT folder_path FROM file_folders WHERE storage_id = ?');
            $markerStmt->execute([$storageId]);
        } else {
            $markerStmt = $pdo->prepare('SELECT folder_path FROM file_folders WHERE storage_id = ? AND (folder_path = ? OR folder_path LIKE ?)');
            $markerStmt->execute([$storageId, $currentPath, $currentPath . '/%']);
        }
        foreach ($markerStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $folderCandidates[] = $this->normalizeFolderPath((string)($row['folder_path'] ?? ''));
        }

        $folders = $this->collectImmediateFolders($folderCandidates, $currentPath);
        Response::json([
            'ok' => true,
            'storage_id' => $storageId,
            'storage_name' => (string)$storage['name'],
            'current_path' => $currentPath,
            'folders' => $folders,
            'files' => $files,
        ]);
    }

    private function filesTree(): void
    {
        $storageId = (int)($_GET['storage_id'] ?? 0);
        if ($storageId <= 0) {
            Response::json(['ok' => false, 'message' => 'storage_id is required'], 422);
            return;
        }

        $items = [];
        foreach ($this->loadAllFolderPaths($storageId) as $path) {
            [$parentPath, $name] = $this->splitFolderParentAndName($path);
            if ($name === '') {
                continue;
            }
            $items[] = [
                'name' => $name,
                'path' => $path,
                'parent_path' => $parentPath,
                'depth' => substr_count($path, '/') + 1,
            ];
        }

        Response::json([
            'ok' => true,
            'storage_id' => $storageId,
            'items' => $items,
        ]);
    }

    private function filesFolderCreate(): void
    {
        $data = $this->jsonBody();
        $storageId = (int)($data['storage_id'] ?? 0);
        $folderPath = $this->normalizeFolderPath((string)($data['folder_path'] ?? ''));
        if ($storageId <= 0 || $folderPath === '') {
            Response::json(['ok' => false, 'message' => 'Invalid folder payload'], 422);
            return;
        }

        [$parentPath, $folderName] = $this->splitFolderParentAndName($folderPath);
        if ($folderName === '') {
            Response::json(['ok' => false, 'message' => 'Invalid folder name'], 422);
            return;
        }
        if ($this->hasFileNameConflict($storageId, $parentPath, $folderName)) {
            Response::json(['ok' => false, 'message' => 'A file with the same name exists in the target folder'], 409);
            return;
        }
        if ($this->folderPathExists($storageId, $folderPath)) {
            Response::json(['ok' => false, 'message' => 'Folder already exists'], 409);
            return;
        }

        $this->persistFolderPath($storageId, $folderPath);
        Response::json(['ok' => true, 'folder_path' => $folderPath]);
    }

    private function filesDelete(): void
    {
        $data = $this->jsonBody();
        $storageId = (int)($data['storage_id'] ?? 0);
        $fileIds = array_values(array_unique(array_map('intval', (array)($data['file_ids'] ?? []))));
        $folderPaths = array_values(array_filter(array_map(fn ($v): string => $this->normalizeFolderPath((string)$v), (array)($data['folder_paths'] ?? []))));

        if (!$fileIds && !$folderPaths) {
            Response::json(['ok' => false, 'message' => 'No items to delete'], 422);
            return;
        }
        if ($folderPaths && $storageId <= 0) {
            Response::json(['ok' => false, 'message' => 'storage_id is required when deleting folders'], 422);
            return;
        }

        $selected = $this->collectFilesForDelete($fileIds, $folderPaths, $storageId);
        if (!$selected && !$folderPaths) {
            Response::json(['ok' => false, 'message' => 'No deletable files found'], 404);
            return;
        }

        $adapters = [];
        foreach ($selected as $file) {
            $selectedStorageId = (int)$file['storage_id'];
            if (!isset($adapters[$selectedStorageId])) {
                $adapters[$selectedStorageId] = StorageFactory::make($file);
            }
            $adapters[$selectedStorageId]->deleteObject((string)$file['object_key']);
        }
        if ($selected) {
            $this->cleanupMatchedUploadSessions($selected, $adapters);
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $deletedFileCount = 0;
            if ($selected) {
                $deleteIds = array_map(static fn (array $row): int => (int)$row['id'], $selected);
                $in = implode(',', array_fill(0, count($deleteIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM files WHERE id IN ($in)");
                $stmt->execute($deleteIds);
                $deletedFileCount = count($deleteIds);
            }

            $deletedFolderCount = 0;
            if ($folderPaths) {
                $this->ensureFolderTable();
                $delFolderStmt = $pdo->prepare('DELETE FROM file_folders WHERE storage_id = ? AND (folder_path = ? OR folder_path LIKE ?)');
                foreach ($folderPaths as $fp) {
                    $delFolderStmt->execute([$storageId, $fp, $fp . '/%']);
                    $deletedFolderCount += (int)$delFolderStmt->rowCount();
                }
            }
            $pdo->commit();
            Response::json([
                'ok' => true,
                'deleted_files' => $deletedFileCount,
                'deleted_folders' => $deletedFolderCount,
            ]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function filesRename(): void
    {
        $data = $this->jsonBody();
        $storageId = (int)($data['storage_id'] ?? 0);
        $fileId = (int)($data['file_id'] ?? 0);
        $newName = trim((string)($data['new_name'] ?? ''));
        $folderPath = $this->normalizeFolderPath((string)($data['folder_path'] ?? ''));
        $newFolderPath = $this->normalizeFolderPath((string)($data['new_folder_path'] ?? ''));

        if ($fileId > 0 && $newName !== '') {
            $stmt = Db::pdo()->prepare('SELECT id, storage_id, folder_path FROM files WHERE id = ? LIMIT 1');
            $stmt->execute([$fileId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$file) {
                Response::json(['ok' => false, 'message' => 'File not found'], 404);
                return;
            }
            $currentFolder = $this->normalizeFolderPath((string)($file['folder_path'] ?? ''));
            $fileStorageId = (int)$file['storage_id'];
            if ($this->hasFileNameConflict($fileStorageId, $currentFolder, $newName, $fileId)) {
                Response::json(['ok' => false, 'message' => 'A file with the same name already exists in this folder'], 409);
                return;
            }

            $upd = Db::pdo()->prepare('UPDATE files SET original_name = ? WHERE id = ?');
            $upd->execute([$newName, $fileId]);
            Response::json(['ok' => true]);
            return;
        }

        if ($storageId > 0 && $folderPath !== '' && $newFolderPath !== '') {
            if ($folderPath === $newFolderPath) {
                Response::json(['ok' => true]);
                return;
            }
            $pdo = Db::pdo();
            $pdo->beginTransaction();
            try {
                $this->moveFolderPath($storageId, $folderPath, $newFolderPath);
                $pdo->commit();
                Response::json(['ok' => true]);
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
            }
            return;
        }

        Response::json(['ok' => false, 'message' => 'Invalid rename parameters'], 422);
    }

    private function filesCopy(): void
    {
        $data = $this->jsonBody();
        $storageId = (int)($data['storage_id'] ?? 0);
        $fileIds = array_values(array_unique(array_map('intval', (array)($data['file_ids'] ?? []))));
        $folderPaths = $this->normalizeRootFolderSelections((array)($data['folder_paths'] ?? []));
        $targetFolder = $this->normalizeFolderPath((string)($data['target_folder_path'] ?? ''));

        if ($storageId <= 0 || (!$fileIds && !$folderPaths)) {
            Response::json(['ok' => false, 'message' => 'No items to copy'], 422);
            return;
        }

        $pdo = Db::pdo();
        $selected = $this->loadSelectedFilesForClipboard($storageId, $fileIds, $folderPaths);
        $folderMap = $this->buildClipboardFolderMap($storageId, $folderPaths, $targetFolder, false);
        if (!$selected && !$folderMap) {
            Response::json(['ok' => false, 'message' => 'No copyable files found'], 404);
            return;
        }
        $createdCopies = [];
        $copiedCount = 0;
        $skippedCount = 0;

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare('INSERT INTO files (storage_id, object_key, original_name, mime_type, size, sha256, md5, folder_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            foreach ($folderMap as $destRootPath) {
                if ($destRootPath !== '') {
                    $this->persistFolderPath($storageId, $destRootPath);
                }
            }
            if ($folderMap) {
                $this->copyFolderMarkersByMap($storageId, $folderMap);
            }

            foreach ($selected as $file) {
                $srcFolder = $this->normalizeFolderPath((string)($file['folder_path'] ?? ''));
                $destFolder = $this->mapSelectedFolderPath($srcFolder, $folderMap) ?? $targetFolder;
                $srcMd5 = strtolower(trim((string)($file['md5'] ?? '')));
                $destName = trim((string)$file['original_name']);
                $conflicts = $this->listFilesByFolderAndName($storageId, $destFolder, $destName);
                if ($conflicts && $srcMd5 !== '') {
                    foreach ($conflicts as $conflict) {
                        $conflictMd5 = strtolower(trim((string)($conflict['md5'] ?? '')));
                        if ($conflictMd5 !== '' && $conflictMd5 === $srcMd5) {
                            $skippedCount++;
                            continue 2;
                        }
                    }
                }
                $destName = $this->nextAvailableFileName($storageId, $destFolder, $destName);
                $newObjectKey = $this->generateObjectKeyForName($destName);
                $adapter = StorageFactory::make($file);
                $adapter->copyObject((string)$file['object_key'], $newObjectKey);
                $createdCopies[] = [$adapter, $newObjectKey];

                $ins->execute([
                    $storageId,
                    $newObjectKey,
                    $destName,
                    (string)$file['mime_type'],
                    (int)$file['size'],
                    $file['sha256'],
                    $file['md5'],
                    $destFolder !== '' ? $destFolder : null,
                ]);
                if ($destFolder !== '') {
                    $this->persistFolderPath($storageId, $destFolder);
                }
                $copiedCount++;
            }

            $pdo->commit();
            Response::json(['ok' => true, 'count' => $copiedCount, 'skipped' => $skippedCount]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($createdCopies as [$adapter, $objectKey]) {
                try {
                    $adapter->deleteObject((string)$objectKey);
                } catch (\Throwable $cleanupError) {
                    error_log(sprintf('[files/copy] cleanup failed key=%s err=%s', (string)$objectKey, $cleanupError->getMessage()));
                }
            }
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function filesMove(): void
    {
        $data = $this->jsonBody();
        $storageId = (int)($data['storage_id'] ?? 0);
        $fileIds = array_values(array_unique(array_map('intval', (array)($data['file_ids'] ?? []))));
        $folderPaths = $this->normalizeRootFolderSelections((array)($data['folder_paths'] ?? []));
        $targetFolder = $this->normalizeFolderPath((string)($data['target_folder_path'] ?? ''));

        if ($storageId <= 0 || (!$fileIds && !$folderPaths)) {
            Response::json(['ok' => false, 'message' => 'No items to move'], 422);
            return;
        }

        foreach ($folderPaths as $rootFolder) {
            if ($targetFolder === $rootFolder || str_starts_with($targetFolder . '/', $rootFolder . '/')) {
                Response::json(['ok' => false, 'message' => 'Cannot move folder into itself'], 422);
                return;
            }
        }

        $pdo = Db::pdo();
        $folderMap = $this->buildClipboardFolderMap($storageId, $folderPaths, $targetFolder, true);
        $selectedFiles = $this->loadSelectedFilesByIds($storageId, $fileIds);
        $selectedFiles = array_values(array_filter($selectedFiles, function (array $file) use ($folderPaths): bool {
            $folderPath = $this->normalizeFolderPath((string)($file['folder_path'] ?? ''));
            foreach ($folderPaths as $rootFolder) {
                if ($folderPath === $rootFolder || str_starts_with($folderPath, $rootFolder . '/')) {
                    return false;
                }
            }
            return true;
        }));

        $movedFiles = 0;
        $movedFolders = 0;

        $pdo->beginTransaction();
        try {
            foreach ($selectedFiles as $file) {
                $fileId = (int)$file['id'];
                $currentFolder = $this->normalizeFolderPath((string)($file['folder_path'] ?? ''));
                $currentName = trim((string)$file['original_name']);
                if ($currentFolder === $targetFolder) {
                    continue;
                }
                $newName = $this->nextAvailableFileName($storageId, $targetFolder, $currentName, $fileId);
                $stmt = $pdo->prepare('UPDATE files SET folder_path = ?, original_name = ? WHERE id = ?');
                $stmt->execute([$targetFolder !== '' ? $targetFolder : null, $newName, $fileId]);
                if ($targetFolder !== '') {
                    $this->persistFolderPath($storageId, $targetFolder);
                }
                $movedFiles++;
            }

            foreach ($folderMap as $sourceFolder => $destFolder) {
                if ($sourceFolder === $destFolder) {
                    continue;
                }
                $this->moveFolderPath($storageId, $sourceFolder, $destFolder);
                $movedFolders++;
            }

            $pdo->commit();
            Response::json([
                'ok' => true,
                'moved_files' => $movedFiles,
                'moved_folders' => $movedFolders,
            ]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function uploadInit(): void
    {
        $data = $this->jsonBody();
        $name = trim((string)($data['name'] ?? ''));
        $size = (int)($data['size'] ?? 0);
        $chunks = (int)($data['chunks'] ?? 0);
        $storageId = (int)($data['storage_id'] ?? 0);
        $folderPath = $this->normalizeFolderPath((string)($data['folder_path'] ?? ''));
        $folderDigest = hash('sha256', $folderPath);
        $expectedMd5 = strtolower(trim((string)($data['expected_md5'] ?? '')));
        $duplicateStrategy = strtolower(trim((string)($data['duplicate_strategy'] ?? 'ask')));
        $conflictToken = trim((string)($data['conflict_token'] ?? ''));
        if ($duplicateStrategy === '') {
            $duplicateStrategy = 'ask';
        }

        if (!$name || $size <= 0 || $chunks <= 0 || $storageId <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid init payload'], 422);
            return;
        }
        if (!in_array($duplicateStrategy, ['ask', 'resume', 'restart'], true)) {
            Response::json(['ok' => false, 'message' => 'Invalid duplicate strategy'], 422);
            return;
        }
        if ($expectedMd5 !== '' && !preg_match('/^[a-f0-9]{32}$/', $expectedMd5)) {
            Response::json(['ok' => false, 'message' => 'Invalid expected_md5'], 422);
            return;
        }
        $expectedChunks = (int)max(1, ceil($size / self::CHUNK_SIZE_BYTES));
        if ($chunks !== $expectedChunks) {
            Response::json([
                'ok' => false,
                'message' => 'Invalid chunk count for 30MB chunk strategy',
                'expected_chunks' => $expectedChunks,
                'received_chunks' => $chunks,
                'file_size' => $size,
                'chunk_size_bytes' => self::CHUNK_SIZE_BYTES,
            ], 422);
            return;
        }

        $existing = null;
        $shouldCheckDuplicate = $expectedMd5 !== '';
        if ($shouldCheckDuplicate) {
            $matchedFiles = $this->listFilesByFolderAndName($storageId, $folderPath, $name);
            foreach ($matchedFiles as $matched) {
                $existingMd5 = strtolower(trim((string)($matched['md5'] ?? '')));
                if ($existingMd5 !== '' && $existingMd5 === $expectedMd5) {
                    Response::json([
                        'ok' => true,
                        'skip_upload' => true,
                        'duplicate' => true,
                        'file_id' => (int)($matched['id'] ?? 0),
                        'md5' => $existingMd5,
                        'folder_path' => $folderPath,
                    ]);
                    return;
                }
            }
        }

        if ($shouldCheckDuplicate && $conflictToken !== '') {
            $byTokenStmt = Db::pdo()->prepare(
                "SELECT us.*, sl.driver, sl.config_json
                 FROM upload_sessions us
                 JOIN storage_locations sl ON sl.id = us.storage_id
                 WHERE us.upload_token = ?
                   AND us.storage_id = ?
                   AND us.original_name = ?
                   AND COALESCE(us.file_sha256, '') = ?
                   AND us.expected_md5 = ?
                   AND us.status IN ('uploading', 'merging', 'md5_mismatch')
                 LIMIT 1"
            );
            $byTokenStmt->execute([$conflictToken, $storageId, $name, $folderDigest, $expectedMd5]);
            $existing = $byTokenStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($shouldCheckDuplicate && !is_array($existing)) {
            $dupStmt = Db::pdo()->prepare(
                "SELECT us.*, sl.driver, sl.config_json
                 FROM upload_sessions us
                 JOIN storage_locations sl ON sl.id = us.storage_id
                 WHERE us.storage_id = ?
                   AND us.original_name = ?
                   AND COALESCE(us.file_sha256, '') = ?
                   AND us.expected_md5 = ?
                   AND us.status IN ('uploading', 'merging', 'md5_mismatch')
                 ORDER BY us.updated_at DESC, us.id DESC
                 LIMIT 1"
            );
            $dupStmt->execute([$storageId, $name, $folderDigest, $expectedMd5]);
            $existing = $dupStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (is_array($existing)) {
            $existingStatus = (string)($existing['status'] ?? 'uploading');
            $sizeMatched = (int)($existing['total_size'] ?? -1) === $size;
            $chunkMatched = (int)($existing['total_chunks'] ?? -1) === $chunks;
            $isFreshMerging = $existingStatus === 'merging' && $this->isFreshMerging((string)($existing['updated_at'] ?? ''));
            $canResume = $sizeMatched && $chunkMatched && !$isFreshMerging;

            if ($duplicateStrategy === 'ask') {
                Response::json([
                    'ok' => false,
                    'code' => 'duplicate_upload_task',
                    'message' => 'Duplicate upload task found',
                    'conflict' => [
                        'token' => (string)$existing['upload_token'],
                        'status' => $existingStatus,
                        'updated_at' => (string)($existing['updated_at'] ?? ''),
                        'total_size' => (int)($existing['total_size'] ?? 0),
                        'total_chunks' => (int)($existing['total_chunks'] ?? 0),
                        'can_resume' => $canResume,
                    ],
                ], 409);
                return;
            }

            if ($duplicateStrategy === 'resume') {
                if (!$canResume) {
                    Response::json([
                        'ok' => false,
                        'message' => $isFreshMerging
                            ? 'Upload is currently merging'
                            : 'Cannot resume by chunks: file size or chunk count changed',
                    ], 422);
                    return;
                }
                $ustmt = Db::pdo()->prepare('UPDATE upload_sessions SET file_sha256 = ?, expected_md5 = ?, status = ?, updated_at = NOW() WHERE id = ?');
                $ustmt->execute([$folderDigest, $expectedMd5 ?: null, 'uploading', (int)$existing['id']]);
                Response::json([
                    'ok' => true,
                    'token' => (string)$existing['upload_token'],
                    'strategy' => 'resume',
                    'reused' => true,
                ]);
                return;
            }

            if ($isFreshMerging) {
                Response::json(['ok' => false, 'message' => 'Upload is currently merging'], 409);
                return;
            }

            $oldTotalChunks = (int)($existing['total_chunks'] ?? 0);
            $sessionId = (int)$existing['id'];
            $sessionToken = (string)$existing['upload_token'];
            $pdo = Db::pdo();
            $pdo->beginTransaction();
            try {
                $delStmt = $pdo->prepare('DELETE FROM upload_chunks WHERE upload_session_id = ?');
                $delStmt->execute([$sessionId]);

                $ustmt = $pdo->prepare('UPDATE upload_sessions SET total_size = ?, total_chunks = ?, file_sha256 = ?, expected_md5 = ?, status = ?, updated_at = NOW() WHERE id = ?');
                $ustmt->execute([$size, $chunks, $folderDigest, $expectedMd5 ?: null, 'uploading', $sessionId]);
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                Response::json(['ok' => false, 'message' => 'Failed to restart duplicate task: ' . $e->getMessage()], 500);
                return;
            }

            try {
                $adapter = StorageFactory::make($existing);
                $adapter->cleanupUploadChunks($sessionToken, $oldTotalChunks);
            } catch (\Throwable $e) {
                error_log(sprintf('[upload/init] restart cleanup failed token=%s err=%s', $sessionToken, $e->getMessage()));
            }

            Response::json([
                'ok' => true,
                'token' => $sessionToken,
                'strategy' => 'restart',
                'reused' => true,
            ]);
            return;
        }

        $token = bin2hex(random_bytes(16));
        $stmt = Db::pdo()->prepare('INSERT INTO upload_sessions (upload_token, original_name, total_size, total_chunks, storage_id, file_sha256, expected_md5, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([$token, $name, $size, $chunks, $storageId, $folderDigest, $expectedMd5 ?: null, 'uploading']);
        Response::json(['ok' => true, 'token' => $token]);
    }

    private function uploadStatus(): void
    {
        $token = trim((string)($_GET['token'] ?? ''));
        $stmt = Db::pdo()->prepare('SELECT id, total_chunks, status FROM upload_sessions WHERE upload_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            Response::json(['ok' => false, 'message' => 'Upload session not found'], 404);
            return;
        }

        $cstmt = Db::pdo()->prepare('SELECT chunk_index FROM upload_chunks WHERE upload_session_id = ?');
        $cstmt->execute([$session['id']]);
        $uploaded = array_map(static fn ($r) => (int)$r['chunk_index'], $cstmt->fetchAll(PDO::FETCH_ASSOC));
        Response::json([
            'ok' => true,
            'uploaded' => $uploaded,
            'total_chunks' => (int)$session['total_chunks'],
            'status' => (string)($session['status'] ?? 'uploading'),
        ]);
    }

    private function uploadChunk(): void
    {
        $token = trim((string)($_POST['token'] ?? ''));
        $idx = (int)($_POST['chunk_index'] ?? -1);

        if (!isset($_FILES['chunk']) || $idx < 0 || !$token) {
            Response::json(['ok' => false, 'message' => 'Invalid chunk payload'], 422);
            return;
        }
        $chunkFile = $_FILES['chunk'];
        if (!is_array($chunkFile)) {
            Response::json(['ok' => false, 'message' => 'Invalid chunk file'], 422);
            return;
        }

        $uploadErr = (int)($chunkFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $status = in_array($uploadErr, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 413 : 422;
            Response::json(['ok' => false, 'message' => $this->uploadErrorMessage($uploadErr)], $status);
            return;
        }
        $tmpName = (string)($chunkFile['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            Response::json(['ok' => false, 'message' => 'Chunk tmp file missing'], 422);
            return;
        }
        $chunkSize = (int)($chunkFile['size'] ?? 0);
        if ($chunkSize <= 0) {
            Response::json(['ok' => false, 'message' => 'Chunk file is empty'], 422);
            return;
        }

        $stmt = Db::pdo()->prepare('SELECT us.*, sl.driver, sl.config_json FROM upload_sessions us JOIN storage_locations sl ON sl.id = us.storage_id WHERE us.upload_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            Response::json(['ok' => false, 'message' => 'Upload session not found'], 404);
            return;
        }
        if ((string)($session['status'] ?? '') === 'completed') {
            Response::json(['ok' => true, 'message' => 'Already completed']);
            return;
        }
        $totalChunks = (int)($session['total_chunks'] ?? 0);
        if ($idx >= $totalChunks) {
            Response::json(['ok' => false, 'message' => 'Invalid chunk index'], 422);
            return;
        }
        $expectedChunkSize = $this->expectedChunkSize((int)$session['total_size'], $totalChunks, $idx);
        if ($chunkSize > $expectedChunkSize) {
            Response::json([
                'ok' => false,
                'message' => 'Chunk is larger than expected for 30MB chunk strategy',
                'max_size' => $expectedChunkSize,
            ], 413);
            return;
        }
        if ($idx < ($totalChunks - 1) && $chunkSize !== self::CHUNK_SIZE_BYTES) {
            Response::json([
                'ok' => false,
                'message' => 'Non-final chunk must be exactly 30MB',
                'required_size' => self::CHUNK_SIZE_BYTES,
            ], 422);
            return;
        }

        try {
            $adapter = StorageFactory::make($session);
            $etag = $adapter->saveChunk($token, $idx, $tmpName);

            $cstmt = Db::pdo()->prepare('INSERT INTO upload_chunks (upload_session_id, chunk_index, chunk_size, etag, created_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE chunk_size = VALUES(chunk_size), etag = VALUES(etag)');
            $cstmt->execute([(int)$session['id'], $idx, $chunkSize, $etag]);

            $ustmt = Db::pdo()->prepare('UPDATE upload_sessions SET updated_at = NOW() WHERE id = ?');
            $ustmt->execute([(int)$session['id']]);

            Response::json(['ok' => true, 'etag' => $etag]);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[upload/chunk] token=%s idx=%d driver=%s err=%s',
                $token,
                $idx,
                (string)($session['driver'] ?? 'unknown'),
                $e->getMessage()
            ));
            $status = str_starts_with((string)$e->getMessage(), 'Out-of-order chunk:') ? 422 : 500;
            Response::json(['ok' => false, 'message' => $e->getMessage()], $status);
        }
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'Chunk exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'Chunk exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'Chunk only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No chunk file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing upload temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write chunk to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension',
            default => 'Unknown upload error: ' . $code,
        };
    }

    private function expectedChunkSize(int $totalSize, int $totalChunks, int $chunkIndex): int
    {
        if ($totalChunks <= 0 || $chunkIndex < 0) {
            return self::CHUNK_SIZE_BYTES;
        }
        if ($chunkIndex < ($totalChunks - 1)) {
            return self::CHUNK_SIZE_BYTES;
        }
        $tail = $totalSize - (($totalChunks - 1) * self::CHUNK_SIZE_BYTES);
        if ($tail <= 0 || $tail > self::CHUNK_SIZE_BYTES) {
            return self::CHUNK_SIZE_BYTES;
        }
        return $tail;
    }

    private function isFreshMerging(string $updatedAt): bool
    {
        $ts = strtotime($updatedAt);
        if ($ts === false) {
            return false;
        }
        return (time() - $ts) < self::MERGING_STALE_SECONDS;
    }

    private function touchUploadSessionHeartbeat(int $sessionId, string $token): void
    {
        try {
            $stmt = Db::pdo()->prepare('UPDATE upload_sessions SET updated_at = NOW() WHERE id = ?');
            $stmt->execute([$sessionId]);
        } catch (\Throwable $e) {
            error_log(sprintf('[upload/complete] heartbeat failed token=%s err=%s', $token, $e->getMessage()));
        }
    }

    /**
     * @param array<int, int> $missingChunks
     */
    private function markChunksMissingForRetry(int $sessionId, array $missingChunks, string $token): void
    {
        $indexes = array_values(array_unique(array_map('intval', $missingChunks), SORT_NUMERIC));
        if (!$indexes) {
            return;
        }
        sort($indexes, SORT_NUMERIC);

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $in = implode(',', array_fill(0, count($indexes), '?'));
            $params = array_merge([(int)$sessionId], $indexes);
            $delStmt = $pdo->prepare("DELETE FROM upload_chunks WHERE upload_session_id = ? AND chunk_index IN ($in)");
            $delStmt->execute($params);

            $ustmt = $pdo->prepare('UPDATE upload_sessions SET status = ?, updated_at = NOW() WHERE id = ?');
            $ustmt->execute(['uploading', (int)$sessionId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log(sprintf('[upload/complete] mark missing chunks failed token=%s err=%s', $token, $e->getMessage()));
            throw $e;
        }
    }

    private function buildObjectKey(array $session): string
    {
        $createdAt = trim((string)($session['created_at'] ?? ''));
        $ts = strtotime($createdAt);
        $prefix = $ts !== false ? date('Y/m/d', $ts) : date('Y/m/d');
        $token = trim((string)($session['upload_token'] ?? ''));
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
        }
        $ext = strtolower((string)pathinfo((string)($session['original_name'] ?? ''), PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]+/', '', $ext) ?: '';
        return $prefix . '/' . $token . ($ext !== '' ? ('.' . $ext) : '');
    }

    private function uploadComplete(): void
    {
        $data = $this->jsonBody();
        $token = trim((string)($data['token'] ?? ''));
        $folderPath = $this->normalizeFolderPath((string)($data['folder_path'] ?? ''));
        $mime = trim((string)($data['mime_type'] ?? 'application/octet-stream'));
        if ($token === '') {
            Response::json(['ok' => false, 'message' => 'Missing token'], 422);
            return;
        }

        $pdo = Db::pdo();
        $session = null;
        $expectedChunkSizes = [];
        $resolvedFolderPath = $folderPath;
        $duplicateExistingFileId = 0;
        $duplicateExistingMd5 = '';
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT us.*, sl.driver, sl.config_json FROM upload_sessions us JOIN storage_locations sl ON sl.id = us.storage_id WHERE us.upload_token = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$token]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$session) {
                $pdo->commit();
                Response::json(['ok' => false, 'message' => 'Upload session not found'], 404);
                return;
            }

            $status = (string)($session['status'] ?? 'uploading');
            if ($status === 'completed') {
                $pdo->commit();
                Response::json(['ok' => true, 'message' => 'Already completed']);
                return;
            }
            if ($status === 'merging' && $this->isFreshMerging((string)($session['updated_at'] ?? ''))) {
                $pdo->commit();
                Response::json(['ok' => false, 'message' => 'Upload is currently merging'], 409);
                return;
            }

            $chunkStmt = $pdo->prepare('SELECT chunk_index, chunk_size FROM upload_chunks WHERE upload_session_id = ?');
            $chunkStmt->execute([(int)$session['id']]);
            $chunkRows = $chunkStmt->fetchAll(PDO::FETCH_ASSOC);
            $chunkByIndex = [];
            foreach ($chunkRows as $r) {
                $idx = (int)($r['chunk_index'] ?? -1);
                if ($idx < 0) {
                    continue;
                }
                $chunkByIndex[$idx] = (int)($r['chunk_size'] ?? 0);
            }

            $totalChunks = (int)($session['total_chunks'] ?? 0);
            $missingFromDb = [];
            $expectedChunkSizes = [];
            for ($i = 0; $i < $totalChunks; $i++) {
                if (!array_key_exists($i, $chunkByIndex)) {
                    $missingFromDb[] = $i;
                    continue;
                }
                $expectedSize = $this->expectedChunkSize((int)$session['total_size'], $totalChunks, $i);
                if ((int)$chunkByIndex[$i] !== $expectedSize) {
                    $missingFromDb[] = $i;
                    continue;
                }
                $expectedChunkSizes[$i] = $expectedSize;
            }

            if ($missingFromDb) {
                $in = implode(',', array_fill(0, count($missingFromDb), '?'));
                $params = array_merge([(int)$session['id']], $missingFromDb);
                $delStmt = $pdo->prepare("DELETE FROM upload_chunks WHERE upload_session_id = ? AND chunk_index IN ($in)");
                $delStmt->execute($params);

                $markStmt = $pdo->prepare('UPDATE upload_sessions SET status = ?, updated_at = NOW() WHERE id = ?');
                $markStmt->execute(['uploading', (int)$session['id']]);
                $pdo->commit();
                Response::json([
                    'ok' => false,
                    'message' => 'Missing chunk: ' . $missingFromDb[0],
                    'missing_chunks' => $missingFromDb,
                ], 422);
                return;
            }

            $expectedMd5 = strtolower((string)($session['expected_md5'] ?? ''));
            $conflictResult = $this->resolveUploadPathConflict(
                (int)$session['storage_id'],
                $folderPath,
                (string)$session['original_name'],
                $expectedMd5
            );
            $resolvedFolderPath = (string)($conflictResult['folder_path'] ?? $folderPath);
            $duplicateExistingFileId = (int)($conflictResult['duplicate_file_id'] ?? 0);
            $duplicateExistingMd5 = strtolower((string)($conflictResult['duplicate_md5'] ?? ''));

            if ($duplicateExistingFileId > 0) {
                $markStmt = $pdo->prepare('UPDATE upload_sessions SET status = ?, updated_at = NOW() WHERE id = ?');
                $markStmt->execute(['completed', (int)$session['id']]);
                $pdo->commit();

                if (!is_array($session)) {
                    Response::json(['ok' => false, 'message' => 'Upload session not found'], 404);
                    return;
                }
                if ($resolvedFolderPath !== '') {
                    $this->persistFolderPath((int)$session['storage_id'], $resolvedFolderPath);
                }
                $adapter = StorageFactory::make($session);
                $responsePayload = [
                    'ok' => true,
                    'file_id' => $duplicateExistingFileId,
                    'md5' => $duplicateExistingMd5,
                    'duplicate' => true,
                    'folder_path' => $resolvedFolderPath,
                ];
                Response::json($responsePayload);
                $this->finishResponseAndDelayChunkCleanup($adapter, $token, (int)$session['total_chunks']);
                return;
            }

            $markStmt = $pdo->prepare('UPDATE upload_sessions SET status = ?, updated_at = NOW() WHERE id = ?');
            $markStmt->execute(['merging', (int)$session['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['ok' => false, 'message' => 'Failed to prepare merge: ' . $e->getMessage()], 500);
            return;
        }

        if (!is_array($session)) {
            Response::json(['ok' => false, 'message' => 'Upload session not found'], 404);
            return;
        }

        $objectKey = $this->buildObjectKey($session);
        $adapter = StorageFactory::make($session);
        $sessionId = (int)$session['id'];

        try {
            if (function_exists('ignore_user_abort')) {
                ignore_user_abort(true);
            }
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }
            @ini_set('max_execution_time', '0');

            $missingInStorage = $adapter->findMissingChunks($token, $expectedChunkSizes);
            if ($missingInStorage) {
                $this->markChunksMissingForRetry($sessionId, $missingInStorage, $token);
                Response::json([
                    'ok' => false,
                    'message' => 'Missing chunk: ' . $missingInStorage[0],
                    'missing_chunks' => $missingInStorage,
                ], 422);
                return;
            }

            $lastHeartbeatAt = microtime(true);
            $adapter->mergeChunks(
                $token,
                $objectKey,
                (int)$session['total_chunks'],
                function (int $mergedChunks, int $chunkTotal) use (&$lastHeartbeatAt, $sessionId, $token): void {
                    $now = microtime(true);
                    if (($now - $lastHeartbeatAt) < self::MERGING_HEARTBEAT_SECONDS) {
                        return;
                    }
                    $lastHeartbeatAt = $now;
                    $this->touchUploadSessionHeartbeat($sessionId, $token);
                }
            );
            $mergedSize = $adapter->objectSize($objectKey);
            if ($mergedSize !== (int)$session['total_size']) {
                throw new \RuntimeException(sprintf(
                    'Merged object size mismatch: expected=%d actual=%d',
                    (int)$session['total_size'],
                    $mergedSize
                ));
            }
            $expectedMd5 = strtolower((string)($session['expected_md5'] ?? ''));
            $actualMd5 = $expectedMd5 !== '' ? $expectedMd5 : strtolower((string)$adapter->objectMd5($objectKey));

            $fileId = 0;
            $existsStmt = Db::pdo()->prepare('SELECT id FROM files WHERE storage_id = ? AND object_key = ? LIMIT 1');
            $existsStmt->execute([(int)$session['storage_id'], $objectKey]);
            $existingId = (int)($existsStmt->fetchColumn() ?: 0);
            if ($existingId > 0) {
                $updFile = Db::pdo()->prepare('UPDATE files SET original_name = ?, mime_type = ?, size = ?, md5 = ?, folder_path = ? WHERE id = ?');
                $updFile->execute([
                    $session['original_name'],
                    $mime,
                    (int)$session['total_size'],
                    $actualMd5,
                    $resolvedFolderPath ?: null,
                    $existingId,
                ]);
                $fileId = $existingId;
            } else {
                $fstmt = Db::pdo()->prepare('INSERT INTO files (storage_id, object_key, original_name, mime_type, size, sha256, md5, folder_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                $fstmt->execute([
                    (int)$session['storage_id'],
                    $objectKey,
                    $session['original_name'],
                    $mime,
                    (int)$session['total_size'],
                    null,
                    $actualMd5,
                    $resolvedFolderPath ?: null,
                ]);
                $fileId = (int)Db::pdo()->lastInsertId();
            }
            if ($resolvedFolderPath !== '') {
                $this->persistFolderPath((int)$session['storage_id'], $resolvedFolderPath);
            }

            $ustmt = Db::pdo()->prepare('UPDATE upload_sessions SET status = ?, updated_at = NOW() WHERE id = ?');
            $ustmt->execute(['completed', (int)$session['id']]);
            $responsePayload = ['ok' => true, 'file_id' => $fileId, 'md5' => $actualMd5 ?: '', 'folder_path' => $resolvedFolderPath];
            Response::json($responsePayload);
            $this->finishResponseAndDelayChunkCleanup($adapter, $token, (int)$session['total_chunks']);
            return;
        } catch (MergeInProgressException $e) {
            $this->touchUploadSessionHeartbeat($sessionId, $token);
            Response::json(['ok' => false, 'message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            try {
                $adapter->deleteObject($objectKey);
            } catch (\Throwable $cleanupError) {
                error_log(sprintf('[upload/complete] object cleanup failed token=%s key=%s err=%s', $token, $objectKey, $cleanupError->getMessage()));
            }
            try {
                $ustmt = Db::pdo()->prepare('UPDATE upload_sessions SET status = ?, updated_at = NOW() WHERE id = ?');
                $ustmt->execute(['uploading', (int)$session['id']]);
            } catch (\Throwable $statusError) {
                error_log(sprintf('[upload/complete] status rollback failed token=%s err=%s', $token, $statusError->getMessage()));
            }
            error_log(sprintf('[upload/complete] token=%s err=%s', $token, $e->getMessage()));
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function normalizeFolderPath(string $path): string
    {
        $raw = trim(str_replace('\\', '/', $path));
        if ($raw === '') {
            return '';
        }
        $parts = [];
        foreach (explode('/', $raw) as $seg) {
            $seg = trim(str_replace("\0", '', $seg));
            if ($seg === '' || $seg === '.' || $seg === '..') {
                continue;
            }
            $parts[] = $seg;
        }
        return implode('/', $parts);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitFolderParentAndName(string $folderPath): array
    {
        $normalized = $this->normalizeFolderPath($folderPath);
        if ($normalized === '') {
            return ['', ''];
        }
        $pos = strrpos($normalized, '/');
        if ($pos === false) {
            return ['', $normalized];
        }
        return [substr($normalized, 0, $pos), substr($normalized, $pos + 1)];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitFileStemAndExtension(string $fileName): array
    {
        $name = trim($fileName);
        if ($name === '') {
            return ['file', ''];
        }
        $pos = strrpos($name, '.');
        if ($pos === false || $pos === 0) {
            return [$name, ''];
        }
        return [substr($name, 0, $pos), substr($name, $pos)];
    }

    /**
     * @return array<int, string>
     */
    private function loadAllFolderPaths(int $storageId): array
    {
        if ($storageId <= 0) {
            return [];
        }

        $this->ensureFolderTable();
        $paths = [];

        $stmt = Db::pdo()->prepare('SELECT folder_path FROM file_folders WHERE storage_id = ?');
        $stmt->execute([$storageId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $path = $this->normalizeFolderPath((string)($row['folder_path'] ?? ''));
            if ($path !== '') {
                $paths[$path] = true;
            }
        }

        $stmt = Db::pdo()->prepare('SELECT DISTINCT folder_path FROM files WHERE storage_id = ? AND folder_path IS NOT NULL AND folder_path <> ?');
        $stmt->execute([$storageId, '']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $path = $this->normalizeFolderPath((string)($row['folder_path'] ?? ''));
            if ($path !== '') {
                $paths[$path] = true;
            }
        }

        $result = array_keys($paths);
        usort($result, static fn (string $a, string $b): int => strcasecmp($a, $b));
        return $result;
    }

    /**
     * @param array<int, int> $fileIds
     * @return array<int, array<string, mixed>>
     */
    private function loadSelectedFilesByIds(int $storageId, array $fileIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $fileIds), static fn (int $id): bool => $id > 0)));
        if ($storageId <= 0 || !$ids) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Db::pdo()->prepare("SELECT f.*, sl.driver, sl.config_json FROM files f JOIN storage_locations sl ON sl.id = f.storage_id WHERE f.storage_id = ? AND f.id IN ($in)");
        $stmt->execute(array_merge([$storageId], $ids));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int, int> $fileIds
     * @param array<int, string> $folderPaths
     * @return array<int, array<string, mixed>>
     */
    private function loadSelectedFilesForClipboard(int $storageId, array $fileIds, array $folderPaths): array
    {
        $selected = [];
        $seen = [];

        foreach ($this->loadSelectedFilesByIds($storageId, $fileIds) as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $selected[] = $row;
        }

        foreach ($folderPaths as $folderPath) {
            $stmt = Db::pdo()->prepare('SELECT f.*, sl.driver, sl.config_json FROM files f JOIN storage_locations sl ON sl.id = f.storage_id WHERE f.storage_id = ? AND (f.folder_path = ? OR f.folder_path LIKE ?)');
            $stmt->execute([$storageId, $folderPath, $folderPath . '/%']);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int)($row['id'] ?? 0);
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $selected[] = $row;
            }
        }

        return $selected;
    }

    /**
     * @param array<int, mixed> $folderPaths
     * @return array<int, string>
     */
    private function normalizeRootFolderSelections(array $folderPaths): array
    {
        $normalized = [];
        foreach ($folderPaths as $value) {
            $path = $this->normalizeFolderPath((string)$value);
            if ($path !== '') {
                $normalized[$path] = true;
            }
        }
        $paths = array_keys($normalized);
        usort($paths, static fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: strcasecmp($a, $b));

        $roots = [];
        foreach ($paths as $path) {
            $isNested = false;
            foreach ($roots as $root) {
                if ($path === $root || str_starts_with($path, $root . '/')) {
                    $isNested = true;
                    break;
                }
            }
            if (!$isNested) {
                $roots[] = $path;
            }
        }
        return $roots;
    }

    /**
     * @param array<int, string> $sourceFolders
     * @return array<string, string>
     */
    private function buildClipboardFolderMap(int $storageId, array $sourceFolders, string $targetFolder, bool $forMove): array
    {
        $map = [];
        $reservedPaths = [];
        foreach ($sourceFolders as $sourceFolder) {
            [, $folderName] = $this->splitFolderParentAndName($sourceFolder);
            if ($folderName === '') {
                continue;
            }
            $candidate = $targetFolder === '' ? $folderName : ($targetFolder . '/' . $folderName);
            if ($forMove && $candidate === $sourceFolder) {
                $map[$sourceFolder] = $sourceFolder;
                $reservedPaths[$sourceFolder] = true;
                continue;
            }
            $destPath = $this->nextAvailableFolderPath(
                $storageId,
                $targetFolder,
                $folderName,
                $forMove ? $sourceFolder : '',
                array_keys($reservedPaths)
            );
            $map[$sourceFolder] = $destPath;
            $reservedPaths[$destPath] = true;
        }
        return $map;
    }

    private function mapSelectedFolderPath(string $path, array $folderMap): ?string
    {
        $normalizedPath = $this->normalizeFolderPath($path);
        foreach ($folderMap as $sourceRoot => $destRoot) {
            if ($normalizedPath === $sourceRoot) {
                return $destRoot;
            }
            if (str_starts_with($normalizedPath, $sourceRoot . '/')) {
                return $destRoot . substr($normalizedPath, strlen($sourceRoot));
            }
        }
        return null;
    }

    /**
     * @param array<int, string> $candidatePaths
     * @return array<int, array{name:string,path:string}>
     */
    private function collectImmediateFolders(array $candidatePaths, string $currentPath): array
    {
        $normalizedCurrent = $this->normalizeFolderPath($currentPath);
        $prefix = $normalizedCurrent !== '' ? ($normalizedCurrent . '/') : '';
        $folders = [];
        foreach ($candidatePaths as $candidate) {
            $path = $this->normalizeFolderPath((string)$candidate);
            if ($path === '') {
                continue;
            }
            if ($prefix !== '' && !str_starts_with($path, $prefix)) {
                continue;
            }
            $rest = $prefix === '' ? $path : substr($path, strlen($prefix));
            if ($rest === false || $rest === '') {
                continue;
            }
            $first = explode('/', $rest, 2)[0];
            $childPath = $normalizedCurrent === '' ? $first : ($normalizedCurrent . '/' . $first);
            $folders[$childPath] = ['name' => $first, 'path' => $childPath];
        }
        uasort($folders, static fn (array $a, array $b): int => strcasecmp((string)$a['name'], (string)$b['name']));
        return array_values($folders);
    }

    private function ensureFolderTable(): void
    {
        if (self::$folderTableEnsured) {
            return;
        }
        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS file_folders (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                storage_id BIGINT NOT NULL,
                folder_path VARCHAR(500) NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uk_storage_folder (storage_id, folder_path),
                INDEX idx_storage_folder (storage_id, folder_path),
                CONSTRAINT fk_folder_storage FOREIGN KEY (storage_id) REFERENCES storage_locations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        self::$folderTableEnsured = true;
    }

    private function persistFolderPath(int $storageId, string $folderPath): void
    {
        $normalized = $this->normalizeFolderPath($folderPath);
        if ($storageId <= 0 || $normalized === '') {
            return;
        }
        $this->ensureFolderTable();
        $segments = explode('/', $normalized);
        $acc = '';
        $stmt = Db::pdo()->prepare('INSERT IGNORE INTO file_folders (storage_id, folder_path, created_at) VALUES (?, ?, NOW())');
        foreach ($segments as $seg) {
            $acc = $acc === '' ? $seg : ($acc . '/' . $seg);
            $stmt->execute([$storageId, $acc]);
        }
    }

    /**
     * @return array<int, array{id:int,md5:?string}>
     */
    private function listFilesByFolderAndName(int $storageId, string $folderPath, string $name): array
    {
        $normalizedFolder = $this->normalizeFolderPath($folderPath);
        $normalizedName = trim($name);
        if ($storageId <= 0 || $normalizedName === '') {
            return [];
        }
        if ($normalizedFolder === '') {
            $stmt = Db::pdo()->prepare(
                'SELECT id, md5 FROM files WHERE storage_id = ? AND original_name = ? AND (folder_path IS NULL OR folder_path = ?)'
            );
            $stmt->execute([$storageId, $normalizedName, '']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = Db::pdo()->prepare(
            'SELECT id, md5 FROM files WHERE storage_id = ? AND folder_path = ? AND original_name = ?'
        );
        $stmt->execute([$storageId, $normalizedFolder, $normalizedName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function hasFileNameConflict(int $storageId, string $folderPath, string $name, int $excludeFileId = 0): bool
    {
        $normalizedFolder = $this->normalizeFolderPath($folderPath);
        $normalizedName = trim($name);
        if ($storageId <= 0 || $normalizedName === '') {
            return false;
        }
        if ($normalizedFolder === '') {
            if ($excludeFileId > 0) {
                $stmt = Db::pdo()->prepare(
                    'SELECT id FROM files WHERE storage_id = ? AND original_name = ? AND (folder_path IS NULL OR folder_path = ?) AND id <> ? LIMIT 1'
                );
                $stmt->execute([$storageId, $normalizedName, '', $excludeFileId]);
            } else {
                $stmt = Db::pdo()->prepare(
                    'SELECT id FROM files WHERE storage_id = ? AND original_name = ? AND (folder_path IS NULL OR folder_path = ?) LIMIT 1'
                );
                $stmt->execute([$storageId, $normalizedName, '']);
            }
            return (int)($stmt->fetchColumn() ?: 0) > 0;
        }

        if ($excludeFileId > 0) {
            $stmt = Db::pdo()->prepare(
                'SELECT id FROM files WHERE storage_id = ? AND folder_path = ? AND original_name = ? AND id <> ? LIMIT 1'
            );
            $stmt->execute([$storageId, $normalizedFolder, $normalizedName, $excludeFileId]);
        } else {
            $stmt = Db::pdo()->prepare(
                'SELECT id FROM files WHERE storage_id = ? AND folder_path = ? AND original_name = ? LIMIT 1'
            );
            $stmt->execute([$storageId, $normalizedFolder, $normalizedName]);
        }
        return (int)($stmt->fetchColumn() ?: 0) > 0;
    }

    private function folderPathExists(int $storageId, string $folderPath): bool
    {
        $normalized = $this->normalizeFolderPath($folderPath);
        if ($storageId <= 0 || $normalized === '') {
            return false;
        }

        $this->ensureFolderTable();
        $stmt = Db::pdo()->prepare('SELECT id FROM file_folders WHERE storage_id = ? AND folder_path = ? LIMIT 1');
        $stmt->execute([$storageId, $normalized]);
        if ((int)($stmt->fetchColumn() ?: 0) > 0) {
            return true;
        }

        $stmt = Db::pdo()->prepare(
            'SELECT id FROM files WHERE storage_id = ? AND (folder_path = ? OR folder_path LIKE ?) LIMIT 1'
        );
        $stmt->execute([$storageId, $normalized, $normalized . '/%']);
        return (int)($stmt->fetchColumn() ?: 0) > 0;
    }

    private function nextAvailableFileName(int $storageId, string $folderPath, string $desiredName, int $excludeFileId = 0): string
    {
        $normalizedName = trim($desiredName);
        if ($normalizedName === '') {
            $normalizedName = '未命名文件';
        }
        if (!$this->hasFileNameConflict($storageId, $folderPath, $normalizedName, $excludeFileId)) {
            return $normalizedName;
        }

        [$stem, $ext] = $this->splitFileStemAndExtension($normalizedName);
        $stem = trim($stem) !== '' ? trim($stem) : '未命名文件';
        for ($i = 1; $i <= 999; $i++) {
            $candidate = sprintf('%s (%d)%s', $stem, $i, $ext);
            if (!$this->hasFileNameConflict($storageId, $folderPath, $candidate, $excludeFileId)) {
                return $candidate;
            }
        }

        return sprintf('%s_%s%s', $stem, substr(bin2hex(random_bytes(4)), 0, 6), $ext);
    }

    private function nextAvailableFolderPath(int $storageId, string $parentFolderPath, string $desiredName, string $excludePath = '', array $reservedPaths = []): string
    {
        $parent = $this->normalizeFolderPath($parentFolderPath);
        $name = trim($desiredName);
        if ($name === '') {
            $name = '新建文件夹';
        }
        $reserved = [];
        foreach ($reservedPaths as $value) {
            $path = $this->normalizeFolderPath((string)$value);
            if ($path !== '') {
                $reserved[$path] = true;
            }
        }
        $candidate = $parent === '' ? $name : ($parent . '/' . $name);
        if (
            $candidate === $excludePath
            || (
                !isset($reserved[$candidate])
                && !$this->hasFileNameConflict($storageId, $parent, $name)
                && !$this->folderPathExists($storageId, $candidate)
            )
        ) {
            return $candidate;
        }

        for ($i = 1; $i <= 999; $i++) {
            $candidateName = sprintf('%s (%d)', $name, $i);
            $candidatePath = $parent === '' ? $candidateName : ($parent . '/' . $candidateName);
            if ($candidatePath === $excludePath) {
                return $candidatePath;
            }
            if (isset($reserved[$candidatePath])) {
                continue;
            }
            if ($this->hasFileNameConflict($storageId, $parent, $candidateName)) {
                continue;
            }
            if ($this->folderPathExists($storageId, $candidatePath)) {
                continue;
            }
            return $candidatePath;
        }

        return ($parent === '' ? $name : ($parent . '/' . $name)) . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
    }

    private function generateObjectKeyForName(string $fileName): string
    {
        $ext = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]+/', '', $ext) ?: '';
        $token = bin2hex(random_bytes(16));
        $prefix = date('Y/m/d');
        return $prefix . '/' . $token . ($ext !== '' ? ('.' . $ext) : '');
    }

    /**
     * @param array<string, string> $folderMap
     */
    private function copyFolderMarkersByMap(int $storageId, array $folderMap): void
    {
        if ($storageId <= 0 || !$folderMap) {
            return;
        }

        $this->ensureFolderTable();
        foreach ($folderMap as $sourceRoot => $destRoot) {
            if ($destRoot !== '') {
                $this->persistFolderPath($storageId, $destRoot);
            }

            $stmt = Db::pdo()->prepare('SELECT folder_path FROM file_folders WHERE storage_id = ? AND (folder_path = ? OR folder_path LIKE ?)');
            $stmt->execute([$storageId, $sourceRoot, $sourceRoot . '/%']);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $sourcePath = $this->normalizeFolderPath((string)($row['folder_path'] ?? ''));
                if ($sourcePath === '') {
                    continue;
                }
                $mappedPath = $this->mapSelectedFolderPath($sourcePath, $folderMap);
                if ($mappedPath !== null && $mappedPath !== '') {
                    $this->persistFolderPath($storageId, $mappedPath);
                }
            }
        }
    }

    private function moveFolderPath(int $storageId, string $folderPath, string $newFolderPath): void
    {
        $source = $this->normalizeFolderPath($folderPath);
        $target = $this->normalizeFolderPath($newFolderPath);
        if ($storageId <= 0 || $source === '' || $target === '' || $source === $target) {
            return;
        }
        if (str_starts_with($target . '/', $source . '/')) {
            throw new \RuntimeException('Cannot move folder into itself');
        }

        [$targetParent, $targetName] = $this->splitFolderParentAndName($target);
        if ($targetName === '') {
            throw new \RuntimeException('Invalid target folder');
        }
        if ($this->hasFileNameConflict($storageId, $targetParent, $targetName)) {
            throw new \RuntimeException('A file with the same name exists in target path');
        }
        if ($this->folderPathExists($storageId, $target) && $target !== $source) {
            throw new \RuntimeException('Target folder already exists');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('UPDATE files SET folder_path = ? WHERE storage_id = ? AND folder_path = ?');
        $stmt->execute([$target, $storageId, $source]);

        $stmt = $pdo->prepare('SELECT id, folder_path FROM files WHERE storage_id = ? AND folder_path LIKE ?');
        $stmt->execute([$storageId, $source . '/%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $u = $pdo->prepare('UPDATE files SET folder_path = ? WHERE id = ?');
        foreach ($rows as $r) {
            $old = (string)$r['folder_path'];
            $new = $target . substr($old, strlen($source));
            $u->execute([$new, (int)$r['id']]);
        }

        $this->ensureFolderTable();
        $folderRowsStmt = $pdo->prepare('SELECT folder_path FROM file_folders WHERE storage_id = ? AND (folder_path = ? OR folder_path LIKE ?)');
        $folderRowsStmt->execute([$storageId, $source, $source . '/%']);
        $folderRows = $folderRowsStmt->fetchAll(PDO::FETCH_ASSOC);
        $insStmt = $pdo->prepare('INSERT IGNORE INTO file_folders (storage_id, folder_path, created_at) VALUES (?, ?, NOW())');
        $delStmt = $pdo->prepare('DELETE FROM file_folders WHERE storage_id = ? AND folder_path = ?');
        foreach ($folderRows as $row) {
            $oldPath = $this->normalizeFolderPath((string)($row['folder_path'] ?? ''));
            if ($oldPath === '') {
                continue;
            }
            $mappedPath = $target . substr($oldPath, strlen($source));
            $insStmt->execute([$storageId, $mappedPath]);
            $delStmt->execute([$storageId, $oldPath]);
        }

        $this->persistFolderPath($storageId, $target);
    }

    private function buildCollisionSubfolderPath(int $storageId, string $baseFolderPath): string
    {
        $base = $this->normalizeFolderPath($baseFolderPath);
        $suffix = date('His');
        for ($i = 0; $i < 50; $i++) {
            $folderName = $i === 0 ? $suffix : ($suffix . '_' . $i);
            $candidate = $base === '' ? $folderName : ($base . '/' . $folderName);
            if ($this->hasFileNameConflict($storageId, $base, $folderName)) {
                continue;
            }
            if ($this->folderPathExists($storageId, $candidate)) {
                continue;
            }
            $this->persistFolderPath($storageId, $candidate);
            return $candidate;
        }

        $folderName = date('His') . '_' . substr(bin2hex(random_bytes(4)), 0, 4);
        $fallback = $base === '' ? $folderName : ($base . '/' . $folderName);
        $this->persistFolderPath($storageId, $fallback);
        return $fallback;
    }

    private function resolveCopiedDestinationFolder(string $sourceFolder, array $selectedFolders, string $targetFolder): string
    {
        $source = $this->normalizeFolderPath($sourceFolder);
        $target = $this->normalizeFolderPath($targetFolder);
        if ($target === '') {
            return $source;
        }

        foreach ($selectedFolders as $selected) {
            $selectedPath = $this->normalizeFolderPath((string)$selected);
            if ($selectedPath === '') {
                continue;
            }
            if ($source === $selectedPath || str_starts_with($source, $selectedPath . '/')) {
                [, $folderName] = $this->splitFolderParentAndName($selectedPath);
                if ($folderName === '') {
                    continue;
                }
                if ($source === $selectedPath) {
                    return $target . '/' . $folderName;
                }
                $relative = substr($source, strlen($selectedPath) + 1);
                return $target . '/' . $folderName . '/' . $relative;
            }
        }

        return $target;
    }

    /**
     * @return array{folder_path:string,duplicate_file_id:int,duplicate_md5:string}
     */
    private function resolveUploadPathConflict(int $storageId, string $folderPath, string $fileName, string $expectedMd5): array
    {
        $normalizedFolder = $this->normalizeFolderPath($folderPath);
        $normalizedMd5 = strtolower(trim($expectedMd5));
        $matchedFiles = $this->listFilesByFolderAndName($storageId, $normalizedFolder, $fileName);
        if (!$matchedFiles) {
            return [
                'folder_path' => $normalizedFolder,
                'duplicate_file_id' => 0,
                'duplicate_md5' => '',
            ];
        }

        if ($normalizedMd5 !== '') {
            foreach ($matchedFiles as $row) {
                $rowMd5 = strtolower(trim((string)($row['md5'] ?? '')));
                if ($rowMd5 !== '' && $rowMd5 === $normalizedMd5) {
                    return [
                        'folder_path' => $normalizedFolder,
                        'duplicate_file_id' => (int)($row['id'] ?? 0),
                        'duplicate_md5' => $rowMd5,
                    ];
                }
            }
        }

        $newFolder = $this->buildCollisionSubfolderPath($storageId, $normalizedFolder);
        return [
            'folder_path' => $newFolder,
            'duplicate_file_id' => 0,
            'duplicate_md5' => '',
        ];
    }

    private function finishResponseAndDelayChunkCleanup(StorageAdapter $adapter, string $token, int $totalChunks): void
    {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }
            @ob_flush();
            @flush();
        }

        if (self::CHUNK_CLEANUP_DELAY_SECONDS > 0) {
            sleep(self::CHUNK_CLEANUP_DELAY_SECONDS);
        }

        try {
            $adapter->cleanupUploadChunks($token, $totalChunks);
        } catch (\Throwable $cleanupError) {
            error_log(sprintf('[upload/complete] chunk cleanup failed token=%s err=%s', $token, $cleanupError->getMessage()));
        }
    }

    private function shareCreate(): void
    {
        $data = $this->jsonBody();
        $title = trim((string)($data['title'] ?? ''));
        $fileIds = $data['file_ids'] ?? [];
        $pass = trim((string)($data['password'] ?? ''));
        $expiresAt = trim((string)($data['expires_at'] ?? ''));

        if (!is_array($fileIds) || count($fileIds) === 0) {
            Response::json(['ok' => false, 'message' => 'Please select files first'], 422);
            return;
        }

        $fileIds = array_values(array_unique(array_map('intval', $fileIds)));
        $shareItems = $this->loadShareFileSnapshots($fileIds);
        if (!$shareItems) {
            Response::json(['ok' => false, 'message' => 'No shareable files found'], 404);
            return;
        }

        $code = rtrim(strtr(base64_encode(random_bytes(8)), '+/', '-_'), '=');
        $hash = $pass ? password_hash($pass, PASSWORD_BCRYPT) : null;

        Db::pdo()->beginTransaction();
        try {
            ShareStore::create([
                'code' => $code,
                'title' => $title ?: null,
                'password_hash' => $hash,
                'expires_at' => $expiresAt ?: null,
                'allow_folder' => 1,
                'items' => $shareItems,
            ]);
            Db::pdo()->commit();

            $pwdQuery = $pass ? ('&pwd=' . urlencode($pass)) : '';
            $link = Url::route('/share') . '&code=' . urlencode($code) . $pwdQuery;
            Response::json(['ok' => true, 'code' => $code, 'link' => $link]);
        } catch (\Throwable $e) {
            Db::pdo()->rollBack();
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function shareList(): void
    {
        $rows = ShareStore::listSummary(200);
        Response::json(['ok' => true, 'items' => $rows]);
    }

    private function shareDelete(): void
    {
        $data = $this->jsonBody();
        $id = (int)($data['id'] ?? 0);
        $code = trim((string)($data['code'] ?? ''));
        if ($id <= 0 && $code === '') {
            Response::json(['ok' => false, 'message' => 'Invalid share id or code'], 422);
            return;
        }
        if ($code !== '') {
            ShareStore::deleteByCode($code);
        } else {
            ShareStore::deleteById($id);
        }
        Response::json(['ok' => true]);
    }

    private function shareMeta(): void
    {
        $code = trim((string)($_GET['code'] ?? ''));
        $customButtons = $this->readCustomButtons();
        $share = ShareStore::findByCode($code);

        if (!$share) {
            Response::json(['ok' => false, 'message' => 'Share cancelled or expired.'], 404);
            return;
        }
        if (!empty($share['expires_at']) && strtotime((string)$share['expires_at']) < time()) {
            Response::json(['ok' => false, 'message' => 'Share cancelled or expired.'], 410);
            return;
        }

        $unlocked = empty($share['password_hash']) || (!empty($_SESSION['share_access'][$code]) && $_SESSION['share_access'][$code] === true);
        if (!$unlocked) {
            Response::json([
                'ok' => true,
                'locked' => true,
                'share' => [
                    'title' => $share['title'],
                    'code' => $share['code'],
                    'created_at' => $share['created_at'],
                ],
                'custom_buttons' => $customButtons,
            ]);
            return;
        }
        $this->setShareAccessCookie($code, (string)($share['expires_at'] ?? ''));

        $rows = [];
        foreach ((array)$share['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = [
                'id' => (int)($item['id'] ?? 0),
                'original_name' => (string)($item['original_name'] ?? ''),
                'size' => (int)($item['size'] ?? 0),
                'mime_type' => (string)($item['mime_type'] ?? 'application/octet-stream'),
                'md5' => isset($item['md5']) ? (string)$item['md5'] : null,
                'created_at' => (string)($item['created_at'] ?? ''),
            ];
        }

        $base = Url::route('/api/file/chunk');
        $items = [];
        foreach ($rows as $row) {
            $fileId = (int)$row['id'];
            $row['chunk_url_base'] = $base
                . '&file_id=' . $fileId
                . '&code=' . rawurlencode((string)$share['code']);
            $items[] = $row;
        }

        Response::json([
            'ok' => true,
            'locked' => false,
            'share' => [
                'title' => $share['title'],
                'code' => $share['code'],
                'created_at' => $share['created_at'],
            ],
            'items' => $items,
            'chunk_size' => self::CHUNK_SIZE_BYTES,
            'custom_buttons' => $customButtons,
        ]);
    }

    private function customButtonsGet(): void
    {
        Response::json([
            'ok' => true,
            'buttons' => $this->readCustomButtons(),
        ]);
    }

    private function customButtonsSave(): void
    {
        $data = $this->jsonBody();
        $buttons = $this->normalizeCustomButtons($data['buttons'] ?? []);

        $stmt = Db::pdo()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        $stmt->execute(['share_custom_buttons', json_encode($buttons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);

        Response::json(['ok' => true, 'buttons' => $buttons]);
    }

    private function shareUnlock(): void
    {
        $data = $this->jsonBody();
        $code = trim((string)($data['code'] ?? ''));
        $password = trim((string)($data['password'] ?? ''));

        $share = ShareStore::findByCode($code);
        if (!$share) {
            Response::json(['ok' => false, 'message' => 'Share cancelled or expired.'], 404);
            return;
        }
        if (!empty($share['expires_at']) && strtotime((string)$share['expires_at']) < time()) {
            Response::json(['ok' => false, 'message' => 'Share cancelled or expired.'], 410);
            return;
        }
        if (!password_verify($password, (string)$share['password_hash'])) {
            Response::json(['ok' => false, 'message' => 'Invalid extraction code'], 403);
            return;
        }

        $_SESSION['share_access'][$code] = true;
        $this->setShareAccessCookie($code, (string)($share['expires_at'] ?? ''));
        Response::json(['ok' => true]);
    }

    private function fileChunk(): void
    {
        $fileId = (int)($_GET['file_id'] ?? 0);
        $chunk = (int)($_GET['chunk'] ?? -1);
        $chunkSize = self::CHUNK_SIZE_BYTES;
        $shareCode = trim((string)($_GET['code'] ?? ''));
        $exp = (int)($_GET['exp'] ?? 0);
        $sig = trim((string)($_GET['sig'] ?? ''));

        $sharedItem = null;
        if (empty($_SESSION['user'])) {
            $signedOk = $this->verifySignedChunkAccess($shareCode, $fileId, $exp, $sig);
            $cookieOk = $this->verifyShareAccessCookie($shareCode);
            $sessionOk = $shareCode !== '' && !empty($_SESSION['share_access'][$shareCode]);
            if (!$signedOk && !$cookieOk && !$sessionOk) {
                http_response_code(403);
                echo 'Forbidden';
                return;
            }
            if ($shareCode === '') {
                http_response_code(403);
                echo 'Forbidden';
                return;
            }
            $sharedItem = $this->findActiveShareItem($shareCode, $fileId);
            if ($sharedItem === null) {
                http_response_code(403);
                echo 'Forbidden';
                return;
            }
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (is_array($sharedItem)) {
            $adapter = StorageFactory::make($sharedItem);
            $adapter->streamChunk((string)$sharedItem['object_key'], $chunk, $chunkSize);
            return;
        }

        if ($fileId <= 0) {
            http_response_code(422);
            echo 'Invalid file id';
            return;
        }

        if (!empty($shareCode)) {
            $sharedItem = $this->findActiveShareItem($shareCode, $fileId);
            if (is_array($sharedItem)) {
                $adapter = StorageFactory::make($sharedItem);
                $adapter->streamChunk((string)$sharedItem['object_key'], $chunk, $chunkSize);
                return;
            }
        }

        $stmt = Db::pdo()->prepare('SELECT f.*, sl.driver, sl.config_json FROM files f JOIN storage_locations sl ON sl.id = f.storage_id WHERE f.id = ? LIMIT 1');
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$file) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $adapter = StorageFactory::make($file);
        $adapter->streamChunk((string)$file['object_key'], $chunk, $chunkSize);
    }

    private function signingKey(): string
    {
        $cfg = (string)\App\Core\Config::get('app.signing_key', '');
        if ($cfg !== '') {
            return $cfg;
        }
        return hash('sha256', (string)\App\Core\Config::get('app.url', 'mungsos-drive'));
    }

    private function buildChunkSignature(string $shareCode, int $fileId, int $exp): string
    {
        $payload = $shareCode . '|' . $fileId . '|' . $exp;
        return hash_hmac('sha256', $payload, $this->signingKey());
    }

    private function verifySignedChunkAccess(string $shareCode, int $fileId, int $exp, string $sig): bool
    {
        if ($shareCode === '' || $fileId <= 0 || $exp <= 0 || $sig === '') {
            return false;
        }
        if ($exp < time()) {
            return false;
        }
        $expected = $this->buildChunkSignature($shareCode, $fileId, $exp);
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        return $this->findActiveShareItem($shareCode, $fileId) !== null;
    }

    private function shareCookieName(string $shareCode): string
    {
        return 'mgs_share_' . substr(hash('sha256', $shareCode), 0, 24);
    }

    private function setShareAccessCookie(string $shareCode, string $expiresAt): void
    {
        if ($shareCode === '') {
            return;
        }
        $now = time();
        $cookieExp = $now + self::SHARE_COOKIE_TTL_SECONDS;
        $shareExpTs = $expiresAt !== '' ? strtotime($expiresAt) : false;
        if (is_int($shareExpTs) && $shareExpTs > 0) {
            $cookieExp = min($cookieExp, $shareExpTs);
        }
        if ($cookieExp <= $now) {
            return;
        }

        $payload = $shareCode . '|' . $cookieExp;
        $sig = hash_hmac('sha256', $payload, $this->signingKey());
        $cookieValue = $cookieExp . '.' . $sig;
        $cookiePath = Url::scriptDir() ?: '/';
        $isSecure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';

        setcookie($this->shareCookieName($shareCode), $cookieValue, [
            'expires' => $cookieExp,
            'path' => $cookiePath,
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function verifyShareAccessCookie(string $shareCode): bool
    {
        if ($shareCode === '') {
            return false;
        }
        $raw = (string)($_COOKIE[$this->shareCookieName($shareCode)] ?? '');
        if ($raw === '') {
            return false;
        }
        $parts = explode('.', $raw, 2);
        if (count($parts) !== 2) {
            return false;
        }
        $exp = (int)$parts[0];
        $sig = trim((string)$parts[1]);
        if ($exp <= time() || $sig === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $shareCode . '|' . $exp, $this->signingKey());
        return hash_equals($expected, $sig);
    }

    private function findActiveShareItem(string $shareCode, int $fileId): ?array
    {
        if ($shareCode === '' || $fileId <= 0) {
            return null;
        }
        $share = ShareStore::findByCode($shareCode);
        if (!$share) {
            return null;
        }
        if (!empty($share['expires_at']) && strtotime((string)$share['expires_at']) < time()) {
            return null;
        }
        foreach ((array)($share['items'] ?? []) as $item) {
            if ((int)($item['id'] ?? 0) === $fileId) {
                return $item;
            }
        }
        return null;
    }

    private function systemInfo(): void
    {
        $fileTotalCount = 0;
        $fileTotalSize = 0;

        try {
            $stats = Db::pdo()->query('SELECT COUNT(*) AS total_count, COALESCE(SUM(size), 0) AS total_size FROM files')->fetch(PDO::FETCH_ASSOC) ?: [];
            $fileTotalCount = (int)($stats['total_count'] ?? 0);
            $fileTotalSize = (int)($stats['total_size'] ?? 0);
        } catch (\Throwable $e) {
            $fileTotalCount = 0;
            $fileTotalSize = 0;
        }

        $tz = new \DateTimeZone('Asia/Shanghai');
        $nowUtc8 = (new \DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');

        Response::json([
            'ok' => true,
            'file_total_count' => $fileTotalCount,
            'file_total_size' => $fileTotalSize,
            'current_time_utc8' => $nowUtc8,
        ]);
    }

    /**
     * @return array<int, array{id:int,storage_id:int,object_key:string,driver:string,config_json:string}>
     */
    private function collectFilesForDelete(array $fileIds, array $folderPaths, int $storageId = 0): array
    {
        $pdo = Db::pdo();
        $selected = [];
        $seen = [];

        if ($fileIds) {
            $in = implode(',', array_fill(0, count($fileIds), '?'));
            $stmt = $pdo->prepare("SELECT f.id, f.storage_id, f.object_key, f.original_name, f.size, sl.driver, sl.config_json FROM files f JOIN storage_locations sl ON sl.id = f.storage_id WHERE f.id IN ($in)");
            $stmt->execute($fileIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int)$row['id'];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $selected[] = $row;
            }
        }

        foreach ($folderPaths as $fp) {
            $path = $this->normalizeFolderPath($fp);
            if ($path === '' || $storageId <= 0) {
                continue;
            }
            $stmt = $pdo->prepare('SELECT f.id, f.storage_id, f.object_key, f.original_name, f.size, sl.driver, sl.config_json FROM files f JOIN storage_locations sl ON sl.id = f.storage_id WHERE f.storage_id = ? AND (f.folder_path = ? OR f.folder_path LIKE ?)');
            $stmt->execute([$storageId, $path, $path . '/%']);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int)$row['id'];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $selected[] = $row;
            }
        }

        return $selected;
    }

    /**
     * Remove temporary chunk records/files matched by deleted files.
     *
     * @param array<int, array<string, mixed>> $selected
     * @param array<int, \App\Storage\StorageAdapter> $adapters
     */
    private function cleanupMatchedUploadSessions(array $selected, array $adapters): void
    {
        if (!$selected) {
            return;
        }

        $pdo = Db::pdo();
        $sessionIds = [];
        $tokenRows = [];
        $stmt = $pdo->prepare(
            'SELECT id, upload_token, total_chunks, storage_id
             FROM upload_sessions
             WHERE storage_id = ?
               AND original_name = ?
               AND total_size = ?
               AND status IN (\'completed\', \'md5_mismatch\')'
        );

        foreach ($selected as $row) {
            $stmt->execute([
                (int)$row['storage_id'],
                (string)($row['original_name'] ?? ''),
                (int)($row['size'] ?? 0),
            ]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $sid = (int)$s['id'];
                if (isset($sessionIds[$sid])) {
                    continue;
                }
                $sessionIds[$sid] = true;
                $tokenRows[] = [
                    'id' => $sid,
                    'storage_id' => (int)$s['storage_id'],
                    'token' => (string)$s['upload_token'],
                    'total_chunks' => (int)$s['total_chunks'],
                ];
            }
        }

        if (!$tokenRows) {
            return;
        }

        foreach ($tokenRows as $s) {
            $storageId = (int)$s['storage_id'];
            if (!isset($adapters[$storageId])) {
                continue;
            }
            try {
                $adapters[$storageId]->cleanupUploadChunks((string)$s['token'], (int)$s['total_chunks']);
            } catch (\Throwable $e) {
                error_log(sprintf('[files/delete] cleanup chunks failed token=%s err=%s', $s['token'], $e->getMessage()));
            }
        }

        $idList = array_keys($sessionIds);
        if (!$idList) {
            return;
        }
        $in = implode(',', array_fill(0, count($idList), '?'));
        $delChunks = $pdo->prepare("DELETE FROM upload_chunks WHERE upload_session_id IN ($in)");
        $delChunks->execute($idList);
        $delSessions = $pdo->prepare("DELETE FROM upload_sessions WHERE id IN ($in)");
        $delSessions->execute($idList);
    }

    /**
     * @return array<int, array{text:string,url:string}>
     */
    private function readCustomButtons(): array
    {
        $stmt = Db::pdo()->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
        $stmt->execute(['share_custom_buttons']);
        $raw = (string)($stmt->fetchColumn() ?: '');
        $decoded = json_decode($raw, true);
        return $this->normalizeCustomButtons($decoded);
    }

    /**
     * @param mixed $buttonsRaw
     * @return array<int, array{text:string,url:string}>
     */
    private function normalizeCustomButtons($buttonsRaw): array
    {
        $buttons = is_array($buttonsRaw) ? array_values($buttonsRaw) : [];
        $defaults = [
            ['text' => 'Button 1', 'url' => ''],
            ['text' => 'Button 2', 'url' => ''],
        ];

        $result = [];
        for ($i = 0; $i < 2; $i++) {
            $row = $buttons[$i] ?? [];
            $text = trim((string)($row['text'] ?? $defaults[$i]['text']));
            $url = trim((string)($row['url'] ?? $defaults[$i]['url']));
            if ($text === '') {
                $text = $defaults[$i]['text'];
            }
            $result[] = ['text' => $text, 'url' => $url];
        }

        return $result;
    }

    /**
     * @param array<int, int|string> $fileIds
     * @return array<int, array{
     *   id:int,storage_id:int,driver:string,config_json:string,object_key:string,original_name:string,mime_type:string,
     *   size:int,md5:?string,folder_path:?string,created_at:string
     * }>
     */
    private function loadShareFileSnapshots(array $fileIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $fileIds), static fn (int $id): bool => $id > 0)));
        if (!$ids) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Db::pdo()->prepare("SELECT f.id, f.storage_id, f.object_key, f.original_name, f.mime_type, f.size, f.md5, f.folder_path, f.created_at, sl.driver, sl.config_json FROM files f JOIN storage_locations sl ON sl.id = f.storage_id WHERE f.id IN ($in)");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int)$row['id']] = $row;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $row = $byId[$id];
                $ordered[] = [
                    'id' => (int)$row['id'],
                    'storage_id' => (int)$row['storage_id'],
                    'driver' => (string)$row['driver'],
                    'config_json' => (string)$row['config_json'],
                    'object_key' => (string)$row['object_key'],
                    'original_name' => (string)$row['original_name'],
                    'mime_type' => (string)($row['mime_type'] ?: 'application/octet-stream'),
                    'size' => (int)$row['size'],
                    'md5' => $row['md5'] !== null ? (string)$row['md5'] : null,
                    'folder_path' => $row['folder_path'] !== null ? (string)$row['folder_path'] : null,
                    'created_at' => (string)$row['created_at'],
                ];
            }
        }
        return $ordered;
    }
}
