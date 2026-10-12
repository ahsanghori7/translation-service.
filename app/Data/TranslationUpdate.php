<?php

declare(strict_types=1);

namespace App\Data;

final readonly class TranslationUpdate
{
    /**
     * @param  list<string>|null  $tags
     */
    public function __construct(
        public ?string $content,
        public bool $descriptionProvided,
        public ?string $description,
        public ?array $tags,
    ) {
    }
}
