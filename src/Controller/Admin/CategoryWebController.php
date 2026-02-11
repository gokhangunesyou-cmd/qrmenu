<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Service\LanguageContext;
use App\Service\TranslationService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/categories')]
class CategoryWebController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $em,
        private readonly TranslationService $translationService,
        private readonly LanguageContext $languageContext,
    ) {
    }

    #[Route('', name: 'admin_category_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale());

        $categories = $this->categoryRepository->findByRestaurant($restaurant);
        $categoryIds = array_values(array_map(static fn (Category $category): int => (int) $category->getId(), $categories));
        $translations = $this->translationService->getFieldMapWithFallback('category', $categoryIds, $locale);

        $categoryView = [];
        foreach ($categories as $category) {
            $categoryId = (int) $category->getId();
            $categoryView[$categoryId] = [
                'name' => $this->translationService->resolve($translations, $categoryId, 'name', $category->getName()) ?? $category->getName(),
                'description' => $this->translationService->resolve($translations, $categoryId, 'description', $category->getDescription()),
            ];
        }

        return $this->render('admin/category/index.html.twig', [
            'categories' => $categories,
            'categoryView' => $categoryView,
            'currentLocale' => $locale,
        ]);
    }

    #[Route('/create', name: 'admin_category_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale());

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('category_create', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Geçersiz CSRF token.');
                return $this->redirectToRoute('admin_category_create', ['lang' => $locale]);
            }

            $name = trim($request->request->getString('name'));
            if ($name === '') {
                $this->addFlash('error', 'Kategori adı zorunludur.');
                return $this->render('admin/category/form.html.twig', [
                    'category' => null,
                    'formData' => $request->request->all(),
                    'currentLocale' => $locale,
                ]);
            }

            try {
                $category = new Category($name, $restaurant);
                $category->setDescription(trim($request->request->getString('description')) ?: null);
                $category->setIcon(trim($request->request->getString('icon')) ?: null);
                $category->setSortOrder($request->request->getInt('sort_order', 0));
                $category->setIsActive($request->request->getBoolean('is_active'));

                $this->em->persist($category);
                $this->em->flush();
                $categoryId = (int) $category->getId();
                if ($categoryId > 0) {
                    $this->translationService->upsert('category', $categoryId, $locale, 'name', $name);
                    $this->translationService->upsert('category', $categoryId, $locale, 'description', trim($request->request->getString('description')) ?: null);
                    $this->em->flush();
                }

                $this->addFlash('success', 'Kategori başarıyla oluşturuldu.');
                return $this->redirectToRoute('admin_category_index', ['lang' => $locale]);
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Bu isimde bir kategori zaten mevcut.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Bir hata oluştu: ' . $e->getMessage());
            }

            return $this->render('admin/category/form.html.twig', [
                'category' => null,
                'formData' => $request->request->all(),
                'currentLocale' => $locale,
            ]);
        }

        return $this->render('admin/category/form.html.twig', [
            'category' => null,
            'formData' => [],
            'currentLocale' => $locale,
        ]);
    }

    #[Route('/{uuid}/edit', name: 'admin_category_edit', methods: ['GET', 'POST'])]
    public function edit(string $uuid, Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale());

        $category = $this->categoryRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if ($category === null) {
            $this->addFlash('error', 'Kategori bulunamadı.');
            return $this->redirectToRoute('admin_category_index', ['lang' => $locale]);
        }
        $translationMap = $this->translationService->getFieldMapWithFallback('category', [(int) $category->getId()], $locale);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('category_edit_' . $uuid, $request->request->getString('_token'))) {
                $this->addFlash('error', 'Geçersiz CSRF token.');
                return $this->redirectToRoute('admin_category_edit', ['uuid' => $uuid, 'lang' => $locale]);
            }

            $name = trim($request->request->getString('name'));
            if ($name === '') {
                $this->addFlash('error', 'Kategori adı zorunludur.');
                return $this->render('admin/category/form.html.twig', [
                    'category' => $category,
                    'formData' => $request->request->all(),
                    'currentLocale' => $locale,
                ]);
            }

            try {
                $isDefaultLocale = $locale === $restaurant->getDefaultLocale();
                if ($isDefaultLocale) {
                    $category->setName($name);
                    $category->setDescription(trim($request->request->getString('description')) ?: null);
                }
                $category->setIcon(trim($request->request->getString('icon')) ?: null);
                $category->setSortOrder($request->request->getInt('sort_order', 0));
                $category->setIsActive($request->request->getBoolean('is_active'));
                $categoryId = (int) $category->getId();
                if ($categoryId > 0) {
                    $this->translationService->upsert('category', $categoryId, $locale, 'name', $name);
                    $this->translationService->upsert('category', $categoryId, $locale, 'description', trim($request->request->getString('description')) ?: null);
                }

                $this->em->flush();

                $this->addFlash('success', 'Kategori başarıyla güncellendi.');
                return $this->redirectToRoute('admin_category_index', ['lang' => $locale]);
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Bu isimde bir kategori zaten mevcut.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Bir hata oluştu: ' . $e->getMessage());
            }
        }

        return $this->render('admin/category/form.html.twig', [
            'category' => $category,
            'formData' => [],
            'localizedName' => $this->translationService->resolve($translationMap, (int) $category->getId(), 'name', $category->getName()),
            'localizedDescription' => $this->translationService->resolve($translationMap, (int) $category->getId(), 'description', $category->getDescription()),
            'currentLocale' => $locale,
        ]);
    }

    #[Route('/{uuid}/delete', name: 'admin_category_delete', methods: ['POST'])]
    public function delete(string $uuid, Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale());

        if (!$this->isCsrfTokenValid('category_delete_' . $uuid, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz CSRF token.');
            return $this->redirectToRoute('admin_category_index', ['lang' => $locale]);
        }

        $category = $this->categoryRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if ($category === null) {
            $this->addFlash('error', 'Kategori bulunamadı.');
            return $this->redirectToRoute('admin_category_index', ['lang' => $locale]);
        }

        if ($category->getProducts()->count() > 0) {
            $this->addFlash('error', 'Bu kategoriye bağlı ürünler olduğu için silinemez.');
            return $this->redirectToRoute('admin_category_index', ['lang' => $locale]);
        }

        try {
            $this->em->remove($category);
            $this->em->flush();
            $this->addFlash('success', 'Kategori başarıyla silindi.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Bir hata oluştu: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_category_index', ['lang' => $locale]);
    }

    #[Route('/{uuid}/toggle-active', name: 'admin_category_toggle_active', methods: ['POST'])]
    public function toggleActive(string $uuid, Request $request): JsonResponse
    {
        $restaurant = $this->getRestaurantOrThrow();

        if (!$this->isCsrfTokenValid('category_toggle_' . $uuid, $request->request->getString('_token'))) {
            return new JsonResponse(['error' => 'Geçersiz CSRF token.'], 403);
        }

        $category = $this->categoryRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if ($category === null) {
            return new JsonResponse(['error' => 'Kategori bulunamadı.'], 404);
        }

        try {
            $category->setIsActive(!$category->isActive());
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'isActive' => $category->isActive(),
            ]);
        } catch (\Exception) {
            return new JsonResponse(['error' => 'Bir hata oluştu.'], 500);
        }
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
}
