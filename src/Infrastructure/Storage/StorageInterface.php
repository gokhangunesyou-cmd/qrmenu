<?php

namespace App\Infrastructure\Storage;

interface StorageInterface
{
    /**
     * Generate a presigned URL for direct client upload.
     *
     * @return array{url: string, expires_at: \DateTimeImmutable}
     */
    public function generatePresignedUploadUrl(string $path, string $mimeType, int $ttl = 3600): array;

    /**
     * Generate a public URL for reading a stored file.
     */
    public function getPublicUrl(string $path): string;

    /**
     * Upload file contents directly to storage.
     */
    public function put(string $path, string $contents, string $mimeType): void;

    /**
     * Check if a file exists at the given path.
     */
    public function exists(string $path): bool;

    /**
     * Delete a file from storage.
     */
    public function delete(string $path): void;
}
