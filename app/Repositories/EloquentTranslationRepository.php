<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ContentMatcher;
use App\Contracts\TranslationRepository;
use App\Data\TranslationFilter;
use App\Models\Translation;
use App\Support\LikeEscaper;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final class EloquentTranslationRepository implements TranslationRepository
{
    public function __construct(private readonly ContentMatcher $contentMatcher)
    {
    }

    public function findForKeyAndLanguage(string $messageKey, int $languageId): ?Translation
    {
        return Translation::query()
            ->select(['id', 'translation_key_id', 'language_id', 'content', 'created_at', 'updated_at'])
            ->where('language_id', $languageId)
            ->whereIn('translation_key_id', function ($query) use ($messageKey): void {
                $query->select('id')
                    ->from('translation_keys')
                    ->where('message_key', $messageKey);
            })
            ->first();
    }

    public function paginate(TranslationFilter $filter): CursorPaginator
    {
        return $this->builder($filter)
            ->with([
                'translationKey.tags',
                'language',
            ])
            ->orderByDesc('id')
            ->cursorPaginate(perPage: $filter->perPage, cursor: $filter->cursor)
            ->withQueryString();
    }

    /**
     * @return Builder<Translation>
     */
    private function builder(TranslationFilter $filter): Builder
    {
        $query = Translation::query()->select([
            'id',
            'translation_key_id',
            'language_id',
            'content',
            'created_at',
            'updated_at',
        ]);

        if ($filter->languageId !== null) {
            $query->where('language_id', $filter->languageId);
        }

        if ($filter->key !== null && $filter->key !== '') {
            $this->applyKey($query, $filter->key, $filter->keyMode);
        }

        if ($filter->group !== null && $filter->group !== '') {
            $query->whereIn('translation_key_id', function ($sub) use ($filter): void {
                $sub->select('id')
                    ->from('translation_keys')
                    ->where('key_group', $filter->group);
            });
        }

        if ($filter->content !== null && $filter->content !== '') {
            $this->contentMatcher->apply($query, $filter->content);
        }

        if ($filter->tagIds !== []) {
            $this->applyTags($query, $filter->tagIds, $filter->tagMode);
        }

        return $query;
    }

    /**
     * @param  Builder<Translation>  $query
     */
    private function applyKey(Builder $query, string $key, string $mode): void
    {
        $query->whereIn('translation_key_id', function ($sub) use ($key, $mode): void {
            $sub->select('id')->from('translation_keys');
            if ($mode === 'exact') {
                $sub->where('message_key', $key);

                return;
            }

            $pattern = $mode === 'contains'
                ? LikeEscaper::contains($key)
                : LikeEscaper::prefix($key);
            $sub->whereRaw('message_key like ? escape ?', [$pattern, '\\']);
        });
    }

    /**
     * @param  Builder<Translation>  $query
     * @param  list<int>  $tagIds
     */
    private function applyTags(Builder $query, array $tagIds, string $mode): void
    {
        if ($mode === 'all') {
            foreach ($tagIds as $tagId) {
                $query->whereExists(function ($sub) use ($tagId): void {
                    $sub->selectRaw('1')
                        ->from('translation_key_tag as pivot')
                        ->whereColumn('pivot.translation_key_id', 'translations.translation_key_id')
                        ->where('pivot.tag_id', $tagId);
                });
            }

            return;
        }

        $query->whereExists(function ($sub) use ($tagIds): void {
            $sub->selectRaw('1')
                ->from('translation_key_tag as pivot')
                ->whereColumn('pivot.translation_key_id', 'translations.translation_key_id')
                ->whereIn('pivot.tag_id', $tagIds);
        });
    }
}
