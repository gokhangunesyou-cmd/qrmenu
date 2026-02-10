<?php

namespace App\Controller\Public;

use App\Service\MenuAssemblerService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Public - Menu')]
class MenuController extends AbstractController
{
    public function __construct(
        private readonly MenuAssemblerService $menuAssemblerService
    ) {
    }

    #[OA\Get(
        path: '/api/public/restaurants/{slug}',
        summary: 'Get restaurant menu by slug',
        tags: ['Public - Menu'],
        parameters: [
            new OA\Parameter(
                name: 'slug',
                in: 'path',
                required: true,
                description: 'Restaurant slug identifier',
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Restaurant menu data including categories, products, and settings'
            ),
            new OA\Response(
                response: 404,
                description: 'Restaurant not found'
            )
        ],
        security: []
    )]
    #[Route('/restaurants/{slug}', name: 'public_menu_get', methods: ['GET'])]
    public function getMenu(string $slug): JsonResponse
    {
        $menu = $this->menuAssemblerService->assembleMenu($slug);

        return $this->json($menu);
    }

    #[OA\Get(
        path: '/api/public/restaurants/{slug}/page/{type}',
        summary: 'Get restaurant page by type',
        tags: ['Public - Menu'],
        parameters: [
            new OA\Parameter(
                name: 'slug',
                in: 'path',
                required: true,
                description: 'Restaurant slug identifier',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'type',
                in: 'path',
                required: true,
                description: 'Page type (e.g., about, contact, privacy)',
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Page content'
            ),
            new OA\Response(
                response: 404,
                description: 'Page not found'
            )
        ],
        security: []
    )]
    #[Route('/restaurants/{slug}/page/{type}', name: 'public_menu_get_page', methods: ['GET'])]
    public function getPage(string $slug, string $type): JsonResponse
    {
        $page = $this->menuAssemblerService->getPage($slug, $type);

        return $this->json($page);
    }
}
