<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InactiveLocaleException extends ApiException
{
    public function __construct(string $locale)
    {
        parent::__construct(__('messages.locale.inactive', ['locale' => $locale]));
    }

    public function status(): int
    {
        return 422;
    }
}
