<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Tag;
use Illuminate\Support\Collection;

interface TagRepository
{
    /**
     * @return Collection<int, Tag>
     */
    public function all(): Collection;

    public function create(string $name): Tag;

    /**
     * @param  list<string>  $slugs
     * @return Collection<int, Tag>
     */
    public function findBySlugs(array $slugs): Collection;

    /**
     * @param  list<string>  $slugs
     * @return list<int>
     */
    public function idsForSlugs(array $slugs): array;

    /**
     * Attaches tags to a key, ignoring pairs that already exist (safe under
     * concurrent writes to the same key).
     *
     * @param  list<int>  $tagIds
     */
    public function attachToKey(int $translationKeyId, array $tagIds): void;
}
