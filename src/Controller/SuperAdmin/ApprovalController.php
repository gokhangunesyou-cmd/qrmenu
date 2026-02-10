<?php

namespace App\Controller\SuperAdmin;

use App\DTO\Request\SuperAdmin\RejectProductRequest;
use App\DTO\Response\SuperAdmin\ApprovalDetailResponse;
use App\DTO\Response\SuperAdmin\PendingApprovalResponse;
use App\Entity\Product;
use App\Infrastructure\Storage\StorageInterface;
use App\Service\CatalogApprovalService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'SuperAdmin - Approvals')]
class ApprovalController extends AbstractController
{
    public function __construct(
        private readonly CatalogApprovalService $catalogApprovalService,
        private readonly StorageInterface $storage,
    ) {
    }

    #[Route('/approvals/pending', name: 'super_admin_approval_list_pending', methods: ['GET'])]
    #[OA\Get(
        path: '/api/super-admin/approvals/pending',
        summary: 'List pending catalog product approvals',
        tags: ['SuperAdmin - Approvals']
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of pending approvals',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'object')
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
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
    #[OA\Get(
        path: '/api/super-admin/approvals/{uuid}',
        summary: 'Get approval details',
        tags: ['SuperAdmin - Approvals']
    )]
    #[OA\Parameter(
        name: 'uuid',
        in: 'path',
        required: true,
        description: 'Product UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns approval details'
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
    #[OA\Response(response: 404, description: 'Product not found')]
    public function detail(string $uuid): JsonResponse
    {
        $product = $this->catalogApprovalService->getDetail($uuid);

        return $this->json($this->toDetail($product));
    }

    #[Route('/approvals/{uuid}/approve', name: 'super_admin_approval_approve', methods: ['POST'])]
    #[OA\Post(
        path: '/api/super-admin/approvals/{uuid}/approve',
        summary: 'Approve a catalog product submission',
        tags: ['SuperAdmin - Approvals']
    )]
    #[OA\Parameter(
        name: 'uuid',
        in: 'path',
        required: true,
        description: 'Product UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Product approved successfully'
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
    #[OA\Response(response: 404, description: 'Product not found')]
    public function approve(string $uuid): JsonResponse
    {
        $product = $this->catalogApprovalService->approve($uuid, $this->getUser());

        return $this->json($this->toDetail($product));
    }

    #[Route('/approvals/{uuid}/reject', name: 'super_admin_approval_reject', methods: ['POST'])]
    #[OA\Post(
        path: '/api/super-admin/approvals/{uuid}/reject',
        summary: 'Reject a catalog product submission',
        tags: ['SuperAdmin - Approvals']
    )]
    #[OA\Parameter(
        name: 'uuid',
        in: 'path',
        required: true,
        description: 'Product UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\RequestBody(
        required: true
    )]
    #[OA\Response(
        response: 200,
        description: 'Product rejected successfully'
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
    #[OA\Response(response: 404, description: 'Product not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
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
