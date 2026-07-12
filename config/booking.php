<?php

$csvList = static function (string $value): array {
    return array_values(array_filter(
        array_map('trim', explode(',', $value)),
        static fn (string $item): bool => $item !== ''
    ));
};
$appEnvironment = (string) env('APP_ENV', 'production');
$isLocalLikeEnvironment = in_array($appEnvironment, ['local', 'testing'], true);
$defaultRealtimeCacheStore = $isLocalLikeEnvironment ? 'file' : 'redis';
$defaultPaymentProvider = (string) env(
    'PAYMENT_PROVIDER_DEFAULT',
    $isLocalLikeEnvironment ? 'simulated' : 'generic_http_hmac'
);
$defaultCustomerSelfPayEnabled = false;
$serviceBufferMinutes = (int) env('SERVICE_BUFFER_MINUTES', 0);
$customerReservationCancellationCutoffMinutes = (int) env('BOOKING_CUSTOMER_RESERVATION_CANCELLATION_CUTOFF_MINUTES', 30);
$customerReservationRescheduleCutoffMinutes = (int) env('BOOKING_CUSTOMER_RESERVATION_RESCHEDULE_CUTOFF_MINUTES', 120);
$waitingListNotifyHoldMinutes = (int) env('WAITING_LIST_NOTIFY_HOLD_MINUTES', 10);
$waitingListServiceMinutes = (int) env('WAITING_LIST_SERVICE_MINUTES', 120);

