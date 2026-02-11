<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

final class ConfiguredStorage implements StorageInterface
{
    private StorageInterface $activeStorage;

    public function __construct(
        LocalStorage $localStorage,
        S3Storage $s3Storage,
        string $driver = 's3',
    ) {
        $normalizedDriver = strtolower(trim($driver));
        $this->activeStorage = match ($normalizedDriver) {
            'local' => $localStorage,
            's3', '' => $s3Storage,
            default => throw new \InvalidArgumentException(sprintf('Unsupported storage driver: "%s". Use "local" or "s3".', $driver)),
        };
    }

    public function generatePresignedUploadUrl(string $path, string $mimeType, int $ttl = 3600): array
    {
        return $this->activeStorage->generatePresignedUploadUrl($path, $mimeType, $ttl);
    }

    public function getPublicUrl(string $path): string
    {
        return $this->activeStorage->getPublicUrl($path);
    }

    public function put(string $path, string $contents, string $mimeType): void
    {
        $this->activeStorage->put($path, $contents, $mimeType);
    }

    public function exists(string $path): bool
    {
        return $this->activeStorage->exists($path);
    }

    public function delete(string $path): void
    {
        $this->activeStorage->delete($path);
    }

    public function get(string $path): string
    {
        return $this->activeStorage->get($path);
    }
}
