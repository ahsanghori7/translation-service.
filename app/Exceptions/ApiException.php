<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Concerns\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

abstract class ApiException extends RuntimeException
{
    use ApiResponses;

    abstract public function status(): int;

    public function render(Request $request): JsonResponse
    {
        return $this->errorResponse($this->getMessage(), $this->status());
    }
}
