<?php

declare(strict_types=1);

return [
    'app_id' => env('CROCT_APP_ID'),
    'api_key' => env('CROCT_API_KEY'),
    'base_endpoint_url' => env('CROCT_BASE_ENDPOINT_URL'),
    // Lifetime in seconds of the issued visitor tokens.
    'token_duration' => (int) env('CROCT_TOKEN_DURATION', \Croct\Plug\Croct::DEFAULT_TOKEN_DURATION),
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
        // First-party path that serves the SDK. Set to null to inject the CDN script URL instead.
        'path' => '/_croct/plug.js',
        'script_url' => \Croct\Plug\CroctScript::DEFAULT_SCRIPT_URL,
        // How the loader is fetched: 'sync' (blocking, croct ready immediately), 'defer' or 'async'.
        'mode' => 'defer',
    ],
];
