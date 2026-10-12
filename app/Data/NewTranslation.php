<?php

declare(strict_types=1);

namespace App\Data;

final readonly class NewTranslation
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $key,
        public string $locale,
        public string $content,
        public ?string $description,
        public array $tags,
    ) {
    }
}
