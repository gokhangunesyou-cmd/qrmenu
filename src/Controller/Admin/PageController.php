<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\UpsertPageRequest;
use App\DTO\Response\Admin\PageResponse;
use App\Entity\RestaurantPage;
use App\Enum\PageType;
use App\Exception\EntityNotFoundException;
use App\Service\PageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class PageController extends AbstractController
{
    public function __construct(
        private readonly PageService $pageService,
    ) {
    }

    #[Route('/pages', name: 'admin_page_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $pages = $this->pageService->listByRestaurant($restaurant);

        return $this->json(array_map(fn(RestaurantPage $p) => $this->toResponse($p), $pages));
    }

    #[Route('/pages/{type}', name: 'admin_page_upsert', methods: ['PUT'])]
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
