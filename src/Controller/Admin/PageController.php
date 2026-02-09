<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\UpsertPageRequest;
use App\DTO\Response\Admin\PageResponse;
use App\Entity\RestaurantPage;
use App\Enum\PageType;
use App\Exception\EntityNotFoundException;
use App\Service\PageService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Admin - Pages')]
class PageController extends AbstractController
{
    public function __construct(
        private readonly PageService $pageService,
    ) {
    }

    #[Route('/pages', name: 'admin_page_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/pages',
        summary: 'List all pages for the restaurant',
        tags: ['Admin - Pages']
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of restaurant pages',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'object')
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function list(): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $pages = $this->pageService->listByRestaurant($restaurant);

        return $this->json(array_map(fn(RestaurantPage $p) => $this->toResponse($p), $pages));
    }

    #[Route('/pages/{type}', name: 'admin_page_upsert', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/admin/pages/{type}',
        summary: 'Create or update a page by type',
        tags: ['Admin - Pages']
    )]
    #[OA\Parameter(
        name: 'type',
        in: 'path',
        required: true,
        description: 'Page type (e.g., about, privacy, terms)',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\RequestBody(
        required: true
    )]
    #[OA\Response(
        response: 200,
        description: 'Page created or updated successfully'
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Invalid page type')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function upsert(string $type, #[MapRequestPayload] UpsertPageRequest $request): JsonResponse
    {
        $pageType = PageType::tryFrom($type);
        if ($pageType === null) {
            throw new EntityNotFoundException('PageType', $type);
        }

        $restaurant = $this->getUser()->getRestaurant();
        $page = $this->pageService->upsert($pageType, $request, $restaurant);

        return $this->json($this->toResponse($page));
    }

    private function toResponse(RestaurantPage $page): PageResponse
    {
        return new PageResponse(
            type: $page->getType()->value,
            title: $page->getTitle(),
            body: $page->getBody(),
            isPublished: $page->isPublished(),
        );
    }
}
