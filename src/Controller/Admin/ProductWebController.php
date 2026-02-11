<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\DTO\Request\Admin\CreateProductRequest;
use App\DTO\Request\Admin\UpdateProductRequest;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Infrastructure\Storage\StorageInterface;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\LanguageContext;
use App\Service\ProductService;
use App\Service\TranslationService;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/products')]
class ProductWebController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductService $productService,
        private readonly StorageInterface $storage,
        private readonly TranslationService $translationService,
        private readonly LanguageContext $languageContext,
    ) {
    }

    #[Route('', name: 'admin_product_web_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale());

        $search = $request->query->getString('search', '');
        $categoryUuid = $request->query->getString('category', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        $category = null;
        if ($categoryUuid !== '') {
            $category = $this->categoryRepository->findOneByUuidAndRestaurant($categoryUuid, $restaurant);
        }

        $result = $this->productRepository->findByRestaurantPaginated(
            $restaurant,
            $category,
            $search !== '' ? $search : null,
            $page,
            $limit,
        );

        $categories = $this->categoryRepository->findByRestaurant($restaurant);
        $categoryIds = array_values(array_map(static fn ($category): int => (int) $category->getId(), $categories));
        $productIds = array_values(array_map(static fn ($product): int => (int) $product->getId(), $result['items']));
        $categoryTranslations = $this->translationService->getFieldMapWithFallback('category', $categoryIds, $locale);
        $productTranslations = $this->translationService->getFieldMapWithFallback('product', $productIds, $locale);

        $categoryView = [];
        foreach ($categories as $category) {
            $categoryId = (int) $category->getId();
            $categoryView[$categoryId] = $this->translationService->resolve($categoryTranslations, $categoryId, 'name', $category->getName()) ?? $category->getName();
        }

        $productView = [];
        foreach ($result['items'] as $product) {
            $productId = (int) $product->getId();
            $productView[$productId] = [
                'name' => $this->translationService->resolve($productTranslations, $productId, 'name', $product->getName()) ?? $product->getName(),
                'description' => $this->translationService->resolve($productTranslations, $productId, 'description', $product->getDescription()),
                'categoryName' => $categoryView[(int) $product->getCategory()->getId()] ?? $product->getCategory()->getName(),
            ];
        }

        return $this->render('admin/product/index.html.twig', [
            'products' => $result['items'],
            'totalItems' => $result['total'],
            'currentPage' => $page,
            'totalPages' => (int) ceil($result['total'] / $limit),
            'search' => $search,
            'categoryUuid' => $categoryUuid,
            'categories' => $categories,
            'storage' => $this->storage,
            'categoryView' => $categoryView,
            'productView' => $productView,
            'currentLocale' => $locale,
        ]);
    }

    #[Route('/create', name: 'admin_product_web_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale());

        $categories = $this->categoryRepository->findByRestaurant($restaurant);
        $categoryIds = array_values(array_map(static fn ($category): int => (int) $category->getId(), $categories));
        $categoryTranslations = $this->translationService->getFieldMapWithFallback('category', $categoryIds, $locale);
        $categoryView = [];
        foreach ($categories as $category) {
            $categoryId = (int) $category->getId();
            $categoryView[$categoryId] = $this->translationService->resolve($categoryTranslations, $categoryId, 'name', $category->getName()) ?? $category->getName();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('product_create', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Geçersiz CSRF token.');
                return $this->redirectToRoute('admin_product_web_create', ['lang' => $locale]);
            }

            try {
                $allergens = $request->request->all('allergens');
                $imageMediaUuids = $this->extractValidMediaUuids($request);

                $dto = new CreateProductRequest(
                    name: trim($request->request->getString('name')),
                    description: trim($request->request->getString('description')) ?: null,
                    price: $request->request->getString('price'),
                    categoryUuid: $request->request->getString('category_uuid'),
                    isFeatured: $request->request->getBoolean('is_featured'),
                    allergens: !empty($allergens) ? $allergens : null,
                    imageMediaUuids: !empty($imageMediaUuids) ? $imageMediaUuids : null,
                );

                $product = $this->productService->create($dto, $restaurant);

                // Set calories if provided
                $calories = $request->request->getString('calories');
                if ($calories !== '') {
                    $product->setCalories((int) $calories);
                }

                // Set isActive (default is true in entity, but handle form)
                $product->setIsActive($request->request->getBoolean('is_active'));

                // Flush for calories & isActive
                $this->productRepository->getEntityManager()->flush();
                $productId = (int) $product->getId();
                if ($productId > 0) {
                    $this->translationService->upsert('product', $productId, $locale, 'name', trim($request->request->getString('name')));
                    $this->translationService->upsert('product', $productId, $locale, 'description', trim($request->request->getString('description')) ?: null);
                    $this->productRepository->getEntityManager()->flush();
                }

                $this->addFlash('success', 'Ürün başarıyla oluşturuldu.');
                return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
            } catch (\App\Exception\EntityNotFoundException $e) {
                $this->addFlash('error', 'Kategori veya medya bulunamadı: ' . $e->getMessage());
            } catch (\App\Exception\ValidationException $e) {
                $this->addFlash('error', 'Doğrulama hatası: ' . $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Bir hata oluştu: ' . $e->getMessage());
            }

            return $this->render('admin/product/form.html.twig', [
                'categories' => $categories,
                'product' => null,
                'formData' => $request->request->all(),
                'storage' => $this->storage,
                'categoryView' => $categoryView,
                'currentLocale' => $locale,
            ]);
        }

        return $this->render('admin/product/form.html.twig', [
            'categories' => $categories,
            'product' => null,
            'formData' => [],
            'storage' => $this->storage,
            'categoryView' => $categoryView,
            'currentLocale' => $locale,
        ]);
    }

    #[Route('/{uuid}/edit', name: 'admin_product_web_edit', methods: ['GET', 'POST'])]
    public function edit(string $uuid, Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale());

        $product = $this->productRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if ($product === null) {
            $this->addFlash('error', 'Ürün bulunamadı.');
            return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
        }

        $categories = $this->categoryRepository->findByRestaurant($restaurant);
        $categoryIds = array_values(array_map(static fn ($category): int => (int) $category->getId(), $categories));
        $categoryTranslations = $this->translationService->getFieldMapWithFallback('category', $categoryIds, $locale);
        $categoryView = [];
        foreach ($categories as $category) {
            $categoryId = (int) $category->getId();
            $categoryView[$categoryId] = $this->translationService->resolve($categoryTranslations, $categoryId, 'name', $category->getName()) ?? $category->getName();
        }
        $productTranslation = $this->translationService->getFieldMapWithFallback('product', [(int) $product->getId()], $locale);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('product_edit_' . $uuid, $request->request->getString('_token'))) {
                $this->addFlash('error', 'Geçersiz CSRF token.');
                return $this->redirectToRoute('admin_product_web_edit', ['uuid' => $uuid, 'lang' => $locale]);
            }

            try {
                $allergens = $request->request->all('allergens');
                $imageMediaUuids = $this->extractValidMediaUuids($request);

                $dto = new UpdateProductRequest(
                    name: $locale === $restaurant->getDefaultLocale() ? trim($request->request->getString('name')) : $product->getName(),
                    description: $locale === $restaurant->getDefaultLocale() ? (trim($request->request->getString('description')) ?: null) : $product->getDescription(),
                    price: $request->request->getString('price'),
                    categoryUuid: $request->request->getString('category_uuid'),
                    isFeatured: $request->request->getBoolean('is_featured'),
                    isActive: $request->request->getBoolean('is_active'),
                    allergens: !empty($allergens) ? $allergens : [],
                    imageMediaUuids: $imageMediaUuids,
                );

                $this->productService->update($uuid, $dto, $restaurant);
                $productId = (int) $product->getId();
                if ($productId > 0) {
                    $this->translationService->upsert('product', $productId, $locale, 'name', trim($request->request->getString('name')));
                    $this->translationService->upsert('product', $productId, $locale, 'description', trim($request->request->getString('description')) ?: null);
                }

                // Set calories
                $calories = $request->request->getString('calories');
                $product->setCalories($calories !== '' ? (int) $calories : null);

                $this->productRepository->getEntityManager()->flush();

                $this->addFlash('success', 'Ürün başarıyla güncellendi.');
                return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
            } catch (\App\Exception\EntityNotFoundException $e) {
                $this->addFlash('error', 'Kategori veya medya bulunamadı: ' . $e->getMessage());
            } catch (\App\Exception\ValidationException $e) {
                $this->addFlash('error', 'Doğrulama hatası: ' . $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Bir hata oluştu: ' . $e->getMessage());
            }
        }

        return $this->render('admin/product/form.html.twig', [
            'categories' => $categories,
            'product' => $product,
            'formData' => [],
            'storage' => $this->storage,
            'categoryView' => $categoryView,
            'localizedName' => $this->translationService->resolve($productTranslation, (int) $product->getId(), 'name', $product->getName()),
            'localizedDescription' => $this->translationService->resolve($productTranslation, (int) $product->getId(), 'description', $product->getDescription()),
            'currentLocale' => $locale,
        ]);
    }

    #[Route('/{uuid}/delete', name: 'admin_product_web_delete', methods: ['POST'])]
    public function delete(string $uuid, Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale());

        if (!$this->isCsrfTokenValid('product_delete_' . $uuid, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz CSRF token.');
            return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
        }

        try {
            $this->productService->delete($uuid, $restaurant);
            $this->addFlash('success', 'Ürün başarıyla silindi.');
        } catch (\App\Exception\EntityNotFoundException) {
            $this->addFlash('error', 'Ürün bulunamadı.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Bir hata oluştu: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
    }

    #[Route('/{uuid}/toggle-active', name: 'admin_product_web_toggle_active', methods: ['POST'])]
    public function toggleActive(string $uuid, Request $request): JsonResponse
    {
        $restaurant = $this->getRestaurantOrThrow();

        if (!$this->isCsrfTokenValid('product_toggle_' . $uuid, $request->request->getString('_token'))) {
            return new JsonResponse(['error' => 'Geçersiz CSRF token.'], 403);
        }

        try {
            $product = $this->productRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
            if ($product === null) {
                return new JsonResponse(['error' => 'Ürün bulunamadı.'], 404);
            }

            $product->setIsActive(!$product->isActive());
            $this->productRepository->getEntityManager()->flush();

            return new JsonResponse([
                'success' => true,
                'isActive' => $product->isActive(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Bir hata oluştu.'], 500);
        }
    }

    #[Route('/bulk-delete', name: 'admin_product_web_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale());

        if (!$this->isCsrfTokenValid('product_bulk_delete', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz CSRF token.');
            return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
        }

        $uuids = $request->request->all('uuids');
        if (empty($uuids)) {
            $this->addFlash('error', 'Silinecek ürün seçilmedi.');
            return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
        }

        $deleted = 0;
        foreach ($uuids as $productUuid) {
            try {
                $this->productService->delete($productUuid, $restaurant);
                $deleted++;
            } catch (\Exception) {
                // Skip failed deletions
            }
        }

        $this->addFlash('success', sprintf('%d ürün başarıyla silindi.', $deleted));
        return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
    }

    #[Route('/bulk-price-update', name: 'admin_product_web_bulk_price_update', methods: ['POST'])]
    public function bulkPriceUpdate(Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale());

        if (!$this->isCsrfTokenValid('product_bulk_price_update', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz CSRF token.');
            return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
        }

        $operation = $request->request->getString('operation', 'increase_fixed');
        $rawAmount = str_replace(',', '.', trim($request->request->getString('amount')));
        $amount = (float) $rawAmount;

        if ($amount <= 0) {
            $this->addFlash('error', 'Lütfen 0\'dan büyük bir değer girin.');
            return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
        }

        $category = null;
        $categoryUuid = trim($request->request->getString('category_uuid'));
        if ($categoryUuid !== '') {
            $category = $this->categoryRepository->findOneByUuidAndRestaurant($categoryUuid, $restaurant);
            if ($category === null) {
                $this->addFlash('error', 'Kategori bulunamadı.');
                return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
            }
        }

        $products = $this->productRepository->findByRestaurantAndCategory($restaurant, $category);
        if ($products === []) {
            $this->addFlash('error', 'Güncellenecek ürün bulunamadı.');
            return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
        }

        $updatedCount = 0;
        foreach ($products as $product) {
            $current = (float) $product->getPrice();

            $newPrice = match ($operation) {
                'increase_fixed' => $current + $amount,
                'decrease_fixed' => $current - $amount,
                'increase_percent' => $current + ($current * ($amount / 100)),
                'decrease_percent' => $current - ($current * ($amount / 100)),
                default => null,
            };

            if ($newPrice === null) {
                $this->addFlash('error', 'Geçersiz fiyat güncelleme işlemi.');
                return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
            }

            $newPrice = max(0, $newPrice);
            $product->setPrice(number_format($newPrice, 2, '.', ''));
            ++$updatedCount;
        }

        $this->productRepository->getEntityManager()->flush();

        $this->addFlash('success', sprintf('%d ürünün fiyatı güncellendi.', $updatedCount));

        return $this->redirectToRoute('admin_product_web_index', ['lang' => $locale]);
    }

    private function getRestaurantOrThrow(): Restaurant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $restaurant = $user->getRestaurant();
        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        return $restaurant;
    }

    /**
     * @return string[]
     */
    private function extractValidMediaUuids(Request $request): array
    {
        $raw = explode(',', $request->request->getString('image_media_uuids', ''));

        return array_values(array_filter(array_map('trim', $raw), static function (string $uuid): bool {
            return $uuid !== '' && $uuid !== 'undefined' && Uuid::isValid($uuid);
        }));
    }
}
