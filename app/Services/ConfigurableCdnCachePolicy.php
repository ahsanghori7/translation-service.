<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CdnCachePolicy;
use App\Data\ExportScope;
use Illuminate\Http\Request;

final class ConfigurableCdnCachePolicy implements CdnCachePolicy
{
    public function headers(ExportScope $scope, string $structure = 'flat'): array
    {
        $common = [
            'ETag' => $scope->etag($structure),
            'X-Translation-Version' => (string) $scope->version,
        ];

        if ($this->requiresAuth()) {
            return $common + [
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'CDN-Cache-Control' => 'no-store',
                'Vary' => 'Authorization, Accept-Encoding',
            ];
        }

        $ttl = max(0, (int) config('cdn.ttl'));
        $cacheControl = 'public, max-age='.$ttl.', must-revalidate';

        return $common + [
            'Cache-Control' => $cacheControl,
            'CDN-Cache-Control' => $cacheControl,
            'Cache-Tag' => 'translations,translation-'.$scope->locale,
            'Surrogate-Key' => 'translations translation-'.$scope->locale,
            'Vary' => 'Accept-Encoding',
        ];
    }

    public function publicUrl(Request $request): ?string
    {
        if ($this->requiresAuth()) {
            return null;
        }

        $base = config('cdn.url');
        if (! is_string($base) || $base === '') {
            return null;
        }

        return rtrim($base, '/').$request->getRequestUri();
    }

    private function requiresAuth(): bool
    {
        return config('translations.export_requires_auth') === true;
    }
}
