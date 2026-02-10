<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\CloneCatalogProductRequest;
use App\DTO\Response\Admin\CatalogProductResponse;
use App\DTO\Response\Admin\ProductResponse;
use App\Entity\Product;
use App\Infrastructure\Storage\StorageInterface;
use App\Repository\ProductRepository;
use App\Service\ProductService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Admin - Catalog')]
class CatalogController extends AbstractController
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductRepository $productRepository,
        private readonly StorageInterface $storage,
    ) {
    }

    #[OA\Get(
        path: '/api/admin/catalog',
        summary: 'Browse approved catalog products',
        tags: ['Admin - Catalog'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns list of approved catalog products'
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    #[Route('/catalog', name: 'admin_catalog_browse', methods: ['GET'])]
    public function browse(): JsonResponse
    {
        $catalogProducts = $this->productRepository->findApprovedCatalog();

        $response = array_map(function (Product $p) {
            $images = [];
            foreach ($p->getImages() as $productImage) {
                $media = $productImage->getMedia();
                $images[] = [
                    'uuid' => $media->getUuid()->toString(),
                    'url' => $this->storage->getPublicUrl($media->getStoragePath()),
                ];
            }

            return new CatalogProductResponse(
                uuid: $p->getUuid()->toString(),
                name: $p->getName(),
                description: $p->getDescription(),
                images: $images,
            );
        }, $catalogProducts);

        return $this->json($response);
    }

    #[OA\Post(
        path: '/api/admin/catalog/{uuid}/clone',
        summary: 'Clone a catalog product to restaurant menu',
        tags: ['Admin - Catalog'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID of the catalog product to clone',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['categoryUuid', 'price'],
                properties: [
                    new OA\Property(property: 'categoryUuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'price', type: 'string', example: '12.99')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Product successfully cloned'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Catalog product not found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    #[Route('/catalog/{uuid}/clone', name: 'admin_catalog_clone', methods: ['POST'])]
    public function clone(string $uuid, #[MapRequestPayload] CloneCatalogProductRequest $request): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $product = $this->productService->cloneFromCatalog($uuid, $request->categoryUuid, $request->price, $restaurant);

        // Build response inline (same shape as ProductResponse)
        $images = [];
        foreach ($product->getImages() as $productImage) {
            $media = $productImage->getMedia();
            $images[] = [
                'uuid' => $media->getUuid()->toString(),
                'url' => $this->storage->getPublicUrl($media->getStoragePath()),
                'sortOrder' => $productImage->getSortOrder(),
            ];
        }

        return $this->json(new ProductResponse(
            uuid: $product->getUuid()->toString(),
            name: $product->getName(),
            description: $product->getDescription(),
            price: $product->getPrice(),
            categoryUuid: $product->getCategory()->getUuid()->toString(),
            status: $product->getStatus()->value,
            isFeatured: $product->isFeatured(),
            isActive: $product->isActive(),
            allergens: $product->getAllergens(),
            sortOrder: $product->getSortOrder(),
            images: $images,
            catalogProductUuid: $product->getCatalogProduct()?->getUuid()->toString(),
            createdAt: $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ), 201);
    }
}
