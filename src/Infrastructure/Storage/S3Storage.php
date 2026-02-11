<?php

namespace App\Infrastructure\Storage;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;

class S3Storage implements StorageInterface
{
    private bool $bucketChecked = false;

    public function __construct(
        private readonly S3Client $s3Client,
        private readonly string $bucket,
        private readonly string $endpoint,
        private readonly string $publicEndpoint = '',
        private readonly bool $autoCreateBucket = false,
        private readonly bool $autoSetPublicReadPolicy = false,
    ) {
    }

    public function generatePresignedUploadUrl(string $path, string $mimeType, int $ttl = 3600): array
    {
        $this->ensureBucketExists();

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
        $cleanPath = ltrim($path, '/');
        $baseEndpoint = $this->publicEndpoint !== '' ? $this->publicEndpoint : $this->endpoint;
        if ($baseEndpoint === '') {
            return $this->s3Client->getObjectUrl($this->bucket, $cleanPath);
        }

        return sprintf('%s/%s/%s', rtrim($baseEndpoint, '/'), $this->bucket, $cleanPath);
    }

    public function put(string $path, string $contents, string $mimeType): void
    {
        $this->ensureBucketExists();

        $this->s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
            'Body' => $contents,
            'ContentType' => $mimeType,
        ]);
    }

    public function exists(string $path): bool
    {
        $this->ensureBucketExists();

        return $this->s3Client->doesObjectExistV2($this->bucket, $path);
    }

    public function delete(string $path): void
    {
        $this->ensureBucketExists();

        $this->s3Client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
        ]);
    }

    public function get(string $path): string
    {
        $this->ensureBucketExists();

        $result = $this->s3Client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
        ]);

        return (string) $result['Body'];
    }

    private function ensureBucketExists(): void
    {
        if ($this->bucketChecked) {
            return;
        }

        try {
            if ($this->autoCreateBucket && !$this->s3Client->doesBucketExistV2($this->bucket)) {
                $this->s3Client->createBucket([
                    'Bucket' => $this->bucket,
                ]);
            }

            if ($this->autoSetPublicReadPolicy) {
                $this->s3Client->putBucketPolicy([
                    'Bucket' => $this->bucket,
                    'Policy' => json_encode([
                        'Version' => '2012-10-17',
                        'Statement' => [[
                            'Sid' => 'PublicReadGetObject',
                            'Effect' => 'Allow',
                            'Principal' => '*',
                            'Action' => ['s3:GetObject'],
                            'Resource' => [sprintf('arn:aws:s3:::%s/*', $this->bucket)],
                        ]],
                    ], \JSON_THROW_ON_ERROR),
                ]);
            }
        } catch (AwsException $e) {
            throw new \RuntimeException(
                sprintf('S3 bucket check/create/policy failed for "%s": %s', $this->bucket, $e->getAwsErrorMessage() ?: $e->getMessage()),
                previous: $e,
            );
        }

        $this->bucketChecked = true;
    }
}
