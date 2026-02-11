<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Infrastructure\Storage\StorageInterface;
use App\Repository\MediaRepository;
use App\Service\Image\ImagePipeline;
use App\Service\Image\ProcessedImage;
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
    private const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly MediaService $mediaService,
        private readonly StorageInterface $storage,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly ImagePipeline $imagePipeline,
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

        if (!$file) {
            return new JsonResponse(['error' => 'Dosya bulunamadı. Maksimum dosya boyutu 10MB.'], 422);
        }

        if (!$file->isValid()) {
            return new JsonResponse(['error' => $this->resolveUploadError($file)], 422);
        }

        $mimeType = $this->resolveMimeType($file);

        // Validate mime type
        if (!in_array($mimeType, self::ALLOWED_MIMES, true)) {
            return new JsonResponse([
                'error' => 'Sadece JPEG, PNG ve WebP dosyaları yüklenebilir.',
            ], 422);
        }

        $fileSize = $this->resolveFileSize($file);

        // Validate file size (10MB)
        if ($fileSize > self::MAX_UPLOAD_BYTES) {
            return new JsonResponse([
                'error' => 'Dosya boyutu 10MB\'dan büyük olamaz.',
            ], 422);
        }

        try {
            $media = $this->uploadAndCreateMedia($file, $restaurant, $mimeType);
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

        $processedOriginal = $this->imagePipeline->processUploadedFile($file, $mimeType, ImagePipeline::PROFILE_CATALOG);

        // Upload optimized original to storage
        $this->storage->put($storagePath, $processedOriginal->getContents(), $processedOriginal->getMimeType());

        // Create thumbnail from optimized source
        $this->createThumbnail($processedOriginal, $restaurant->getUuid()->toString(), $mediaUuid, $extension);

        // Create media entity
        $media = new Media(
            $file->getClientOriginalName(),
            $storagePath,
            $processedOriginal->getMimeType(),
            $processedOriginal->getSize(),
            $restaurant,
        );

        $media->setWidth($processedOriginal->getWidth());
        $media->setHeight($processedOriginal->getHeight());

        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }

    private function createThumbnail(
        ProcessedImage $source,
        string $restaurantUuid,
        string $mediaUuid,
        string $extension,
    ): void {
        $thumb = $this->imagePipeline->processBinary(
            $source->getContents(),
            $source->getMimeType(),
            ImagePipeline::PROFILE_THUMB,
        );

        $thumbPath = sprintf('media/%s/thumbs/%s.%s', $restaurantUuid, $mediaUuid, $extension);
        $this->storage->put($thumbPath, $thumb->getContents(), $thumb->getMimeType());
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

    private function resolveUploadError(UploadedFile $file): string
    {
        return match ($file->getError()) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu sunucu limitini aşıyor. Maksimum 10MB.',
            \UPLOAD_ERR_PARTIAL => 'Dosya eksik yüklendi. Lütfen tekrar deneyin.',
            \UPLOAD_ERR_NO_FILE => 'Yüklenecek dosya bulunamadı.',
            \UPLOAD_ERR_NO_TMP_DIR, \UPLOAD_ERR_CANT_WRITE, \UPLOAD_ERR_EXTENSION => 'Sunucu dosyayı işleyemedi. Lütfen daha sonra tekrar deneyin.',
            default => 'Geçersiz dosya.',
        };
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
