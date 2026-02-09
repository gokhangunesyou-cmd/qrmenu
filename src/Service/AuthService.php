<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\AccessDeniedException;
use App\Exception\EntityNotFoundException;
use App\Repository\UserRepository;
use App\Security\JwtTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly EntityManagerInterface $em,
        private readonly int $accessTtl,
    ) {
    }

    /**
     * Authenticate user and return access + refresh tokens.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     *
     * @throws EntityNotFoundException
     * @throws AccessDeniedException
     */
    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            throw new AccessDeniedException('Invalid credentials.');
        }

        if (!$user->isActive() || $user->getDeletedAt() !== null) {
            throw new AccessDeniedException('Account is disabled.');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new AccessDeniedException('Invalid credentials.');
        }

        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->generateTokens($user);
    }

    /**
     * Refresh access token using a valid refresh token.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     *
     * @throws AccessDeniedException
     */
    public function refreshToken(string $refreshToken): array
    {
        $token = $this->jwtTokenManager->parseToken($refreshToken);

        if ($token === null) {
            throw new AccessDeniedException('Invalid or expired refresh token.');
        }

        if ($this->jwtTokenManager->getTokenType($token) !== 'refresh') {
            throw new AccessDeniedException('Invalid token type. Expected refresh token.');
        }

        $userId = $this->jwtTokenManager->getUserIdFromToken($token);

        if ($userId === null) {
            throw new AccessDeniedException('Invalid token payload.');
        }

        $user = $this->userRepository->find($userId);

        if ($user === null || !$user->isActive() || $user->getDeletedAt() !== null) {
            throw new AccessDeniedException('User not found or inactive.');
        }

        return $this->generateTokens($user);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    private function generateTokens(User $user): array
    {
        return [
            'access_token' => $this->jwtTokenManager->createAccessToken($user),
            'refresh_token' => $this->jwtTokenManager->createRefreshToken($user),
            'expires_in' => $this->accessTtl,
        ];
    }
}
