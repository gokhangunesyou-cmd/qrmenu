<?php

namespace App\Security;

use App\Entity\User;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Psr\Clock\ClockInterface;

class JwtTokenManager
{
    private Configuration $config;

    public function __construct(
        private readonly string $secretKey,
        private readonly int $accessTtl,
        private readonly int $refreshTtl,
    ) {
        $this->config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->secretKey),
        );
    }

    public function createAccessToken(User $user): string
    {
        $now = new \DateTimeImmutable();

        $builder = $this->config->builder()
            ->issuedAt($now)
            ->expiresAt($now->modify(sprintf('+%d seconds', $this->accessTtl)))
            ->withClaim('sub', $user->getId())
            ->withClaim('uuid', (string) $user->getUuid())
            ->withClaim('email', $user->getEmail())
            ->withClaim('roles', $user->getRoles())
            ->withClaim('type', 'access');

        $restaurant = $user->getRestaurant();
        if ($restaurant !== null) {
            $builder = $builder
                ->withClaim('restaurant_id', $restaurant->getId())
                ->withClaim('restaurant_uuid', (string) $restaurant->getUuid());
        }

        return $builder->getToken($this->config->signer(), $this->config->signingKey())->toString();
    }

    public function createRefreshToken(User $user): string
    {
        $now = new \DateTimeImmutable();

        return $this->config->builder()
            ->issuedAt($now)
            ->expiresAt($now->modify(sprintf('+%d seconds', $this->refreshTtl)))
            ->withClaim('sub', $user->getId())
            ->withClaim('type', 'refresh')
            ->getToken($this->config->signer(), $this->config->signingKey())
            ->toString();
    }

    public function parseToken(string $jwt): ?Plain
    {
        try {
            $token = $this->config->parser()->parse($jwt);

            if (!$token instanceof Plain) {
                return null;
            }

            $constraints = [
                new SignedWith($this->config->signer(), $this->config->signingKey()),
                new StrictValidAt(new class implements ClockInterface {
                    public function now(): \DateTimeImmutable
                    {
                        return new \DateTimeImmutable();
                    }
                }),
            ];

            if (!$this->config->validator()->validate($token, ...$constraints)) {
                return null;
            }

            return $token;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getUserIdFromToken(Plain $token): ?int
    {
        return $token->claims()->get('sub');
    }

    public function getTokenType(Plain $token): ?string
    {
        return $token->claims()->get('type');
    }
}
