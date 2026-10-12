<?php

declare(strict_types=1);

use App\Exceptions\ApiErrorRenderer;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureUserCanWrite;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
        $middleware->alias([
            'auth.token' => AuthenticateApiToken::class,
            'can.write' => EnsureUserCanWrite::class,
        ]);
        $middleware->api(append: [
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(
            fn (NotFoundHttpException $e, Request $request) => app(ApiErrorRenderer::class)->notFound($e, $request),
        );
        $exceptions->render(
            fn (MethodNotAllowedHttpException $e, Request $request) => app(ApiErrorRenderer::class)->methodNotAllowed($e, $request),
        );
    })->create();
