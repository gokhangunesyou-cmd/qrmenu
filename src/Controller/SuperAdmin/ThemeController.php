<?php

namespace App\Controller\SuperAdmin;

use App\Entity\Theme;
use App\Repository\ThemeRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'SuperAdmin - Themes')]
class ThemeController extends AbstractController
{
    public function __construct(
        private readonly ThemeRepository $themeRepository,
    ) {
    }

    #[Route('/themes', name: 'super_admin_theme_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/super-admin/themes',
        summary: 'List all active themes',
        tags: ['SuperAdmin - Themes']
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of active themes',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'slug', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'previewImageUrl', type: 'string', nullable: true),
                    new OA\Property(property: 'defaultConfig', type: 'object'),
                    new OA\Property(property: 'sortOrder', type: 'integer'),
                ],
                type: 'object'
            )
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden - SuperAdmin access required')]
    public function list(): JsonResponse
    {
        $themes = $this->themeRepository->findAllActive();

        return $this->json(array_map(fn(Theme $t) => [
            'id' => $t->getId(),
            'slug' => $t->getSlug(),
            'name' => $t->getName(),
            'description' => $t->getDescription(),
            'previewImageUrl' => $t->getPreviewImageUrl(),
            'defaultConfig' => $t->getDefaultConfig(),
            'sortOrder' => $t->getSortOrder(),
        ], $themes));
    }
}
