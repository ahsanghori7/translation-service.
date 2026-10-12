<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ExportsTranslations;
use App\Exceptions\ApiException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BenchmarkExportCommand extends Command
{
    protected $signature = 'translations:benchmark {--locale=} {--assert}';

    protected $description = 'Measure JSON export time against the response budget';

    public function handle(ExportsTranslations $exports): int
    {
        $localeOption = $this->option('locale');
        $locales = is_string($localeOption) && $localeOption !== ''
            ? [$localeOption]
            : DB::table('languages')->where('is_active', true)->orderBy('code')->pluck('code')->all();

        if ($locales === []) {
            $this->warn('No active locales to benchmark.');

            return self::SUCCESS;
        }

        $budget = (int) config('translations.export_budget_ms');
        $failed = false;

        foreach ($locales as $locale) {
            try {
                $started = hrtime(true);
                $document = $exports->export((string) $locale, [], 'any');
                $elapsed = (hrtime(true) - $started) / 1_000_000;
            } catch (ApiException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->line(sprintf(
                'locale=%s count=%d time=%.2fms budget=%dms',
                $locale,
                $document->entryCount(),
                $elapsed,
                $budget,
            ));

            if ($this->option('assert') && $elapsed > $budget) {
                $this->error("Export for [{$locale}] exceeded {$budget}ms.");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
