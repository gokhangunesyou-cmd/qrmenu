<?php

namespace App\Controller\SuperAdmin;

use App\DTO\Request\SuperAdmin\RejectProductRequest;
use App\DTO\Response\SuperAdmin\ApprovalDetailResponse;
use App\DTO\Response\SuperAdmin\PendingApprovalResponse;
use App\Entity\Product;
use App\Infrastructure\Storage\StorageInterface;
use App\Service\CatalogApprovalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class ApprovalController extends AbstractController
{
    public function __construct(
        private readonly CatalogApprovalService $catalogApprovalService,
        private readonly StorageInterface $storage,
    ) {
    }

    #[Route('/approvals/pending', name: 'super_admin_approval_list_pending', methods: ['GET'])]
    public function listPending(): JsonResponse
    {
        $products = $this->catalogApprovalService->listPending();

        $response = array_map(function (Product $p) {
            $imageUrl = null;
            $images = $p->getImages();
            if (!$images->isEmpty()) {
                $imageUrl = $this->storage->getPublicUrl($images->first()->getMedia()->getStoragePath());
            }

            return new PendingApprovalResponse(
                uuid: $p->getUuid()->toString(),
                productName: $p->getName(),
                restaurantName: $p->getRestaurant()?->getName() ?? '',
                submittedAt: $p->getSubmittedAt()?->format(\DateTimeInterface::ATOM) ?? '',
                imageUrl: $imageUrl,
            );
        }, $products);

        return $this->json($response);
    }

    #[Route('/approvals/{uuid}', name: 'super_admin_approval_detail', methods: ['GET'])]
    public function detail(string $uuid): JsonResponse
    {
        $product = $this->catalogApprovalService->getDetail($uuid);

        return $this->json($this->toDetail($product));
    }

    #[Route('/approvals/{uuid}/approve', name: 'super_admin_approval_approve', methods: ['POST'])]
    public function approve(string $uuid): JsonResponse
    {
        $product = $this->catalogApprovalService->approve($uuid, $this->getUser());

        return $this->json($this->toDetail($product));
    }

    #[Route('/approvals/{uuid}/reject', name: 'super_admin_approval_reject', methods: ['POST'])]
    public function reject(string $uuid, #[MapRequestPayload] RejectProductRequest $request): JsonResponse
    {
        $product = $this->catalogApprovalService->reject($uuid, $request->note, $this->getUser());

        return $this->json($this->toDetail($product));
    }

    private function toDetail(Product $product): ApprovalDetailResponse
    {
        $images = [];
        foreach ($product->getImages() as $productImage) {
            $media = $productImage->getMedia();
            $images[] = [
                'uuid' => $media->getUuid()->toString(),
                'url' => $this->storage->getPublicUrl($media->getStoragePath()),
            ];
        }

        $history = $this->catalogApprovalService->getApprovalHistory($product);
        $approvalHistory = array_map(fn($log) => [
            'action' => $log->getAction()->value,
            'performedBy' => sprintf('%s %s', $log->getPerformedBy()->getFirstName(), $log->getPerformedBy()->getLastName()),
            'note' => $log->getNote(),
            'createdAt' => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $history);

        // Find the submitter from the approval log (first SUBMITTED entry)
        $submitterName = '';
        foreach ($history as $log) {
            if ($log->getAction() === \App\Enum\ApprovalAction::SUBMITTED) {
                $submitterName = sprintf('%s %s', $log->getPerformedBy()->getFirstName(), $log->getPerformedBy()->getLastName());
                break;
            }
        }

        return new ApprovalDetailResponse(
            uuid: $product->getUuid()->toString(),
            name: $product->getName(),
            description: $product->getDescription(),
            price: $product->getPrice(),
            restaurantName: $product->getRestaurant()?->getName() ?? '',
            submitterName: $submitterName,
            submittedAt: $product->getSubmittedAt()?->format(\DateTimeInterface::ATOM) ?? '',
            images: $images,
            approvalHistory: $approvalHistory,
        );
    }
}
