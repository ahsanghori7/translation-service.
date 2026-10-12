<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Data\IssuedToken;
use App\Http\Resources\UserResource;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * The JSON shapes the API returns, defined once: token issue, created
 * resources and `{"message": ...}` errors.
 */
trait ApiResponses
{
    protected function tokenResponse(IssuedToken $issued, User $user, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $issued->plainTextToken,
            'expires_at' => $issued->expiresAt?->format(DateTimeInterface::ATOM),
            'user' => new UserResource($user),
        ], $status);
    }

    protected function createdResponse(JsonResource $resource): JsonResponse
    {
        return $resource->response()->setStatusCode(Response::HTTP_CREATED);
    }

    protected function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
