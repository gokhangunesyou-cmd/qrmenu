<?php

namespace App\Controller\SuperAdmin;

use App\DTO\Request\SuperAdmin\CreateRestaurantRequest;
use App\DTO\Request\SuperAdmin\UpdateRestaurantRequest;
use App\DTO\Response\SuperAdmin\RestaurantDetailResponse;
use App\DTO\Response\SuperAdmin\RestaurantListItemResponse;
use App\Entity\Restaurant;
use App\Service\RestaurantService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class RestaurantController extends AbstractController
{
    public function __construct(
        private readonly RestaurantService $restaurantService,
    ) {
    }

    #[Route('/restaurants', name: 'super_admin_restaurant_list', methods: ['GET'])]
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
    public function create(#[MapRequestPayload] CreateRestaurantRequest $request): JsonResponse
    {
        $restaurant = $this->restaurantService->onboard($request);

        return $this->json($this->toDetail($restaurant), 201);
    }

    #[Route('/restaurants/{uuid}', name: 'super_admin_restaurant_update', methods: ['PUT'])]
    public function update(string $uuid, #[MapRequestPayload] UpdateRestaurantRequest $request): JsonResponse
    {
        $restaurant = $this->restaurantService->updateBySuperAdmin($uuid, $request);

        return $this->json($this->toDetail($restaurant));
    }

    #[Route('/restaurants/{uuid}/activate', name: 'super_admin_restaurant_activate', methods: ['PATCH'])]
    public function activate(string $uuid): JsonResponse
    {
        $restaurant = $this->restaurantService->activate($uuid);

        return $this->json($this->toDetail($restaurant));
    }

    #[Route('/restaurants/{uuid}/deactivate', name: 'super_admin_restaurant_deactivate', methods: ['PATCH'])]
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
