<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\CdnCachePolicy;
use App\Contracts\CdnPurger;
use App\Contracts\ContentMatcher;
use App\Contracts\ExportRepository;
use App\Contracts\ExportsTranslations;
use App\Contracts\LanguageRepository;
use App\Contracts\ManagesTranslations;
use App\Contracts\RevisionStore;
use App\Contracts\SearchesTranslations;
use App\Contracts\TagRepository;
use App\Contracts\TokenManager;
use App\Contracts\TranslationRepository;
use App\Repositories\DatabaseRevisionStore;
use App\Repositories\EloquentLanguageRepository;
use App\Repositories\EloquentTagRepository;
use App\Repositories\EloquentTranslationRepository;
use App\Repositories\QueryExportRepository;
use App\Services\ApiTokenManager;
use App\Services\CloudflareCdnPurger;
use App\Services\ConfigurableCdnCachePolicy;
use App\Services\DriverContentMatcher;
use App\Services\TranslationExporter;
use App\Services\TranslationSearcher;
use App\Services\TranslationWriter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LanguageRepository::class, EloquentLanguageRepository::class);
        $this->app->bind(TagRepository::class, EloquentTagRepository::class);
        $this->app->bind(TranslationRepository::class, EloquentTranslationRepository::class);
        $this->app->bind(ExportRepository::class, QueryExportRepository::class);
        $this->app->bind(RevisionStore::class, DatabaseRevisionStore::class);
        $this->app->bind(TokenManager::class, ApiTokenManager::class);
        $this->app->bind(ContentMatcher::class, DriverContentMatcher::class);
        $this->app->bind(CdnPurger::class, CloudflareCdnPurger::class);
        $this->app->bind(CdnCachePolicy::class, ConfigurableCdnCachePolicy::class);
        $this->app->bind(ManagesTranslations::class, TranslationWriter::class);
        $this->app->bind(SearchesTranslations::class, TranslationSearcher::class);
        $this->app->bind(ExportsTranslations::class, TranslationExporter::class);
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute((int) config('translations.api_rate_limit'))->by((string) $request->ip());
        });

        RateLimiter::for('auth', function (Request $request): Limit {
            return Limit::perMinute((int) config('translations.auth_rate_limit'))->by((string) $request->ip());
        });

        RateLimiter::for('export', function (Request $request): Limit {
            return Limit::perMinute((int) config('translations.export_rate_limit'))->by((string) $request->ip());
        });
    }
}