return [
    // Hold lifecycle and reservation expiration.
    'hold_default_minutes' => (int) env('BOOKING_HOLD_MINUTES', 5),
    'hold_max_total_minutes' => (int) env('BOOKING_HOLD_MAX_TOTAL_MINUTES', 15),
    'hold_max_duration_minutes' => (int) env('BOOKING_HOLD_MAX_DURATION_MINUTES', 240),
    'expire_reservation_grace_minutes' => (int) env('RESERVATION_EXPIRE_GRACE_MINUTES', 0),

    // Request throttles.
    'hold_rate_limit_per_minute' => (int) env('HOLD_RATE_LIMIT_PER_MINUTE', 20),
    'hold_rate_limit_window_seconds' => (int) env('HOLD_RATE_LIMIT_WINDOW_SECONDS', 60),
    'throttle_tables_available_limit' => (int) env('THROTTLE_TABLES_AVAILABLE_LIMIT', $isLocalLikeEnvironment ? 1200 : 60),
    'throttle_tables_available_window' => (int) env('THROTTLE_TABLES_AVAILABLE_WINDOW', 60),
    'throttle_table_holds_store_limit' => (int) env('THROTTLE_TABLE_HOLDS_STORE_LIMIT', $isLocalLikeEnvironment ? 120 : 5),
    'throttle_table_holds_store_window' => (int) env('THROTTLE_TABLE_HOLDS_STORE_WINDOW', 60),
    'throttle_reservations_store_limit' => (int) env('THROTTLE_RESERVATIONS_STORE_LIMIT', $isLocalLikeEnvironment ? 120 : 20),
    'throttle_reservations_store_window' => (int) env('THROTTLE_RESERVATIONS_STORE_WINDOW', 60),
    'throttle_reservations_show_limit' => (int) env('THROTTLE_RESERVATIONS_SHOW_LIMIT', $isLocalLikeEnvironment ? 1200 : 120),
    'throttle_reservations_show_window' => (int) env('THROTTLE_RESERVATIONS_SHOW_WINDOW', 60),
    'throttle_reservations_status_limit' => (int) env('THROTTLE_RESERVATIONS_STATUS_LIMIT', $isLocalLikeEnvironment ? 120 : 30),
    'throttle_reservations_status_window' => (int) env('THROTTLE_RESERVATIONS_STATUS_WINDOW', 60),
    'throttle_staff_limit' => (int) env('THROTTLE_STAFF_LIMIT', $isLocalLikeEnvironment ? 1200 : 300),
    'throttle_staff_window' => (int) env('THROTTLE_STAFF_WINDOW', 60),

    // Idempotency timing and scope contract.
    'idempotency_ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),
    'idempotency_lock_seconds' => (int) env('IDEMPOTENCY_LOCK_SECONDS', 120),
    'idempotency_pending_seconds' => (int) env('IDEMPOTENCY_PENDING_SECONDS', 300),
    'idempotency_required_for_reservations' => (bool) env('IDEMPOTENCY_REQUIRED_FOR_RESERVATIONS', true),
    'idempotency_required_scopes' => [
        'reservations',
        'table-holds',
        'table-holds.cancel',
        'table-holds.refresh',
        'customer.reservations.cancel',
        'customer.reservations.reschedule',
        'customer.reservations.preorder.replace',
        'customer.reservations.preorder.submit',
        'customer.reservations.preorder.clear',
        'customer.privacy-requests.store',
        'customer.favorites.store',
        'customer.favorites.destroy',
        'customer.favorites.sync',
        'customer.reservation-deposit.acknowledge',
        'customer.reservation-deposit.submit-intent',
        'customer.reservation-deposit.revoke-intent',
        'customer.reservation-deposit-payment-sessions.create',
        'customer.reservation-deposit-payment-sessions.refresh',
        'customer.reservation-deposit-payment-sessions.confirm',
        'customer.reservation-bill-payment-sessions.create',
        'customer.reservation-bill-payment-sessions.refresh',
        'customer.reservation-bill-payment-sessions.confirm',
        'customer.waiting-list.create',
        'customer.waiting-list.accept',
        'customer.waiting-list.decline',
        'customer.waiting-list.confirm-arrival',
        'customer.waiting-list.cancel',
        'staff.order-close',
        'staff.order-pay',
        'staff.checkout',
        'staff.reservation-refund',
        'staff.reservation-refund-cancel',
        'staff.reservation-checkin',
        'staff.service-sessions.walk-in',
        'staff.reservation-status',
        'staff.reservation-reschedule',
        'staff.reservation-preorder-confirm',
        'staff.reservation-preorder-reject',
        'staff.reservation-preorder-convert',
        'staff.reservation-move-table',
        'staff.table-release',
        'staff.waiting-list-create',
        'staff.waiting-list-notify',
        'staff.waiting-list-seat',
        'staff.waiting-list-cancel',
        'staff.waiting-list-advance',
        'staff.reservation-voucher-apply',
        'staff.reservation-voucher-remove',
        'staff.user-loyalty-adjust',
        'staff.reservation-loyalty-redeem',
        'staff.reservation-loyalty-release',
        'staff.table-orders',
        'staff.order-items',
        'staff.reservation-assign-table',
        'staff.reservation-assign-best-fit',
        'staff.reservation-deposit-pay',
        'staff.cashier-shift.open',
        'staff.cashier-shift.close',
        'staff.finance-invoice.issue',
        'staff.kitchen.dispatch',
        'staff.kitchen.fire',
        'staff.kitchen.bump',
        'staff.kitchen.recall',
        'staff.order-item.update',
        'staff.order-item.component-swap',
        'staff.order-item.status',
        'staff.conversation-assign',
        'staff.conversation-take-over',
        'staff.conversation-unassign',
        'staff.conversation-workflow-state',
        'staff.conversation-link',
        'staff.conversation-unlink-reservation',
        'staff.conversation-unlink-waiting-list',
        'staff.conversation-internal-note',
        'staff.conversation-outbound-reply',
        'admin.branches.import',
        'admin.tables.import',
        'admin.menu-categories.import',
        'admin.menu-categories.store',
        'admin.menu-categories.update',
        'admin.menu-items.import',
        'admin.menu-items.store',
        'admin.menu-items.update',
        'admin.prices.import',
        'admin.master-data.import',
        'admin.menu-item-prices.store',
        'admin.menu-item-prices.update',
        'admin.privacy-requests.review',
        'admin.benefits-vouchers.store',
        'admin.benefits-vouchers.update',
        'admin.loyalty-tiers.store',
        'admin.loyalty-tiers.update',
        'admin.benefit-settings.upsert',
        'customer.reservation-voucher.apply',
        'customer.reservation-voucher.remove',
        'customer.reservation-loyalty.redeem',
        'customer.reservation-loyalty.release',
        'admin.inventory-ingredients.store',
        'admin.inventory-ingredients.update',
        'admin.inventory-menu-item-recipe.sync',
        'admin.inventory-movements.store',
        'admin.inventory-suppliers.store',
        'admin.inventory-suppliers.update',
        'admin.inventory-purchase-orders.store',
        'admin.inventory-purchase-orders.update',
        'admin.inventory-purchase-order-receipts.store',
        'admin.kitchen-stations.store',
        'admin.kitchen-stations.update',
        'admin.kitchen-station-category-routes.sync',
        'admin.restaurant-zones.rename',
        'admin.restaurant-tables.store',
        'admin.restaurant-tables.update',
        'admin.restaurant-tables.delete',
        'admin.settings-branches.store',
        'admin.settings-branches.update',
        'admin.settings-finance-tax-profile.upsert',
        'admin.reporting-snapshots.rebuild',
    ],
    'idempotency_route_aliases' => [
        'v1/staff/orders/{order_id}/close' => 'v1/staff/orders/{order_id}/bill-snapshot',
        'v1/staff/orders/{order_id}/checkout' => 'v1/staff/orders/{order_id}/settlement/finalize',
        'v1/staff/reservations/{reservation_id}/voucher/release' => 'v1/staff/reservations/{reservation_id}/voucher/remove',
        'v1/staff/reservations/{reservation_id}/loyalty/release' => 'v1/staff/reservations/{reservation_id}/loyalty/redeem/release',
    ],
    'api_alias_deprecations' => [
        'observation_release_cycles' => 1,
        'audit_log_event' => 'api_deprecated_alias_used',
        'idempotency_compatibility_event' => 'idempotency_compatibility_key_used',
        'routes' => [
            [
                'key' => 'staff.orders.close',
                'canonical_route' => 'POST /api/v1/staff/orders/{order_id}/bill-snapshot',
                'deprecated_alias' => 'POST /api/v1/staff/orders/{order_id}/close',
                'removal_criteria' => 'Zero alias hits in production/staging audit logs for one release cycle and no frontend or integration source references.',
                'minimum_evidence' => [
                    'booking:route-gate alias_deprecation_plan includes this row',
                    'audit log query for api_deprecated_alias_used where alias_key=staff.orders.close returns zero hits for the release cycle',
                    'source scan confirms no client uses /api/v1/staff/orders/{order_id}/close',
                ],
            ],
            [
                'key' => 'staff.orders.checkout',
                'canonical_route' => 'POST /api/v1/staff/orders/{order_id}/settlement/finalize',
                'deprecated_alias' => 'POST /api/v1/staff/orders/{order_id}/checkout',
                'removal_criteria' => 'Zero alias hits in production/staging audit logs for one release cycle and no frontend or integration source references.',
                'minimum_evidence' => [
                    'booking:route-gate alias_deprecation_plan includes this row',
                    'audit log query for api_deprecated_alias_used where alias_key=staff.orders.checkout returns zero hits for the release cycle',
                    'source scan confirms no client uses /api/v1/staff/orders/{order_id}/checkout',
                ],
            ],
            [
                'key' => 'staff.reservations.voucher.release',
                'canonical_route' => 'POST /api/v1/staff/reservations/{reservation_id}/voucher/remove',
                'deprecated_alias' => 'POST /api/v1/staff/reservations/{reservation_id}/voucher/release',
                'removal_criteria' => 'Zero alias hits in production/staging audit logs for one release cycle and no frontend or integration source references.',
                'minimum_evidence' => [
                    'booking:route-gate alias_deprecation_plan includes this row',
                    'audit log query for api_deprecated_alias_used where alias_key=staff.reservations.voucher.release returns zero hits for the release cycle',
                    'source scan confirms no client uses /api/v1/staff/reservations/{reservation_id}/voucher/release',
                ],
            ],
            [
                'key' => 'staff.reservations.loyalty.release',
                'canonical_route' => 'POST /api/v1/staff/reservations/{reservation_id}/loyalty/redeem/release',
                'deprecated_alias' => 'POST /api/v1/staff/reservations/{reservation_id}/loyalty/release',
                'removal_criteria' => 'Zero alias hits in production/staging audit logs for one release cycle and no frontend or integration source references.',
                'minimum_evidence' => [
                    'booking:route-gate alias_deprecation_plan includes this row',
                    'audit log query for api_deprecated_alias_used where alias_key=staff.reservations.loyalty.release returns zero hits for the release cycle',
                    'source scan confirms no client uses /api/v1/staff/reservations/{reservation_id}/loyalty/release',
                ],
            ],
            [
                'key' => 'staff.tables.table-board',
                'canonical_route' => 'GET /api/v1/staff/tables/board',
                'deprecated_alias' => 'GET /api/v1/staff/table-board',
                'removal_criteria' => 'Zero alias hits in production/staging audit logs for one release cycle and no frontend or integration source references.',
                'minimum_evidence' => [
                    'booking:route-gate alias_deprecation_plan includes this row',
                    'audit log query for api_deprecated_alias_used where alias_key=staff.tables.table-board returns zero hits for the release cycle',
                    'source scan confirms no client uses /api/v1/staff/table-board',
                ],
            ],
        ],
        'idempotency_inputs' => [
            [
                'key' => 'idempotency.x_header',
                'canonical_input' => 'Idempotency-Key header',
                'deprecated_alias' => 'X-Idempotency-Key header',
                'removal_criteria' => 'Zero compatibility-key hits for one release cycle and all generated clients send Idempotency-Key.',
                'minimum_evidence' => [
                    'audit log query for idempotency_compatibility_key_used where source=X-Idempotency-Key returns zero hits for the release cycle',
                    'staff-web and customer-web contract checks confirm canonical idempotency options',
                ],
            ],
            [
                'key' => 'idempotency.body_key',
                'canonical_input' => 'Idempotency-Key header',
                'deprecated_alias' => 'body idempotency_key',
                'removal_criteria' => 'Zero compatibility-key hits for one release cycle and all generated clients send Idempotency-Key.',
                'minimum_evidence' => [
                    'audit log query for idempotency_compatibility_key_used where source=body.idempotency_key returns zero hits for the release cycle',
                    'staff-web and customer-web contract checks confirm canonical idempotency options',
                ],
            ],
        ],
    ],

    // Reservation locks and runtime coordination.
    'ops_heartbeat_store' => (string) env('BOOKING_OPS_HEARTBEAT_STORE', $defaultRealtimeCacheStore),
    'require_redis_for_booking_api' => (bool) env('REQUIRE_REDIS_FOR_BOOKING_API', true),
    'scheduler_heartbeat_ttl_seconds' => (int) env('SCHEDULER_HEARTBEAT_TTL_SECONDS', 300),
    'scheduler_heartbeat_stale_seconds' => (int) env('SCHEDULER_HEARTBEAT_STALE_SECONDS', 180),
    'reservation_lock_ttl_seconds' => (int) env('RESERVATION_LOCK_TTL_SECONDS', 60),
    'reservation_lock_wait_seconds' => (int) env('RESERVATION_LOCK_WAIT_SECONDS', 10),
    'reservation_lock_prefix' => (string) env('RESERVATION_LOCK_PREFIX', 'booking:lock:table'),
    'reservation_lock_reservation_prefix' => (string) env('RESERVATION_LOCK_RESERVATION_PREFIX', 'booking:lock:reservation'),

    // Reservation and customer-facing timing windows.
    'check_in_grace_minutes' => (int) env('STAFF_CHECKIN_GRACE_MINUTES', 15),
    'no_show_grace_minutes' => (int) env('RESERVATION_NO_SHOW_GRACE_MINUTES', 15),
    'service_buffer_minutes' => $serviceBufferMinutes,
    'customer_preorder_management_cutoff_minutes' => (int) env('BOOKING_CUSTOMER_PREORDER_MANAGEMENT_CUTOFF_MINUTES', 60),
    'customer_reservation_cancellation_cutoff_minutes' => $customerReservationCancellationCutoffMinutes,
    'customer_reservation_reschedule_cutoff_minutes' => $customerReservationRescheduleCutoffMinutes,

    // Reservation code generation.
    'reservation_code_prefix' => (string) env('RESERVATION_CODE_PREFIX', 'RSV'),
    'reservation_code_random_len' => (int) env('RESERVATION_CODE_RANDOM_LEN', 6),
    'reservation_code_max_attempts' => (int) env('RESERVATION_CODE_MAX_ATTEMPTS', 12),

    // Customer pagination contracts.
    'customer_menu_page_default' => (int) env('BOOKING_CUSTOMER_MENU_PAGE_DEFAULT', 20),
    'customer_menu_page_max' => (int) env('BOOKING_CUSTOMER_MENU_PAGE_MAX', 100),
    'customer_reservation_self_service_page_default' => (int) env('BOOKING_CUSTOMER_RESERVATION_SELF_SERVICE_PAGE_DEFAULT', 10),
    'customer_reservation_self_service_page_max' => (int) env('BOOKING_CUSTOMER_RESERVATION_SELF_SERVICE_PAGE_MAX', 20),
    'customer_waiting_list_page_default' => (int) env('BOOKING_CUSTOMER_WAITING_LIST_PAGE_DEFAULT', 20),
    'customer_waiting_list_page_max' => (int) env('BOOKING_CUSTOMER_WAITING_LIST_PAGE_MAX', 100),

    // Payment provider contract.
    'customer_deposit_payment_default_provider' => (string) env('CUSTOMER_DEPOSIT_PAYMENT_DEFAULT_PROVIDER', $defaultPaymentProvider),
    'customer_bill_payment_default_provider' => (string) env('CUSTOMER_BILL_PAYMENT_DEFAULT_PROVIDER', $defaultPaymentProvider),
    'customer_deposit_payment_simulated_session_ttl_minutes' => (int) env('CUSTOMER_DEPOSIT_PAYMENT_SIMULATED_SESSION_TTL_MINUTES', 30),
    'customer_bill_payment_simulated_session_ttl_minutes' => (int) env('CUSTOMER_BILL_PAYMENT_SIMULATED_SESSION_TTL_MINUTES', 30),
    'payment_providers' => [
        'default_provider' => $defaultPaymentProvider,
        'customer_self_pay' => [
            'enabled' => (bool) env('PAYMENT_CUSTOMER_SELF_PAY_ENABLED', $defaultCustomerSelfPayEnabled),
            'production_like_environments' => $csvList((string) env('PAYMENT_PROVIDER_PRODUCTION_LIKE_ENVIRONMENTS', 'production,staging,limited-production')),
            'allow_simulated_in_production_like' => (bool) env('PAYMENT_PROVIDER_ALLOW_SIMULATED_IN_PRODUCTION_LIKE', false),
        ],
        'scopes' => [
            'deposit' => [
                'default_provider' => (string) env('CUSTOMER_DEPOSIT_PAYMENT_DEFAULT_PROVIDER', $defaultPaymentProvider),
            ],
            'bill' => [
                'default_provider' => (string) env('CUSTOMER_BILL_PAYMENT_DEFAULT_PROVIDER', $defaultPaymentProvider),
            ],
        ],
        'webhook' => [
            'signature_header' => (string) env('PAYMENT_PROVIDER_WEBHOOK_SIGNATURE_HEADER', 'X-Payment-Signature'),
            'timestamp_header' => (string) env('PAYMENT_PROVIDER_WEBHOOK_TIMESTAMP_HEADER', 'X-Payment-Timestamp'),
            'max_age_seconds' => (int) env('PAYMENT_PROVIDER_WEBHOOK_MAX_AGE_SECONDS', 300),
        ],
        'observability' => [
            'enabled' => (bool) env('PAYMENT_PROVIDER_OBSERVABILITY_ENABLED', true),
            'log_channel' => (string) env('PAYMENT_PROVIDER_OBSERVABILITY_LOG_CHANNEL', 'audit'),
            'duplicate_level' => (string) env('PAYMENT_PROVIDER_OBSERVABILITY_DUPLICATE_LEVEL', 'info'),
            'applied_level' => (string) env('PAYMENT_PROVIDER_OBSERVABILITY_APPLIED_LEVEL', 'info'),
            'ignored_level' => (string) env('PAYMENT_PROVIDER_OBSERVABILITY_IGNORED_LEVEL', 'warning'),
            'failed_level' => (string) env('PAYMENT_PROVIDER_OBSERVABILITY_FAILED_LEVEL', 'warning'),
        ],
        'providers' => [
            'simulated' => [
                'enabled' => (bool) env('PAYMENT_PROVIDER_SIMULATED_ENABLED', $isLocalLikeEnvironment),
                'mode' => 'simulated',
                'enforce_signature' => (bool) env('PAYMENT_PROVIDER_SIMULATED_ENFORCE_SIGNATURE', true),
                'webhook_secret' => (string) env('PAYMENT_PROVIDER_SIMULATED_WEBHOOK_SECRET', ''),
                'webhook' => [
                    'algorithm' => 'sha256',
                    'signature_header' => (string) env('PAYMENT_PROVIDER_WEBHOOK_SIGNATURE_HEADER', 'X-Payment-Signature'),
                    'secret' => (string) env('PAYMENT_PROVIDER_SIMULATED_WEBHOOK_SECRET', ''),
                ],
                'deposit' => [
                    'session_ttl_minutes' => (int) env('CUSTOMER_DEPOSIT_PAYMENT_SIMULATED_SESSION_TTL_MINUTES', 30),
                ],
                'bill' => [
                    'session_ttl_minutes' => (int) env('CUSTOMER_BILL_PAYMENT_SIMULATED_SESSION_TTL_MINUTES', 30),
                ],
            ],
            'generic_http_hmac' => [
                'enabled' => (bool) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_ENABLED', false),
                'mode' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_MODE', 'sandbox'),
                'base_url' => rtrim((string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_BASE_URL', ''), '/'),
                'merchant_id' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_MERCHANT_ID', ''),
                'merchant_code' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_MERCHANT_CODE', ''),
                'create_endpoint' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_CREATE_ENDPOINT', '/sessions'),
                'session_create_endpoint' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_CREATE_ENDPOINT', '/sessions'),
                'refresh_endpoint' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REFRESH_ENDPOINT', ''),
                'confirm_endpoint' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_CONFIRM_ENDPOINT', ''),
                'endpoints' => [
                    'create' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_CREATE_ENDPOINT', '/sessions'),
                    'refresh' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REFRESH_ENDPOINT', ''),
                    'confirm' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_CONFIRM_ENDPOINT', ''),
                ],
                'timeout_seconds' => (int) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_TIMEOUT_SECONDS', 15),
                'connect_timeout_seconds' => (int) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_CONNECT_TIMEOUT_SECONDS', 5),
                'retry' => [
                    'attempts' => (int) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_RETRY_ATTEMPTS', 0),
                    'sleep_ms' => (int) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_RETRY_SLEEP_MS', 250),
                ],
                'api_key' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_API_KEY', ''),
                'api_secret' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_API_SECRET', ''),
                'signing_secret' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_SIGNING_SECRET', ''),
                'supported_event_types' => $csvList((string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_SUPPORTED_EVENT_TYPES', '')),
                'request' => [
                    'algorithm' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REQUEST_ALGORITHM', 'sha256'),
                    'signature_header' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REQUEST_SIGNATURE_HEADER', 'X-Payment-Signature'),
                    'key_header' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REQUEST_KEY_HEADER', 'X-Payment-Key'),
                    'timestamp_header' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REQUEST_TIMESTAMP_HEADER', 'X-Payment-Timestamp'),
                    'idempotency_header' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REQUEST_IDEMPOTENCY_HEADER', 'X-Idempotency-Key'),
                    'merchant_id_header' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REQUEST_MERCHANT_ID_HEADER', 'X-Merchant-Id'),
                    'merchant_code_header' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REQUEST_MERCHANT_CODE_HEADER', 'X-Merchant-Code'),
                    'secret' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REQUEST_SECRET', ''),
                    'headers' => [],
                ],
                'webhook' => [
                    'algorithm' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_ALGORITHM', 'sha256'),
                    'signature_header' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SIGNATURE_HEADER', 'X-Payment-Signature'),
                    'timestamp_header' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_TIMESTAMP_HEADER', 'X-Payment-Timestamp'),
                    'max_age_seconds' => (int) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_MAX_AGE_SECONDS', 300),
                    'secret' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET', ''),
                    'scope_field' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SCOPE_FIELD', 'payment_scope'),
                    'session_code_field' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SESSION_CODE_FIELD', 'provider_session_code'),
                    'event_type_field' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_EVENT_TYPE_FIELD', 'event_type'),
                    'status_field' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_STATUS_FIELD', 'session_status'),
                    'event_code_field' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_EVENT_CODE_FIELD', 'provider_event_code'),
                    'payment_code_field' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_PAYMENT_CODE_FIELD', 'provider_payment_code'),
                    'expires_at_field' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_EXPIRES_AT_FIELD', 'provider_expires_at'),
                    'occurred_at_field' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_OCCURRED_AT_FIELD', 'occurred_at'),
                ],
                'deposit' => [
                    'create_endpoint' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_DEPOSIT_CREATE_ENDPOINT', ''),
                    'session_create_endpoint' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_DEPOSIT_CREATE_ENDPOINT', ''),
                    'refresh_endpoint' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_DEPOSIT_REFRESH_ENDPOINT', ''),
                    'confirm_endpoint' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_DEPOSIT_CONFIRM_ENDPOINT', ''),
                    'merchant_reference_prefix' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_DEPOSIT_MERCHANT_REFERENCE_PREFIX', 'reservation-deposit-'),
                ],
                'bill' => [
                    'create_endpoint' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_BILL_CREATE_ENDPOINT', ''),
                    'session_create_endpoint' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_BILL_CREATE_ENDPOINT', ''),
                    'refresh_endpoint' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_BILL_REFRESH_ENDPOINT', ''),
                    'confirm_endpoint' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_BILL_CONFIRM_ENDPOINT', ''),
                    'merchant_reference_prefix' => (string) env('PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_BILL_MERCHANT_REFERENCE_PREFIX', 'reservation-bill-'),
                ],
            ],
            'vnpay' => [
                'enabled' => (bool) env('PAYMENT_PROVIDER_VNPAY_ENABLED', false),
                'mode' => (string) env('PAYMENT_PROVIDER_VNPAY_MODE', 'sandbox'),
                'tmn_code' => (string) env('VNPAY_TMN_CODE', ''),
                'hash_secret' => (string) env('VNPAY_HASH_SECRET', ''),
                'return_url' => (string) env('VNPAY_RETURN_URL', ''),
                'ipn_url' => (string) env('VNPAY_IPN_URL', ''),
            ],
            'momo' => [
                'enabled' => (bool) env('PAYMENT_PROVIDER_MOMO_ENABLED', false),
                'mode' => (string) env('PAYMENT_PROVIDER_MOMO_MODE', 'sandbox'),
                'partner_code' => (string) env('MOMO_PARTNER_CODE', ''),
                'access_key' => (string) env('MOMO_ACCESS_KEY', ''),
                'secret_key' => (string) env('MOMO_SECRET_KEY', ''),
                'ipn_url' => (string) env('MOMO_IPN_URL', ''),
            ],
        ],
    ],

    // Realtime operational feed contract.
    'realtime' => [
        'enabled' => (bool) env('BOOKING_REALTIME_ENABLED', true),
        'cache_store' => (string) env('BOOKING_REALTIME_CACHE_STORE', $defaultRealtimeCacheStore),
        'production_like_environments' => $csvList((string) env('BOOKING_REALTIME_PRODUCTION_LIKE_ENVIRONMENTS', 'production,staging,limited-production')),
        'local_like_environments' => $csvList((string) env('BOOKING_REALTIME_LOCAL_LIKE_ENVIRONMENTS', 'local,development,testing')),
        'distributed_store_drivers' => $csvList((string) env('BOOKING_REALTIME_DISTRIBUTED_STORE_DRIVERS', 'redis,memcached,database,dynamodb')),
        'local_fallback_store_drivers' => $csvList((string) env('BOOKING_REALTIME_LOCAL_FALLBACK_STORE_DRIVERS', 'file,array')),
        'recent_event_limit' => (int) env('BOOKING_REALTIME_RECENT_EVENT_LIMIT', 50),
        'event_ttl_seconds' => (int) env('BOOKING_REALTIME_EVENT_TTL_SECONDS', 300),
        'poll_hint_ms' => (int) env('BOOKING_REALTIME_POLL_HINT_MS', 5000),
    ],

    // Reporting pagination and rebuild controls.
    'reporting_page_default' => (int) env('BOOKING_REPORTING_PAGE_DEFAULT', 25),
    'reporting_page_max' => (int) env('BOOKING_REPORTING_PAGE_MAX', 100),
    'reporting_snapshot_rebuild_max_days' => (int) env('BOOKING_REPORTING_SNAPSHOT_REBUILD_MAX_DAYS', 90),
    'reporting_snapshot_auto_rebuild_enabled' => (bool) env('BOOKING_REPORTING_SNAPSHOT_AUTO_REBUILD_ENABLED', true),
    'reporting_snapshot_auto_rebuild_hours' => max(1, (int) env('BOOKING_REPORTING_SNAPSHOT_AUTO_REBUILD_HOURS', 2)),
    'reporting_snapshot_auto_rebuild_lookback_days' => max(1, (int) env('BOOKING_REPORTING_SNAPSHOT_AUTO_REBUILD_LOOKBACK_DAYS', 7)),

    // Bootstrap defaults.
    'bootstrap' => [
        'admin_username' => (string) env('BOOTSTRAP_ADMIN_USERNAME', ''),
        'staff_username' => (string) env('BOOTSTRAP_STAFF_USERNAME', ''),
    ],

    // Multi-branch defaults for single-site compatibility and bootstrap.
    'multi_branch' => [
        'default_branch_code' => (string) env('BOOKING_DEFAULT_BRANCH_CODE', 'MS-HK'),
        'default_branch_name' => (string) env('BOOKING_DEFAULT_BRANCH_NAME', 'Mộc Sen Bistro - Hoàn Kiếm'),
        'default_branch_timezone' => (string) env('BOOKING_DEFAULT_BRANCH_TIMEZONE', 'Asia/Ho_Chi_Minh'),
        'default_branch_currency' => (string) env('BOOKING_DEFAULT_BRANCH_CURRENCY', 'VND'),
    ],
    'branch_policy_defaults' => [
        'business_hours' => [
            ['day_of_week' => 0, 'periods' => [['start_time' => '00:00', 'end_time' => '24:00']]],
            ['day_of_week' => 1, 'periods' => [['start_time' => '00:00', 'end_time' => '24:00']]],
            ['day_of_week' => 2, 'periods' => [['start_time' => '00:00', 'end_time' => '24:00']]],
            ['day_of_week' => 3, 'periods' => [['start_time' => '00:00', 'end_time' => '24:00']]],
            ['day_of_week' => 4, 'periods' => [['start_time' => '00:00', 'end_time' => '24:00']]],
            ['day_of_week' => 5, 'periods' => [['start_time' => '00:00', 'end_time' => '24:00']]],
            ['day_of_week' => 6, 'periods' => [['start_time' => '00:00', 'end_time' => '24:00']]],
        ],
        'closure_windows' => [],
        'booking_policy' => [
            'reservation' => [
                'min_lead_time_minutes' => 0,
                'max_advance_time_minutes' => 60 * 24 * 365,
                'same_day_cutoff_time' => null,
                'cancellation_cutoff_minutes' => $customerReservationCancellationCutoffMinutes,
                'reschedule_cutoff_minutes' => $customerReservationRescheduleCutoffMinutes,
            ],
            'waiting_list' => [
                'enabled' => true,
                'notify_hold_minutes' => $waitingListNotifyHoldMinutes,
                'default_service_minutes' => $waitingListServiceMinutes,
            ],
            'availability' => [
                'service_buffer_minutes' => $serviceBufferMinutes,
            ],
        ],
    ],

    // Admin inventory and purchasing pagination.
    'admin_inventory_page_default' => (int) env('BOOKING_ADMIN_INVENTORY_PAGE_DEFAULT', 25),
    'admin_inventory_page_max' => (int) env('BOOKING_ADMIN_INVENTORY_PAGE_MAX', 100),

    // Finance and invoice defaults.
    'finance_tax_invoice_profile' => [
        'tax_code' => (string) env('BOOKING_FINANCE_TAX_CODE', 'VAT10'),
        'tax_name' => (string) env('BOOKING_FINANCE_TAX_NAME', 'VAT 10%'),
        'tax_rate_percentage' => (float) env('BOOKING_FINANCE_TAX_RATE_PERCENTAGE', 10),
        'prices_include_tax' => (bool) env('BOOKING_FINANCE_PRICES_INCLUDE_TAX', true),
        'invoice_prefix' => (string) env('BOOKING_FINANCE_INVOICE_PREFIX', 'INV'),
        'seller_name' => (string) env('BOOKING_FINANCE_SELLER_NAME', 'Mộc Sen Bistro'),
        'seller_tax_id' => (string) env('BOOKING_FINANCE_SELLER_TAX_ID', ''),
        'seller_address' => (string) env('BOOKING_FINANCE_SELLER_ADDRESS', ''),
    ],

    // Metrics and privacy toggles.
    'metrics_enabled' => (bool) env('METRICS_ENABLED', true),
    'metrics_sample_rate' => max(0.0, min(1.0, (float) env('METRICS_SAMPLE_RATE', 1.0))),
    'doctor' => [
        'allow_local_bypass' => (bool) env('BOOKING_DOCTOR_ALLOW_LOCAL_BYPASS', false),
    ],
    'expose_session_id' => (bool) env('EXPOSE_SESSION_ID', false),
    'expose_hold_user_id' => (bool) env('EXPOSE_HOLD_USER_ID', false),

    // Staff table board heuristics and redaction.
    'staff_table_board_user_fields' => $csvList((string) env('STAFF_TABLE_BOARD_USER_FIELDS', 'user_id,full_name,phone')),
    'staff_table_board_close_fit_max_extra_seats' => (int) env('STAFF_TABLE_BOARD_CLOSE_FIT_MAX_EXTRA_SEATS', 2),
    'staff_table_board_candidate_preview_limit' => (int) env('STAFF_TABLE_BOARD_CANDIDATE_PREVIEW_LIMIT', 5),

    // Voucher and loyalty defaults.
    'voucher_lock_minutes' => (int) env('VOUCHER_LOCK_MINUTES', 5),
    'loyalty_enabled' => (bool) env('LOYALTY_ENABLED', true),
    'loyalty_redeem_amount_per_point' => max(0.01, (float) env('LOYALTY_REDEEM_AMOUNT_PER_POINT', 1000)),
    'loyalty_earn_amount_per_point' => max(0.01, (float) env('LOYALTY_EARN_AMOUNT_PER_POINT', 10000)),
    'loyalty_min_redeem_points' => max(1, (int) env('LOYALTY_MIN_REDEEM_POINTS', 1)),

    // Customer session access windows.
    'customer_session_exact_link_access_hours' => (int) env('CUSTOMER_SESSION_EXACT_LINK_ACCESS_HOURS', 24),
    'customer_session_legacy_access_hours' => (int) env('CUSTOMER_SESSION_LEGACY_ACCESS_HOURS', 0),

    // Test harness and database contract.
    'testing' => [
        'fail_fast_on_missing_schema' => (bool) env('BOOKING_TEST_FAIL_FAST_ON_MISSING_SCHEMA', true),
    ],
    'database_contract' => [
        'supported_drivers' => $csvList((string) env('SUPPORTED_DB_DRIVERS', 'mysql')),
        'enforce_supported_driver' => (bool) env('DB_ENFORCE_SUPPORTED_DRIVER', false),
    ],

    // Operational thresholds and alert plumbing.
    'ops' => [
        'payment_over_refund_fail_count' => (int) env('OPS_PAYMENT_OVER_REFUND_FAIL_COUNT', 1),
        'refund_without_source_fail_count' => (int) env('OPS_REFUND_WITHOUT_SOURCE_FAIL_COUNT', 1),
        'reporting_snapshot_stale_hours' => (int) env('OPS_REPORTING_SNAPSHOT_STALE_HOURS', 48),
        'stale_voucher_lock_warn_count' => (int) env('OPS_STALE_VOUCHER_LOCK_WARN_COUNT', 10),
        'unlinked_session_hold_warn_count' => (int) env('OPS_UNLINKED_SESSION_HOLD_WARN_COUNT', 5),
        'staff_api_keys_never_used_warn_count' => (int) env('OPS_STAFF_API_KEYS_NEVER_USED_WARN_COUNT', 5),
        'staff_api_keys_expiring_soon_days' => (int) env('OPS_STAFF_API_KEYS_EXPIRING_SOON_DAYS', 14),
        'staff_api_keys_missing_active_fail_count' => (int) env('OPS_STAFF_API_KEYS_MISSING_ACTIVE_FAIL_COUNT', 1),
        'table_state_audit_missing_actor_warn_count' => (int) env('OPS_TABLE_STATE_AUDIT_MISSING_ACTOR_WARN_COUNT', 1),
        'table_state_audit_missing_context_warn_count' => (int) env('OPS_TABLE_STATE_AUDIT_MISSING_CONTEXT_WARN_COUNT', 3),
        'table_state_audit_recent_window_hours' => (int) env('OPS_TABLE_STATE_AUDIT_RECENT_WINDOW_HOURS', 24),
        'row_version_contract_missing_required_fail_count' => (int) env('OPS_ROW_VERSION_CONTRACT_MISSING_REQUIRED_FAIL_COUNT', 1),
        'kitchen_queued_backlog_warn_seconds' => (int) env('OPS_KITCHEN_QUEUED_BACKLOG_WARN_SECONDS', 900),
        'kitchen_fired_backlog_warn_seconds' => (int) env('OPS_KITCHEN_FIRED_BACKLOG_WARN_SECONDS', 900),
        'kitchen_ready_backlog_warn_seconds' => (int) env('OPS_KITCHEN_READY_BACKLOG_WARN_SECONDS', 600),
        'inventory_purchase_scan_limit' => (int) env('OPS_INVENTORY_PURCHASE_SCAN_LIMIT', 50),
        'inventory_purchase_overdue_warn_count' => (int) env('OPS_INVENTORY_PURCHASE_OVERDUE_WARN_COUNT', 1),
        'inventory_purchase_overdue_warn_seconds' => (int) env('OPS_INVENTORY_PURCHASE_OVERDUE_WARN_SECONDS', 86400),
        'conversation_unassigned_warn_count' => (int) env('OPS_CONVERSATION_UNASSIGNED_WARN_COUNT', 5),
        'conversation_overdue_warn_count' => (int) env('OPS_CONVERSATION_OVERDUE_WARN_COUNT', 5),
        'conversation_oldest_overdue_warn_seconds' => (int) env('OPS_CONVERSATION_OLDEST_OVERDUE_WARN_SECONDS', 3600),
        'alerts' => [
            'webhook_url' => trim((string) env('OPS_ALERTS_WEBHOOK_URL', '')),
            'timeout_seconds' => (int) env('OPS_ALERTS_TIMEOUT_SECONDS', 5),
        ],
    ],

    // Waiting list defaults.
    'waiting_list_notify_hold_minutes' => $waitingListNotifyHoldMinutes,
    'waiting_list_service_minutes' => $waitingListServiceMinutes,
];
