<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Tag;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class ExportTest extends ApiTestCase
{
    public function test_export_returns_fresh_flat_and_nested_payloads(): void
    {
        $headers = $this->bearer($this->token());
        Language::factory()->create(['code' => 'en', 'name' => 'English']);
        Tag::factory()->create(['name' => 'Web', 'slug' => 'web']);
        Tag::factory()->create(['name' => 'Mobile', 'slug' => 'mobile']);

        $this->postJson('/api/translations', [
            'key' => 'auth.login',
            'locale' => 'en',
            'content' => 'Sign in',
            'tags' => ['web', 'mobile'],
        ], $headers)->assertCreated();

        $this->postJson('/api/translations', [
            'key' => 'auth.logout',
            'locale' => 'en',
            'content' => 'Sign out',
            'tags' => ['web'],
        ], $headers)->assertCreated();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $first = $this->getJson('/api/export/en');
        $firstQueries = count(DB::getQueryLog());
        $first->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('count', 2)
            ->assertHeader('X-Translation-Version');
        $this->assertStringContainsString('max-age=0', (string) $first->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', (string) $first->headers->get('Cache-Control'));
        $this->assertSame('Sign in', $first->json('translations')['auth.login']);

        $etag = $first->headers->get('ETag');
        $this->assertIsString($etag);

        DB::flushQueryLog();
        $second = $this->getJson('/api/export/en');
        $this->assertLessThan($firstQueries, count(DB::getQueryLog()));
        $second->assertOk();
        $this->assertSame($first->getContent(), $second->getContent());

        $this->getJson('/api/export/en', ['If-None-Match' => $etag])->assertStatus(304);
        $this->getJson('/api/export/en', ['If-None-Match' => 'W/'.$etag])->assertStatus(304);
        $this->getJson('/api/export/en', ['If-None-Match' => '*'])->assertStatus(304);

        $login = Translation::query()->where('content', 'Sign in')->firstOrFail();
        $this->putJson('/api/translations/'.$login->id, [
            'content' => 'Log in',
        ], $headers)->assertOk();

        $updated = $this->getJson('/api/export/en', ['If-None-Match' => $etag]);
        $updated->assertOk();
        $this->assertSame('Log in', $updated->json('translations')['auth.login']);
        $this->assertNotSame($etag, $updated->headers->get('ETag'));

        $this->getJson('/api/export/en?structure=nested')
            ->assertOk()
            ->assertJsonPath('translations.auth.login', 'Log in')
            ->assertJsonPath('translations.auth.logout', 'Sign out');

        $tagged = $this->getJson('/api/export/en?tags=mobile')
            ->assertOk()
            ->assertJsonPath('count', 1);
        $this->assertSame('Log in', $tagged->json('translations')['auth.login']);
        $this->assertArrayNotHasKey('auth.logout', $tagged->json('translations'));

        $this->getJson('/api/export/en?tags=web,mobile&tag_mode=all')
            ->assertOk()
            ->assertJsonPath('count', 1);

        $missing = $this->getJson('/api/export/en?tags=missing')->assertOk()->assertJsonPath('count', 0);
        $this->assertStringContainsString('"translations":{}', $missing->getContent());

        config(['cdn.url' => 'https://cdn.example.com', 'cdn.ttl' => 15]);
        $cdn = $this->getJson('/api/export/en')
            ->assertOk()
            ->assertJsonPath('cdn_url', 'https://cdn.example.com/api/export/en');
        $this->assertStringContainsString('max-age=15', (string) $cdn->headers->get('Cache-Control'));
    }

    public function test_export_guards_unknown_and_inactive_locales_and_optional_auth(): void
    {
        Language::factory()->inactive()->create(['code' => 'de', 'name' => 'German']);

        $this->getJson('/api/export/zz')->assertNotFound();
        $this->getJson('/api/export/de')->assertStatus(422);
        $this->getJson('/api/export/EN')->assertNotFound();

        Language::factory()->create(['code' => 'en', 'name' => 'English']);
        config(['translations.export_requires_auth' => true]);

        $this->getJson('/api/export/en')->assertUnauthorized();
        $this->getJson('/api/export/en', $this->bearer($this->token()))
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    public function test_token_protected_exports_are_never_stored_by_shared_caches(): void
    {
        Language::factory()->create(['code' => 'en', 'name' => 'English']);
        config([
            'translations.export_requires_auth' => true,
            'cdn.url' => 'https://cdn.example.com',
            'cdn.ttl' => 300,
        ]);
        $viewer = $this->bearer($this->token(User::factory()->viewer()->create()));

        $private = $this->getJson('/api/export/en', $viewer)->assertOk();
        $this->assertSame('max-age=0, must-revalidate, private', $private->headers->get('Cache-Control'));
        $this->assertSame('no-store', $private->headers->get('CDN-Cache-Control'));
        $this->assertStringContainsString('Authorization', (string) $private->headers->get('Vary'));
        $this->assertNull($private->headers->get('Surrogate-Key'));
        $this->assertNull($private->headers->get('Cache-Tag'));
        $private->assertJsonMissingPath('cdn_url');

        $this->getJson('/api/export/en', $viewer + ['If-None-Match' => (string) $private->headers->get('ETag')])
            ->assertStatus(304)
            ->assertHeader('CDN-Cache-Control', 'no-store');

        config(['translations.export_requires_auth' => false]);
        $public = $this->getJson('/api/export/en')->assertOk();
        $this->assertStringContainsString('public', (string) $public->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', (string) $public->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('Authorization', (string) $public->headers->get('Vary'));
        $public->assertJsonPath('cdn_url', 'https://cdn.example.com/api/export/en');
    }

    public function test_export_sql_selects_only_the_columns_it_returns(): void
    {
        Language::factory()->create(['code' => 'en', 'name' => 'English']);
        $key = TranslationKey::factory()->create([
            'message_key' => 'home.title',
            'key_group' => 'home',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $key->id,
            'language_id' => Language::query()->where('code', 'en')->value('id'),
            'content' => 'Home',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/export/en')->assertOk();

        $sql = strtolower(implode("\n", array_column(DB::getQueryLog(), 'query')));
        $this->assertStringContainsString('message_key', $sql);
        $this->assertStringContainsString('language_id', $sql);
        $this->assertMatchesRegularExpression('/from "?translations"?/', $sql);
        $this->assertStringNotContainsString('select *', $sql);
    }

    public function test_conditional_requests_skip_the_export_query(): void
    {
        $this->seedHomeTitle();

        $etag = $this->getJson('/api/export/en')->assertOk()->headers->get('ETag');
        Cache::flush();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/export/en', ['If-None-Match' => (string) $etag])
            ->assertStatus(304)
            ->assertHeader('ETag', (string) $etag);

        $sql = strtolower(implode("\n", array_column(DB::getQueryLog(), 'query')));
        $this->assertStringNotContainsString('translation_keys', $sql);
        $this->assertStringNotContainsString('"translations"', $sql);
    }

    public function test_a_new_version_overwrites_the_cached_export_instead_of_adding_one(): void
    {
        $headers = $this->bearer($this->token());
        $translation = $this->seedHomeTitle();
        $slot = 'translations.export.'.$translation->language_id.'.'.substr(hash('sha256', 'any|'), 0, 16).'.flat';

        $first = $this->getJson('/api/export/en')->assertOk();
        $this->assertSame((int) $first->json('version'), (int) Cache::get($slot)['version']);

        $this->putJson('/api/translations/'.$translation->id, ['content' => 'Start'], $headers)->assertOk();

        $second = $this->getJson('/api/export/en')
            ->assertOk()
            ->assertJsonPath('translations', ['home.title' => 'Start']);
        $this->assertGreaterThan((int) $first->json('version'), (int) $second->json('version'));
        $this->assertSame((int) $second->json('version'), (int) Cache::get($slot)['version']);
        $this->assertSame($second->getContent(), Cache::get($slot)['body']);
    }

    private function seedHomeTitle(): Translation
    {
        $language = Language::factory()->create(['code' => 'en', 'name' => 'English']);
        $key = TranslationKey::factory()->create([
            'message_key' => 'home.title',
            'key_group' => 'home',
        ]);

        return Translation::factory()->create([
            'translation_key_id' => $key->id,
            'language_id' => $language->id,
            'content' => 'Home',
        ]);
    }
}
