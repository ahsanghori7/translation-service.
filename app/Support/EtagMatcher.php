<?php

declare(strict_types=1);

namespace App\Support;

final class EtagMatcher
{
    public function matches(?string $header, string $etag): bool
    {
        if ($header === null || $header === '') {
            return false;
        }

        foreach (array_map(trim(...), explode(',', $header)) as $candidate) {
            if ($candidate === '*') {
                return true;
            }

            $normalized = str_starts_with($candidate, 'W/') ? substr($candidate, 2) : $candidate;
            if ($normalized === $etag) {
                return true;
            }
        }

        return false;
    }
}
