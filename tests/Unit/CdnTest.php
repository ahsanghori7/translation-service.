<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\CdnCachePolicy;
use App\Contracts\CdnPurger;
use App\Data\ExportScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CdnTest extends TestCase
{
    public function test_cache_policy_builds_revalidation_headers_and_cdn_links(): void
    {
        $scope = new ExportScope('en', 1, [], 'any', 'fp', 4);
        $policy = app(CdnCachePolicy::class);

        config(['cdn.ttl' => 0, 'cdn.url' => null]);
        $headers = $policy->headers($scope, 'nested');
        $this->assertSame('public, max-age=0, must-revalidate', $headers['Cache-Control']);
        $this->assertStringContainsString('nested', $headers['ETag']);
        $this->assertSame('4', $headers['X-Translation-Version']);
        $this->assertNull($policy->publicUrl(Request::create('/api/export/en', 'GET')));

        config(['cdn.ttl' => -5, 'cdn.url' => '']);
        $this->assertSame(
            'public, max-age=0, must-revalidate',
            $policy->headers($scope)['Cache-Control'],
        );
        $this->assertNull($policy->publicUrl(Request::create('/api/export/en', 'GET')));

        config(['cdn.ttl' => 30, 'cdn.url' => 'https://cdn.example.com/']);
        $this->assertSame(
            'public, max-age=30, must-revalidate',
            $policy->headers($scope)['Cache-Control'],
        );
        $this->assertSame(
            'https://cdn.example.com/api/export/en?tags=web',
            $policy->publicUrl(Request::create('/api/export/en?tags=web', 'GET')),
        );
    }

    public function test_cloudflare_purge_respects_configuration_and_failures(): void
    {
        $purger = app(CdnPurger::class);

        Http::fake();
        config(['cdn.enabled' => false]);
        $purger->purge();
        Http::assertNothingSent();

        config(['cdn.enabled' => true, 'cdn.cloudflare.zone_id' => null, 'cdn.cloudflare.api_token' => 'token']);
        $purger->purge();
        Http::assertNothingSent();

        config(['cdn.enabled' => true, 'cdn.cloudflare.zone_id' => '', 'cdn.cloudflare.api_token' => 'token']);
        $purger->purge();
        Http::assertNothingSent();

        config(['cdn.enabled' => true, 'cdn.cloudflare.zone_id' => 'zone', 'cdn.cloudflare.api_token' => '']);
        $purger->purge();
        Http::assertNothingSent();

        config(['cdn.enabled' => true, 'cdn.cloudflare.zone_id' => 'zone', 'cdn.cloudflare.api_token' => 'token']);
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        $purger->purge();
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/zones/zone/purge_cache'));

        Http::fake(['*' => Http::response(['success' => false], 500)]);
        $purger->purge();
        Http::assertSentCount(1);

        Http::fake(function (): void {
            throw new RuntimeException('cdn down');
        });
        $purger->purge();
        $this->assertTrue(true);
    }
}
