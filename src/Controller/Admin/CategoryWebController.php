<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\CreateCategoryRequest;
use App\DTO\Request\Admin\UpdateCategoryRequest;
use App\Entity\Category;
use App\Entity\Media;
use App\Form\CategoryType;
use App\Infrastructure\Storage\StorageInterface;
use App\Service\CategoryService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories')]
#[IsGranted('ROLE_RESTAURANT_ADMIN')]
class CategoryWebController extends AbstractController
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly StorageInterface $storage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/', name: 'admin_category_index', methods: ['GET'])]
    public function index(): Response
    {
        $restaurant = $this->getUser()->getRestaurant();
        $categories = $this->categoryService->listByRestaurant($restaurant);

        return $this->render('admin/category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/new', name: 'admin_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $restaurant = $this->getUser()->getRestaurant();
        $category = new Category('', $restaurant);
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $imageUuid = $this->handleImageUpload($form->get('imageFile')->getData(), $restaurant);

                $createRequest = new CreateCategoryRequest(
                    name: $category->getName(),
                    description: $category->getDescription(),
                    icon: $category->getIcon(),
                    imageMediaUuid: $imageUuid
                );

                $this->categoryService->create($createRequest, $restaurant);
                $this->addFlash('success', 'Kategori başarıyla oluşturuldu.');

                return $this->redirectToRoute('admin_category_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Hata: ' . $e->getMessage());
            }
        }

        return $this->render('admin/category/new.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{uuid}/edit', name: 'admin_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $uuid): Response
    {
        $restaurant = $this->getUser()->getRestaurant();
        // We fetch directly from repository or service to get the entity for the Form
        // Since Service returns entities, we can use list or find logic.
        // But Service doesn't have a simple "get" public method that returns Entity (it has update/delete).
        // I will use repository directly here for the Form binding, which is standard pattern.
        $category = $this->entityManager->getRepository(Category::class)->findOneByUuidAndRestaurant($uuid, $restaurant);

        if (!$category) {
            throw $this->createNotFoundException('Kategori bulunamadı.');
        }

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $imageUuid = $this->handleImageUpload($form->get('imageFile')->getData(), $restaurant);
                
                // If no new image, keep existing (null in Request means "don't change" in Service logic)
                // But CreateCategoryRequest needs all fields? No, UpdateCategoryRequest has optionals.
                
                $updateRequest = new UpdateCategoryRequest(
                    name: $category->getName(),
                    description: $category->getDescription(),
                    icon: $category->getIcon(),
                    imageMediaUuid: $imageUuid,
                    isActive: $category->isActive()
                );

                $this->categoryService->update($uuid, $updateRequest, $restaurant);
                $this->addFlash('success', 'Kategori güncellendi.');

                return $this->redirectToRoute('admin_category_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Hata: ' . $e->getMessage());
            }
        }

        return $this->render('admin/category/edit.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{uuid}', name: 'admin_category_delete', methods: ['POST'])]
    public function delete(Request $request, string $uuid): Response
    {
        $restaurant = $this->getUser()->getRestaurant();
        if ($this->isCsrfTokenValid('delete'.$uuid, $request->request->get('_token'))) {
            try {
                $this->categoryService->delete($uuid, $restaurant);
                $this->addFlash('success', 'Kategori silindi.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Silinemedi: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_category_index');
    }

    private function handleImageUpload($file, $restaurant): ?string
    {
        if (!$file) {
            return null;
        }

        $mimeType = $file->getMimeType();
        $extension = $file->guessExtension() ?? 'bin';
        $mediaUuid = Uuid::uuid7()->toString();
        $storagePath = sprintf('media/%s/%s.%s', $restaurant->getUuid()->toString(), $mediaUuid, $extension);

        $this->storage->put($storagePath, file_get_contents($file->getPathname()), $mimeType);

        $media = new Media(
            $file->getClientOriginalName(),
            $storagePath,
            $mimeType,
            $file->getSize(),
            $restaurant
        );

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media->getUuid()->toString();
    }
}
