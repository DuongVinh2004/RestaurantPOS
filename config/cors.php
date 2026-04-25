<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Explicit CORS contract for the split frontend architecture:
    |   - customer-web (Next.js + TypeScript)
    |   - staff-web    (React + TypeScript + Vite)
    |
    | Origins are env-driven. In production, only explicitly listed origins
    | are allowed. An empty CORS_ALLOWED_ORIGINS denies all cross-origin
    | requests (same-origin only).
    |
    | The default API contract uses header-based authentication
    | (X-Customer-Token, X-Staff-Key), so supports_credentials is false.
    | Staff browser refresh cookies are opt-in only; when enabled, credentials
    | are still constrained to exact origins from CORS_ALLOWED_ORIGINS.
    |
    | See: docs/runbooks/api-consumer-artifacts.md, section "Cross-Origin (CORS)"
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    'allowed_origins' => array_filter(
        array_map(
            'trim',
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
        )
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Accept',
        'Authorization',
        'X-Customer-Token',
        'X-Staff-Key',
        'X-Staff-CSRF',
        'X-Session-Id',
        'Idempotency-Key',
        'X-Idempotency-Key',
        'X-Request-Id',
        'X-Requested-With',
    ],

    'exposed_headers' => [
        'X-Request-Id',
    ],

    'max_age' => 7200,

    'supports_credentials' => (bool) env(
        'CORS_SUPPORTS_CREDENTIALS',
        (bool) env('STAFF_AUTH_BROWSER_SESSION_COOKIE_ENABLED', false)
    ),

];
