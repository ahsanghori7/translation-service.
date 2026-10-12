<?php

declare(strict_types=1);

namespace App\Contracts;

interface RevisionStore
{
    public function current(): int;

    public function bump(): int;
}
