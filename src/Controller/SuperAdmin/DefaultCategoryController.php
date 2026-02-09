<?php

namespace App\Controller\SuperAdmin;

use App\DTO\Request\SuperAdmin\CreateDefaultCategoryRequest;
use App\DTO\Request\SuperAdmin\UpdateDefaultCategoryRequest;
use App\DTO\Response\SuperAdmin\DefaultCategoryResponse;
use App\Entity\Category;
use App\Exception\EntityNotFoundException;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'SuperAdmin - Default Categories')]
class DefaultCategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/default-categories', name: 'super_admin_default_category_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/super-admin/default-categories',
        summary: 'List all global default categories',
        tags: ['SuperAdmin - Default Categories']
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of default categories',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'object')
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
    public function list(): JsonResponse
    {
        $categories = $this->categoryRepository->findGlobalDefaults();

        return $this->json(array_map(fn(Category $c) => $this->toResponse($c), $categories));
    }

    #[Route('/default-categories', name: 'super_admin_default_category_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/super-admin/default-categories',
        summary: 'Create a new default category',
        tags: ['SuperAdmin - Default Categories']
    )]
    #[OA\RequestBody(
        required: true
    )]
    #[OA\Response(
        response: 201,
        description: 'Default category created successfully'
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
    #[OA\Response(response: 422, description: 'Validation error')]
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
    #[OA\Put(
        path: '/api/super-admin/default-categories/{uuid}',
        summary: 'Update a default category',
        tags: ['SuperAdmin - Default Categories']
    )]
    #[OA\Parameter(
        name: 'uuid',
        in: 'path',
        required: true,
        description: 'Category UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\RequestBody(
        required: true
    )]
    #[OA\Response(
        response: 200,
        description: 'Default category updated successfully'
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
    #[OA\Response(response: 404, description: 'Category not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
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
