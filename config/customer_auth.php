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
    'jwt_secret' => (string) env('CUSTOMER_AUTH_JWT_SECRET', ''),

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
        'App\Modules\Reservations\Http\Controllers\Customer\ReservationController@store' => [
            'require_owned_hold' => true,
        ],
        'App\Modules\Reservations\Http\Controllers\Customer\ReservationController@show' => [],
        'App\Modules\Reservations\Http\Controllers\Customer\ReservationSelfServiceController@index' => [],
        'App\Modules\Reservations\Http\Controllers\Customer\ReservationSelfServiceController@cancel' => [],
        'App\Modules\Reservations\Http\Controllers\Customer\ReservationSelfServiceController@reschedule' => [],
        'App\Modules\Reservations\Http\Controllers\CustomerReservationPreorderController@show' => [],
        'App\Modules\Reservations\Http\Controllers\CustomerReservationPreorderController@preview' => [],
        'App\Modules\Reservations\Http\Controllers\CustomerReservationPreorderController@replace' => [],
        'App\Modules\Reservations\Http\Controllers\CustomerReservationPreorderController@clear' => [],
        'App\Modules\Reservations\Http\Controllers\CustomerReservationDepositController@show' => [],
        'App\Modules\Reservations\Http\Controllers\CustomerReservationDepositController@acknowledge' => [],
        'App\Modules\Reservations\Http\Controllers\CustomerReservationDepositController@submitIntent' => [],
        'App\Modules\Reservations\Http\Controllers\CustomerReservationDepositController@revokeIntent' => [],
        'App\Modules\Payments\Http\Controllers\Customer\ReservationDepositPaymentController@store' => [],
        'App\Modules\Payments\Http\Controllers\Customer\ReservationDepositPaymentController@show' => [],
        'App\Modules\Payments\Http\Controllers\Customer\ReservationDepositPaymentController@refresh' => [],
        'App\Modules\Payments\Http\Controllers\Customer\ReservationDepositPaymentController@confirm' => [],
        'App\Modules\Billing\Http\Controllers\Customer\ReservationBillController@show' => [],
        'App\Modules\Billing\Http\Controllers\Customer\ReservationBillController@activeOrder' => [],
        'App\Modules\Billing\Http\Controllers\Customer\ReservationBillController@billPreview' => [],
        'App\Modules\Payments\Http\Controllers\Customer\ReservationBillPaymentController@store' => [],
        'App\Modules\Payments\Http\Controllers\Customer\ReservationBillPaymentController@show' => [],
        'App\Modules\Payments\Http\Controllers\Customer\ReservationBillPaymentController@refresh' => [],
        'App\Modules\Payments\Http\Controllers\Customer\ReservationBillPaymentController@confirm' => [],
    ],
];
