<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Media;
use App\Entity\Restaurant;
use App\Repository\MediaRepository;
use App\Repository\ProductImageRepository;
use App\Infrastructure\Storage\StorageInterface;
use App\Exception\EntityNotFoundException;
use App\Exception\MediaInUseException;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;

class MediaService
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_FILE_SIZE_MB = 10;

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly ProductImageRepository $productImageRepository,
        private readonly StorageInterface $storage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Generate a presigned upload URL and create a pending media entity.
     * The client uploads directly to S3 using this URL, then calls confirmUpload().
     *
     * @return array{media_uuid: string, upload_url: string, expires_at: string}
     */
    public function presignUpload(string $filename, string $mimeType, Restaurant $restaurant): array
    {
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException([
                ['field' => 'mimeType', 'message' => sprintf('Allowed types: %s.', implode(', ', self::ALLOWED_MIME_TYPES))],
            ]);
        }

        // Build a unique storage path: media/{restaurantUuid}/{uuid}.{ext}
        $extension = $this->extensionFromMime($mimeType);
        $mediaUuid = \Ramsey\Uuid\Uuid::uuid7()->toString();
        $storagePath = sprintf('media/%s/%s.%s', $restaurant->getUuid()->toString(), $mediaUuid, $extension);

        // Create the media entity (fileSize=0 until confirmed)
        $media = new Media($filename, $storagePath, $mimeType, 0, $restaurant);

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        // Generate presigned PUT URL (15 min TTL)
        $presign = $this->storage->generatePresignedUploadUrl($storagePath, $mimeType, 900);

        return [
            'media_uuid' => $media->getUuid()->toString(),
            'upload_url' => $presign['url'],
            'expires_at' => $presign['expires_at']->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Confirm that the client has uploaded the file to S3.
     * Verifies the file exists in storage before finalizing.
     */
    public function confirmUpload(string $uuid, Restaurant $restaurant): Media
    {
        $media = $this->mediaRepository->findOneByUuidAndRestaurant($uuid, $restaurant);

        if ($media === null) {
            throw new EntityNotFoundException('Media', $uuid);
        }

        // Verify the file actually exists in storage
        if (!$this->storage->exists($media->getStoragePath())) {
            throw new ValidationException([
                ['field' => 'file', 'message' => 'File has not been uploaded yet.'],
            ]);
        }

        return $media;
    }

    /**
     * Delete a media file and its entity.
     * Prevents deletion if the media is referenced by any restaurant, category, or product.
     */
    public function delete(string $uuid, Restaurant $restaurant): void
    {
        $media = $this->mediaRepository->findOneByUuidAndRestaurant($uuid, $restaurant);

        if ($media === null) {
            throw new EntityNotFoundException('Media', $uuid);
        }

        // Check if media is in use as restaurant logo or cover
        if ($restaurant->getLogoMedia()?->getId() === $media->getId()
            || $restaurant->getCoverMedia()?->getId() === $media->getId()) {
            throw new MediaInUseException($uuid);
        }

        // Check if media is in use by any product image
        $usedByProduct = $this->productImageRepository->findOneBy(['media' => $media]);
        if ($usedByProduct !== null) {
            throw new MediaInUseException($uuid);
        }

        // Check if media is in use by any category
        $usedByCategory = $this->entityManager->getRepository(\App\Entity\Category::class)
            ->findOneBy(['imageMedia' => $media]);
        if ($usedByCategory !== null) {
            throw new MediaInUseException($uuid);
        }

        // Delete from storage
        $this->storage->delete($media->getStoragePath());

        // Hard delete the entity (not soft delete — orphan cleanup)
        $this->entityManager->remove($media);
        $this->entityManager->flush();
    }

    private function extensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }
}
