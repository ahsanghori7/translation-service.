<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\CdnPurger;
use App\Contracts\RevisionStore;
use App\Models\Language;
use App\Models\Tag;
use App\Models\Translation;
use Tests\ApiTestCase;

class ExportVersionTest extends ApiTestCase
{
    private object $purger;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purger = new class () implements CdnPurger {
            public int $calls = 0;

            public function purge(): void
            {
                $this->calls++;
            }
        };
        $this->app->instance(CdnPurger::class, $this->purger);

        $this->headers = $this->bearer($this->token());
        Language::factory()->create(['code' => 'en', 'name' => 'English']);
        Tag::factory()->create(['name' => 'Web', 'slug' => 'web']);
        Tag::factory()->create(['name' => 'Mobile', 'slug' => 'mobile']);
    }

    public function test_a_no_op_update_keeps_the_version_the_etag_and_skips_the_purge(): void
    {
        $id = $this->createLogin();
        $etag = (string) $this->getJson('/api/export/en')->assertOk()->headers->get('ETag');
        $version = $this->version();
        $purges = $this->purger->calls;
        $updatedAt = Translation::query()->findOrFail($id)->updated_at?->toIso8601String();

        $this->putJson('/api/translations/'.$id, [
            'content' => 'Sign in',
            'tags' => ['web'],
        ], $this->headers)->assertOk()->assertJsonPath('data.content', 'Sign in');

        $this->assertSame($version, $this->version());
        $this->assertSame($purges, $this->purger->calls);
        $this->assertSame($updatedAt, Translation::query()->findOrFail($id)->updated_at?->toIso8601String());
        $this->getJson('/api/export/en', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_a_description_only_update_is_saved_without_invalidating_the_export(): void
    {
        $id = $this->createLogin();
        $version = $this->version();

        $this->putJson('/api/translations/'.$id, ['description' => 'Login button'], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.description', 'Login button');

        $this->assertSame($version, $this->version());
        $this->assertSame(1, $this->purger->calls);
    }

    public function test_content_and_tag_changes_still_bump_the_version_and_purge(): void
    {
        $id = $this->createLogin();
        $etag = (string) $this->getJson('/api/export/en')->headers->get('ETag');
        $version = $this->version();

        $this->putJson('/api/translations/'.$id, ['content' => 'Log in'], $this->headers)->assertOk();
        $this->assertSame($version + 1, $this->version());
        $this->assertSame(2, $this->purger->calls);
        $this->getJson('/api/export/en', ['If-None-Match' => $etag])
            ->assertOk()
            ->assertJsonPath('translations', ['auth.login' => 'Log in']);

        $this->putJson('/api/translations/'.$id, ['tags' => ['web', 'mobile']], $this->headers)->assertOk();
        $this->assertSame($version + 2, $this->version());
        $this->getJson('/api/export/en?tags=mobile')->assertOk()->assertJsonPath('count', 1);

        $this->putJson('/api/translations/'.$id, ['tags' => ['web']], $this->headers)->assertOk();
        $this->assertSame($version + 3, $this->version());
        $this->getJson('/api/export/en?tags=mobile')->assertOk()->assertJsonPath('count', 0);
        $this->assertSame(4, $this->purger->calls);
    }

    private function createLogin(): int
    {
        return (int) $this->postJson('/api/translations', [
            'key' => 'auth.login',
            'locale' => 'en',
            'content' => 'Sign in',
            'tags' => ['web'],
        ], $this->headers)->assertCreated()->json('data.id');
    }

    private function version(): int
    {
        return app(RevisionStore::class)->current();
    }
}
