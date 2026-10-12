<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\TranslationQuery;
use App\Http\Requests\Concerns\NormalizesTranslationInput;
use Illuminate\Foundation\Http\FormRequest;

class SearchTranslationsRequest extends FormRequest
{
    use NormalizesTranslationInput;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['sometimes', 'nullable', 'string', 'max:191'],
            'key_mode' => ['sometimes', 'in:prefix,contains,exact'],
            'content' => ['sometimes', 'nullable', 'string', 'max:200'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:16'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:64'],
            'tag_mode' => ['sometimes', 'in:any,all'],
            'group' => ['sometimes', 'nullable', 'string', 'max:64'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('translations.per_page_max')],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:512'],
        ];
    }

    public function toQuery(): TranslationQuery
    {
        return new TranslationQuery(
            key: $this->filled('key') ? $this->string('key')->toString() : null,
            keyMode: $this->input('key_mode', 'prefix'),
            content: $this->filled('content') ? $this->string('content')->toString() : null,
            locale: $this->filled('locale') ? $this->string('locale')->toString() : null,
            tags: $this->exists('tags') ? array_values($this->input('tags', [])) : [],
            tagMode: $this->input('tag_mode', 'any'),
            group: $this->filled('group') ? $this->string('group')->toString() : null,
            perPage: $this->integer('per_page', (int) config('translations.per_page')),
            cursor: $this->filled('cursor') ? $this->string('cursor')->toString() : null,
        );
    }
}
