<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface ContentMatcher
{
    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, string $content): void;

    /**
     * @param  Builder<Model>  $query
     */
    public function supportsFulltext(Builder $query): bool;
}
