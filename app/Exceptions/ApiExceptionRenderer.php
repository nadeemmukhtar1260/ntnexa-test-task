<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Converts any exception thrown during an API request into the
 * standard { success: false, message, errors? } JSON envelope.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => ApiResponse::error(
                'Validation failed.',
                $e->status,
                $e->errors(),
            ),

            $e instanceof AuthenticationException => ApiResponse::error(
                'Unauthenticated.',
                401,
            ),

            $e instanceof NotFoundHttpException => ApiResponse::error(
                $this->notFoundMessage($e),
                404,
            ),

            $e instanceof HttpExceptionInterface => ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : $this->defaultHttpMessage($e->getStatusCode()),
                $e->getStatusCode(),
                headers: $e->getHeaders(),
            ),

            default => ApiResponse::error(
                config('app.debug') ? $e->getMessage() : 'Something went wrong. Please try again later.',
                500,
            ),
        };
    }

    private function notFoundMessage(NotFoundHttpException $e): string
    {
        // Laravel wraps ModelNotFoundException (route model binding / findOrFail)
        // in a NotFoundHttpException; use the model name for a helpful message.
        $previous = $e->getPrevious();

        if ($previous instanceof ModelNotFoundException) {
            return class_basename($previous->getModel()).' not found.';
        }

        return 'Resource not found.';
    }

    private function defaultHttpMessage(int $status): string
    {
        return match ($status) {
            403 => 'This action is unauthorized.',
            405 => 'Method not allowed.',
            429 => 'Too many requests.',
            default => 'An error occurred.',
        };
    }
}
