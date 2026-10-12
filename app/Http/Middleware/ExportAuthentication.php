<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExportAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('translations.export_requires_auth') !== true) {
            return $next($request);
        }

        return app(AuthenticateApiToken::class)->handle($request, $next);
    }
}
