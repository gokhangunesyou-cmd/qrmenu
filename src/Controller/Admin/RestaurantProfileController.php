<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\UpdateRestaurantRequest;
use App\DTO\Response\Admin\RestaurantResponse;
use App\Entity\Restaurant;
use App\Infrastructure\Storage\StorageInterface;
use App\Service\RestaurantService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Admin - Restaurant Profile')]
class RestaurantProfileController extends AbstractController
{
    public function __construct(
        private readonly RestaurantService $restaurantService,
        private readonly StorageInterface $storage,
    ) {
    }

    #[Route('/restaurant', name: 'admin_restaurant_get_profile', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/restaurant',
        summary: 'Get restaurant profile',
        tags: ['Admin - Restaurant Profile']
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns restaurant profile'
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function getProfile(): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $restaurant = $this->restaurantService->getProfile($restaurant);

        return $this->json($this->toResponse($restaurant));
    }

    #[Route('/restaurant', name: 'admin_restaurant_update_profile', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/admin/restaurant',
        summary: 'Update restaurant profile',
        tags: ['Admin - Restaurant Profile']
    )]
    #[OA\RequestBody(
        required: true
    )]
    #[OA\Response(
        response: 200,
        description: 'Restaurant profile updated successfully'
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function updateProfile(#[MapRequestPayload] UpdateRestaurantRequest $request): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $restaurant = $this->restaurantService->updateProfile($restaurant, $request);

        return $this->json($this->toResponse($restaurant));
    }

    private function toResponse(Restaurant $restaurant): RestaurantResponse
    {
        $logoUrl = $restaurant->getLogoMedia() !== null
            ? $this->storage->getPublicUrl($restaurant->getLogoMedia()->getStoragePath())
            : null;

        $coverUrl = $restaurant->getCoverMedia() !== null
            ? $this->storage->getPublicUrl($restaurant->getCoverMedia()->getStoragePath())
            : null;

        return new RestaurantResponse(
            uuid: $restaurant->getUuid()->toString(),
            name: $restaurant->getName(),
            slug: $restaurant->getSlug(),
            description: $restaurant->getDescription(),
            phone: $restaurant->getPhone(),
            email: $restaurant->getEmail(),
            address: $restaurant->getAddress(),
            city: $restaurant->getCity(),
            countryCode: $restaurant->getCountryCode(),
            themeId: $restaurant->getTheme()->getId(),
            defaultLocale: $restaurant->getDefaultLocale(),
            currencyCode: $restaurant->getCurrencyCode(),
            colorOverrides: $restaurant->getColorOverrides(),
            metaTitle: $restaurant->getMetaTitle(),
            metaDescription: $restaurant->getMetaDescription(),
            logoUrl: $logoUrl,
            coverUrl: $coverUrl,
            isActive: $restaurant->isActive(),
            createdAt: $restaurant->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
