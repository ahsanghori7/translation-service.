<?php

declare(strict_types=1);

namespace App\Data;

use DateTimeInterface;

final readonly class IssuedToken
{
    public function __construct(
        public string $plainTextToken,
        public ?DateTimeInterface $expiresAt,
    ) {
    }
}
