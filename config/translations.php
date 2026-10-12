<?php

declare(strict_types=1);

return [

    'token_ttl_days' => (int) env('API_TOKEN_TTL_DAYS', 30),

    'export_requires_auth' => filter_var(env('EXPORT_REQUIRES_AUTH', false), FILTER_VALIDATE_BOOLEAN),

    'export_cache_ttl' => (int) env('EXPORT_CACHE_TTL', 3600),

    'export_budget_ms' => (int) env('EXPORT_BUDGET_MS', 500),

    'fulltext_min_length' => (int) env('FULLTEXT_MIN_LENGTH', 3),

    'per_page' => 15,

    'per_page_max' => 100,

    'api_rate_limit' => (int) env('API_RATE_LIMIT', 300),

    'auth_rate_limit' => (int) env('AUTH_RATE_LIMIT', 20),

    'export_rate_limit' => (int) env('EXPORT_RATE_LIMIT', 600),

    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
        // Required in production; local/testing fall back to "password".
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
    ],

];
