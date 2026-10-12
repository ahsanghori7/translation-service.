<?php

declare(strict_types=1);

namespace App\Exceptions;

final class LocaleNotFoundException extends ApiException
{
    public function __construct(string $locale)
    {
        parent::__construct(__('messages.locale.not_found', ['locale' => $locale]));
    }

    public function status(): int
    {
        return 404;
    }
}
