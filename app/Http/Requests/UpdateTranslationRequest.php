<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\TranslationUpdate;
use App\Http\Requests\Concerns\NormalizesTranslationInput;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTranslationRequest extends FormRequest
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
            'content' => ['sometimes', 'string', 'min:1', 'max:5000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->exists('content') && ! $this->exists('description') && ! $this->exists('tags')) {
                $validator->errors()->add('content', __('messages.validation.translation_update_empty'));
            }
        });
    }

    public function toData(): TranslationUpdate
    {
        return new TranslationUpdate(
            content: $this->exists('content') ? $this->string('content')->toString() : null,
            descriptionProvided: $this->exists('description'),
            description: $this->exists('description') ? $this->input('description') : null,
            tags: $this->exists('tags') ? array_values($this->input('tags', [])) : null,
        );
    }
}
