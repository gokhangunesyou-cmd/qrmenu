<?php

namespace App\Controller\Public;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Public - Health')]
class HealthController extends AbstractController
{
    #[OA\Get(
        path: '/api/health',
        summary: 'Health check endpoint',
        tags: ['Public - Health'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service is healthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time')
                    ]
                )
            )
        ],
        security: []
    )]
    #[Route('/health', name: 'public_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
