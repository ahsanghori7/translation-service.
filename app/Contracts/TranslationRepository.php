<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\TranslationFilter;
use App\Models\Translation;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface TranslationRepository
{
    public function findForKeyAndLanguage(string $messageKey, int $languageId): ?Translation;

    /**
     * @return CursorPaginator<int, Translation>
     */
    public function paginate(TranslationFilter $filter): CursorPaginator;
}
