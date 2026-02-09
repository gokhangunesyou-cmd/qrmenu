<?php

namespace App\Controller\SuperAdmin;

use App\Entity\Theme;
use App\Repository\ThemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ThemeController extends AbstractController
{
    public function __construct(
        private readonly ThemeRepository $themeRepository,
    ) {
    }

    #[Route('/themes', name: 'super_admin_theme_list', methods: ['GET'])]
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
