<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Language;
use Illuminate\Support\Collection;

interface LanguageRepository
{
    /**
     * @return Collection<int, Language>
     */
    public function all(): Collection;

    public function findByCode(string $code): ?Language;

    public function create(string $code, string $name, bool $isActive): Language;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Language $language, array $attributes): Language;
}
