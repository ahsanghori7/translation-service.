<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Language;
use App\Models\Tag;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Models\User;
use Tests\ApiTestCase;

class AuthorizationTest extends ApiTestCase
{
    public function test_viewers_can_read_but_every_write_endpoint_is_forbidden(): void
    {
        $viewer = $this->bearer($this->token(User::factory()->viewer()->create()));
        $language = Language::factory()->create(['code' => 'en', 'name' => 'English']);
        Tag::factory()->create(['name' => 'Web', 'slug' => 'web']);
        $translation = Translation::factory()->create([
            'translation_key_id' => TranslationKey::factory()->create(['message_key' => 'home.title', 'key_group' => 'home'])->id,
            'language_id' => $language->id,
            'content' => 'Home',
        ]);

        $this->getJson('/api/languages', $viewer)->assertOk();
        $this->getJson('/api/languages/en', $viewer)->assertOk();
        $this->getJson('/api/tags', $viewer)->assertOk();
        $this->getJson('/api/translations', $viewer)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/translations/'.$translation->id, $viewer)->assertOk();
        $this->getJson('/api/auth/user', $viewer)->assertOk()->assertJsonPath('data.role', 'viewer');

        $writes = [
            ['POST', '/api/languages', ['code' => 'de', 'name' => 'German']],
            ['PATCH', '/api/languages/en', ['name' => 'British English']],
            ['POST', '/api/tags', ['name' => 'Mobile']],
            ['POST', '/api/translations', ['key' => 'home.body', 'locale' => 'en', 'content' => 'Hi']],
            ['PUT', '/api/translations/'.$translation->id, ['content' => 'Changed']],
            ['DELETE', '/api/translations/'.$translation->id, []],
        ];
        foreach ($writes as [$method, $uri, $body]) {
            $this->json($method, $uri, $body, $viewer)
                ->assertForbidden()
                ->assertJsonPath('message', 'This action requires the editor role.');
        }

        $this->assertSame('Home', $translation->fresh()?->content);
        $this->assertSame(1, Language::query()->count());
        $this->assertSame(1, Tag::query()->count());
    }

    public function test_editors_can_write_and_unauthenticated_writes_are_rejected_first(): void
    {
        $editor = $this->bearer($this->token(User::factory()->create()));
        Language::factory()->create(['code' => 'en', 'name' => 'English']);

        $this->postJson('/api/tags', ['name' => 'Web'])->assertUnauthorized();

        $this->postJson('/api/tags', ['name' => 'Web'], $editor)->assertCreated();
        $this->postJson('/api/translations', [
            'key' => 'home.title', 'locale' => 'en', 'content' => 'Home',
        ], $editor)->assertCreated();
    }

    public function test_role_cannot_be_self_assigned_and_existing_users_keep_write_access(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'editor',
        ])->assertCreated()->assertJsonPath('user.role', 'viewer');

        $this->assertSame(UserRole::Viewer, User::query()->where('email', 'mallory@example.com')->first()?->role);

        $this->seed();
        $this->assertSame(UserRole::Editor, User::query()->where('email', 'admin@example.com')->first()?->role);
    }
}
