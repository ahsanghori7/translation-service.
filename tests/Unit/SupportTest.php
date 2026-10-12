<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\ExportDocument;
use App\Data\ExportScope;
use App\Support\EtagMatcher;
use App\Support\FulltextTerm;
use App\Support\LikeEscaper;
use App\Support\NestedMessageTree;
use App\Support\TranslationKeyRules;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_fulltext_terms_are_sanitized_and_capped(): void
    {
        $terms = new FulltextTerm();

        $this->assertSame('+hello +world', $terms->toBoolean('  hello   world '));
        $this->assertSame('+foo', $terms->toBoolean('++foo'));
        $this->assertSame('', $terms->toBoolean('+++'));
        $this->assertSame('', $terms->toBoolean('   '));
        $this->assertSame('+café', $terms->toBoolean('café'));
        $this->assertSame(8, substr_count($terms->toBoolean(implode(' ', array_fill(0, 10, 'word'))), '+'));
    }

    public function test_like_patterns_escape_wildcards(): void
    {
        $this->assertSame('100\\%\\_a\\\\b', LikeEscaper::escape('100%_a\\b'));
        $this->assertSame('%100\\%%', LikeEscaper::contains('100%'));
        $this->assertSame('a\\_b%', LikeEscaper::prefix('a_b'));
    }

    public function test_translation_key_groups_use_the_first_segment(): void
    {
        $rules = new TranslationKeyRules();

        $this->assertSame('auth', $rules->group('auth.login.title'));
        $this->assertSame('home', $rules->group('home'));
        $this->assertSame(64, strlen($rules->group(str_repeat('a', 80).'.child')));
    }

    public function test_nested_message_trees_keep_sibling_keys(): void
    {
        $tree = new NestedMessageTree();

        $nested = $tree->nest([
            'auth.login.title' => 'Sign in',
            'auth.logout' => 'Sign out',
            'home' => 'Home',
        ]);

        $this->assertSame('Sign in', $nested['auth']['login']['title']);
        $this->assertSame('Sign out', $nested['auth']['logout']);
        $this->assertSame('Home', $nested['home']);
        $this->assertSame(['login' => 'Login'], $tree->nest([
            'auth' => 'Auth',
            'auth.login' => 'Login',
        ])['auth']);
        $this->assertSame([], $tree->nest(['.login' => 'Dropped', '' => 'Dropped']));
    }

    public function test_export_documents_build_etags_and_round_trip_through_cache(): void
    {
        $scope = new ExportScope('en', 1, [], 'any', 'abc', 2);
        $this->assertSame('"2-en-abc-flat"', $scope->etag());
        $this->assertSame('"2-en-abc-nested"', $scope->etag('nested'));
        $this->assertFalse($scope->matchesNothing);

        $document = new ExportDocument('en', 2, [], '2026-01-01T00:00:00+00:00', 'abc');
        $this->assertSame(0, $document->toArray()['count']);
        $this->assertEquals(new \stdClass(), $document->toArray()['translations']);

        $cached = $document->toCacheArray();
        $this->assertArrayNotHasKey('translations', $cached);
        $this->assertSame(0, $cached['count']);

        $restored = ExportDocument::fromArray($cached);
        $this->assertSame('en', $restored->locale);
        $this->assertSame(2, $restored->version);
        $this->assertSame([], $restored->translations);
        $this->assertSame(0, $restored->entryCount());
        $this->assertSame($cached['body'], $restored->encodedBody());

        $populated = new ExportDocument('fr', 3, ['auth.login' => 'Sign in'], '2026-01-01T00:00:00+00:00', 'def');
        $populatedCache = $populated->toCacheArray();
        $this->assertArrayNotHasKey('translations', $populatedCache);
        $this->assertSame(1, $populatedCache['count']);
        $this->assertStringContainsString('Sign in', $populatedCache['body']);

        $populatedRestored = ExportDocument::fromArray($populatedCache);
        $this->assertSame([], $populatedRestored->translations);
        $this->assertSame(1, $populatedRestored->entryCount());
        $this->assertSame($populatedCache['body'], $populatedRestored->encodedBody());
    }

    public function test_etag_matcher_accepts_weak_star_and_lists(): void
    {
        $matcher = new EtagMatcher();
        $etag = '"2-en-abc-flat"';

        $this->assertFalse($matcher->matches(null, $etag));
        $this->assertFalse($matcher->matches('', $etag));
        $this->assertFalse($matcher->matches('"other"', $etag));
        $this->assertTrue($matcher->matches($etag, $etag));
        $this->assertTrue($matcher->matches('W/'.$etag, $etag));
        $this->assertTrue($matcher->matches('*', $etag));
        $this->assertTrue($matcher->matches(' "nope" , '.$etag, $etag));
    }
}
