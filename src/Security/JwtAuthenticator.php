<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JwtTokenManager $jwtManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization', '');
        $jwt = substr($authHeader, 7);

        if ($jwt === '' || $jwt === false) {
            throw new CustomUserMessageAuthenticationException('Missing JWT token.');
        }

        $token = $this->jwtManager->parseToken($jwt);

        if ($token === null) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired token.');
        }

        if ($this->jwtManager->getTokenType($token) !== 'access') {
            throw new CustomUserMessageAuthenticationException('Invalid token type.');
        }

        $userId = $this->jwtManager->getUserIdFromToken($token);

        if ($userId === null) {
            throw new CustomUserMessageAuthenticationException('Invalid token payload.');
        }

        return new SelfValidatingPassport(
            new UserBadge((string) $userId, function (string $identifier): User {
                $user = $this->userRepository->find((int) $identifier);

                if ($user === null || !$user->isActive() || $user->getDeletedAt() !== null) {
                    throw new CustomUserMessageAuthenticationException('User not found or inactive.');
                }

                return $user;
            }),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // Let the request continue
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'status' => 401,
            'type' => 'unauthorized',
            'message' => $exception->getMessageKey(),
        ], 401);
    }
}
