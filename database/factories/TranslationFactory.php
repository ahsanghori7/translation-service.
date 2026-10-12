<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Language;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Translation>
 */
class TranslationFactory extends Factory
{
    protected $model = Translation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'translation_key_id' => TranslationKey::factory(),
            'language_id' => Language::factory(),
            'content' => fake()->sentence(3),
        ];
    }
}
