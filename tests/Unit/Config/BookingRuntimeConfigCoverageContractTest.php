<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

final class BookingRuntimeConfigCoverageContractTest extends TestCase
{
    public function test_booking_config_declares_every_current_hot_path_key_used_by_source(): void
    {
        $this->assertConfigPathsExist([
            'admin_inventory_page_default',
            'admin_inventory_page_max',
            'branch_policy_defaults.booking_policy',
            'branch_policy_defaults.business_hours',
            'branch_policy_defaults.closure_windows',
            'check_in_grace_minutes',
            'customer_bill_payment_default_provider',
            'customer_bill_payment_simulated_session_ttl_minutes',
            'customer_deposit_payment_default_provider',
            'customer_deposit_payment_simulated_session_ttl_minutes',
            'customer_menu_page_default',
            'customer_menu_page_max',
            'customer_preorder_management_cutoff_minutes',
            'customer_reservation_cancellation_cutoff_minutes',
            'customer_reservation_reschedule_cutoff_minutes',
            'customer_reservation_self_service_page_default',
            'customer_reservation_self_service_page_max',
            'customer_session_exact_link_access_hours',
            'customer_session_legacy_access_hours',
            'customer_waiting_list_page_default',
            'customer_waiting_list_page_max',
            'database_contract.enforce_supported_driver',
            'database_contract.supported_drivers',
            'expire_reservation_grace_minutes',
            'expose_hold_user_id',
            'expose_session_id',
            'finance_tax_invoice_profile',
            'hold_default_minutes',
            'hold_max_duration_minutes',
            'hold_max_total_minutes',
            'hold_rate_limit_per_minute',
            'hold_rate_limit_window_seconds',
            'idempotency_lock_seconds',
            'idempotency_pending_seconds',
            'idempotency_required_for_reservations',
            'idempotency_required_scopes',
            'idempotency_route_aliases',
            'idempotency_ttl_hours',
            'loyalty_earn_amount_per_point',
            'loyalty_enabled',
            'loyalty_min_redeem_points',
            'loyalty_redeem_amount_per_point',
            'metrics_enabled',
            'metrics_sample_rate',
            'multi_branch.default_branch_code',
            'multi_branch.default_branch_currency',
            'multi_branch.default_branch_name',
            'multi_branch.default_branch_timezone',
            'no_show_grace_minutes',
            'ops.alerts.timeout_seconds',
            'ops.alerts.webhook_url',
            'ops.payment_over_refund_fail_count',
            'ops.refund_without_source_fail_count',
            'ops.reporting_snapshot_stale_hours',
            'ops.row_version_contract_missing_required_fail_count',
            'ops.staff_api_keys_expiring_soon_days',
            'ops.staff_api_keys_missing_active_fail_count',
            'ops.staff_api_keys_never_used_warn_count',
            'ops.stale_voucher_lock_warn_count',
            'ops.table_state_audit_missing_actor_warn_count',
            'ops.table_state_audit_missing_context_warn_count',
            'ops.table_state_audit_recent_window_hours',
            'ops.unlinked_session_hold_warn_count',
            'payment_providers.default_provider',
            'payment_providers.observability.applied_level',
            'payment_providers.observability.duplicate_level',
            'payment_providers.observability.enabled',
            'payment_providers.observability.failed_level',
            'payment_providers.observability.ignored_level',
            'payment_providers.observability.log_channel',
            'payment_providers.providers.generic_http_hmac.connect_timeout_seconds',
            'payment_providers.providers.generic_http_hmac.confirm_endpoint',
            'payment_providers.providers.generic_http_hmac',
            'payment_providers.providers.generic_http_hmac.endpoints.confirm',
            'payment_providers.providers.generic_http_hmac.endpoints.create',
            'payment_providers.providers.generic_http_hmac.endpoints.refresh',
            'payment_providers.providers.generic_http_hmac.enabled',
            'payment_providers.providers.generic_http_hmac.mode',
            'payment_providers.providers.generic_http_hmac.request.idempotency_header',
            'payment_providers.providers.generic_http_hmac.retry.attempts',
            'payment_providers.providers.generic_http_hmac.retry.sleep_ms',
            'payment_providers.providers.generic_http_hmac.webhook.max_age_seconds',
            'payment_providers.providers.generic_http_hmac.webhook.timestamp_header',
            'payment_providers.providers.simulated.bill.session_ttl_minutes',
            'payment_providers.providers.simulated.deposit.session_ttl_minutes',
            'payment_providers.providers.simulated.enabled',
            'payment_providers.providers.simulated.enforce_signature',
            'payment_providers.providers.simulated.mode',
            'payment_providers.providers.simulated.webhook.secret',
            'payment_providers.providers.simulated.webhook_secret',
            'payment_providers.scopes.bill.default_provider',
            'payment_providers.scopes.deposit.default_provider',
            'payment_providers.webhook.signature_header',
            'payment_providers.webhook.timestamp_header',
            'payment_providers.webhook.max_age_seconds',
            'realtime.cache_store',
            'realtime.enabled',
            'realtime.event_ttl_seconds',
            'realtime.poll_hint_ms',
            'realtime.recent_event_limit',
            'reporting_page_default',
            'reporting_page_max',
            'reporting_snapshot_rebuild_max_days',
            'require_redis_for_booking_api',
            'reservation_code_max_attempts',
            'reservation_code_prefix',
            'reservation_code_random_len',
            'reservation_lock_prefix',
            'reservation_lock_reservation_prefix',
            'reservation_lock_ttl_seconds',
            'reservation_lock_wait_seconds',
            'scheduler_heartbeat_stale_seconds',
            'scheduler_heartbeat_ttl_seconds',
            'service_buffer_minutes',
            'staff_table_board_candidate_preview_limit',
            'staff_table_board_close_fit_max_extra_seats',
            'testing.fail_fast_on_missing_schema',
            'throttle_reservations_show_limit',
            'throttle_reservations_show_window',
            'throttle_reservations_status_limit',
            'throttle_reservations_status_window',
            'throttle_reservations_store_limit',
            'throttle_reservations_store_window',
            'throttle_staff_limit',
            'throttle_staff_window',
            'throttle_table_holds_store_limit',
            'throttle_table_holds_store_window',
            'throttle_tables_available_limit',
            'throttle_tables_available_window',
            'voucher_lock_minutes',
            'waiting_list_notify_hold_minutes',
            'waiting_list_service_minutes',
        ]);
    }

