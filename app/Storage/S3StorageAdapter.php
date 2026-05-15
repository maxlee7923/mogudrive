<?php

declare(strict_types=1);

namespace App\Storage;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Utils;
use RuntimeException;
use Throwable;

final class S3StorageAdapter implements StorageAdapter
{
    private const DELETE_BATCH_SIZE = 1000;
    private const DEFAULT_COPY_CONCURRENCY = 8;
    private const MAX_COPY_CONCURRENCY = 32;
    private const STREAM_BUFFER_SIZE = 1024 * 1024;

    private S3Client $client;
    private array $cfg;

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $cfg['region'],
            'endpoint' => $cfg['endpoint'] ?: null,
            'use_path_style_endpoint' => (bool)($cfg['path_style'] ?? false),
            'credentials' => [
                'key' => $cfg['access_key'],
                'secret' => $cfg['secret_key'],
            ],
        ]);
    }

    public function saveChunk(string $uploadToken, int $chunkIndex, string $tmpFile): string
    {
        $key = ".chunks/{$uploadToken}/{$chunkIndex}.part";
        try {
            $this->client->putObject([
                'Bucket' => $this->cfg['bucket'],
                'Key' => $key,
                'SourceFile' => $tmpFile,
            ]);
        } catch (AwsException $e) {
            throw new RuntimeException('S3 save chunk failed: ' . $e->getMessage());
        }
        return md5_file($tmpFile) ?: '';
    }

    public function mergeChunks(string $uploadToken, string $targetKey, int $totalChunks, ?callable $onProgress = null): void
    {
        $partsByNumber = [];
        $bucket = (string)$this->cfg['bucket'];
        $copyConcurrency = $this->copyConcurrency();
        $upload = $this->client->createMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $targetKey,
        ]);
        $uploadId = (string)$upload['UploadId'];
        $merged = 0;
        try {
            for ($start = 0; $start < $totalChunks; $start += $copyConcurrency) {
                $end = min($totalChunks, $start + $copyConcurrency);
                $promises = [];

                for ($i = $start; $i < $end; $i++) {
                    $partNumber = $i + 1;
                    $copySource = rawurlencode($bucket) . '/.chunks/' . $uploadToken . '/' . $i . '.part';
                    $promises[$partNumber] = $this->client->uploadPartCopyAsync([
                        'Bucket' => $bucket,
                        'Key' => $targetKey,
                        'UploadId' => $uploadId,
                        'PartNumber' => $partNumber,
                        'CopySource' => $copySource,
                    ]);
                }

                $results = Utils::settle($promises)->wait();
                foreach ($results as $partNumber => $result) {
                    if (($result['state'] ?? '') !== 'fulfilled') {
                        $reason = $result['reason'] ?? null;
                        $message = $reason instanceof Throwable ? $reason->getMessage() : 'uploadPartCopy failed';
                        throw new RuntimeException('S3 merge failed: ' . $message);
                    }

                    $value = $result['value'] ?? null;
                    $etag = (string)($value['CopyPartResult']['ETag'] ?? '');
                    if ($etag === '') {
                        throw new RuntimeException('S3 merge failed: empty ETag for part ' . (int)$partNumber);
                    }

                    $partsByNumber[(int)$partNumber] = [
                        'PartNumber' => (int)$partNumber,
                        'ETag' => $etag,
                    ];
                    $merged++;
                    if ($onProgress !== null) {
                        $onProgress($merged, $totalChunks);
                    }
                }
            }

            ksort($partsByNumber, SORT_NUMERIC);
            $this->client->completeMultipartUpload([
                'Bucket' => $bucket,
                'Key' => $targetKey,
                'UploadId' => $uploadId,
                'MultipartUpload' => ['Parts' => array_values($partsByNumber)],
            ]);
        } catch (Throwable $e) {
            try {
                $this->client->abortMultipartUpload([
                    'Bucket' => $bucket,
                    'Key' => $targetKey,
                    'UploadId' => $uploadId,
                ]);
            } catch (Throwable $abortError) {
                // Abort is best-effort.
            }

            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('S3 merge failed: ' . $e->getMessage());
        }
    }

    public function findMissingChunks(string $uploadToken, array $expectedChunkSizes): array
    {
        try {
            $chunkObjects = $this->listChunkObjects($uploadToken);
        } catch (Throwable $e) {
            return $this->findMissingChunksByHead($uploadToken, $expectedChunkSizes);
        }

        $prefixLen = strlen(".chunks/{$uploadToken}/");
        $actualChunkSizes = [];
        foreach ($chunkObjects as $key => $size) {
            $suffix = substr($key, $prefixLen);
            if ($suffix === false || !preg_match('/^(\d+)\.part$/', $suffix, $m)) {
                continue;
            }
            $actualChunkSizes[(int)$m[1]] = (int)$size;
        }

        $missing = [];
        foreach ($expectedChunkSizes as $idx => $expectedSize) {
            $i = (int)$idx;
            if (!array_key_exists($i, $actualChunkSizes) || (int)$actualChunkSizes[$i] !== (int)$expectedSize) {
                $missing[] = (int)$idx;
            }
        }
        sort($missing, SORT_NUMERIC);
        return array_values(array_unique($missing, SORT_NUMERIC));
    }

    public function cleanupUploadChunks(string $uploadToken, int $totalChunks): void
    {
        $keys = $this->listChunkKeys($uploadToken);
        if (!$keys) {
            return;
        }

        $bucket = (string)$this->cfg['bucket'];
        $batches = array_chunk($keys, self::DELETE_BATCH_SIZE);
        foreach ($batches as $batch) {
            $objects = array_map(static fn (string $key): array => ['Key' => $key], $batch);
            try {
                $this->client->deleteObjects([
                    'Bucket' => $bucket,
                    'Delete' => [
                        'Objects' => $objects,
                        'Quiet' => true,
                    ],
                ]);
                continue;
            } catch (Throwable $e) {
                // Fall through to single deletes.
            }

            foreach ($batch as $key) {
                try {
                    $this->client->deleteObject([
                        'Bucket' => $bucket,
                        'Key' => $key,
                    ]);
                } catch (Throwable $deleteError) {
                    // Cleanup is best-effort; keep upload flow resilient.
                }
            }
        }
    }

    public function putObjectFromPath(string $targetKey, string $localPath): void
    {
        $this->client->putObject([
            'Bucket' => $this->cfg['bucket'],
            'Key' => $targetKey,
            'SourceFile' => $localPath,
        ]);
    }

    public function copyObject(string $sourceObjectKey, string $targetObjectKey): void
    {
        try {
            $this->client->copyObject([
                'Bucket' => $this->cfg['bucket'],
                'Key' => $targetObjectKey,
                'CopySource' => rawurlencode((string)$this->cfg['bucket']) . '/' . ltrim($sourceObjectKey, '/'),
            ]);
        } catch (AwsException $e) {
            throw new RuntimeException('S3 copy failed: ' . $e->getMessage());
        }
    }

    public function deleteObject(string $objectKey): void
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->cfg['bucket'],
                'Key' => $objectKey,
            ]);
        } catch (AwsException $e) {
            throw new RuntimeException('S3 delete failed: ' . $e->getMessage());
        }
    }

    public function streamChunk(string $objectKey, int $chunkIndex, int $chunkSize): void
    {
        $offset = $chunkIndex * $chunkSize;
        $end = $offset + $chunkSize - 1;
        $result = $this->client->getObject([
            'Bucket' => $this->cfg['bucket'],
            'Key' => $objectKey,
            'Range' => "bytes={$offset}-{$end}",
        ]);
        $body = $result['Body'];
        $length = (int)($result['ContentLength'] ?? 0);
        if ($length <= 0 && is_object($body) && method_exists($body, 'getSize')) {
            $length = (int)($body->getSize() ?? 0);
        }
        header('Content-Type: application/octet-stream');
        if ($length > 0) {
            header('Content-Length: ' . $length);
        }
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=31536000, immutable');
        $this->prepareStreamingOutput();

        try {
            while (is_object($body) && method_exists($body, 'eof') && method_exists($body, 'read') && !$body->eof()) {
                $chunk = $body->read(self::STREAM_BUFFER_SIZE);
                if ($chunk === '') {
                    break;
                }
                echo $chunk;
                $this->flushStreamingOutput();
                if (connection_aborted()) {
                    break;
                }
            }
        } finally {
            if (is_object($body) && method_exists($body, 'close')) {
                $body->close();
            }
        }
    }

    public function objectSize(string $objectKey): int
    {
        $result = $this->client->headObject([
            'Bucket' => $this->cfg['bucket'],
            'Key' => $objectKey,
        ]);
        return (int)$result['ContentLength'];
    }

    public function objectMd5(string $objectKey): string
    {
        $result = $this->client->getObject([
            'Bucket' => $this->cfg['bucket'],
            'Key' => $objectKey,
        ]);
        $body = $result['Body'];
        $ctx = hash_init('md5');
        while (!$body->eof()) {
            hash_update($ctx, $body->read(1024 * 1024));
        }
        return hash_final($ctx);
    }

    public function makeObjectUrl(string $objectKey): string
    {
        $cmd = $this->client->getCommand('GetObject', [
            'Bucket' => $this->cfg['bucket'],
            'Key' => $objectKey,
        ]);
        return (string)$this->client->createPresignedRequest($cmd, '+20 minutes')->getUri();
    }

    /**
     * @return array<string, int> object key => size bytes
     */
    private function listChunkObjects(string $uploadToken): array
    {
        $prefix = ".chunks/{$uploadToken}/";
        $objects = [];
        $continuationToken = null;

        do {
            $params = [
                'Bucket' => $this->cfg['bucket'],
                'Prefix' => $prefix,
                'MaxKeys' => self::DELETE_BATCH_SIZE,
            ];
            if ($continuationToken !== null) {
                $params['ContinuationToken'] = $continuationToken;
            }

            $result = $this->client->listObjectsV2($params);
            $contents = $result['Contents'] ?? [];
            if (is_array($contents)) {
                foreach ($contents as $row) {
                    $key = (string)($row['Key'] ?? '');
                    if ($key === '') {
                        continue;
                    }
                    $objects[$key] = (int)($row['Size'] ?? -1);
                }
            }

            $isTruncated = (bool)($result['IsTruncated'] ?? false);
            $nextToken = $result['NextContinuationToken'] ?? null;
            $continuationToken = $isTruncated && is_string($nextToken) && $nextToken !== '' ? $nextToken : null;
        } while ($continuationToken !== null);

        return $objects;
    }

    /**
     * @param array<int, int> $expectedChunkSizes map: chunk_index => expected_size
     * @return array<int, int> missing or size-mismatched chunk indexes
     */
    private function findMissingChunksByHead(string $uploadToken, array $expectedChunkSizes): array
    {
        $missing = [];
        foreach ($expectedChunkSizes as $idx => $expectedSize) {
            $key = ".chunks/{$uploadToken}/" . (int)$idx . '.part';
            try {
                $head = $this->client->headObject([
                    'Bucket' => $this->cfg['bucket'],
                    'Key' => $key,
                ]);
                $actualSize = (int)($head['ContentLength'] ?? -1);
                if ($actualSize !== (int)$expectedSize) {
                    $missing[] = (int)$idx;
                }
            } catch (AwsException $e) {
                $missing[] = (int)$idx;
            }
        }
        sort($missing, SORT_NUMERIC);
        return array_values(array_unique($missing, SORT_NUMERIC));
    }

    /**
     * @return array<int, string>
     */
    private function listChunkKeys(string $uploadToken): array
    {
        $objects = $this->listChunkObjects($uploadToken);
        if (!$objects) {
            return [];
        }
        return array_values(array_keys($objects));
    }

    private function copyConcurrency(): int
    {
        $value = (int)($this->cfg['merge_copy_concurrency'] ?? self::DEFAULT_COPY_CONCURRENCY);
        if ($value < 1) {
            return 1;
        }
        if ($value > self::MAX_COPY_CONCURRENCY) {
            return self::MAX_COPY_CONCURRENCY;
        }
        return $value;
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
}
