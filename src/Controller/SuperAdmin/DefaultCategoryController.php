<?php

namespace App\Controller\SuperAdmin;

use App\DTO\Request\SuperAdmin\CreateDefaultCategoryRequest;
use App\DTO\Request\SuperAdmin\UpdateDefaultCategoryRequest;
use App\DTO\Response\SuperAdmin\DefaultCategoryResponse;
use App\Entity\Category;
use App\Exception\EntityNotFoundException;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class DefaultCategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/default-categories', name: 'super_admin_default_category_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $categories = $this->categoryRepository->findGlobalDefaults();

        return $this->json(array_map(fn(Category $c) => $this->toResponse($c), $categories));
    }

    #[Route('/default-categories', name: 'super_admin_default_category_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateDefaultCategoryRequest $request): JsonResponse
    {
        $category = new Category($request->name);
        $category->setDescription($request->description);
        $category->setIcon($request->icon);

        $existing = $this->categoryRepository->findGlobalDefaults();
        $category->setSortOrder(count($existing));

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $this->json($this->toResponse($category), 201);
    }

    #[Route('/default-categories/{uuid}', name: 'super_admin_default_category_update', methods: ['PUT'])]
    public function update(string $uuid, #[MapRequestPayload] UpdateDefaultCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryRepository->createQueryBuilder('c')
            ->where('c.uuid = :uuid')
            ->andWhere('c.restaurant IS NULL')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();

        if ($category === null) {
            throw new EntityNotFoundException('DefaultCategory', $uuid);
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

        $this->entityManager->flush();

        return $this->json($this->toResponse($category));
    }

    private function toResponse(Category $category): DefaultCategoryResponse
    {
        return new DefaultCategoryResponse(
            uuid: $category->getUuid()->toString(),
            name: $category->getName(),
            description: $category->getDescription(),
            icon: $category->getIcon(),
            sortOrder: $category->getSortOrder(),
        );
    }
}
