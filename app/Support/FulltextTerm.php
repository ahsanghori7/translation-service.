<?php

declare(strict_types=1);

namespace App\Support;

final class FulltextTerm
{
    public function toBoolean(string $content): string
    {
        $clean = preg_replace('/[+\-<>()~*"@]+/u', ' ', $content) ?? '';
        $parts = preg_split('/\s+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return '';
        }

        $parts = array_slice($parts, 0, 8);

        return implode(' ', array_map(
            static fn (string $part): string => '+'.$part,
            $parts,
        ));
    }
}
