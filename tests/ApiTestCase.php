<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\TokenManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function token(?User $user = null): string
    {
        $user ??= User::factory()->create();

        return app(TokenManager::class)->issue($user, 'phpunit')->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
