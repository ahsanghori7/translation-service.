<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ExportRepository;
use App\Contracts\ExportsTranslations;
use App\Contracts\LanguageRepository;
use App\Contracts\RevisionStore;
use App\Contracts\TagRepository;
use App\Data\ExportDocument;
use App\Data\ExportScope;
use App\Exceptions\InactiveLocaleException;
use App\Exceptions\LocaleNotFoundException;
use App\Support\NestedMessageTree;
use Illuminate\Support\Facades\Cache;

final class TranslationExporter implements ExportsTranslations
{
    public function __construct(
        private readonly LanguageRepository $languages,
        private readonly TagRepository $tags,
        private readonly ExportRepository $exports,
        private readonly RevisionStore $revisions,
        private readonly NestedMessageTree $tree,
    ) {
    }

    public function scope(string $locale, array $tags, string $tagMode): ExportScope
    {
        $language = $this->languages->findByCode($locale);
        if ($language === null) {
            throw new LocaleNotFoundException($locale);
        }

        if (! $language->is_active) {
            throw new InactiveLocaleException($locale);
        }

        $slugs = array_values(array_unique($tags));
        sort($slugs);

        $tagIds = [];
        $matchesNothing = false;
        if ($slugs !== []) {
            $found = $this->tags->findBySlugs($slugs);
            $matchesNothing = $found->count() !== count($slugs);
            $tagIds = $found
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->sort()
                ->values()
                ->all();
        }

        return new ExportScope(
            locale: $language->code,
            languageId: (int) $language->id,
            tagIds: $tagIds,
            tagMode: $tagMode,
            fingerprint: substr(hash('sha256', $tagMode.'|'.implode(',', $slugs)), 0, 16),
            version: $this->revisions->current(),
            matchesNothing: $matchesNothing,
        );
    }

    public function render(ExportScope $scope, string $structure = 'flat'): ExportDocument
    {
        $structure = $structure === 'nested' ? 'nested' : 'flat';

        if ($scope->matchesNothing) {
            return $this->makeDocument($scope, [], $structure);
        }

        $cacheKey = 'translations.export.'.$scope->languageId.'.'.$scope->fingerprint.'.'.$structure;
        $cached = Cache::get($cacheKey);
        if ($this->isCachedBody($cached) && (int) $cached['version'] === $scope->version) {
            return ExportDocument::fromArray($cached);
        }

        $translations = [];
        foreach ($this->exports->cursor($scope->languageId, $scope->tagIds, $scope->tagMode) as $key => $content) {
            $translations[$key] = $content;
        }

        $document = $this->makeDocument($scope, $translations, $structure);

        Cache::put(
            $cacheKey,
            $document->toCacheArray(),
            now()->addSeconds(max(1, (int) config('translations.export_cache_ttl'))),
        );

        return $document;
    }

    public function export(string $locale, array $tags, string $tagMode, string $structure = 'flat'): ExportDocument
    {
        return $this->render($this->scope($locale, $tags, $tagMode), $structure);
    }

    /**
     * @param  array<string, string>  $translations
     */
    private function makeDocument(ExportScope $scope, array $translations, string $structure): ExportDocument
    {
        $generatedAt = now()->toIso8601String();
        $count = count($translations);
        $payload = $structure === 'nested'
            ? $this->tree->nest($translations)
            : $translations;

        $body = json_encode([
            'locale' => $scope->locale,
            'version' => $scope->version,
            'generated_at' => $generatedAt,
            'count' => $count,
            'translations' => $payload === [] ? (object) [] : $payload,
        ], JSON_THROW_ON_ERROR);

        return ExportDocument::withBody(
            locale: $scope->locale,
            version: $scope->version,
            generatedAt: $generatedAt,
            fingerprint: $scope->fingerprint,
            body: $body,
            count: $count,
        );
    }

    /**
     * @phpstan-assert-if-true array{locale: string, version: int|string, generated_at: string, fingerprint: string, count: int|string, body: string} $cached
     */
    private function isCachedBody(mixed $cached): bool
    {
        return is_array($cached)
            && is_string($cached['body'] ?? null)
            && is_string($cached['locale'] ?? null)
            && is_string($cached['generated_at'] ?? null)
            && is_string($cached['fingerprint'] ?? null)
            && isset($cached['version'], $cached['count']);
    }
}
