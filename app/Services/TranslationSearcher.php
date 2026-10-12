<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\LanguageRepository;
use App\Contracts\SearchesTranslations;
use App\Contracts\TagRepository;
use App\Contracts\TranslationRepository;
use App\Data\TranslationFilter;
use App\Data\TranslationQuery;
use App\Models\Translation;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Pagination\CursorPaginator as CursorPage;
use Illuminate\Pagination\Paginator;

final class TranslationSearcher implements SearchesTranslations
{
    public function __construct(
        private readonly LanguageRepository $languages,
        private readonly TagRepository $tags,
        private readonly TranslationRepository $translations,
    ) {
    }

    public function search(TranslationQuery $query): CursorPaginator
    {
        $languageId = null;
        if ($query->locale !== null && $query->locale !== '') {
            $language = $this->languages->findByCode($query->locale);
            if ($language === null) {
                return $this->empty($query);
            }
            $languageId = (int) $language->id;
        }

        $tagIds = [];
        if ($query->tags !== []) {
            $found = $this->tags->findBySlugs($query->tags);
            if ($found->count() !== count($query->tags)) {
                return $this->empty($query);
            }
            $tagIds = $found
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
        }

        return $this->translations->paginate(new TranslationFilter(
            languageId: $languageId,
            tagIds: $tagIds,
            tagMode: $query->tagMode,
            key: $query->key,
            keyMode: $query->keyMode,
            content: $query->content,
            group: $query->group,
            perPage: $query->perPage,
            cursor: $query->cursor,
        ));
    }

    /**
     * @return CursorPaginator<int, Translation>
     */
    private function empty(TranslationQuery $query): CursorPaginator
    {
        return new CursorPage(
            items: [],
            perPage: $query->perPage,
            options: [
                'path' => Paginator::resolveCurrentPath(),
                'query' => Paginator::resolveQueryString(),
            ],
        );
    }
}
