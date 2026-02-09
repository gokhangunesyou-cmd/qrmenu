<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\PresignUploadRequest;
use App\DTO\Response\Admin\MediaResponse;
use App\DTO\Response\Admin\PresignResponse;
use App\Entity\Media;
use App\Infrastructure\Storage\StorageInterface;
use App\Service\MediaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class MediaController extends AbstractController
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly StorageInterface $storage,
    ) {
    }

    #[Route('/media/presign', name: 'admin_media_presign', methods: ['POST'])]
    public function presign(#[MapRequestPayload] PresignUploadRequest $request): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $result = $this->mediaService->presignUpload($request->filename, $request->mimeType, $restaurant);

        return $this->json(new PresignResponse(
            mediaUuid: $result['media_uuid'],
            uploadUrl: $result['upload_url'],
            expiresAt: $result['expires_at'],
        ));
    }

    #[Route('/media/{uuid}/confirm', name: 'admin_media_confirm', methods: ['POST'])]
    public function confirm(string $uuid): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $media = $this->mediaService->confirmUpload($uuid, $restaurant);

        return $this->json($this->toResponse($media));
    }

    #[Route('/media/{uuid}', name: 'admin_media_delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $this->mediaService->delete($uuid, $restaurant);

        return $this->json(null, 204);
    }

    private function toResponse(Media $media): MediaResponse
    {
        return new MediaResponse(
            uuid: $media->getUuid()->toString(),
            originalFilename: $media->getOriginalFilename(),
            url: $this->storage->getPublicUrl($media->getStoragePath()),
            mimeType: $media->getMimeType(),
            fileSize: $media->getFileSize(),
            width: $media->getWidth(),
            height: $media->getHeight(),
        );
    }
}
