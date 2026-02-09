<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\CreateCategoryRequest;
use App\DTO\Request\Admin\ReorderRequest;
use App\DTO\Request\Admin\UpdateCategoryRequest;
use App\DTO\Response\Admin\CategoryResponse;
use App\Entity\Category;
use App\Infrastructure\Storage\StorageInterface;
use App\Service\CategoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly StorageInterface $storage,
    ) {
    }

    #[Route('/categories', name: 'admin_category_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $categories = $this->categoryService->listByRestaurant($restaurant);

        return $this->json(array_map(fn(Category $c) => $this->toResponse($c), $categories));
    }

    #[Route('/categories', name: 'admin_category_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateCategoryRequest $request): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $category = $this->categoryService->create($request, $restaurant);

        return $this->json($this->toResponse($category), 201);
    }

    #[Route('/categories/reorder', name: 'admin_category_reorder', methods: ['PUT'])]
    public function reorder(#[MapRequestPayload] ReorderRequest $request): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $this->categoryService->reorder($request->uuids, $restaurant);

        return $this->json(['message' => 'Categories reordered.']);
    }

    #[Route('/categories/{uuid}', name: 'admin_category_update', methods: ['PUT'])]
    public function update(string $uuid, #[MapRequestPayload] UpdateCategoryRequest $request): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $category = $this->categoryService->update($uuid, $request, $restaurant);

        return $this->json($this->toResponse($category));
    }

    #[Route('/categories/{uuid}', name: 'admin_category_delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $this->categoryService->delete($uuid, $restaurant);

        return $this->json(null, 204);
    }

    private function toResponse(Category $category): CategoryResponse
    {
        $imageUrl = null;
        if ($category->getImageMedia() !== null) {
            $imageUrl = $this->storage->getPublicUrl($category->getImageMedia()->getStoragePath());
        }

        return new CategoryResponse(
            uuid: $category->getUuid()->toString(),
            name: $category->getName(),
            description: $category->getDescription(),
            icon: $category->getIcon(),
            imageUrl: $imageUrl,
            sortOrder: $category->getSortOrder(),
            isActive: $category->isActive(),
            createdAt: $category->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
