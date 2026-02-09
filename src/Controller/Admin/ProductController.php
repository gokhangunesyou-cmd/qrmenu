<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\CreateProductRequest;
use App\DTO\Request\Admin\ReorderRequest;
use App\DTO\Request\Admin\UpdateProductRequest;
use App\DTO\Response\Admin\ProductResponse;
use App\Entity\Product;
use App\Infrastructure\Storage\StorageInterface;
use App\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly StorageInterface $storage,
    ) {
    }

    #[Route('/products', name: 'admin_product_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $products = $this->productService->listByRestaurant(
            $restaurant,
            $request->query->get('category'),
            $request->query->get('status'),
        );

        return $this->json(array_map(fn(Product $p) => $this->toResponse($p), $products));
    }

    #[Route('/products', name: 'admin_product_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateProductRequest $request): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $product = $this->productService->create($request, $restaurant);

        return $this->json($this->toResponse($product), 201);
    }

    #[Route('/products/reorder', name: 'admin_product_reorder', methods: ['PUT'])]
    public function reorder(#[MapRequestPayload] ReorderRequest $request): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $this->productService->reorder($request->categoryUuid, $request->uuids, $restaurant);

        return $this->json(['message' => 'Products reordered.']);
    }

    #[Route('/products/{uuid}', name: 'admin_product_update', methods: ['PUT'])]
    public function update(string $uuid, #[MapRequestPayload] UpdateProductRequest $request): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $product = $this->productService->update($uuid, $request, $restaurant);

        return $this->json($this->toResponse($product));
    }

    #[Route('/products/{uuid}', name: 'admin_product_delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $this->productService->delete($uuid, $restaurant);

        return $this->json(null, 204);
    }

    #[Route('/products/{uuid}/submit', name: 'admin_product_submit', methods: ['POST'])]
    public function submit(string $uuid): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $product = $this->productService->submitForApproval($uuid, $restaurant, $this->getUser());

        return $this->json($this->toResponse($product));
    }

    private function toResponse(Product $product): ProductResponse
    {
        $images = [];
        foreach ($product->getImages() as $productImage) {
            $media = $productImage->getMedia();
            $images[] = [
                'uuid' => $media->getUuid()->toString(),
                'url' => $this->storage->getPublicUrl($media->getStoragePath()),
                'sortOrder' => $productImage->getSortOrder(),
            ];
        }

        return new ProductResponse(
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
        );
    }
}
