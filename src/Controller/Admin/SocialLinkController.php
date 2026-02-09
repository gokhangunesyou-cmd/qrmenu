<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\ReplaceSocialLinksRequest;
use App\DTO\Response\Admin\SocialLinkResponse;
use App\Entity\RestaurantSocialLink;
use App\Service\SocialLinkService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class SocialLinkController extends AbstractController
{
    public function __construct(
        private readonly SocialLinkService $socialLinkService,
    ) {
    }

    #[Route('/social-links', name: 'admin_social_link_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $links = $this->socialLinkService->listByRestaurant($restaurant);

        return $this->json(array_map(fn(RestaurantSocialLink $l) => $this->toResponse($l), $links));
    }

    #[Route('/social-links', name: 'admin_social_link_replace_all', methods: ['PUT'])]
    public function replaceAll(#[MapRequestPayload] ReplaceSocialLinksRequest $request): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $links = $this->socialLinkService->replaceAll($request->links, $restaurant);

        return $this->json(array_map(fn(RestaurantSocialLink $l) => $this->toResponse($l), $links));
    }

    private function toResponse(RestaurantSocialLink $link): SocialLinkResponse
    {
        return new SocialLinkResponse(
            platform: $link->getPlatform()->value,
            url: $link->getUrl(),
            sortOrder: $link->getSortOrder(),
        );
    }
}
