<?php

$rawPurposes = (string) env('CUSTOMER_AUTH_ALLOWED_PURPOSES', 'VerifyEmail,VerifyPhone');
$allowedPurposes = array_values(array_filter(array_map('trim', explode(',', $rawPurposes)), fn ($v) => $v !== ''));

$allowedRoleIds = array_values(array_filter(array_map(
    static fn ($value) => (int) trim((string) $value),
    explode(',', (string) env('CUSTOMER_AUTH_ALLOWED_ROLE_IDS', '3'))
), static fn (int $value) => $value > 0));

$rawLegacyAllowedEnvironments = (string) env(
    'CUSTOMER_AUTH_LEGACY_USER_AUTH_TOKENS_ALLOWED_ENVIRONMENTS',
    'local,testing'
);

$legacyAllowedEnvironments = array_values(array_filter(array_map(
    static fn ($value) => trim((string) $value),
    explode(',', $rawLegacyAllowedEnvironments)
), static fn (string $value) => $value !== ''));

return [
    'enabled' => (bool) env('CUSTOMER_AUTH_ENABLED', true),
    'header' => (string) env('CUSTOMER_AUTH_HEADER', 'X-Customer-Token'),
    'allow_bearer' => (bool) env('CUSTOMER_AUTH_ALLOW_BEARER', false),

    // Dedicated access/session model for customer-authenticated self-service flows.
    'access_session_table' => (string) env('CUSTOMER_AUTH_ACCESS_SESSION_TABLE', 'customer_access_sessions'),
    'access_session_ttl_minutes' => max(1, (int) env('CUSTOMER_AUTH_ACCESS_SESSION_TTL_MINUTES', 60 * 24 * 14)),
    'touch_last_used_at' => (bool) env('CUSTOMER_AUTH_TOUCH_LAST_USED_AT', true),
    'login_throttle_limit' => max(1, (int) env('CUSTOMER_AUTH_LOGIN_THROTTLE_LIMIT', 10)),
    'login_throttle_window_seconds' => max(1, (int) env('CUSTOMER_AUTH_LOGIN_THROTTLE_WINDOW_SECONDS', 60)),
    'password_reset_ttl_minutes' => max(1, (int) env('CUSTOMER_AUTH_PASSWORD_RESET_TTL_MINUTES', 60)),
    'password_reset_debug_expose_token' => (bool) env('CUSTOMER_AUTH_PASSWORD_RESET_DEBUG_EXPOSE_TOKEN', false),
    'password_reset_url' => trim((string) env('CUSTOMER_AUTH_PASSWORD_RESET_URL', '')),
    'password_reset_throttle_limit' => max(1, (int) env('CUSTOMER_AUTH_PASSWORD_RESET_THROTTLE_LIMIT', 5)),
    'password_reset_throttle_window_seconds' => max(1, (int) env('CUSTOMER_AUTH_PASSWORD_RESET_THROTTLE_WINDOW_SECONDS', 900)),

    // Additional hardening for the legacy bridge path only.
    'allowed_purposes' => $allowedPurposes,
    'require_unused' => (bool) env('CUSTOMER_AUTH_REQUIRE_UNUSED', true),

    // SECURITY: user_auth_tokens is a verification/reset token table, not a dedicated
    // customer session/access-token table. Keep legacy auth-by-user_auth_tokens opt-in only.
    'allow_legacy_user_auth_tokens' => (bool) env('CUSTOMER_AUTH_ALLOW_LEGACY_USER_AUTH_TOKENS', false),

    // Only allow legacy token auth in explicitly permitted environments.
    'legacy_user_auth_tokens_allowed_environments' => $legacyAllowedEnvironments,

    // Extra hardening: both dedicated access sessions and the legacy bridge path only allow
    // roles intended for customer-facing flows. Override CUSTOMER_AUTH_ALLOWED_ROLE_IDS if
    // your installation uses different role identifiers.
    'allowed_role_ids' => $allowedRoleIds,

    // Source of truth for which shared customer/staff surfaces may fall back to session-bound
    // customer access when no owner or staff actor is present.
    'session_bound_route_contracts' => [
        'App\Http\Controllers\Api\ReservationController@store' => [
            'require_owned_hold' => true,
        ],
        'App\Http\Controllers\Api\ReservationController@show' => [],
        'App\Http\Controllers\Api\CustomerReservationSelfServiceController@index' => [],
        'App\Http\Controllers\Api\CustomerReservationSelfServiceController@cancel' => [],
        'App\Http\Controllers\Api\CustomerReservationSelfServiceController@reschedule' => [],
        'App\Http\Controllers\Api\CustomerReservationPreorderController@show' => [],
        'App\Http\Controllers\Api\CustomerReservationPreorderController@preview' => [],
        'App\Http\Controllers\Api\CustomerReservationPreorderController@replace' => [],
        'App\Http\Controllers\Api\CustomerReservationPreorderController@clear' => [],
        'App\Http\Controllers\Api\CustomerReservationDepositController@show' => [],
        'App\Http\Controllers\Api\CustomerReservationDepositController@acknowledge' => [],
        'App\Http\Controllers\Api\CustomerReservationDepositController@submitIntent' => [],
        'App\Http\Controllers\Api\CustomerReservationDepositController@revokeIntent' => [],
        'App\Http\Controllers\Api\CustomerReservationDepositPaymentController@store' => [],
        'App\Http\Controllers\Api\CustomerReservationDepositPaymentController@show' => [],
        'App\Http\Controllers\Api\CustomerReservationDepositPaymentController@refresh' => [],
        'App\Http\Controllers\Api\CustomerReservationDepositPaymentController@confirm' => [],
        'App\Http\Controllers\Api\CustomerReservationOrderBillController@show' => [],
        'App\Http\Controllers\Api\CustomerReservationOrderBillController@activeOrder' => [],
        'App\Http\Controllers\Api\CustomerReservationOrderBillController@billPreview' => [],
        'App\Http\Controllers\Api\CustomerReservationBillPaymentController@store' => [],
        'App\Http\Controllers\Api\CustomerReservationBillPaymentController@show' => [],
        'App\Http\Controllers\Api\CustomerReservationBillPaymentController@refresh' => [],
        'App\Http\Controllers\Api\CustomerReservationBillPaymentController@confirm' => [],
    ],
];
