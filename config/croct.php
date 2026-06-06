<?php

declare(strict_types=1);

return [
    'app_id' => env('CROCT_APP_ID'),
    'api_key' => env('CROCT_API_KEY'),
    'base_endpoint_url' => env('CROCT_BASE_ENDPOINT_URL'),
    'locale' => [
        // Detects the visitor locale from the application locale.
        'enabled' => true,
        // Locale used as the override when detection is on, or the fixed value when off.
        'default' => null,
    ],
    'cookie' => [
        'domain' => env('CROCT_COOKIE_DOMAIN'),
        'secure' => true,
        'same_site' => 'none',
    ],
    'script' => [
        // Injects the client-side SDK bootstrap into HTML responses.
        'auto_inject' => true,
        'placement' => 'head',
        'loader_url' => 'https://cdn.croct.io/js/v1/lib/plug.js',
    ],
];
