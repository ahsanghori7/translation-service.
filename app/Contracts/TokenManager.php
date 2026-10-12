<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\AuthenticatedToken;
use App\Data\IssuedToken;
use App\Models\ApiToken;
use App\Models\User;

interface TokenManager
{
    public function issue(User $user, string $name): IssuedToken;

    public function authenticate(string $plainTextToken): ?AuthenticatedToken;

    public function revoke(ApiToken $token): void;
}
