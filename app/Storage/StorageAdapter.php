<?php

declare(strict_types=1);

namespace App\Storage;

interface StorageAdapter
{
    public function saveChunk(string $uploadToken, int $chunkIndex, string $tmpFile): string;
    public function mergeChunks(string $uploadToken, string $targetKey, int $totalChunks, ?callable $onProgress = null): void;
    /**
     * @param array<int, int> $expectedChunkSizes map: chunk_index => expected_size
     * @return array<int, int> missing or size-mismatched chunk indexes
     */
    public function findMissingChunks(string $uploadToken, array $expectedChunkSizes): array;
    public function cleanupUploadChunks(string $uploadToken, int $totalChunks): void;
    public function putObjectFromPath(string $targetKey, string $localPath): void;
    public function copyObject(string $sourceObjectKey, string $targetObjectKey): void;
    public function deleteObject(string $objectKey): void;
    public function streamChunk(string $objectKey, int $chunkIndex, int $chunkSize): void;
    public function objectSize(string $objectKey): int;
    public function objectMd5(string $objectKey): string;
    public function makeObjectUrl(string $objectKey): string;
}