    public function test_env_example_documents_booking_runtime_knobs_across_each_contract_block(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));
        self::assertNotFalse($envExample);

        foreach ([
            'APP_TIMEZONE',
            'AUDIT_HASH_KEY',
            'AUDIT_FAILURE_ALERT_CHANNEL',
            'AUDIT_ALERT_LOG_STACK',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'MYSQL_BIN',
            'MYSQLD_BIN',
            'SESSION_DRIVER',
            'SESSION_CONNECTION',
            'SESSION_STORE',
            'SESSION_SECURE_COOKIE',
            'QUEUE_CONNECTION',
            'QUEUE_FAILED_DRIVER',
            'CACHE_STORE',
            'REDIS_CLIENT',
            'REDIS_HOST',
            'REDIS_PORT',
            'REDIS_DB',
            'REDIS_CACHE_DB',
            'IDEMPOTENCY_LOCK_SECONDS',
            'IDEMPOTENCY_PENDING_SECONDS',
            'REQUIRE_REDIS_FOR_BOOKING_API',
            'STAFF_AUTH_DATABASE_STORE_ENABLED',
            'STAFF_AUTH_ALLOW_ENV_FALLBACK',
            'STAFF_AUTH_ALLOW_ROLE_NAME_FALLBACK',
            'STAFF_AUTH_DENY_ENV_FALLBACK_IN_PRODUCTION_LIKE',
            'STAFF_AUTH_DENY_ROLE_NAME_FALLBACK_IN_PRODUCTION_LIKE',
            'STAFF_API_KEYS_JSON',
            'STAFF_API_KEY',
            'CUSTOMER_AUTH_JWT_SECRET',
            'BOOKING_CUSTOMER_MENU_PAGE_DEFAULT',
            'BOOKING_CUSTOMER_RESERVATION_SELF_SERVICE_PAGE_DEFAULT',
            'BOOKING_CUSTOMER_WAITING_LIST_PAGE_DEFAULT',
            'BOOKING_REALTIME_ENABLED',
            'BOOKING_REALTIME_CACHE_STORE',
            'BOOKING_REPORTING_SNAPSHOT_REBUILD_MAX_DAYS',
            'BOOKING_DEFAULT_BRANCH_CODE',
            'BOOKING_ADMIN_INVENTORY_PAGE_DEFAULT',
            'BOOKING_FINANCE_TAX_CODE',
            'PAYMENT_PROVIDER_DEFAULT',
            'PAYMENT_PROVIDER_WEBHOOK_TIMESTAMP_HEADER',
            'PAYMENT_PROVIDER_WEBHOOK_MAX_AGE_SECONDS',
            'PAYMENT_PROVIDER_OBSERVABILITY_LOG_CHANNEL',
            'PAYMENT_PROVIDER_SIMULATED_ENABLED',
            'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_ENABLED',
            'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_MODE',
            'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REFRESH_ENDPOINT',
            'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_CONFIRM_ENDPOINT',
            'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_CONNECT_TIMEOUT_SECONDS',
            'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_RETRY_ATTEMPTS',
            'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REQUEST_IDEMPOTENCY_HEADER',
            'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_TIMESTAMP_HEADER',
            'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_MAX_AGE_SECONDS',
            'STAFF_TABLE_BOARD_CLOSE_FIT_MAX_EXTRA_SEATS',
            'STAFF_TABLE_BOARD_CANDIDATE_PREVIEW_LIMIT',
            'OPS_REPORTING_SNAPSHOT_STALE_HOURS',
            'OPS_ALERTS_WEBHOOK_URL',
            'OPS_ALERTS_TIMEOUT_SECONDS',
            'WAITING_LIST_NOTIFY_HOLD_MINUTES',
        ] as $envName) {
            self::assertStringContainsString($envName.'=', $envExample, $envName);
        }

        foreach ([
            'Runtime gate database.',
            'Runtime gates always probe Redis',
            'Staff auth production safety.',
            'Customer JWT auth',
        ] as $expectedDocumentation) {
            self::assertStringContainsString($expectedDocumentation, $envExample);
        }
    }

    public function test_local_like_runtime_defaults_do_not_self_throttle_the_local_performance_lane(): void
    {
        self::assertGreaterThanOrEqual(1200, (int) config('booking.throttle_tables_available_limit'));
        self::assertGreaterThanOrEqual(1200, (int) config('booking.throttle_reservations_show_limit'));
        self::assertGreaterThanOrEqual(1200, (int) config('booking.throttle_staff_limit'));
        self::assertGreaterThanOrEqual(120, (int) config('booking.throttle_table_holds_store_limit'));
        self::assertGreaterThanOrEqual(120, (int) config('booking.throttle_reservations_store_limit'));
        self::assertGreaterThanOrEqual(120, (int) config('booking.throttle_reservations_status_limit'));
    }

    /**
     * @param  list<string>  $paths
     */
    private function assertConfigPathsExist(array $paths): void
    {
        foreach ($paths as $path) {
            self::assertTrue(config()->has('booking.'.$path), $path);
        }
    }
}
