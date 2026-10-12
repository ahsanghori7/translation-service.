<?php

declare(strict_types=1);

namespace App\Exceptions;

final class DuplicateTagException extends ApiException
{
    public function __construct(string $slug)
    {
        parent::__construct(__('messages.tag.duplicate', ['slug' => $slug]));
    }

    public function status(): int
    {
        return 409;
    }
}
