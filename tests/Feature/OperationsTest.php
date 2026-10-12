<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class OperationsTest extends ApiTestCase
{
    public function test_docs_security_headers_and_root_index(): void
    {
        $this->getJson('/')
            ->assertOk()
            ->assertJsonPath('service', 'Translation Management Service');

        $this->get('/api/docs')
            ->assertOk()
            ->assertSee('swagger-ui', false)
            ->assertSee('openapi.yaml', false);

        $this->get('/api/openapi.yaml')
            ->assertOk()
            ->assertSee('openapi:', false);

        $spec = $this->get('http://translation-service.test/api/openapi.yaml')->assertOk()->getContent();
        $this->assertStringContainsString(
            "servers:\n  - url: 'http://translation-service.test'\n    description: Current host",
            (string) $spec,
        );
        $this->assertSame(1, substr_count((string) $spec, 'servers:'));

        $languages = $this->getJson('/api/languages')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', (string) $languages->headers->get('Cache-Control'));
    }

    public function test_database_seeder_is_idempotent_and_the_admin_can_log_in(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(3, Language::query()->count());
        $this->assertSame(1, DB::table('users')->where('email', 'admin@example.com')->count());

        $this->postJson('/api/auth/token', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_seeder_uses_configured_credentials_and_never_resets_an_existing_password(): void
    {
        config([
            'translations.seed.admin_email' => 'owner@example.com',
            'translations.seed.admin_password' => 'first-secret',
        ]);
        $this->seed();

        config(['translations.seed.admin_password' => 'second-secret']);
        $this->seed();

        $this->postJson('/api/auth/token', [
            'email' => 'owner@example.com',
            'password' => 'first-secret',
        ])->assertOk();
        $this->postJson('/api/auth/token', [
            'email' => 'owner@example.com',
            'password' => 'second-secret',
        ])->assertUnauthorized();
    }

    public function test_seeder_skips_the_default_admin_in_production(): void
    {
        config(['translations.seed.admin_password' => null]);
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('db:seed', ['--force' => true])
            ->expectsOutputToContain('SEED_ADMIN_PASSWORD is not set')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(3, Language::query()->count());

        config(['translations.seed.admin_password' => 'prod-secret']);
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('users')->where('email', 'admin@example.com')->count());
    }

    public function test_populate_command_inserts_and_replaces_a_dataset(): void
    {
        Language::factory()->inactive()->create(['code' => 'de', 'name' => 'German']);

        $this->artisan('translations:populate', ['--count' => 0])->assertFailed();
        $this->artisan('translations:populate', ['--count' => 9, '--chunk' => 10])->assertFailed();

        $this->artisan('translations:populate', ['--count' => 9, '--chunk' => 50])
            ->assertSuccessful()
            ->expectsOutputToContain('Inserted 9 translations');

        $this->assertSame(9, Translation::query()->count());
        $this->assertSame(3, DB::table('translation_keys')->count());
        $this->assertSame(3, DB::table('translation_key_tag')->count());
        $this->assertDatabaseHas('translations', ['content' => 'en sample content 0']);
        $this->assertDatabaseHas('languages', ['code' => 'de', 'is_active' => 0]);
        $this->assertSame(2, (int) DB::table('translation_revisions')->value('version'));

        $this->artisan('translations:populate', ['--count' => 9, '--chunk' => 50])->assertFailed();
        $this->assertSame(2, (int) DB::table('translation_revisions')->value('version'));

        $this->artisan('translations:populate', ['--count' => 9, '--chunk' => 50, '--fresh' => true])
            ->assertSuccessful();

        $this->assertSame(9, Translation::query()->count());
        $this->assertSame(3, (int) DB::table('translation_revisions')->value('version'));
        $this->assertDatabaseHas('languages', ['code' => 'de']);
    }

    public function test_benchmark_command_reports_export_time(): void
    {
        $this->artisan('translations:benchmark')
            ->assertSuccessful()
            ->expectsOutputToContain('No active locales');

        $this->artisan('translations:benchmark', ['--locale' => 'zz'])->assertFailed();

        Language::factory()->inactive()->create(['code' => 'de', 'name' => 'German']);
        $this->artisan('translations:benchmark', ['--locale' => 'de'])
            ->assertFailed()
            ->expectsOutputToContain('inactive');

        $this->seed();
        $this->artisan('translations:benchmark')
            ->assertSuccessful()
            ->expectsOutputToContain('locale=en')
            ->expectsOutputToContain('locale=fr')
            ->expectsOutputToContain('time=');

        config(['translations.export_budget_ms' => -1]);
        $this->artisan('translations:benchmark', ['--locale' => 'en', '--assert' => true])
            ->assertFailed()
            ->expectsOutputToContain('exceeded');
    }

    public function test_endpoints_stay_inside_the_response_budget_for_a_large_page(): void
    {
        $this->artisan('translations:populate', ['--count' => 300, '--chunk' => 100])->assertSuccessful();

        $headers = $this->bearer($this->token());
        $sample = Translation::query()->value('id');

        $started = hrtime(true);
        $this->getJson('/api/translations?per_page=15', $headers)->assertOk();
        $listMs = (hrtime(true) - $started) / 1_000_000;

        $started = hrtime(true);
        $this->getJson('/api/translations/'.$sample, $headers)->assertOk();
        $showMs = (hrtime(true) - $started) / 1_000_000;

        $started = hrtime(true);
        $export = $this->getJson('/api/export/en');
        $exportMs = (hrtime(true) - $started) / 1_000_000;

        $export->assertOk()->assertJsonPath('count', 100);
        $this->assertLessThan(200, $listMs, 'List endpoint exceeded 200ms: '.$listMs);
        $this->assertLessThan(200, $showMs, 'Show endpoint exceeded 200ms: '.$showMs);
        $this->assertLessThan(500, $exportMs, 'Export endpoint exceeded 500ms: '.$exportMs);

        $this->artisan('translations:benchmark', ['--locale' => 'en', '--assert' => true])
            ->assertSuccessful();
    }
}
