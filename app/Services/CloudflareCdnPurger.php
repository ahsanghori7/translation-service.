<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CdnPurger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CloudflareCdnPurger implements CdnPurger
{
    public function purge(): void
    {
        if (config('cdn.enabled') !== true) {
            return;
        }

        $zone = config('cdn.cloudflare.zone_id');
        $token = config('cdn.cloudflare.api_token');
        if (! is_string($zone) || $zone === '' || ! is_string($token) || $token === '') {
            return;
        }

        try {
            $response = Http::timeout(2)
                ->withToken($token)
                ->acceptJson()
                ->post("https://api.cloudflare.com/client/v4/zones/{$zone}/purge_cache", [
                    'tags' => ['translations'],
                ]);

            if (! $response->successful()) {
                Log::warning('CDN purge failed.', ['status' => $response->status()]);
            }
        } catch (Throwable $exception) {
            Log::warning('CDN purge failed.', ['error' => $exception->getMessage()]);
        }
    }
}
