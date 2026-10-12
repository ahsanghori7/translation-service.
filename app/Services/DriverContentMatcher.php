<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ContentMatcher;
use App\Support\FulltextTerm;
use App\Support\LikeEscaper;
use Illuminate\Database\Eloquent\Builder;

class DriverContentMatcher implements ContentMatcher
{
    public function __construct(private readonly FulltextTerm $terms)
    {
    }

    public function apply(Builder $query, string $content): void
    {
        $term = trim($content);
        $minLength = (int) config('translations.fulltext_min_length', 3);
        if ($this->supportsFulltext($query) && mb_strlen($term) >= $minLength) {
            $boolean = $this->terms->toBoolean($term);
            if ($boolean !== '') {
                $query->whereRaw(
                    'MATCH(translations.content) AGAINST (? IN BOOLEAN MODE)',
                    [$boolean],
                );

                return;
            }
        }

        $query->whereRaw('content like ? escape ?', [LikeEscaper::contains($term), '\\']);
    }

    public function supportsFulltext(Builder $query): bool
    {
        return $query->getConnection()->getDriverName() === 'mysql';
    }
}
