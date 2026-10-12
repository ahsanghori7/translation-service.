<?php

declare(strict_types=1);

namespace App\Exceptions;

final class DuplicateTranslationException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('messages.translation.duplicate'));
    }

    public function status(): int
    {
        return 409;
    }
}
