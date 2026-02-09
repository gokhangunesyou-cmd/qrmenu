<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Restaurant;
use App\Repository\CategoryRepository;
use App\Repository\MediaRepository;
use App\DTO\Request\Admin\CreateCategoryRequest;
use App\DTO\Request\Admin\UpdateCategoryRequest;
use App\Exception\ConflictException;
use App\Exception\EntityNotFoundException;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return Category[]
     */
    public function listByRestaurant(Restaurant $restaurant): array
    {
        return $this->categoryRepository->findByRestaurant($restaurant);
    }

    /**
     * @throws ValidationException
     */
    public function create(CreateCategoryRequest $request, Restaurant $restaurant): Category
    {
        $category = new Category($request->name, $restaurant);
        $category->setDescription($request->description);
        $category->setIcon($request->icon);

        if ($request->imageMediaUuid !== null) {
            $media = $this->mediaRepository->findOneByUuidAndRestaurant($request->imageMediaUuid, $restaurant);
            if ($media === null) {
                throw new EntityNotFoundException('Media', $request->imageMediaUuid);
            }
            $category->setImageMedia($media);
        }

        // Auto-assign sort order: place at end
        $existing = $this->categoryRepository->findByRestaurant($restaurant);
        $category->setSortOrder(count($existing));

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function update(string $uuid, UpdateCategoryRequest $request, Restaurant $restaurant): Category
    {
        $category = $this->categoryRepository->findOneByUuidAndRestaurant($uuid, $restaurant);

        if ($category === null) {
            throw new EntityNotFoundException('Category', $uuid);
        }

        if ($request->name !== null) {
            $category->setName($request->name);
        }

        if ($request->description !== null) {
            $category->setDescription($request->description);
        }

        if ($request->icon !== null) {
            $category->setIcon($request->icon);
        }

        if ($request->imageMediaUuid !== null) {
            $media = $this->mediaRepository->findOneByUuidAndRestaurant($request->imageMediaUuid, $restaurant);
            if ($media === null) {
                throw new EntityNotFoundException('Media', $request->imageMediaUuid);
            }
            $category->setImageMedia($media);
        }

        if ($request->isActive !== null) {
            $category->setIsActive($request->isActive);
        }

        $this->entityManager->flush();

        return $category;
    }

    /**
     * Reorder categories by setting sortOrder based on the position in the UUID array.
     *
     * @param string[] $uuids Ordered list of category UUIDs
     * @throws ValidationException
     */
    public function reorder(array $uuids, Restaurant $restaurant): void
    {
        if (empty($uuids)) {
            throw new ValidationException([
                ['field' => 'uuids', 'message' => 'UUID list cannot be empty.'],
            ]);
        }

        $categories = $this->categoryRepository->findByRestaurant($restaurant);

        // Index by UUID for fast lookup
        $indexed = [];
        foreach ($categories as $category) {
            $indexed[$category->getUuid()->toString()] = $category;
        }

        // Validate all provided UUIDs belong to this restaurant
        foreach ($uuids as $uuid) {
            if (!isset($indexed[$uuid])) {
                throw new EntityNotFoundException('Category', $uuid);
            }
        }

        // Apply new sort order
        foreach ($uuids as $position => $uuid) {
            $indexed[$uuid]->setSortOrder($position);
        }

        $this->entityManager->flush();
    }

    /**
     * Soft-delete a category. Prevents deletion if it still contains products.
     *
     * @throws EntityNotFoundException
     * @throws ConflictException
     */
    public function delete(string $uuid, Restaurant $restaurant): void
    {
        $category = $this->categoryRepository->findOneByUuidAndRestaurant($uuid, $restaurant);

        if ($category === null) {
            throw new EntityNotFoundException('Category', $uuid);
        }

        // Prevent deleting a category that still has products
        if (!$category->getProducts()->isEmpty()) {
            throw new ConflictException('Cannot delete category that still contains products. Move or delete products first.');
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }
}
