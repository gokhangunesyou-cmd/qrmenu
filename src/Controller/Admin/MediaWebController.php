<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Infrastructure\Storage\StorageInterface;
use App\Repository\MediaRepository;
use App\Service\MediaService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/media')]
class MediaWebController extends AbstractController
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly MediaService $mediaService,
        private readonly StorageInterface $storage,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'admin_media_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();

        $search = $request->query->getString('search', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;

        $result = $this->mediaRepository->findByRestaurantPaginated(
            $restaurant,
            $search,
            $page,
            $limit,
        );

        return $this->render('admin/media/index.html.twig', [
            'mediaItems' => $result['items'],
            'totalItems' => $result['total'],
            'currentPage' => $page,
            'totalPages' => (int) ceil($result['total'] / $limit),
            'search' => $search,
        ]);
    }

    #[Route('/upload', name: 'admin_media_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $restaurant = $this->getRestaurantOrThrow();

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        if (!$file || !$file->isValid()) {
            return new JsonResponse(['error' => 'Geçersiz dosya.'], 422);
        }

        $mimeType = $this->resolveMimeType($file);

        // Validate mime type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            return new JsonResponse([
                'error' => 'Sadece JPEG, PNG ve WebP dosyaları yüklenebilir.',
            ], 422);
        }

        $fileSize = $this->resolveFileSize($file);

        // Validate file size (5MB)
        $maxSize = 5 * 1024 * 1024;
        if ($fileSize > $maxSize) {
            return new JsonResponse([
                'error' => 'Dosya boyutu 5MB\'dan büyük olamaz.',
            ], 422);
        }

        try {
            $media = $this->uploadAndCreateMedia($file, $restaurant, $mimeType, $fileSize);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Media upload failed [%s]: %s', $e::class, $e->getMessage()), [
                'exception' => $e,
                'filename' => $file->getClientOriginalName(),
                'mimeType' => $mimeType,
                'size' => $fileSize,
            ]);

            return new JsonResponse([
                'error' => 'Dosya yüklenirken bir hata oluştu.',
            ], 500);
        }

        return new JsonResponse([
            'uuid' => $media->getUuid()->toString(),
            'originalFilename' => $media->getOriginalFilename(),
            'url' => $this->storage->getPublicUrl($media->getStoragePath()),
            'mimeType' => $media->getMimeType(),
            'fileSize' => $media->getFileSize(),
            'width' => $media->getWidth(),
            'height' => $media->getHeight(),
            'createdAt' => $media->getCreatedAt()->format('d.m.Y H:i'),
        ]);
    }

    #[Route('/{uuid}/delete', name: 'admin_media_web_delete', methods: ['POST'])]
    public function delete(string $uuid, Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();

        // CSRF protection
        if (!$this->isCsrfTokenValid('media_delete_' . $uuid, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz CSRF token.');

            return $this->redirectToRoute('admin_media_index');
        }

        try {
            $this->mediaService->delete($uuid, $restaurant);
            $this->addFlash('success', 'Medya başarıyla silindi.');
        } catch (\App\Exception\MediaInUseException) {
            $this->addFlash('error', 'Bu medya kullanımda olduğu için silinemez.');
        } catch (\App\Exception\EntityNotFoundException) {
            $this->addFlash('error', 'Medya bulunamadı.');
        }

        return $this->redirectToRoute('admin_media_index');
    }

    #[Route('/{uuid}/delete-ajax', name: 'admin_media_web_delete_ajax', methods: ['POST'])]
    public function deleteAjax(string $uuid, Request $request): JsonResponse
    {
        $restaurant = $this->getRestaurantOrThrow();

        if (!$this->isCsrfTokenValid('media_delete_' . $uuid, $request->request->getString('_token'))) {
            return new JsonResponse(['error' => 'Geçersiz CSRF token.'], 403);
        }

        try {
            $this->mediaService->delete($uuid, $restaurant);

            return new JsonResponse(['success' => true]);
        } catch (\App\Exception\MediaInUseException) {
            return new JsonResponse(['error' => 'Bu medya kullanımda olduğu için silinemez.'], 409);
        } catch (\App\Exception\EntityNotFoundException) {
            return new JsonResponse(['error' => 'Medya bulunamadı.'], 404);
        }
    }

    private function uploadAndCreateMedia(
        UploadedFile $file,
        \App\Entity\Restaurant $restaurant,
        string $mimeType,
        int $fileSize,
    ): Media
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
        }

        $mediaUuid = \Ramsey\Uuid\Uuid::uuid7()->toString();
        $storagePath = sprintf('media/%s/%s.%s', $restaurant->getUuid()->toString(), $mediaUuid, $extension);

        $fileContent = file_get_contents($file->getPathname());
        if ($fileContent === false) {
            throw new \RuntimeException('Uploaded file could not be read.');
        }

        // Upload original to storage
        $this->storage->put($storagePath, $fileContent, $mimeType);

        // Get image dimensions
        $imageSize = @getimagesize($file->getPathname());
        $width = $imageSize ? $imageSize[0] : null;
        $height = $imageSize ? $imageSize[1] : null;

        // Create thumbnail
        $this->createThumbnail($file, $restaurant->getUuid()->toString(), $mediaUuid, $extension, $mimeType);

        // Create media entity
        $media = new Media(
            $file->getClientOriginalName(),
            $storagePath,
            $mimeType,
            $fileSize,
            $restaurant,
        );

        if ($width !== null) {
            $media->setWidth($width);
        }
        if ($height !== null) {
            $media->setHeight($height);
        }

        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }

    private function createThumbnail(
        UploadedFile $file,
        string $restaurantUuid,
        string $mediaUuid,
        string $extension,
        string $mimeType,
    ): void {
        $maxThumbWidth = 300;
        $maxThumbHeight = 300;

        $imageSize = @getimagesize($file->getPathname());
        if (!$imageSize) {
            return;
        }

        [$origWidth, $origHeight] = $imageSize;

        // Calculate thumbnail dimensions maintaining aspect ratio
        $ratio = min($maxThumbWidth / $origWidth, $maxThumbHeight / $origHeight);
        if ($ratio >= 1.0) {
            // Image is already small enough, use original as thumbnail
            $thumbContent = (string) file_get_contents($file->getPathname());
        } else {
            if (!function_exists('imagecreatetruecolor')) {
                return;
            }
            $newWidth = (int) round($origWidth * $ratio);
            $newHeight = (int) round($origHeight * $ratio);
            $newWidth = max(1, $newWidth);
            $newHeight = max(1, $newHeight);

            $source = match ($mimeType) {
                'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file->getPathname()) : false,
                'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($file->getPathname()) : false,
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getPathname()) : false,
                default => false,
            };

            if (!$source) {
                return;
            }

            $thumb = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG and WebP
            if (in_array($mimeType, ['image/png', 'image/webp'], true)) {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
            }

            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

            ob_start();
            if ($mimeType === 'image/jpeg' && function_exists('imagejpeg')) {
                imagejpeg($thumb, null, 80);
            } elseif ($mimeType === 'image/png' && function_exists('imagepng')) {
                imagepng($thumb, null, 6);
            } elseif ($mimeType === 'image/webp' && function_exists('imagewebp')) {
                imagewebp($thumb, null, 80);
            }
            $thumbContent = ob_get_clean();

            imagedestroy($source);
            imagedestroy($thumb);
        }

        if (!empty($thumbContent)) {
            $thumbPath = sprintf('media/%s/thumbs/%s.%s', $restaurantUuid, $mediaUuid, $extension);
            $this->storage->put($thumbPath, $thumbContent, $mimeType);
        }
    }

    private function resolveMimeType(UploadedFile $file): string
    {
        $imageSize = @getimagesize($file->getPathname());
        if (is_array($imageSize) && isset($imageSize['mime']) && is_string($imageSize['mime'])) {
            return $imageSize['mime'];
        }

        return $file->getClientMimeType();
    }

    private function resolveFileSize(UploadedFile $file): int
    {
        $size = @filesize($file->getPathname());
        if (!is_int($size) || $size < 0) {
            throw new \InvalidArgumentException('Dosya boyutu okunamadı.');
        }

        return $size;
    }

    private function getRestaurantOrThrow(): Restaurant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $restaurant = $user->getRestaurant();
        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        return $restaurant;
    }
}
