<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->remove('X-Powered-By');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        if (! str_contains($cacheControl, 'must-revalidate')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
