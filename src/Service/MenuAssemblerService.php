<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Response\Public\MenuResponse;
use App\DTO\Response\Public\PageResponse;
use App\Entity\Category;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\RestaurantPage;
use App\Entity\RestaurantSocialLink;
use App\Enum\PageType;
use App\Repository\RestaurantRepository;
use App\Repository\CategoryRepository;
use App\Repository\RestaurantPageRepository;
use App\Repository\RestaurantSocialLinkRepository;
use App\Infrastructure\Storage\StorageInterface;
use App\Exception\EntityNotFoundException;

class MenuAssemblerService
{
    public function __construct(
        private readonly RestaurantRepository $restaurantRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly RestaurantPageRepository $restaurantPageRepository,
        private readonly RestaurantSocialLinkRepository $restaurantSocialLinkRepository,
        private readonly StorageInterface $storage,
    ) {
    }

    /**
     * Assemble full denormalized menu response for public display.
     * Uses optimized repository queries to avoid N+1 problems.
     */
    public function assembleMenu(string $slug): MenuResponse
    {
        $restaurant = $this->restaurantRepository->findActiveBySlugForMenu($slug);

        if ($restaurant === null) {
            throw new EntityNotFoundException('Restaurant', $slug);
        }

        // Single query: categories + products + images + media
        $categories = $this->categoryRepository->findActiveWithProductsForMenu($restaurant);

        // Pages and social links
        $pages = $this->restaurantPageRepository->findByRestaurant($restaurant);
        $socialLinks = $this->restaurantSocialLinkRepository->findByRestaurant($restaurant);

        // Build theme array
        $theme = $restaurant->getTheme();
        $themeData = [
            'slug' => $theme->getSlug(),
            'name' => $theme->getName(),
            'config' => $theme->getDefaultConfig(),
        ];

        // Merge restaurant color overrides into theme config
        if ($restaurant->getColorOverrides() !== null) {
            $themeData['config'] = array_merge($themeData['config'], $restaurant->getColorOverrides());
        }

        if ($restaurant->getCustomCss() !== null) {
            $themeData['customCss'] = $restaurant->getCustomCss();
        }

        return new MenuResponse(
            uuid: $restaurant->getUuid()->toString(),
            name: $restaurant->getName(),
            slug: $restaurant->getSlug(),
            description: $restaurant->getDescription(),
            phone: $restaurant->getPhone(),
            email: $restaurant->getEmail(),
            address: $restaurant->getAddress(),
            city: $restaurant->getCity(),
            theme: $themeData,
            categories: $this->mapCategories($categories),
            socialLinks: $this->mapSocialLinks($socialLinks),
            pages: $this->mapPages($pages),
            currency: $restaurant->getCurrencyCode(),
            locale: $restaurant->getDefaultLocale(),
            logoUrl: $this->mediaUrl($restaurant->getLogoMedia()),
            coverUrl: $this->mediaUrl($restaurant->getCoverMedia()),
            metaTitle: $restaurant->getMetaTitle(),
            metaDescription: $restaurant->getMetaDescription(),
        );
    }

    /**
     * Get a single published page for a restaurant.
     */
    public function getPage(string $slug, string $type): PageResponse
    {
        $restaurant = $this->restaurantRepository->findActiveBySlugForMenu($slug);

        if ($restaurant === null) {
            throw new EntityNotFoundException('Restaurant', $slug);
        }

        $pageType = PageType::tryFrom($type);
        if ($pageType === null) {
            throw new EntityNotFoundException('Page', $type);
        }

        $page = $this->restaurantPageRepository->findOneByRestaurantAndType($restaurant, $pageType);

        if ($page === null || !$page->isPublished()) {
            throw new EntityNotFoundException('Page', $type);
        }

        return new PageResponse(
            type: $page->getType()->value,
            title: $page->getTitle(),
            body: $page->getBody(),
        );
    }

    /**
     * @param Category[] $categories
     */
    private function mapCategories(array $categories): array
    {
        $result = [];

        foreach ($categories as $category) {
            $products = [];
            foreach ($category->getProducts() as $product) {
                $products[] = $this->mapProduct($product);
            }

            // Skip categories with no active products
            if (empty($products)) {
                continue;
            }

            $result[] = [
                'uuid' => $category->getUuid()->toString(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
                'icon' => $category->getIcon(),
                'imageUrl' => $this->mediaUrl($category->getImageMedia()),
                'sortOrder' => $category->getSortOrder(),
                'products' => $products,
            ];
        }

        return $result;
    }

    private function mapProduct(Product $product): array
    {
        $images = [];
        foreach ($product->getImages() as $productImage) {
            $media = $productImage->getMedia();
            $images[] = [
                'url' => $this->storage->getPublicUrl($media->getStoragePath()),
                'altText' => $media->getAltText(),
                'sortOrder' => $productImage->getSortOrder(),
            ];
        }

        return [
            'uuid' => $product->getUuid()->toString(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'isFeatured' => $product->isFeatured(),
            'allergens' => $product->getAllergens(),
            'sortOrder' => $product->getSortOrder(),
            'images' => $images,
        ];
    }

    /**
     * @param RestaurantSocialLink[] $socialLinks
     */
    private function mapSocialLinks(array $socialLinks): array
    {
        return array_map(fn(RestaurantSocialLink $link) => [
            'platform' => $link->getPlatform()->value,
            'url' => $link->getUrl(),
            'sortOrder' => $link->getSortOrder(),
        ], $socialLinks);
    }

    /**
     * @param RestaurantPage[] $pages
     */
    private function mapPages(array $pages): array
    {
        $result = [];

        foreach ($pages as $page) {
            if (!$page->isPublished()) {
                continue;
            }

            $result[] = [
                'type' => $page->getType()->value,
                'title' => $page->getTitle(),
            ];
        }

        return $result;
    }

    private function mediaUrl(?Media $media): ?string
    {
        if ($media === null) {
            return null;
        }

        return $this->storage->getPublicUrl($media->getStoragePath());
    }
}
