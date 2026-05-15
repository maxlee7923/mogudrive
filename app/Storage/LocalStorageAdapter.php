<?php

declare(strict_types=1);

namespace App\Storage;

use RuntimeException;

final class LocalStorageAdapter implements StorageAdapter
{
    private const APPEND_STATE_FILE = '.append.state.json';
    private const APPEND_DATA_FILE = '.append.data.tmp';
    private const APPEND_LOCK_FILE = '.append.lock';
    private const STREAM_BUFFER_SIZE = 1024 * 1024;

    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        if (!is_dir($this->basePath) && !mkdir($this->basePath, 0775, true) && !is_dir($this->basePath)) {
            throw new RuntimeException('Cannot create storage path.');
        }
    }

    public function saveChunk(string $uploadToken, int $chunkIndex, string $tmpFile): string
    {
        $dir = $this->chunkDir($uploadToken);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create chunk path.');
        }

        $lock = $this->openAppendLock($dir, false);
        try {
            $state = $this->readAppendState($dir);
            $nextIndex = (int)($state['next_index'] ?? 0);

            // Idempotent handling for retries: treat already-written chunks as success.
            if ($chunkIndex < $nextIndex) {
                return md5_file($tmpFile) ?: '';
            }
            if ($chunkIndex > $nextIndex) {
                throw new RuntimeException(sprintf('Out-of-order chunk: expected %d got %d', $nextIndex, $chunkIndex));
            }

            $appendPath = $this->appendDataPath($dir);
            $out = fopen($appendPath, 'c+b');
            if (!$out) {
                throw new RuntimeException('Cannot open append file.');
            }
            if (fseek($out, 0, SEEK_END) !== 0) {
                fclose($out);
                throw new RuntimeException('Cannot seek append file.');
            }
            $beforeSize = ftell($out);
            if ($beforeSize === false || $beforeSize < 0) {
                fclose($out);
                throw new RuntimeException('Cannot stat append file.');
            }
            $in = fopen($tmpFile, 'rb');
            if (!$in) {
                fclose($out);
                throw new RuntimeException('Cannot open uploaded chunk temp file.');
            }

            try {
                $copied = stream_copy_to_stream($in, $out);
            } finally {
                fclose($in);
            }

            if ($copied === false || (int)$copied <= 0) {
                fclose($out);
                throw new RuntimeException('Failed to append chunk.');
            }
            if (!fflush($out)) {
                fclose($out);
                throw new RuntimeException('Failed to flush append file.');
            }
            fclose($out);

            $writtenBytes = (int)($state['bytes_written'] ?? 0) + (int)$copied;
            try {
                $this->writeAppendState($dir, [
                    'next_index' => $nextIndex + 1,
                    'bytes_written' => $writtenBytes,
                ]);
            } catch (\Throwable $e) {
                $rollback = fopen($appendPath, 'c+b');
                if ($rollback) {
                    @ftruncate($rollback, (int)$beforeSize);
                    fclose($rollback);
                }
                throw $e;
            }
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }

        return md5_file($tmpFile) ?: '';
    }

    public function mergeChunks(string $uploadToken, string $targetKey, int $totalChunks, ?callable $onProgress = null): void
    {
        if ($totalChunks <= 0) {
            throw new RuntimeException('Invalid chunk count.');
        }

        $chunkDir = $this->chunkDir($uploadToken);
        if (!is_dir($chunkDir)) {
            throw new RuntimeException('Chunk directory not found.');
        }

        $fullPath = $this->full($targetKey);
        $targetDir = dirname($fullPath);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Cannot create target path.');
        }

        $lock = $this->openAppendLock($chunkDir, true);
        try {
            $state = $this->readAppendState($chunkDir);
            $nextIndex = (int)($state['next_index'] ?? 0);
            if ($nextIndex < $totalChunks) {
                throw new RuntimeException('Missing chunk: ' . $nextIndex);
            }

            $appendPath = $this->appendDataPath($chunkDir);
            if (!is_file($appendPath)) {
                throw new RuntimeException('Temporary assembled file not found.');
            }

            if (is_file($fullPath) && !@unlink($fullPath)) {
                throw new RuntimeException('Cannot replace existing target file.');
            }
            if (!@rename($appendPath, $fullPath)) {
                if (!@copy($appendPath, $fullPath)) {
                    throw new RuntimeException('Failed to finalize uploaded file.');
                }
                @unlink($appendPath);
            }

            @unlink($this->appendStatePath($chunkDir));
            if ($onProgress !== null) {
                $onProgress($totalChunks, $totalChunks);
            }
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($this->appendLockPath($chunkDir));
        }
    }

    public function findMissingChunks(string $uploadToken, array $expectedChunkSizes): array
    {
        $dir = $this->chunkDir($uploadToken);
        $expected = [];
        foreach ($expectedChunkSizes as $idx => $expectedSize) {
            $i = (int)$idx;
            if ($i < 0) {
                continue;
            }
            $expected[$i] = (int)$expectedSize;
        }
        if (!$expected) {
            return [];
        }
        ksort($expected, SORT_NUMERIC);

        if (!is_dir($dir)) {
            return array_values(array_keys($expected));
        }

        $state = $this->readAppendState($dir);
        $nextIndex = max(0, (int)($state['next_index'] ?? 0));
        $appendPath = $this->appendDataPath($dir);
        $actualSize = is_file($appendPath) ? (int)(filesize($appendPath) ?: -1) : -1;
        if ($actualSize < 0) {
            return array_values(array_keys($expected));
        }

        $completedBySize = 0;
        $prefixExpectedSize = 0;
        foreach ($expected as $idx => $expectedSize) {
            $prefixExpectedSize += max(0, (int)$expectedSize);
            if ($actualSize >= $prefixExpectedSize) {
                $completedBySize = $idx + 1;
                continue;
            }
            break;
        }
        $completedIndex = min($nextIndex, $completedBySize);

        $missing = [];
        foreach (array_keys($expected) as $idx) {
            if ((int)$idx >= $completedIndex) {
                $missing[] = (int)$idx;
            }
        }
        sort($missing, SORT_NUMERIC);
        return array_values(array_unique($missing, SORT_NUMERIC));
    }

    public function cleanupUploadChunks(string $uploadToken, int $totalChunks): void
    {
        $dir = $this->chunkDir($uploadToken);
        if (!is_dir($dir)) {
            return;
        }
        $this->removeDirectoryContents($dir);
        @rmdir($dir);
    }

    public function putObjectFromPath(string $targetKey, string $localPath): void
    {
        $target = $this->full($targetKey);
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!rename($localPath, $target)) {
            copy($localPath, $target);
            @unlink($localPath);
        }
    }

    public function copyObject(string $sourceObjectKey, string $targetObjectKey): void
    {
        $source = $this->full($sourceObjectKey);
        if (!is_file($source)) {
            throw new RuntimeException('Source object not found: ' . $sourceObjectKey);
        }

        $target = $this->full($targetObjectKey);
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create target path.');
        }

        if (!copy($source, $target)) {
            throw new RuntimeException('Failed to copy object: ' . $sourceObjectKey);
        }
    }

    public function deleteObject(string $objectKey): void
    {
        $path = $this->full($objectKey);
        if (!is_file($path)) {
            return;
        }
        if (!@unlink($path)) {
            throw new RuntimeException('Failed to delete object: ' . $objectKey);
        }
    }

    public function streamChunk(string $objectKey, int $chunkIndex, int $chunkSize): void
    {
        $path = $this->full($objectKey);
        if (!is_file($path)) {
            http_response_code(404);
            exit('Not found');
        }
        $offset = $chunkIndex * $chunkSize;
        $size = filesize($path) ?: 0;
        if ($offset >= $size) {
            http_response_code(416);
            exit('Chunk out of range');
        }
        $length = min($chunkSize, $size - $offset);
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . $length);
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=31536000, immutable');
        $fp = fopen($path, 'rb');
        if (!$fp) {
            throw new RuntimeException('Cannot open object for streaming.');
        }
        if (fseek($fp, $offset) !== 0) {
            fclose($fp);
            throw new RuntimeException('Cannot seek object for streaming.');
        }

        $this->prepareStreamingOutput();
        $remaining = $length;
        try {
            while ($remaining > 0 && !feof($fp)) {
                $chunk = fread($fp, min(self::STREAM_BUFFER_SIZE, $remaining));
                if ($chunk === false) {
                    throw new RuntimeException('Failed to read object chunk.');
                }
                if ($chunk === '') {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
                $this->flushStreamingOutput();
                if (connection_aborted()) {
                    break;
                }
            }
        } finally {
            fclose($fp);
        }
    }

    public function objectSize(string $objectKey): int
    {
        return filesize($this->full($objectKey)) ?: 0;
    }

    public function objectMd5(string $objectKey): string
    {
        return md5_file($this->full($objectKey)) ?: '';
    }

    public function makeObjectUrl(string $objectKey): string
    {
        return '/public/api/file/raw?key=' . urlencode($objectKey);
    }

    private function full(string $key): string
    {
        return rtrim($this->basePath, '/\\') . '/' . ltrim($key, '/\\');
    }

    private function chunkDir(string $uploadToken): string
    {
        return rtrim($this->basePath, '/\\') . '/.chunks/' . $uploadToken;
    }

    private function appendStatePath(string $dir): string
    {
        return rtrim($dir, '/\\') . '/' . self::APPEND_STATE_FILE;
    }

    private function appendDataPath(string $dir): string
    {
        return rtrim($dir, '/\\') . '/' . self::APPEND_DATA_FILE;
    }

    private function appendLockPath(string $dir): string
    {
        return rtrim($dir, '/\\') . '/' . self::APPEND_LOCK_FILE;
    }

    private function prepareStreamingOutput(): void
    {
        @set_time_limit(0);
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
    }

    private function flushStreamingOutput(): void
    {
        @flush();
    }

    private function openAppendLock(string $dir, bool $nonBlocking)
    {
        $lock = fopen($this->appendLockPath($dir), 'c+');
        if (!$lock) {
            throw new RuntimeException('Cannot create append lock.');
        }
        $mode = LOCK_EX | ($nonBlocking ? LOCK_NB : 0);
        if (!flock($lock, $mode)) {
            fclose($lock);
            if ($nonBlocking) {
                throw new MergeInProgressException('Upload is currently merging');
            }
            throw new RuntimeException('Cannot lock append file.');
        }
        return $lock;
    }

    /**
     * @return array{next_index:int,bytes_written:int}
     */
    private function readAppendState(string $dir): array
    {
        $default = ['next_index' => 0, 'bytes_written' => 0];
        $path = $this->appendStatePath($dir);
        if (!is_file($path)) {
            return $default;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $default;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $default;
        }
        $nextIndex = max(0, (int)($decoded['next_index'] ?? 0));
        $bytesWritten = max(0, (int)($decoded['bytes_written'] ?? 0));
        return ['next_index' => $nextIndex, 'bytes_written' => $bytesWritten];
    }

    /**
     * @param array{next_index:int,bytes_written:int} $state
     */
    private function writeAppendState(string $dir, array $state): void
    {
        $path = $this->appendStatePath($dir);
        $tmpPath = $path . '.tmp';
        $payload = json_encode([
            'next_index' => max(0, (int)($state['next_index'] ?? 0)),
            'bytes_written' => max(0, (int)($state['bytes_written'] ?? 0)),
        ], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('Failed to encode append state.');
        }
        if (@file_put_contents($tmpPath, $payload, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write append state.');
        }
        if (is_file($path) && !@unlink($path)) {
            @unlink($tmpPath);
            throw new RuntimeException('Failed to replace append state.');
        }
        if (!@rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new RuntimeException('Failed to finalize append state.');
        }
    }

    private function removeDirectoryContents(string $dir): void
    {
        $entries = scandir($dir);
        if (!is_array($entries)) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectoryContents($path);
                @rmdir($path);
                continue;
            }
            @unlink($path);
        }
    }
}
