<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TagRepository;
use App\Exceptions\DuplicateTagException;
use App\Exceptions\UnknownTagsException;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentTagRepository implements TagRepository
{
    public function all(): Collection
    {
        return Tag::query()
            ->orderBy('slug')
            ->get(['id', 'name', 'slug', 'created_at', 'updated_at']);
    }

    public function create(string $name): Tag
    {
        $slug = Str::slug($name);
        $existing = Tag::query()->where('slug', $slug)->first(['id', 'slug']);
        if ($existing !== null) {
            throw new DuplicateTagException($slug);
        }

        return Tag::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    public function findBySlugs(array $slugs): Collection
    {
        if ($slugs === []) {
            return new Collection();
        }

        return Tag::query()
            ->whereIn('slug', $slugs)
            ->get(['id', 'name', 'slug']);
    }

    public function idsForSlugs(array $slugs): array
    {
        $slugs = array_values(array_unique($slugs));
        if ($slugs === []) {
            return [];
        }

        $found = $this->findBySlugs($slugs);
        $known = $found->pluck('slug')->all();
        $missing = array_values(array_diff($slugs, $known));
        if ($missing !== []) {
            throw new UnknownTagsException($missing);
        }

        return $found
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function attachToKey(int $translationKeyId, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }

        DB::table('translation_key_tag')->insertOrIgnore(array_map(
            static fn (int $tagId): array => [
                'translation_key_id' => $translationKeyId,
                'tag_id' => $tagId,
            ],
            array_values(array_unique($tagIds)),
        ));
    }
}
