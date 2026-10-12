<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\ExportDocument;
use App\Data\ExportScope;

interface ExportsTranslations
{
    /**
     * Resolves locale, tags and the current version only, so callers can
     * answer conditional requests without building the export.
     *
     * @param  list<string>  $tags
     */
    public function scope(string $locale, array $tags, string $tagMode): ExportScope;

    public function render(ExportScope $scope, string $structure = 'flat'): ExportDocument;

    /**
     * @param  list<string>  $tags
     */
    public function export(string $locale, array $tags, string $tagMode, string $structure = 'flat'): ExportDocument;
}
