<?php

declare(strict_types=1);

namespace App\Data;

final readonly class TranslationFilter
{
    /**
     * @param  list<int>  $tagIds
     */
    public function __construct(
        public ?int $languageId,
        public array $tagIds,
        public string $tagMode,
        public ?string $key,
        public string $keyMode,
        public ?string $content,
        public ?string $group,
        public int $perPage,
        public ?string $cursor,
    ) {
    }
}
