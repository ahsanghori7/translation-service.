<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\TranslationQuery;
use App\Models\Translation;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface SearchesTranslations
{
    /**
     * @return CursorPaginator<int, Translation>
     */
    public function search(TranslationQuery $query): CursorPaginator;
}
