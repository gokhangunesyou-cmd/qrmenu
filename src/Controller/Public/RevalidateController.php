<?php

namespace App\Controller\Public;

use App\Exception\AccessDeniedException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Public - Revalidation')]
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
    #[OA\Post(
        path: '/api/public/revalidate',
        summary: 'Trigger Next.js ISR revalidation',
        description: 'Webhook endpoint for invalidating cached pages after admin changes',
        tags: ['Public - Revalidation'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['secret', 'slug'],
                properties: [
                    new OA\Property(property: 'secret', type: 'string', description: 'Revalidation secret'),
                    new OA\Property(property: 'slug', type: 'string', description: 'Restaurant slug to revalidate')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Revalidation triggered successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'revalidated', type: 'boolean', example: true),
                        new OA\Property(property: 'slug', type: 'string')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Missing slug'
            ),
            new OA\Response(
                response: 403,
                description: 'Invalid revalidation secret'
            )
        ],
        security: []
    )]
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
