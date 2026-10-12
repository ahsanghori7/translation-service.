<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Tag;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class TranslationSearchTest extends ApiTestCase
{
    private string $headersToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headersToken = $this->token();
        $english = Language::factory()->create(['code' => 'en', 'name' => 'English']);
        $french = Language::factory()->create(['code' => 'fr', 'name' => 'French']);
        $web = Tag::factory()->create(['name' => 'Web', 'slug' => 'web']);
        $mobile = Tag::factory()->create(['name' => 'Mobile', 'slug' => 'mobile']);

        $login = TranslationKey::factory()->create([
            'message_key' => 'auth.login',
            'key_group' => 'auth',
            'description' => 'Login',
        ]);
        $login->tags()->sync([$web->id, $mobile->id]);

        $logout = TranslationKey::factory()->create([
            'message_key' => 'auth.logout',
            'key_group' => 'auth',
        ]);
        $logout->tags()->sync([$web->id]);

        $dashboard = TranslationKey::factory()->create([
            'message_key' => 'dashboard.title',
            'key_group' => 'dashboard',
        ]);
        $dashboard->tags()->sync([$mobile->id]);

        $underscore = TranslationKey::factory()->create([
            'message_key' => 'a_b.title',
            'key_group' => 'a_b',
        ]);
        $similar = TranslationKey::factory()->create([
            'message_key' => 'axb.title',
            'key_group' => 'axb',
        ]);
        $percent = TranslationKey::factory()->create([
            'message_key' => 'units.percent',
            'key_group' => 'units',
        ]);
        $hello = TranslationKey::factory()->create([
            'message_key' => 'units.hello',
            'key_group' => 'units',
        ]);

        Translation::factory()->create([
            'translation_key_id' => $login->id,
            'language_id' => $english->id,
            'content' => 'Sign in please',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $logout->id,
            'language_id' => $english->id,
            'content' => 'Sign out',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $dashboard->id,
            'language_id' => $english->id,
            'content' => 'Dashboard',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $login->id,
            'language_id' => $french->id,
            'content' => 'Connexion',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $underscore->id,
            'language_id' => $english->id,
            'content' => 'Underscore',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $similar->id,
            'language_id' => $english->id,
            'content' => 'Similar',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $percent->id,
            'language_id' => $english->id,
            'content' => '100%',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $hello->id,
            'language_id' => $english->id,
            'content' => 'Hello',
        ]);
    }

    public function test_search_filters_by_key_content_tag_locale_and_group(): void
    {
        $headers = $this->bearer($this->headersToken);

        $this->getJson('/api/translations?key=auth&key_mode=prefix&locale=en', $headers)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/translations?key=auth.login&key_mode=exact&locale=en', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', 'Sign in please');

        $this->getJson('/api/translations?key=login&key_mode=contains', $headers)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/translations?content=sign&locale=en', $headers)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/translations?tags=web,mobile&tag_mode=all&locale=en', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'auth.login');

        $this->getJson('/api/translations?tags=web&locale=en', $headers)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/translations?group=dashboard', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.key', 'dashboard.title');

        $empty = $this->getJson('/api/translations?locale=zz', $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.path', url('/api/translations'))
            ->assertJsonPath('meta.next_cursor', null);
        $this->assertArrayNotHasKey('total', (array) $empty->json('meta'));

        $this->getJson('/api/translations?tags=missing', $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/translations?key=a_b&key_mode=prefix&locale=en', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'a_b.title');

        $this->getJson('/api/translations?content='.urlencode('%').'&locale=en', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', '100%');

        $this->getJson("/api/translations?content=' OR 1=1 --&locale=en", $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $seen = [];
        $cursor = null;
        do {
            $page = $this->getJson('/api/translations?per_page=3&locale=en'.($cursor !== null ? '&cursor='.urlencode($cursor) : ''), $headers)
                ->assertOk();
            $this->assertLessThanOrEqual(3, count($page->json('data')));
            array_push($seen, ...array_column($page->json('data'), 'id'));
            $cursor = $page->json('meta.next_cursor');
            if ($cursor !== null) {
                $this->assertStringContainsString('locale=en', (string) $page->json('links.next'));
            }
        } while ($cursor !== null);

        $this->assertCount(7, $seen);
        $this->assertSame($seen, array_values(array_unique($seen)));
        $sorted = $seen;
        rsort($sorted);
        $this->assertSame($sorted, $seen);

        $this->getJson('/api/translations?cursor=not-a-cursor&locale=en', $headers)
            ->assertOk()
            ->assertJsonCount(7, 'data');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/translations?key=auth&locale=en', $headers)->assertOk();
        $sql = strtolower(implode("\n", array_column(DB::getQueryLog(), 'query')));
        $this->assertStringNotContainsString('count(', $sql);
        $this->assertStringNotContainsString('offset', $sql);

        $this->getJson('/api/translations?per_page=500', $headers)->assertUnprocessable();
        $this->getJson('/api/translations?key_mode=sideways', $headers)->assertUnprocessable();
        $this->json('GET', '/api/translations', ['tags' => 1], $headers)->assertUnprocessable();
    }
}
