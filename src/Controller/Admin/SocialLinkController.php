<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\ReplaceSocialLinksRequest;
use App\DTO\Response\Admin\SocialLinkResponse;
use App\Entity\RestaurantSocialLink;
use App\Service\SocialLinkService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Admin - Social Links')]
class SocialLinkController extends AbstractController
{
    public function __construct(
        private readonly SocialLinkService $socialLinkService,
    ) {
    }

    #[Route('/social-links', name: 'admin_social_link_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/social-links',
        summary: 'List all social links for the restaurant',
        tags: ['Admin - Social Links']
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of social links',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'object')
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function list(): JsonResponse
    {
        $restaurant = $this->getUser()->getRestaurant();
        $links = $this->socialLinkService->listByRestaurant($restaurant);

        return $this->json(array_map(fn(RestaurantSocialLink $l) => $this->toResponse($l), $links));
    }

    #[Route('/social-links', name: 'admin_social_link_replace_all', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/admin/social-links',
        summary: 'Replace all social links for the restaurant',
        tags: ['Admin - Social Links']
    )]
    #[OA\RequestBody(
        required: true
    )]
    #[OA\Response(
        response: 200,
        description: 'Social links replaced successfully',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'object')
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 422, description: 'Validation error')]
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
