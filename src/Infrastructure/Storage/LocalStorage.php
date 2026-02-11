<?php

namespace App\Infrastructure\Storage;

final class LocalStorage implements StorageInterface
{
    private string $rootPath;
    private string $publicPrefix;

    public function __construct(string $projectDir, string $publicPrefix = '/uploads')
    {
        $this->rootPath = rtrim($projectDir, '/') . '/public' . $publicPrefix;
        $this->publicPrefix = rtrim($publicPrefix, '/');
    }

    public function generatePresignedUploadUrl(string $path, string $mimeType, int $ttl = 3600): array
    {
        $expiresAt = new \DateTimeImmutable(sprintf('+%d seconds', $ttl));

        return [
            'url' => $this->getPublicUrl($path),
            'expires_at' => $expiresAt,
        ];
    }

    public function getPublicUrl(string $path): string
    {
        return sprintf('%s/%s', $this->publicPrefix, ltrim($path, '/'));
    }

    public function put(string $path, string $contents, string $mimeType): void
    {
        $fullPath = $this->toFilesystemPath($path);
        $dir = dirname($fullPath);

        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Upload directory could not be created: %s', $dir));
        }

        if (!is_writable($dir)) {
            @chmod($dir, 0777);
        }
        if (!is_writable($dir)) {
            throw new \RuntimeException(sprintf('Upload directory is not writable: %s', $dir));
        }

        if (file_put_contents($fullPath, $contents) === false) {
            throw new \RuntimeException(sprintf('File could not be written: %s', $fullPath));
        }
    }

    public function exists(string $path): bool
    {
        return file_exists($this->toFilesystemPath($path));
    }

    public function delete(string $path): void
    {
        $fullPath = $this->toFilesystemPath($path);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    public function get(string $path): string
    {
        return (string) file_get_contents($this->toFilesystemPath($path));
    }

    private function toFilesystemPath(string $path): string
    {
        return $this->rootPath . '/' . ltrim($path, '/');
    }
}
