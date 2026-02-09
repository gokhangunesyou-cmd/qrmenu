<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\LoginRequest;
use App\DTO\Request\Admin\RefreshTokenRequest;
use App\DTO\Response\Admin\AuthTokenResponse;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

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
