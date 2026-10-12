<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\ApiTestCase;

class AuthTest extends ApiTestCase
{
    public function test_registration_creates_a_viewer(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonPath('user.role', 'viewer');
    }

    public function test_register_login_me_and_logout(): void
    {
        $registered = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'Ada@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'tests',
        ]);

        $registered->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonPath('user.role', 'viewer')
            ->assertJsonMissingPath('user.password');

        $token = $registered->json('access_token');
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
        $this->assertDatabaseHas('api_tokens', [
            'name' => 'tests',
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('api_tokens', [
            'token_hash' => $token,
        ]);

        $this->getJson('/api/auth/user', $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.email', 'ada@example.com');

        $this->deleteJson('/api/auth/token', [], $this->bearer($token))
            ->assertNoContent();

        $this->getJson('/api/auth/user', $this->bearer($token))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_login_accepts_a_lowercase_bearer_scheme(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $login = $this->postJson('/api/auth/token', [
            'email' => ' USER@example.com ',
            'password' => 'password123',
        ])->assertOk();

        $token = $login->json('access_token');
        $this->getJson('/api/auth/user', [
            'Authorization' => 'bearer '.$token,
        ])->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_login_rejects_unknown_and_wrong_passwords(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/token', [
            'email' => 'missing@example.com',
            'password' => 'password123',
        ])->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials.');

        $this->postJson('/api/auth/token', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_unknown_emails_still_pay_for_one_hash_check(): void
    {
        $hasher = new class (app('hash')) implements Hasher {
            public int $checks = 0;

            public function __construct(private readonly Hasher $inner)
            {
            }

            public function info($hashedValue): array
            {
                return $this->inner->info($hashedValue);
            }

            public function make($value, array $options = []): string
            {
                return $this->inner->make($value, $options);
            }

            public function check($value, $hashedValue, array $options = []): bool
            {
                $this->checks++;

                return $this->inner->check($value, $hashedValue, $options);
            }

            public function needsRehash($hashedValue, array $options = []): bool
            {
                return $this->inner->needsRehash($hashedValue, $options);
            }
        };
        Hash::swap($hasher);

        $this->postJson('/api/auth/token', [
            'email' => 'missing@example.com',
            'password' => 'password123',
        ])->assertUnauthorized();

        $this->assertSame(1, $hasher->checks);
        $this->assertTrue(Cache::has('auth.dummy_password_hash'));
    }

    public function test_registration_validates_input(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->postJson('/api/auth/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable();
    }

    public function test_expired_and_missing_tokens_are_rejected(): void
    {
        $token = $this->token();
        ApiToken::query()->update(['expires_at' => now()->subMinute()]);

        $this->getJson('/api/languages', $this->bearer($token))->assertUnauthorized();
        $this->getJson('/api/languages')->assertUnauthorized();
        $this->getJson('/api/languages', ['Authorization' => 'Token abc'])->assertUnauthorized();
    }

    public function test_a_token_whose_user_was_removed_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);

        Schema::disableForeignKeyConstraints();
        DB::table('users')->delete();
        Schema::enableForeignKeyConstraints();

        $this->getJson('/api/languages', $this->bearer($token))->assertUnauthorized();
    }
}
