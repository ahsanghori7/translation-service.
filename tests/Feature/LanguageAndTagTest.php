<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Language;
use Tests\ApiTestCase;

class LanguageAndTagTest extends ApiTestCase
{
    public function test_languages_can_be_created_listed_shown_and_updated(): void
    {
        $headers = $this->bearer($this->token());

        $this->postJson('/api/languages', [
            'code' => 'PT-BR',
            'name' => 'Portuguese',
        ], $headers)->assertCreated()
            ->assertJsonPath('data.code', 'pt-br')
            ->assertJsonPath('data.is_active', true);

        $this->postJson('/api/languages', [
            'code' => 'de',
            'name' => 'German',
            'is_active' => false,
        ], $headers)->assertCreated()->assertJsonPath('data.is_active', false);

        $this->postJson('/api/languages', [
            'code' => 'de',
            'name' => 'German',
        ], $headers)->assertUnprocessable();

        $this->getJson('/api/languages', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.code', 'de')
            ->assertJsonPath('data.1.code', 'pt-br');

        $this->getJson('/api/languages/pt-br', $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Portuguese');

        $this->patchJson('/api/languages/de', [
            'name' => 'Deutsch',
            'is_active' => true,
        ], $headers)->assertOk()
            ->assertJsonPath('data.name', 'Deutsch')
            ->assertJsonPath('data.is_active', true);

        $this->patchJson('/api/languages/de', [], $headers)->assertUnprocessable();
        $this->getJson('/api/languages/missing', $headers)->assertNotFound();
        $this->assertSame(2, Language::query()->count());
    }

    public function test_tags_are_created_and_slug_collisions_conflict(): void
    {
        $headers = $this->bearer($this->token());

        $this->postJson('/api/tags', ['name' => ' Mobile '], $headers)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'mobile');

        $this->postJson('/api/tags', ['name' => 'Mobile'], $headers)
            ->assertUnprocessable();

        $this->postJson('/api/tags', ['name' => 'Web App'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'web-app');

        $this->postJson('/api/tags', ['name' => 'web-app'], $headers)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Tag web-app already exists.');

        $this->postJson('/api/tags', ['name' => '!!'], $headers)
            ->assertUnprocessable();

        $this->getJson('/api/tags', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'mobile');
    }
}
