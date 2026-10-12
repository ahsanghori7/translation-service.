<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\TokenManager;
use App\Data\AuthenticatedToken;
use App\Http\Concerns\ApiResponses;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateApiToken
{
    use ApiResponses;

    public function __construct(private readonly TokenManager $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');
        if (! is_string($header) || ! preg_match('/^Bearer\s+(\S+)\s*$/i', $header, $matches)) {
            return $this->unauthenticated();
        }

        $authenticated = $this->tokens->authenticate($matches[1]);
        if (! $authenticated instanceof AuthenticatedToken) {
            return $this->unauthenticated();
        }

        $request->setUserResolver(static fn (): mixed => $authenticated->user);
        $request->attributes->set('apiToken', $authenticated->token);
        Auth::setUser($authenticated->user);

        return $next($request);
    }

    private function unauthenticated(): Response
    {
        return $this->errorResponse(__('messages.auth.unauthenticated'), Response::HTTP_UNAUTHORIZED);
    }
}
