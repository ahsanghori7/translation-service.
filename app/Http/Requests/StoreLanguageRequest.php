<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLanguageRequest extends FormRequest
{
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
            'code' => ['required', 'string', 'max:16', 'regex:/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/', 'unique:languages,code'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('code') && is_string($this->input('code'))) {
            $this->merge([
                'code' => strtolower(trim($this->input('code'))),
            ]);
        }
    }
}
