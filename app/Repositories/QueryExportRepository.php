<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ExportRepository;
use Illuminate\Support\Facades\DB;

final class QueryExportRepository implements ExportRepository
{
    public function cursor(int $languageId, array $tagIds, string $tagMode): iterable
    {
        $query = DB::table('translations as t')
            ->join('translation_keys as k', 'k.id', '=', 't.translation_key_id')
            ->where('t.language_id', '=', $languageId)
            ->select(['k.message_key as translation_key', 't.content'])
            ->orderBy('k.message_key');

        if ($tagMode === 'all') {
            foreach ($tagIds as $tagId) {
                $query->whereExists(function ($sub) use ($tagId): void {
                    $sub->selectRaw('1')
                        ->from('translation_key_tag as pivot')
                        ->whereColumn('pivot.translation_key_id', 't.translation_key_id')
                        ->where('pivot.tag_id', $tagId);
                });
            }
        } elseif ($tagIds !== []) {
            $query->whereExists(function ($sub) use ($tagIds): void {
                $sub->selectRaw('1')
                    ->from('translation_key_tag as pivot')
                    ->whereColumn('pivot.translation_key_id', 't.translation_key_id')
                    ->whereIn('pivot.tag_id', $tagIds);
            });
        }

        foreach ($query->cursor() as $row) {
            yield (string) $row->translation_key => (string) $row->content;
        }
    }
}
