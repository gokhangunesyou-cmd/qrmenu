<?php

namespace App\DTO\Response\Admin;

final readonly class AuthTokenResponse
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {
    }
}
