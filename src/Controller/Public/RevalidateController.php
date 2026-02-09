<?php

namespace App\Controller\Public;

use App\Exception\AccessDeniedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class RevalidateController extends AbstractController
{
    public function __construct(
        private readonly string $revalidationSecret,
    ) {
    }

    /**
     * Webhook endpoint for Next.js ISR on-demand revalidation.
     * The frontend calls this to invalidate cached pages after admin changes.
     */
    #[Route('/revalidate', name: 'public_revalidate', methods: ['POST'])]
    public function revalidate(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $secret = $payload['secret'] ?? '';
        if ($secret !== $this->revalidationSecret) {
            throw new AccessDeniedException('Invalid revalidation secret.');
        }

        $slug = $payload['slug'] ?? null;
        if ($slug === null) {
            return $this->json(['revalidated' => false, 'message' => 'Missing slug.'], 400);
        }

        // In a full implementation this would call the Next.js revalidation API.
        // For now, acknowledge the request.
        return $this->json([
            'revalidated' => true,
            'slug' => $slug,
        ]);
    }
}
