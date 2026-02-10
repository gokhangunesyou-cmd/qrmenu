<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductApprovalLog;
use App\Entity\ProductImage;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Enum\ApprovalAction;
use App\Enum\ProductStatus;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\MediaRepository;
use App\DTO\Request\Admin\CreateProductRequest;
use App\DTO\Request\Admin\UpdateProductRequest;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidStatusTransitionException;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return Product[]
     */
    public function listByRestaurant(Restaurant $restaurant, ?string $categoryUuid, ?string $status): array
    {
        $category = null;
        if ($categoryUuid !== null) {
            $category = $this->categoryRepository->findOneByUuidAndRestaurant($categoryUuid, $restaurant);
            if ($category === null) {
                throw new EntityNotFoundException('Category', $categoryUuid);
            }
        }

        $statusEnum = null;
        if ($status !== null) {
            $statusEnum = ProductStatus::tryFrom($status);
            if ($statusEnum === null) {
                throw new ValidationException([
                    ['field' => 'status', 'message' => sprintf('Invalid status "%s".', $status)],
                ]);
            }
        }

        return $this->productRepository->findByRestaurantFiltered($restaurant, $category, $statusEnum);
    }

    /**
     * @throws EntityNotFoundException
     */
    public function create(CreateProductRequest $request, Restaurant $restaurant): Product
    {
        $category = $this->categoryRepository->findOneByUuidAndRestaurant($request->categoryUuid, $restaurant);
        if ($category === null) {
            throw new EntityNotFoundException('Category', $request->categoryUuid);
        }

        $product = new Product($request->name, $request->price, $category, $restaurant);
        $product->setDescription($request->description);

        if ($request->isFeatured !== null) {
            $product->setIsFeatured($request->isFeatured);
        }

        if ($request->allergens !== null) {
            $product->setAllergens($request->allergens);
        }

        // Auto-assign sort order within category
        $existing = $this->productRepository->findByRestaurantAndCategory($restaurant, $category);
        $product->setSortOrder(count($existing));

        $this->entityManager->persist($product);

        // Attach images
        if ($request->imageMediaUuids !== null) {
            $this->syncImages($product, $request->imageMediaUuids, $restaurant);
        }

        $this->entityManager->flush();

        return $product;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function update(string $uuid, UpdateProductRequest $request, Restaurant $restaurant): Product
    {
        $product = $this->productRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if ($product === null) {
            throw new EntityNotFoundException('Product', $uuid);
        }

        if ($request->name !== null) {
            $product->setName($request->name);
        }

        if ($request->description !== null) {
            $product->setDescription($request->description);
        }

        if ($request->price !== null) {
            $product->setPrice($request->price);
        }

        if ($request->categoryUuid !== null) {
            $category = $this->categoryRepository->findOneByUuidAndRestaurant($request->categoryUuid, $restaurant);
            if ($category === null) {
                throw new EntityNotFoundException('Category', $request->categoryUuid);
            }
            $product->setCategory($category);
        }

        if ($request->isFeatured !== null) {
            $product->setIsFeatured($request->isFeatured);
        }

        if ($request->isActive !== null) {
            $product->setIsActive($request->isActive);
        }

        if ($request->allergens !== null) {
            $product->setAllergens($request->allergens);
        }

        if ($request->imageMediaUuids !== null) {
            $this->syncImages($product, $request->imageMediaUuids, $restaurant);
        }

        $this->entityManager->flush();

        return $product;
    }

    /**
     * @param string[] $productUuids
     * @throws EntityNotFoundException
     * @throws ValidationException
     */
    public function reorder(string $categoryUuid, array $productUuids, Restaurant $restaurant): void
    {
        if (empty($productUuids)) {
            throw new ValidationException([
                ['field' => 'uuids', 'message' => 'UUID list cannot be empty.'],
            ]);
        }

        $category = $this->categoryRepository->findOneByUuidAndRestaurant($categoryUuid, $restaurant);
        if ($category === null) {
            throw new EntityNotFoundException('Category', $categoryUuid);
        }

        $products = $this->productRepository->findByRestaurantAndCategory($restaurant, $category);

        $indexed = [];
        foreach ($products as $product) {
            $indexed[$product->getUuid()->toString()] = $product;
        }

        foreach ($productUuids as $uuid) {
            if (!isset($indexed[$uuid])) {
                throw new EntityNotFoundException('Product', $uuid);
            }
        }

        foreach ($productUuids as $position => $uuid) {
            $indexed[$uuid]->setSortOrder($position);
        }

        $this->entityManager->flush();
    }

    /**
     * @throws EntityNotFoundException
     */
    public function delete(string $uuid, Restaurant $restaurant): void
    {
        $product = $this->productRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if ($product === null) {
            throw new EntityNotFoundException('Product', $uuid);
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();
    }

    /**
     * @throws EntityNotFoundException
     * @throws InvalidStatusTransitionException
     */
    public function submitForApproval(string $uuid, Restaurant $restaurant, User $user): Product
    {
        $product = $this->productRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if ($product === null) {
            throw new EntityNotFoundException('Product', $uuid);
        }

        if (!$product->getStatus()->canTransitionTo(ProductStatus::PENDING_APPROVAL)) {
            throw new InvalidStatusTransitionException($product->getStatus(), ProductStatus::PENDING_APPROVAL);
        }

        $product->setStatus(ProductStatus::PENDING_APPROVAL);
        $product->setSubmittedAt(new \DateTimeImmutable());

        $log = new ProductApprovalLog($product, ApprovalAction::SUBMITTED, $user);
        $this->entityManager->persist($log);

        $this->entityManager->flush();

        return $product;
    }

    /**
     * Clone an approved catalog product into the restaurant's own menu.
     *
     * @throws EntityNotFoundException
     */
    public function cloneFromCatalog(string $catalogUuid, string $categoryUuid, string $price, Restaurant $restaurant): Product
    {
        // Catalog products have no restaurant (global)
        $catalogProduct = $this->productRepository->createQueryBuilder('p')
            ->where('p.uuid = :uuid')
            ->andWhere('p.restaurant IS NULL')
            ->andWhere('p.status = :status')
            ->setParameter('uuid', $catalogUuid)
            ->setParameter('status', ProductStatus::APPROVED)
            ->getQuery()
            ->getOneOrNullResult();

        if ($catalogProduct === null) {
            throw new EntityNotFoundException('CatalogProduct', $catalogUuid);
        }

        $category = $this->categoryRepository->findOneByUuidAndRestaurant($categoryUuid, $restaurant);
        if ($category === null) {
            throw new EntityNotFoundException('Category', $categoryUuid);
        }

        $product = new Product($catalogProduct->getName(), $price, $category, $restaurant);
        $product->setDescription($catalogProduct->getDescription());
        $product->setAllergens($catalogProduct->getAllergens());
        $product->setCatalogProduct($catalogProduct);
        $product->setStatus(ProductStatus::APPROVED);

        // Clone images from catalog product
        $sortOrder = 0;
        foreach ($catalogProduct->getImages() as $catalogImage) {
            $image = new ProductImage($product, $catalogImage->getMedia(), $sortOrder++);
            $product->addImage($image);
            $this->entityManager->persist($image);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    /**
     * Sync product images: replace all existing images with the given media UUIDs.
     *
     * @param string[] $mediaUuids
     * @throws EntityNotFoundException
     */
    private function syncImages(Product $product, array $mediaUuids, Restaurant $restaurant): void
    {
        // Remove existing images
        foreach ($product->getImages() as $image) {
            $product->removeImage($image);
            $this->entityManager->remove($image);
        }

        // Add new images in the given order
        foreach ($mediaUuids as $sortOrder => $mediaUuid) {
            $media = $this->mediaRepository->findOneByUuidAndRestaurant($mediaUuid, $restaurant);
            if ($media === null) {
                throw new EntityNotFoundException('Media', $mediaUuid);
            }

            $image = new ProductImage($product, $media, $sortOrder);
            $product->addImage($image);
            $this->entityManager->persist($image);
        }
    }
}
