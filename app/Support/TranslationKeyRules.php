<?php

declare(strict_types=1);

namespace App\Support;

final class TranslationKeyRules
{
    public function group(string $key): string
    {
        $group = str_contains($key, '.') ? strstr($key, '.', true) : $key;

        return substr((string) $group, 0, 64);
    }
}
