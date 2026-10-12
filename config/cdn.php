<?php

declare(strict_types=1);

return [

    'enabled' => filter_var(env('CDN_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'url' => env('CDN_URL'),

    'ttl' => (int) env('CDN_TTL', 0),

    'cloudflare' => [
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
    ],

];
