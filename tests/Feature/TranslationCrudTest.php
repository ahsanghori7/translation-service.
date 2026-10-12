<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\TranslationRepository;
use App\Data\TranslationFilter;
use App\Models\Language;
use App\Models\Tag;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class TranslationCrudTest extends ApiTestCase
{
    public function test_translations_can_be_created_viewed_updated_and_deleted(): void
    {
        $headers = $this->bearer($this->token());
        Language::factory()->create(['code' => 'en', 'name' => 'English']);
        Language::factory()->create(['code' => 'fr', 'name' => 'French']);
        Tag::factory()->create(['name' => 'Web', 'slug' => 'web']);
        Tag::factory()->create(['name' => 'Mobile', 'slug' => 'mobile']);

        $created = $this->postJson('/api/translations', [
            'key' => ' auth.login ',
            'locale' => ' EN ',
            'content' => 'Sign in',
            'description' => 'Login title',
            'tags' => ['Web'],
        ], $headers);

        $created->assertCreated()
            ->assertJsonPath('data.key', 'auth.login')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.group', 'auth')
            ->assertJsonPath('data.tags.0', 'web');

        $id = $created->json('data.id');

        $this->getJson('/api/translations/'.$id, $headers)
            ->assertOk()
            ->assertJsonPath('data.content', 'Sign in');

        $french = $this->postJson('/api/translations', [
            'key' => 'auth.login',
            'locale' => 'fr',
            'content' => 'Connexion',
            'tags' => ['mobile'],
        ], $headers)->assertCreated();

        $merged = $this->getJson('/api/translations/'.$id, $headers)->assertOk();
        $this->assertEqualsCanonicalizing(['mobile', 'web'], $merged->json('data.tags'));

        $this->putJson('/api/translations/'.$id, [
            'content' => 'Log in',
            'description' => 'Shared title',
        ], $headers)->assertOk()->assertJsonPath('data.content', 'Log in');

        $this->getJson('/api/translations/'.$french->json('data.id'), $headers)
            ->assertJsonPath('data.description', 'Shared title');

        $this->putJson('/api/translations/'.$id, [
            'tags' => ['mobile'],
        ], $headers)->assertOk()->assertJsonCount(1, 'data.tags');

        $this->putJson('/api/translations/'.$id, [
            'tags' => [],
        ], $headers)->assertOk()->assertJsonCount(0, 'data.tags');

        $this->putJson('/api/translations/'.$id, [], $headers)->assertUnprocessable();

        $this->deleteJson('/api/translations/'.$id, [], $headers)->assertNoContent();
        $this->assertDatabaseHas('translation_keys', ['message_key' => 'auth.login']);

        $this->deleteJson('/api/translations/'.$french->json('data.id'), [], $headers)
            ->assertNoContent();
        $this->assertDatabaseMissing('translation_keys', ['message_key' => 'auth.login']);
        $this->getJson('/api/translations/'.$id, $headers)->assertNotFound();
    }

    public function test_create_rejects_duplicates_unknown_locales_and_unknown_tags(): void
    {
        $headers = $this->bearer($this->token());
        Language::factory()->create(['code' => 'en', 'name' => 'English']);
        Language::factory()->inactive()->create(['code' => 'de', 'name' => 'German']);

        $payload = [
            'key' => 'home.title',
            'locale' => 'en',
            'content' => 'Home',
        ];

        $this->postJson('/api/translations', $payload, $headers)->assertCreated();
        $this->postJson('/api/translations', $payload, $headers)
            ->assertStatus(409)
            ->assertJsonPath('message', 'A translation for this key and locale already exists.');

        $this->postJson('/api/translations', [
            'key' => 'home.title',
            'locale' => 'zz',
            'content' => 'Home',
        ], $headers)->assertNotFound();

        $this->postJson('/api/translations', [
            'key' => 'home.title',
            'locale' => 'de',
            'content' => 'Zuhause',
        ], $headers)->assertStatus(422)->assertJsonPath('message', 'Locale de is inactive.');

        $this->postJson('/api/translations', [
            'key' => 'home.subtitle',
            'locale' => 'en',
            'content' => 'Welcome',
            'tags' => ['missing'],
        ], $headers)->assertStatus(422)->assertJsonPath('message', 'Unknown tags: missing.');

        $this->postJson('/api/translations', [
            'key' => 'Bad Key',
            'locale' => 'en',
            'content' => 'Nope',
        ], $headers)->assertUnprocessable();

        $this->assertSame(1, Translation::query()->count());
        $this->assertSame(1, TranslationKey::query()->count());
    }

    public function test_a_duplicate_that_slips_past_the_pre_check_returns_409_not_500(): void
    {
        $headers = $this->bearer($this->token());
        Language::factory()->create(['code' => 'en', 'name' => 'English']);
        Tag::factory()->create(['name' => 'Web', 'slug' => 'web']);
        $payload = ['key' => 'home.title', 'locale' => 'en', 'content' => 'Home', 'tags' => ['web']];

        $real = app(TranslationRepository::class);
        $this->app->instance(TranslationRepository::class, new class ($real) implements TranslationRepository {
            public function __construct(private readonly TranslationRepository $inner)
            {
            }

            public function findForKeyAndLanguage(string $messageKey, int $languageId): ?Translation
            {
                return null;
            }

            public function paginate(TranslationFilter $filter): CursorPaginator
            {
                return $this->inner->paginate($filter);
            }
        });

        $this->postJson('/api/translations', $payload, $headers)->assertCreated();
        $this->postJson('/api/translations', $payload, $headers)
            ->assertStatus(409)
            ->assertJsonPath('message', 'A translation for this key and locale already exists.');
        $this->assertSame(1, Translation::query()->count());
        $this->assertSame(1, DB::table('translation_key_tag')->count());
    }

    public function test_adding_a_locale_with_a_new_description_updates_the_shared_key(): void
    {
        $headers = $this->bearer($this->token());
        Language::factory()->create(['code' => 'en', 'name' => 'English']);
        Language::factory()->create(['code' => 'fr', 'name' => 'French']);

        $this->postJson('/api/translations', [
            'key' => 'home.title', 'locale' => 'en', 'content' => 'Home', 'description' => 'Old',
        ], $headers)->assertCreated();

        $this->postJson('/api/translations', [
            'key' => 'home.title', 'locale' => 'fr', 'content' => 'Accueil', 'description' => 'Page heading',
        ], $headers)->assertCreated()->assertJsonPath('data.description', 'Page heading');

        $this->assertSame('Page heading', TranslationKey::query()->value('description'));
        $this->assertSame(1, TranslationKey::query()->count());
    }

    public function test_a_key_created_concurrently_is_reused_instead_of_failing(): void
    {
        $headers = $this->bearer($this->token());
        Language::factory()->create(['code' => 'en', 'name' => 'English']);

        $raced = false;
        DB::listen(static function (QueryExecuted $query) use (&$raced): void {
            if ($raced || ! str_contains($query->sql, 'from "translation_keys" where "message_key"')) {
                return;
            }

            $raced = true;
            DB::table('translation_keys')->insert([
                'message_key' => 'home.title',
                'key_group' => 'home',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->postJson('/api/translations', [
            'key' => 'home.title',
            'locale' => 'en',
            'content' => 'Home',
        ], $headers)->assertCreated()->assertJsonPath('data.key', 'home.title');

        $this->assertSame(1, TranslationKey::query()->count());
        $this->assertSame(1, Translation::query()->count());
    }
}
