<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Translation;
use App\Services\DriverContentMatcher;
use App\Support\FulltextTerm;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

class ContentMatcherTest extends TestCase
{
    public function test_sqlite_uses_a_like_fallback(): void
    {
        $query = Translation::query();
        app(DriverContentMatcher::class)->apply($query, 'hello');

        $this->assertStringContainsString('like', strtolower($query->toSql()));
        $this->assertFalse(app(DriverContentMatcher::class)->supportsFulltext($query));
    }

    public function test_mysql_fulltext_is_used_for_long_terms_and_skipped_for_short_or_empty_ones(): void
    {
        $matcher = new class (new FulltextTerm()) extends DriverContentMatcher {
            public function supportsFulltext(Builder $query): bool
            {
                return true;
            }
        };

        $matched = Translation::query();
        $matcher->apply($matched, 'hello world');
        $this->assertStringContainsString('MATCH(translations.content)', $matched->toSql());
        $this->assertContains('+hello +world', $matched->getBindings());

        $short = Translation::query();
        $matcher->apply($short, 'hi');
        $this->assertStringContainsString('like', strtolower($short->toSql()));

        $symbols = Translation::query();
        $matcher->apply($symbols, '+++');
        $this->assertStringContainsString('like', strtolower($symbols->toSql()));
    }
}
