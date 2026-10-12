<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranslationKey>
 */
class TranslationKeyFactory extends Factory
{
    protected $model = TranslationKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numerify('#####');

        return [
            'message_key' => 'group.item_'.$number,
            'key_group' => 'group',
            'description' => null,
        ];
    }
}
