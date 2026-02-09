<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\CreateQrCodeRequest;
use App\DTO\Response\Admin\QrCodeResponse;
use App\Entity\QrCode;
use App\Infrastructure\Storage\StorageInterface;
use App\Service\QrCodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class QrCodeController extends AbstractController
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
        private readonly StorageInterface $storage,
    ) {
    }

    #[Route('/qr-codes', name: 'admin_qr_code_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $qrCodes = $this->qrCodeService->listByRestaurant($restaurant);

        return $this->json(array_map(fn(QrCode $qr) => $this->toResponse($qr), $qrCodes));
    }

    #[Route('/qr-codes', name: 'admin_qr_code_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateQrCodeRequest $request): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $qrCode = $this->qrCodeService->create($request, $restaurant);

        return $this->json($this->toResponse($qrCode), 201);
    }

    #[Route('/qr-codes/{uuid}', name: 'admin_qr_code_delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $this->qrCodeService->delete($uuid, $restaurant);

        return $this->json(null, 204);
    }

    private function toResponse(QrCode $qrCode): QrCodeResponse
    {
        $imageUrl = $qrCode->getStoragePath() !== null
            ? $this->storage->getPublicUrl($qrCode->getStoragePath())
            : '';

        return new QrCodeResponse(
            uuid: $qrCode->getUuid()->toString(),
            type: $qrCode->getType()->value,
            tableNumber: $qrCode->getTableNumber(),
            tableLabel: $qrCode->getTableLabel(),
            encodedUrl: $qrCode->getEncodedUrl(),
            imageUrl: $imageUrl,
            isActive: $qrCode->isActive(),
            createdAt: $qrCode->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
