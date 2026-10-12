<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\RevisionStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PopulateTranslationsCommand extends Command
{
    protected $signature = 'translations:populate {--count=100000} {--chunk=500} {--fresh}';

    protected $description = 'Insert a large translation dataset for scalability checks';

    public function handle(RevisionStore $revisions): int
    {
        $count = (int) $this->option('count');
        $chunk = (int) $this->option('chunk');
        if ($count < 1 || $count > 5_000_000 || $chunk < 50 || $chunk > 2000) {
            $this->error('Count must be between 1 and 5000000 and chunk between 50 and 2000.');

            return self::FAILURE;
        }

        if (DB::table('translation_keys')->exists() && ! $this->option('fresh')) {
            $this->error('Translation keys already exist. Re-run with --fresh to replace them.');

            return self::FAILURE;
        }

        DB::disableQueryLog();
        $started = hrtime(true);

        if ($this->option('fresh')) {
            $this->purge();
        }

        $now = now()->toDateTimeString();
        $locales = [
            $this->ensureLanguage('en', 'English', $now),
            $this->ensureLanguage('fr', 'French', $now),
            $this->ensureLanguage('es', 'Spanish', $now),
        ];
        $tagIds = [
            $this->ensureTag('mobile', $now),
            $this->ensureTag('desktop', $now),
            $this->ensureTag('web', $now),
        ];

        $remaining = $count;
        $sequence = 0;
        $localeIndex = 0;
        $inserted = 0;
        $localeCount = count($locales);

        while ($remaining > 0) {
            $planned = [];
            while ($remaining > 0 && $this->plannedCount($planned) < $chunk) {
                $rows = [];
                $added = 0;
                while (
                    $remaining > 0
                    && $added < $localeCount
                    && $this->plannedCount($planned) + count($rows) < $chunk
                ) {
                    $rows[] = $locales[$localeIndex % $localeCount];
                    $localeIndex++;
                    $remaining--;
                    $added++;
                }

                if ($rows === []) {
                    break;
                }

                $planned[$sequence] = $rows;
                $sequence++;
            }

            $this->insertBatch($planned, $tagIds);
            $inserted += $this->plannedCount($planned);
            $this->line("Inserted {$inserted} / {$count}");
        }

        $revisions->bump();
        $ms = (hrtime(true) - $started) / 1_000_000;
        $this->info(sprintf('Inserted %d translations in %.2fms.', $count, $ms));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, list<array{id: int, code: string}>>  $planned
     */
    private function plannedCount(array $planned): int
    {
        $total = 0;
        foreach ($planned as $rows) {
            $total += count($rows);
        }

        return $total;
    }

    /**
     * @param  array<int, list<array{id: int, code: string}>>  $planned
     * @param  list<int>  $tagIds
     */
    private function insertBatch(array $planned, array $tagIds): void
    {
        $now = now()->toDateTimeString();
        $keyRows = [];
        foreach ($planned as $sequence => $rows) {
            $group = $this->groupName($sequence);
            $keyRows[] = [
                'message_key' => $group.'.item_'.$sequence,
                'key_group' => $group,
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $lastId = (int) DB::table('translation_keys')->max('id');
        $this->insertChunks('translation_keys', $keyRows);

        $idByKey = [];
        $insertedKeys = DB::table('translation_keys')
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->get(['id', 'message_key']);
        foreach ($insertedKeys as $row) {
            $idByKey[(string) $row->message_key] = (int) $row->id;
        }

        $translations = [];
        $pivots = [];
        $tagCount = count($tagIds);
        foreach ($planned as $sequence => $rows) {
            $group = $this->groupName($sequence);
            $keyName = $group.'.item_'.$sequence;
            $keyId = $idByKey[$keyName];
            $pivots[] = [
                'translation_key_id' => $keyId,
                'tag_id' => $tagIds[$sequence % $tagCount],
            ];
            foreach ($rows as $row) {
                $translations[] = [
                    'translation_key_id' => $keyId,
                    'language_id' => $row['id'],
                    'content' => $row['code'].' sample content '.$sequence,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertChunks('translations', $translations);
        $this->insertChunks('translation_key_tag', $pivots);
    }

    private function groupName(int $sequence): string
    {
        return 'g'.str_pad((string) ($sequence % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * @return array{id: int, code: string}
     */
    private function ensureLanguage(string $code, string $name, string $now): array
    {
        $id = DB::table('languages')->where('code', $code)->value('id');
        if ($id === null) {
            $id = DB::table('languages')->insertGetId([
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('languages')->where('id', $id)->update([
                'is_active' => true,
                'updated_at' => $now,
            ]);
        }

        return ['id' => (int) $id, 'code' => $code];
    }

    private function ensureTag(string $slug, string $now): int
    {
        $id = DB::table('tags')->where('slug', $slug)->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table('tags')->insertGetId([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function purge(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('translation_key_tag')->delete();
        DB::table('translations')->delete();
        DB::table('translation_keys')->delete();
        Schema::enableForeignKeyConstraints();
    }
}
