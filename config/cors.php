<?php

declare(strict_types=1);

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'ETag',
        'Cache-Control',
        'Cache-Tag',
        'CDN-Cache-Control',
        'Surrogate-Key',
        'X-Translation-Version',
    ],

    'max_age' => 0,

    'supports_credentials' => false,

];
