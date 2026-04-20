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
    | This API uses header-based authentication (X-Customer-Token, X-Staff-Key),
    | not cookie sessions, so supports_credentials is false.
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

    'supports_credentials' => false,

];
