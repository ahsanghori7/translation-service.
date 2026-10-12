<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocsController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Middleware\ExportAuthentication;
use Illuminate\Support\Facades\Route;

Route::controller(DocsController::class)->group(function (): void {
    Route::get('/docs', 'ui');
    Route::get('/openapi.yaml', 'spec');
});

Route::prefix('auth')->controller(AuthController::class)->group(function (): void {
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('/register', 'register');
        Route::post('/token', 'login');
    });

    Route::middleware('auth.token')->group(function (): void {
        Route::delete('/token', 'logout');
        Route::get('/user', 'me');
    });
});

Route::get('/export/{locale}', ExportController::class)
    ->middleware(['throttle:export', ExportAuthentication::class])
    ->where('locale', '[a-z]{2,3}(-[a-z0-9]{2,8})*');

Route::middleware('auth.token')->group(function (): void {
    Route::prefix('languages')->controller(LanguageController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('/{language}', 'show');

        Route::middleware('can.write')->group(function (): void {
            Route::post('/', 'store');
            Route::patch('/{language}', 'update');
        });
    });

    Route::prefix('tags')->controller(TagController::class)->group(function (): void {
        Route::get('/', 'index');

        Route::middleware('can.write')->group(function (): void {
            Route::post('/', 'store');
        });
    });

    Route::prefix('translations')->controller(TranslationController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('/{translation}', 'show');

        Route::middleware('can.write')->group(function (): void {
            Route::post('/', 'store');
            Route::put('/{translation}', 'update');
            Route::delete('/{translation}', 'destroy');
        });
    });
});
