<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\TagRepository;
use App\Contracts\TokenManager;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\ApiTestCase;

class TokenManagerTest extends ApiTestCase
{
    public function test_tokens_expire_touch_and_can_be_permanent(): void
    {
        $manager = app(TokenManager::class);
        $user = User::factory()->create();

        config(['translations.token_ttl_days' => 0]);
        $permanent = $manager->issue($user, 'permanent');
        $this->assertNull($permanent->expiresAt);
        $this->assertInstanceOf(User::class, $manager->authenticate($permanent->plainTextToken)?->user);

        config(['translations.token_ttl_days' => 30]);
        $issued = $manager->issue($user, 'session');
        $this->assertNotNull($issued->expiresAt);
        $this->assertNull($manager->authenticate(''));
        $this->assertNull($manager->authenticate('short'));
        $this->assertNull($manager->authenticate(str_repeat('ab', 32)));

        $authenticated = $manager->authenticate($issued->plainTextToken);
        $this->assertNotNull($authenticated);
        $touched = $authenticated->token->last_used_at;
        $this->assertNotNull($touched);

        $this->travel(1)->minutes();
        $manager->authenticate($issued->plainTextToken);
        $again = ApiToken::query()->where('name', 'session')->firstOrFail();
        $this->assertTrue($again->last_used_at?->equalTo($touched));

        $this->travel(6)->minutes();
        $manager->authenticate($issued->plainTextToken);
        $again->refresh();
        $this->assertTrue($again->last_used_at?->greaterThan($touched));

        $manager->revoke($again);
        $this->assertNull($manager->authenticate($issued->plainTextToken));

        $replacement = $manager->issue($user, 'replacement');
        ApiToken::query()->where('name', 'replacement')->update(['expires_at' => now()->subMinute()]);
        $this->assertNull($manager->authenticate($replacement->plainTextToken));
    }

    public function test_authentication_fails_when_the_user_row_is_gone(): void
    {
        $user = User::factory()->create();
        $issued = app(TokenManager::class)->issue($user, 'orphan');

        Schema::disableForeignKeyConstraints();
        DB::table('users')->delete();
        Schema::enableForeignKeyConstraints();

        $this->assertNull(app(TokenManager::class)->authenticate($issued->plainTextToken));
    }

    public function test_empty_tag_lookups_do_not_hit_the_database_for_ids(): void
    {
        $tags = app(TagRepository::class);

        $this->assertCount(0, $tags->findBySlugs([]));
        $this->assertSame([], $tags->idsForSlugs([]));
    }
}
