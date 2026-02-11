<?php

namespace App\EventListener;

use App\Exception\AccessDeniedException;
use App\Exception\ConflictException;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidStatusTransitionException;
use App\Exception\MediaInUseException;
use App\Exception\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as SymfonyAccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class ExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $response = $this->createResponse($exception);
        $event->setResponse($response);
    }

    private function createResponse(\Throwable $exception): JsonResponse
    {
        return match (true) {
            $exception instanceof ValidationException => new JsonResponse([
                'status' => 422,
                'type' => 'validation_error',
                'message' => $exception->getMessage(),
                'errors' => $exception->getErrors(),
            ], 422),

            $exception instanceof EntityNotFoundException => new JsonResponse([
                'status' => 404,
                'type' => 'not_found',
                'message' => $exception->getMessage(),
            ], 404),

            $exception instanceof AccessDeniedException => new JsonResponse([
                'status' => 403,
                'type' => 'forbidden',
                'message' => $exception->getMessage(),
            ], 403),

            $exception instanceof SymfonyAccessDeniedException => new JsonResponse([
                'status' => 403,
                'type' => 'forbidden',
                'message' => 'Access denied.',
            ], 403),

            $exception instanceof AuthenticationException => new JsonResponse([
                'status' => 401,
                'type' => 'unauthorized',
                'message' => 'Authentication required.',
            ], 401),

            $exception instanceof ConflictException => new JsonResponse([
                'status' => 409,
                'type' => 'conflict',
                'message' => $exception->getMessage(),
            ], 409),

            $exception instanceof InvalidStatusTransitionException => new JsonResponse([
                'status' => 400,
                'type' => 'bad_request',
                'message' => $exception->getMessage(),
            ], 400),

            $exception instanceof MediaInUseException => new JsonResponse([
                'status' => 409,
                'type' => 'conflict',
                'message' => $exception->getMessage(),
            ], 409),

            $exception instanceof HttpExceptionInterface => new JsonResponse([
                'status' => $exception->getStatusCode(),
                'type' => 'http_error',
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode()),

            default => new JsonResponse([
                'status' => 500,
                'type' => 'internal_error',
                'message' => 'An internal error occurred.',
            ], 500),
        };
    }
}
