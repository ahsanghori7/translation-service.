<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesTranslationInput;
use Illuminate\Foundation\Http\FormRequest;

class ExportTranslationsRequest extends FormRequest
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
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:64'],
            'tag_mode' => ['sometimes', 'in:any,all'],
            'structure' => ['sometimes', 'in:flat,nested'],
        ];
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return array_values($this->input('tags', []));
    }

    public function tagMode(): string
    {
        return $this->string('tag_mode', 'any')->toString();
    }

    public function structure(): string
    {
        return $this->string('structure', 'flat')->toString();
    }
}
