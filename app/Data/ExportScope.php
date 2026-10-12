<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Everything needed to identify an export (and its ETag) without loading
 * any translation rows.
 */
final readonly class ExportScope
{
    /**
     * @param  list<int>  $tagIds
     */
    public function __construct(
        public string $locale,
        public int $languageId,
        public array $tagIds,
        public string $tagMode,
        public string $fingerprint,
        public int $version,
        public bool $matchesNothing = false,
    ) {
    }

    public function etag(string $structure = 'flat'): string
    {
        return '"'.$this->version.'-'.$this->locale.'-'.$this->fingerprint.'-'.$structure.'"';
    }
}
