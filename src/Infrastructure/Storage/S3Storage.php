<?php

namespace App\Infrastructure\Storage;

use Aws\S3\S3Client;

class S3Storage implements StorageInterface
{
    public function __construct(
        private readonly S3Client $s3Client,
        private readonly string $bucket,
        private readonly string $endpoint,
    ) {
    }

    public function generatePresignedUploadUrl(string $path, string $mimeType, int $ttl = 3600): array
    {
        $command = $this->s3Client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $path,
            'ContentType' => $mimeType,
        ]);

        $expiresAt = new \DateTimeImmutable(sprintf('+%d seconds', $ttl));
        $request = $this->s3Client->createPresignedRequest($command, $expiresAt);

        return [
            'url' => (string) $request->getUri(),
            'expires_at' => $expiresAt,
        ];
    }

    public function getPublicUrl(string $path): string
    {
        return sprintf('%s/%s/%s', rtrim($this->endpoint, '/'), $this->bucket, ltrim($path, '/'));
    }

    public function put(string $path, string $contents, string $mimeType): void
    {
        $this->s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
            'Body' => $contents,
            'ContentType' => $mimeType,
        ]);
    }

    public function exists(string $path): bool
    {
        return $this->s3Client->doesObjectExistV2($this->bucket, $path);
    }

    public function delete(string $path): void
    {
        $this->s3Client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
        ]);
    }
}
