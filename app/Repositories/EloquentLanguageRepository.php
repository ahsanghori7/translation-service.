<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\LanguageRepository;
use App\Models\Language;
use Illuminate\Support\Collection;

final class EloquentLanguageRepository implements LanguageRepository
{
    public function all(): Collection
    {
        return Language::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'is_active', 'created_at', 'updated_at']);
    }

    public function findByCode(string $code): ?Language
    {
        return Language::query()
            ->where('code', $code)
            ->first(['id', 'code', 'name', 'is_active', 'created_at', 'updated_at']);
    }

    public function create(string $code, string $name, bool $isActive): Language
    {
        return Language::query()->create([
            'code' => $code,
            'name' => $name,
            'is_active' => $isActive,
        ]);
    }

    public function update(Language $language, array $attributes): Language
    {
        $language->fill($attributes);
        $language->save();

        return $language;
    }
}
