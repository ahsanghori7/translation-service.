<?php

declare(strict_types=1);

namespace App\Data;

final readonly class TranslationQuery
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public ?string $key,
        public string $keyMode,
        public ?string $content,
        public ?string $locale,
        public array $tags,
        public string $tagMode,
        public ?string $group,
        public int $perPage,
        public ?string $cursor,
    ) {
    }
}
