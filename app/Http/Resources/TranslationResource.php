<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Translation */
class TranslationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->translationKey->message_key,
            'group' => $this->translationKey->key_group,
            'description' => $this->translationKey->description,
            'locale' => $this->language->code,
            'content' => $this->content,
            'tags' => $this->translationKey->tags->pluck('slug')->values(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
