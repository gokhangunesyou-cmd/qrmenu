<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\PresignUploadRequest;
use App\DTO\Response\Admin\MediaResponse;
use App\DTO\Response\Admin\PresignResponse;
use App\Entity\Media;
use App\Infrastructure\Storage\StorageInterface;
use App\Service\MediaService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Admin - Media')]
class MediaController extends AbstractController
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly StorageInterface $storage,
    ) {
    }

    #[OA\Post(
        path: '/api/admin/media/presign',
        summary: 'Get presigned URL for media upload',
        requestBody: new OA\RequestBody(
            required: true
        ),
        tags: ['Admin - Media'],
        responses: [
            new OA\Response(response: 200, description: 'Presigned URL generated successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
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

    #[OA\Post(
        path: '/api/admin/media/{uuid}/confirm',
        summary: 'Confirm media upload',
        tags: ['Admin - Media'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Media upload confirmed'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Media not found'),
        ]
    )]
    #[Route('/media/{uuid}/confirm', name: 'admin_media_confirm', methods: ['POST'])]
    public function confirm(string $uuid): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $media = $this->mediaService->confirmUpload($uuid, $restaurant);

        return $this->json($this->toResponse($media));
    }

    #[OA\Delete(
        path: '/api/admin/media/{uuid}',
        summary: 'Delete a media file',
        tags: ['Admin - Media'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Media deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Media not found'),
        ]
    )]
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
