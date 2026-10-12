<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Models\Translation;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\ApiTestCase;

/**
 * Seeds 100k+ translations with the real populate command, then times every
 * endpoint in-process. Reads use the median of several runs after a warm-up;
 * the cold export is measured once on an empty cache.
 */
#[Group('performance')]
class LargeDatasetPerformanceTest extends ApiTestCase
{
    private const ROWS = 100_000;

    private const RUNS = 5;

    /** @var array<string, string> */
    private array $headers;

    /** @var array<string, float> */
    private array $report = [];

    public function test_endpoints_meet_the_response_budgets_on_100k_translations(): void
    {
        $this->artisan('translations:populate', ['--count' => self::ROWS, '--chunk' => 2000])
            ->assertSuccessful();
        $this->assertSame(self::ROWS, Translation::query()->count());

        $this->headers = $this->bearer($this->token());
        $apiBudget = 200.0;
        $exportBudget = (float) config('translations.export_budget_ms');
        $sample = (int) Translation::query()->where('language_id', 1)->max('id');

        $this->budget('list first page', $apiBudget, '/api/translations?per_page=50');
        $this->budget('list deep page (cursor)', $apiBudget, '/api/translations?per_page=50&cursor='
            .urlencode((new Cursor(['id' => 500], true))->encode()));
        $this->budget('search key prefix', $apiBudget, '/api/translations?key=g42.&locale=en');
        $this->budget('search key exact', $apiBudget, '/api/translations?key=g42.item_4242&key_mode=exact');
        $this->budget('search content (common)', $apiBudget, '/api/translations?content=sample&locale=fr');
        $this->budget('search content (rare)', $apiBudget, '/api/translations?content=content%2033332&locale=en');
        $this->budget('search by tag', $apiBudget, '/api/translations?tags=web&locale=es');
        $this->budget('search tags=all + group', $apiBudget, '/api/translations?tags=web,mobile&tag_mode=all&group=g07');
        $this->budget('show', $apiBudget, '/api/translations/'.$sample);

        Cache::flush();
        $cold = $this->timed(fn (): TestResponse => $this->getJson('/api/export/en'));
        $cold['response']->assertOk();
        $this->assertGreaterThan(30_000, (int) $cold['response']->json('count'));
        $this->record('export cold (flat)', $cold['ms'], $exportBudget);

        Cache::flush();
        $nested = $this->timed(fn (): TestResponse => $this->getJson('/api/export/fr?structure=nested'));
        $nested['response']->assertOk();
        $this->record('export cold (nested)', $nested['ms'], $exportBudget);

        $this->budget('export warm', $exportBudget, '/api/export/en');
        $etag = (string) $cold['response']->headers->get('ETag');
        $this->budget('export 304', $apiBudget, '/api/export/en', ['If-None-Match' => $etag], 304);

        $created = $this->timed(fn (): TestResponse => $this->postJson('/api/translations', [
            'key' => 'perf.created',
            'locale' => 'en',
            'content' => 'Created under load',
            'tags' => ['web'],
        ], $this->headers));
        $created['response']->assertCreated();
        $this->record('create', $created['ms'], $apiBudget);

        $updated = $this->timed(fn (): TestResponse => $this->putJson('/api/translations/'.$sample, [
            'content' => 'Updated under load',
        ], $this->headers));
        $updated['response']->assertOk();
        $this->record('update', $updated['ms'], $apiBudget);

        $fresh = $this->timed(fn (): TestResponse => $this->getJson('/api/export/en'));
        $fresh['response']->assertOk();
        $this->assertSame('Created under load', $fresh['response']->json('translations')['perf.created']);
        $this->record('export after write (rebuild)', $fresh['ms'], $exportBudget);

        $this->printReport();
    }

    /**
     * @param  array<string, string>  $extraHeaders
     */
    private function budget(string $label, float $budgetMs, string $uri, array $extraHeaders = [], int $status = 200): void
    {
        $headers = array_merge($this->headers, $extraHeaders);
        $this->getJson($uri, $headers)->assertStatus($status);

        $samples = [];
        for ($i = 0; $i < self::RUNS; $i++) {
            $run = $this->timed(fn (): TestResponse => $this->getJson($uri, $headers));
            $run['response']->assertStatus($status);
            $samples[] = $run['ms'];
        }

        sort($samples);
        $this->record($label, $samples[intdiv(count($samples), 2)], $budgetMs);
    }

    /**
     * @param  callable(): TestResponse  $request
     * @return array{response: TestResponse, ms: float}
     */
    private function timed(callable $request): array
    {
        $started = hrtime(true);
        $response = $request();

        return ['response' => $response, 'ms' => (hrtime(true) - $started) / 1_000_000];
    }

    private function record(string $label, float $ms, float $budgetMs): void
    {
        $this->report[$label] = $ms;
        $this->assertLessThan($budgetMs, $ms, sprintf('%s took %.1fms (budget %.0fms)', $label, $ms, $budgetMs));
    }

    private function printReport(): void
    {
        $lines = [sprintf("\n  %-32s %10s", 'endpoint ('.number_format(self::ROWS).' rows)', 'ms')];
        foreach ($this->report as $label => $ms) {
            $lines[] = sprintf('  %-32s %10.1f', $label, $ms);
        }

        fwrite(STDERR, implode("\n", $lines)."\n");
    }
}
