<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Concerns\ApiResponses;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after auth.token: only editors may create, update or delete.
 */
final class EnsureUserCanWrite
{
    use ApiResponses;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->canWrite()) {
            return $this->errorResponse(__('messages.auth.editor_required'), Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
