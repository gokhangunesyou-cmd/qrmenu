<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\LoginRequest;
use App\DTO\Request\Admin\RefreshTokenRequest;
use App\DTO\Response\Admin\AuthTokenResponse;
use App\Service\AuthService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Admin - Auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    #[OA\Post(
        path: '/api/admin/auth/login',
        summary: 'Login to get JWT tokens',
        description: 'Authenticate with email and password to receive access and refresh tokens',
        tags: ['Admin - Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'owner@restaurant.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authentication successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'accessToken', type: 'string', description: 'JWT access token (valid for 1 hour)'),
                        new OA\Property(property: 'refreshToken', type: 'string', description: 'JWT refresh token (valid for 7 days)'),
                        new OA\Property(property: 'expiresIn', type: 'integer', example: 3600, description: 'Access token expiration in seconds')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            )
        ],
        security: []
    )]
    #[Route('/auth/login', name: 'admin_auth_login', methods: ['POST'])]
    public function login(#[MapRequestPayload] LoginRequest $request): JsonResponse
    {
        $tokens = $this->authService->login($request->email, $request->password);

        return $this->json(new AuthTokenResponse(
            accessToken: $tokens['access_token'],
            refreshToken: $tokens['refresh_token'],
            expiresIn: $tokens['expires_in'],
        ));
    }

    #[OA\Post(
        path: '/api/admin/auth/refresh',
        summary: 'Refresh access token',
        description: 'Get a new access token using a refresh token',
        tags: ['Admin - Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['refreshToken'],
                properties: [
                    new OA\Property(property: 'refreshToken', type: 'string', description: 'Valid refresh token')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token refreshed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'accessToken', type: 'string'),
                        new OA\Property(property: 'refreshToken', type: 'string'),
                        new OA\Property(property: 'expiresIn', type: 'integer', example: 3600)
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid or expired refresh token'
            )
        ],
        security: []
    )]
    #[Route('/auth/refresh', name: 'admin_auth_refresh', methods: ['POST'])]
    public function refresh(#[MapRequestPayload] RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->authService->refreshToken($request->refreshToken);

        return $this->json(new AuthTokenResponse(
            accessToken: $tokens['access_token'],
            refreshToken: $tokens['refresh_token'],
            expiresIn: $tokens['expires_in'],
        ));
    }
}
