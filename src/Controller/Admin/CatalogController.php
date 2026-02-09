<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\CloneCatalogProductRequest;
use App\DTO\Response\Admin\CatalogProductResponse;
use App\DTO\Response\Admin\ProductResponse;
use App\Entity\Product;
use App\Infrastructure\Storage\StorageInterface;
use App\Repository\ProductRepository;
use App\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class CatalogController extends AbstractController
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductRepository $productRepository,
        private readonly StorageInterface $storage,
    ) {
    }

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
