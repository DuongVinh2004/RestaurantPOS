<?php

$rawKeyMap = env('STAFF_API_KEYS_JSON', '{}');
$decodedKeyMap = json_decode((string) $rawKeyMap, true);
$apiKeys = is_array($decodedKeyMap) ? $decodedKeyMap : [];

$allowedRoleIds = array_values(array_filter(array_map(
    static fn ($value) => (int) trim((string) $value),
    explode(',', (string) env('STAFF_ALLOWED_ROLE_IDS', '1,2'))
), static fn (int $value) => $value > 0));

return [
    /*
    |--------------------------------------------------------------------------
    | Staff API authentication
    |--------------------------------------------------------------------------
    | Recommended: prefer database-backed hashed credentials stored in
    | staff_api_keys. Env-backed keys are retained only as an explicit,
    | tightly-controlled fallback for local/testing or legacy rollout.
    */
    'database_store_enabled' => (bool) env('STAFF_AUTH_DATABASE_STORE_ENABLED', true),
    'touch_last_used_at' => (bool) env('STAFF_AUTH_TOUCH_LAST_USED_AT', true),
    'session_ttl_minutes' => max(1, (int) env('STAFF_AUTH_SESSION_TTL_MINUTES', 720)),
    'login_throttle_limit' => max(1, (int) env('STAFF_AUTH_LOGIN_THROTTLE_LIMIT', 10)),
    'login_throttle_window_seconds' => max(1, (int) env('STAFF_AUTH_LOGIN_THROTTLE_WINDOW_SECONDS', 60)),
    'api_keys' => $apiKeys,
    'legacy_key' => (string) env('STAFF_API_KEY', ''),
    'legacy_user_id' => (int) env('STAFF_API_KEY_USER_ID', 0),
    'allow_bearer' => (bool) env('STAFF_AUTH_ALLOW_BEARER', false),
    'allow_env_fallback' => (bool) env('STAFF_AUTH_ALLOW_ENV_FALLBACK', false),
    'allow_env_fallback_when_database_store_unavailable' => (bool) env('STAFF_AUTH_ALLOW_ENV_FALLBACK_WHEN_DATABASE_STORE_UNAVAILABLE', false),
    'env_fallback_allowed_environments' => array_values(array_filter(array_map('trim', explode(',', (string) env('STAFF_AUTH_ENV_FALLBACK_ALLOWED_ENVIRONMENTS', 'local,testing'))))),
    'production_like_environments' => array_values(array_filter(array_map('trim', explode(',', (string) env('STAFF_AUTH_PRODUCTION_LIKE_ENVIRONMENTS', 'production'))))),
    'deny_env_fallback_in_production_like' => (bool) env('STAFF_AUTH_DENY_ENV_FALLBACK_IN_PRODUCTION_LIKE', true),
    'deny_role_name_fallback_in_production_like' => (bool) env('STAFF_AUTH_DENY_ROLE_NAME_FALLBACK_IN_PRODUCTION_LIKE', true),
    // Default role ids 1,2 assume the canonical Admin/Staff reference roles on a clean bootstrap.
    // Override STAFF_ALLOWED_ROLE_IDS when an existing installation uses different identifiers.
    'allowed_role_ids' => $allowedRoleIds,
    'allow_role_name_fallback' => (bool) env('STAFF_AUTH_ALLOW_ROLE_NAME_FALLBACK', false),
    // Deprecated fallback: disabled by default; enable only for controlled legacy rollout.
    'allowed_role_names' => array_values(array_filter(array_map('trim', explode(',', (string) env('STAFF_ALLOWED_ROLE_NAMES', 'Admin,Staff'))))),
];
