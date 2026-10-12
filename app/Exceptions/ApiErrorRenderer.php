<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Concerns\ApiResponses;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Replaces framework 404/405 texts (which name model classes and routes)
 * with client-friendly messages for API requests.
 */
final class ApiErrorRenderer
{
    use ApiResponses;

    public function notFound(NotFoundHttpException $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof ModelNotFoundException) {
            return $this->errorResponse(__('messages.http.resource_not_found', [
                'resource' => Str::headline(class_basename($previous->getModel())),
                'id' => implode(', ', array_map('strval', $previous->getIds())),
            ]), 404);
        }

        return $this->errorResponse(__('messages.http.endpoint_not_found'), 404);
    }

    public function methodNotAllowed(MethodNotAllowedHttpException $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return $this->errorResponse(__('messages.http.method_not_allowed', [
            'method' => $request->method(),
            'allowed' => (string) ($exception->getHeaders()['Allow'] ?? ''),
        ]), 405)->withHeaders($exception->getHeaders());
    }
}
