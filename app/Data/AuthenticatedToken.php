<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ApiToken;
use App\Models\User;

final readonly class AuthenticatedToken
{
    public function __construct(
        public User $user,
        public ApiToken $token,
    ) {
    }
}
