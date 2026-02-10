<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\DTO\Request\Admin\CreateProductRequest;
use App\DTO\Request\Admin\UpdateProductRequest;
use App\Infrastructure\Storage\StorageInterface;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\ProductService;
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
    ) {
    }

    #[Route('', name: 'admin_product_web_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

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

        return $this->render('admin/product/index.html.twig', [
            'products' => $result['items'],
            'totalItems' => $result['total'],
            'currentPage' => $page,
            'totalPages' => (int) ceil($result['total'] / $limit),
            'search' => $search,
            'categoryUuid' => $categoryUuid,
            'categories' => $categories,
            'storage' => $this->storage,
        ]);
    }

    #[Route('/create', name: 'admin_product_web_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        $categories = $this->categoryRepository->findByRestaurant($restaurant);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('product_create', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Geçersiz CSRF token.');
                return $this->redirectToRoute('admin_product_web_create');
            }

            try {
                $allergens = $request->request->all('allergens');
                $imageMediaUuids = array_filter(explode(',', $request->request->getString('image_media_uuids', '')));

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

                $this->addFlash('success', 'Ürün başarıyla oluşturuldu.');
                return $this->redirectToRoute('admin_product_web_index');
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
            ]);
        }

        return $this->render('admin/product/form.html.twig', [
            'categories' => $categories,
            'product' => null,
            'formData' => [],
            'storage' => $this->storage,
        ]);
    }

    #[Route('/{uuid}/edit', name: 'admin_product_web_edit', methods: ['GET', 'POST'])]
    public function edit(string $uuid, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        $product = $this->productRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if ($product === null) {
            $this->addFlash('error', 'Ürün bulunamadı.');
            return $this->redirectToRoute('admin_product_web_index');
        }

        $categories = $this->categoryRepository->findByRestaurant($restaurant);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('product_edit_' . $uuid, $request->request->getString('_token'))) {
                $this->addFlash('error', 'Geçersiz CSRF token.');
                return $this->redirectToRoute('admin_product_web_edit', ['uuid' => $uuid]);
            }

            try {
                $allergens = $request->request->all('allergens');
                $imageMediaUuids = array_filter(explode(',', $request->request->getString('image_media_uuids', '')));

                $dto = new UpdateProductRequest(
                    name: trim($request->request->getString('name')),
                    description: trim($request->request->getString('description')) ?: null,
                    price: $request->request->getString('price'),
                    categoryUuid: $request->request->getString('category_uuid'),
                    isFeatured: $request->request->getBoolean('is_featured'),
                    isActive: $request->request->getBoolean('is_active'),
                    allergens: !empty($allergens) ? $allergens : [],
                    imageMediaUuids: $imageMediaUuids,
                );

                $this->productService->update($uuid, $dto, $restaurant);

                // Set calories
                $calories = $request->request->getString('calories');
                $product->setCalories($calories !== '' ? (int) $calories : null);

                $this->productRepository->getEntityManager()->flush();

                $this->addFlash('success', 'Ürün başarıyla güncellendi.');
                return $this->redirectToRoute('admin_product_web_index');
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
        ]);
    }

    #[Route('/{uuid}/delete', name: 'admin_product_web_delete', methods: ['POST'])]
    public function delete(string $uuid, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        if (!$this->isCsrfTokenValid('product_delete_' . $uuid, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz CSRF token.');
            return $this->redirectToRoute('admin_product_web_index');
        }

        try {
            $this->productService->delete($uuid, $restaurant);
            $this->addFlash('success', 'Ürün başarıyla silindi.');
        } catch (\App\Exception\EntityNotFoundException) {
            $this->addFlash('error', 'Ürün bulunamadı.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Bir hata oluştu: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_product_web_index');
    }

    #[Route('/{uuid}/toggle-active', name: 'admin_product_web_toggle_active', methods: ['POST'])]
    public function toggleActive(string $uuid, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            return new JsonResponse(['error' => 'Restaurant not found'], 404);
        }

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
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        if (!$this->isCsrfTokenValid('product_bulk_delete', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz CSRF token.');
            return $this->redirectToRoute('admin_product_web_index');
        }

        $uuids = $request->request->all('uuids');
        if (empty($uuids)) {
            $this->addFlash('error', 'Silinecek ürün seçilmedi.');
            return $this->redirectToRoute('admin_product_web_index');
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
        return $this->redirectToRoute('admin_product_web_index');
    }
}
