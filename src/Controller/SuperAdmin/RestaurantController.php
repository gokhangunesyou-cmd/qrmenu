<?php

namespace App\Controller\SuperAdmin;

use App\DTO\Request\SuperAdmin\CreateRestaurantRequest;
use App\DTO\Request\SuperAdmin\UpdateRestaurantRequest;
use App\DTO\Response\SuperAdmin\RestaurantDetailResponse;
use App\DTO\Response\SuperAdmin\RestaurantListItemResponse;
use App\Entity\Restaurant;
use App\Service\RestaurantService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'SuperAdmin - Restaurants')]
class RestaurantController extends AbstractController
{
    public function __construct(
        private readonly RestaurantService $restaurantService,
    ) {
    }

    #[Route('/restaurants', name: 'super_admin_restaurant_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/super-admin/restaurants',
        summary: 'List all restaurants',
        tags: ['SuperAdmin - Restaurants']
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of all restaurants',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'object')
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
    public function list(): JsonResponse
    {
        $restaurants = $this->restaurantService->listAll();

        $response = array_map(fn(Restaurant $r) => new RestaurantListItemResponse(
            uuid: $r->getUuid()->toString(),
            name: $r->getName(),
            slug: $r->getSlug(),
            isActive: $r->isActive(),
            productCount: $r->getProducts()->count(),
            createdAt: $r->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ), $restaurants);

        return $this->json($response);
    }

    #[Route('/restaurants', name: 'super_admin_restaurant_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/super-admin/restaurants',
        summary: 'Create a new restaurant',
        tags: ['SuperAdmin - Restaurants']
    )]
    #[OA\RequestBody(
        required: true
    )]
    #[OA\Response(
        response: 201,
        description: 'Restaurant created successfully'
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function create(#[MapRequestPayload] CreateRestaurantRequest $request): JsonResponse
    {
        $restaurant = $this->restaurantService->onboard($request);

        return $this->json($this->toDetail($restaurant), 201);
    }

    #[Route('/restaurants/{uuid}', name: 'super_admin_restaurant_update', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/super-admin/restaurants/{uuid}',
        summary: 'Update a restaurant',
        tags: ['SuperAdmin - Restaurants']
    )]
    #[OA\Parameter(
        name: 'uuid',
        in: 'path',
        required: true,
        description: 'Restaurant UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\RequestBody(
        required: true
    )]
    #[OA\Response(
        response: 200,
        description: 'Restaurant updated successfully'
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
    #[OA\Response(response: 404, description: 'Restaurant not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(string $uuid, #[MapRequestPayload] UpdateRestaurantRequest $request): JsonResponse
    {
        $restaurant = $this->restaurantService->updateBySuperAdmin($uuid, $request);

        return $this->json($this->toDetail($restaurant));
    }

    #[Route('/restaurants/{uuid}/activate', name: 'super_admin_restaurant_activate', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/super-admin/restaurants/{uuid}/activate',
        summary: 'Activate a restaurant',
        tags: ['SuperAdmin - Restaurants']
    )]
    #[OA\Parameter(
        name: 'uuid',
        in: 'path',
        required: true,
        description: 'Restaurant UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Restaurant activated successfully'
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
    #[OA\Response(response: 404, description: 'Restaurant not found')]
    public function activate(string $uuid): JsonResponse
    {
        $restaurant = $this->restaurantService->activate($uuid);

        return $this->json($this->toDetail($restaurant));
    }

    #[Route('/restaurants/{uuid}/deactivate', name: 'super_admin_restaurant_deactivate', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/super-admin/restaurants/{uuid}/deactivate',
        summary: 'Deactivate a restaurant',
        tags: ['SuperAdmin - Restaurants']
    )]
    #[OA\Parameter(
        name: 'uuid',
        in: 'path',
        required: true,
        description: 'Restaurant UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Restaurant deactivated successfully'
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
    #[OA\Response(response: 404, description: 'Restaurant not found')]
    public function deactivate(string $uuid): JsonResponse
    {
        $restaurant = $this->restaurantService->deactivate($uuid);

        return $this->json($this->toDetail($restaurant));
    }

    private function toDetail(Restaurant $restaurant): RestaurantDetailResponse
    {
        $owner = $this->restaurantService->findOwnerByRestaurant($restaurant);

        return new RestaurantDetailResponse(
            uuid: $restaurant->getUuid()->toString(),
            name: $restaurant->getName(),
            slug: $restaurant->getSlug(),
            ownerEmail: $owner?->getEmail() ?? '',
            ownerName: $owner !== null ? sprintf('%s %s', $owner->getFirstName(), $owner->getLastName()) : '',
            isActive: $restaurant->isActive(),
            themeSlug: $restaurant->getTheme()->getSlug(),
            createdAt: $restaurant->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
