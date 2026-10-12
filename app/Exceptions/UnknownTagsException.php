<?php

declare(strict_types=1);

namespace App\Exceptions;

final class UnknownTagsException extends ApiException
{
    /**
     * @param  list<string>  $slugs
     */
    public function __construct(array $slugs)
    {
        parent::__construct(__('messages.tag.unknown', ['tags' => implode(', ', $slugs)]));
    }

    public function status(): int
    {
        return 422;
    }
}
