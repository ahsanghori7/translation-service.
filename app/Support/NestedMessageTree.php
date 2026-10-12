<?php

declare(strict_types=1);

namespace App\Support;

final class NestedMessageTree
{
    /**
     * @param  array<string, string>  $flat
     * @return array<string, mixed>
     */
    public function nest(array $flat): array
    {
        $tree = [];
        foreach ($flat as $key => $value) {
            $segments = explode('.', (string) $key);
            $tree = $this->insert($tree, $segments, $value);
        }

        return $tree;
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  list<string>  $segments
     * @return array<string, mixed>
     */
    private function insert(array $tree, array $segments, string $value): array
    {
        $segment = array_shift($segments);
        if ($segment === null || $segment === '') {
            return $tree;
        }

        if ($segments === []) {
            $tree[$segment] = $value;

            return $tree;
        }

        $child = $tree[$segment] ?? [];
        if (! is_array($child)) {
            $child = [];
        }

        $tree[$segment] = $this->insert($child, $segments, $value);

        return $tree;
    }
}
