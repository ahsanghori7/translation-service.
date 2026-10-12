<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\NewTranslation;
use App\Http\Requests\Concerns\NormalizesTranslationInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreTranslationRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$/'],
            'locale' => ['required', 'string', 'max:16', 'regex:/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/'],
            'content' => ['required', 'string', 'min:1', 'max:5000'],
            'description' => ['nullable', 'string', 'max:500'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => __('messages.validation.key_format'),
            'locale.regex' => __('messages.validation.locale_format'),
        ];
    }

    public function toData(): NewTranslation
    {
        return new NewTranslation(
            key: $this->string('key')->toString(),
            locale: $this->string('locale')->toString(),
            content: $this->string('content')->toString(),
            description: $this->exists('description') ? $this->input('description') : null,
            tags: $this->exists('tags') ? array_values($this->input('tags', [])) : [],
        );
    }
}
