<?php

declare(strict_types=1);

namespace App\Contracts;

interface ExportRepository
{
    /**
     * @param  list<int>  $tagIds
     * @return iterable<string, string>
     */
    public function cursor(int $languageId, array $tagIds, string $tagMode): iterable;
}
