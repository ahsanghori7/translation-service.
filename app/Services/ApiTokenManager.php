<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TokenManager as TokenManagerContract;
use App\Data\AuthenticatedToken;
use App\Data\IssuedToken;
use App\Models\ApiToken;
use App\Models\User;

final class ApiTokenManager implements TokenManagerContract
{
    public function issue(User $user, string $name): IssuedToken
    {
        $ttl = (int) config('translations.token_ttl_days');
        $expiresAt = $ttl > 0 ? now()->addDays($ttl) : null;
        $plain = bin2hex(random_bytes(32));

        $token = new ApiToken();
        $token->user_id = $user->id;
        $token->name = $name;
        $token->token_hash = hash('sha256', $plain);
        $token->expires_at = $expiresAt;
        $token->save();

        return new IssuedToken($plain, $expiresAt);
    }

    public function authenticate(string $plainTextToken): ?AuthenticatedToken
    {
        if (strlen($plainTextToken) < 32) {
            return null;
        }

        $token = ApiToken::query()
            ->select(['id', 'user_id', 'name', 'token_hash', 'last_used_at', 'expires_at'])
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->first();

        if ($token === null || $token->isExpired()) {
            return null;
        }

        $user = User::query()->select(['id', 'name', 'email', 'role'])->find($token->user_id);
        if (! $user instanceof User) {
            return null;
        }

        $this->touch($token);
        $token->setRelation('user', $user);

        return new AuthenticatedToken($user, $token);
    }

    public function revoke(ApiToken $token): void
    {
        $token->delete();
    }

    private function touch(ApiToken $token): void
    {
        if ($token->last_used_at !== null && $token->last_used_at->greaterThan(now()->subMinutes(5))) {
            return;
        }

        $token->forceFill(['last_used_at' => now()])->save();
    }
}
