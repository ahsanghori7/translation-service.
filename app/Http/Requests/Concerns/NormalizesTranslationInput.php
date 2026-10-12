<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait NormalizesTranslationInput
{
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['key', 'locale', 'group', 'content'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $value = trim($this->input($field));
                if ($field === 'locale') {
                    $value = strtolower($value);
                }
                $merge[$field] = $value;
            }
        }

        if ($this->exists('tags')) {
            $tags = $this->normalizeTags($this->input('tags'));
            if (is_array($tags)) {
                $merge['tags'] = $tags;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    private function normalizeTags(mixed $tags): mixed
    {
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        if (! is_array($tags)) {
            return $tags;
        }

        $normalized = [];
        foreach ($tags as $tag) {
            $slug = strtolower(trim((string) $tag));
            if ($slug !== '') {
                $normalized[] = $slug;
            }
        }

        return array_values(array_unique($normalized));
    }
}
