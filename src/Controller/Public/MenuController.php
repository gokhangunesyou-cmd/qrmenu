<?php

namespace App\Controller\Public;

use App\Service\MenuAssemblerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class MenuController extends AbstractController
{
    public function __construct(
        private readonly MenuAssemblerService $menuAssemblerService
    ) {
    }

    #[Route('/restaurants/{slug}', name: 'public_menu_get', methods: ['GET'])]
    public function getMenu(string $slug): JsonResponse
    {
        $menu = $this->menuAssemblerService->assembleMenu($slug);

        return $this->json($menu);
    }

    #[Route('/restaurants/{slug}/page/{type}', name: 'public_menu_get_page', methods: ['GET'])]
    public function getPage(string $slug, string $type): JsonResponse
    {
        $page = $this->menuAssemblerService->getPage($slug, $type);

        return $this->json($page);
    }
}
