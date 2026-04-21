<?php

namespace App\Platform\Health\Services;

use App\Enums\PaymentSessionScope;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\PaymentProviderRolloutConfig;

class BookingEnvironmentValidator
{
    /**
     * @return array{
     *   ok: bool,
     *   errors: list<string>,
     *   warnings: list<string>,
     *   checks: array<string, array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}>
     * }
     */
    public function validate(): array
    {
        $checks = [];

        $this->addCheck($checks, 'app.key', $this->validateAppKey());
        $this->addCheck($checks, 'booking.idempotency', $this->validateIdempotencyConfig());
        $this->addCheck($checks, 'booking.scheduler_heartbeat', $this->validateSchedulerHeartbeatConfig());
        $this->addCheck($checks, 'booking.reservation_lock', $this->validateReservationLockConfig());
        $this->addCheck($checks, 'database.connection', $this->validateDatabaseConfig());
        $this->addCheck($checks, 'queue.storage', $this->validateQueueConfig());
        $this->addCheck($checks, 'cache.redis', $this->validateRedisConfig());
        $this->addCheck($checks, 'notifications.outbox', $this->validateNotificationsConfig());
        $this->addCheck($checks, 'loyalty', $this->validateLoyaltyConfig());
        $this->addCheck($checks, 'staff_auth', $this->validateStaffAuthConfig());
        $this->addCheck($checks, 'customer_auth', $this->validateCustomerAuthConfig());
        $this->addCheck($checks, 'payment_providers', $this->validatePaymentProviderConfig());

        $errors = [];
        $warnings = [];

        foreach ($checks as $name => $check) {
            if (! $check['ok']) {
                $line = sprintf('%s: %s', $name, $check['message']);
                if (($check['severity'] ?? 'error') === 'warning') {
                    $warnings[] = $line;
                } else {
                    $errors[] = $line;
                }
            }
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'checks' => $checks,
        ];
    }

    private function addCheck(array &$checks, string $name, array $result): void
    {
        $checks[$name] = $result;
    }

    private function ok(string $message, array $meta = []): array
    {
        $result = ['ok' => true, 'severity' => 'info', 'message' => $message];
        if ($meta !== []) {
            $result['meta'] = $meta;
        }
        return $result;
    }

    private function error(string $message, array $meta = []): array
    {
        $result = ['ok' => false, 'severity' => 'error', 'message' => $message];
        if ($meta !== []) {
            $result['meta'] = $meta;
        }
        return $result;
    }

    private function warning(string $message, array $meta = []): array
    {
        $result = ['ok' => false, 'severity' => 'warning', 'message' => $message];
        if ($meta !== []) {
            $result['meta'] = $meta;
        }
        return $result;
    }

    private function validateAppKey(): array
    {
        $appKey = (string) config('app.key', '');
        if ($appKey === '') {
            return $this->error('APP_KEY is empty.');
        }

        if (str_starts_with($appKey, 'base64:')) {
            return $this->ok('APP_KEY is configured (base64).');
        }

        if (strlen($appKey) < 16) {
            return $this->warning('APP_KEY is configured but looks unusually short.', [
                'length' => strlen($appKey),
            ]);
        }

        return $this->ok('APP_KEY is configured.');
    }

    private function validateIdempotencyConfig(): array
    {
        $ttlHours = (int) config('booking.idempotency_ttl_hours', 0);
        $scopes = config('booking.idempotency_required_scopes', []);

        if ($ttlHours <= 0) {
            return $this->error('booking.idempotency_ttl_hours must be greater than 0.', [
                'idempotency_ttl_hours' => $ttlHours,
            ]);
        }

        if (! is_array($scopes) || $scopes === []) {
            return $this->warning('booking.idempotency_required_scopes is empty; state-changing endpoints may miss replay protection.');
        }

        return $this->ok('Idempotency configuration looks valid.', [
            'idempotency_ttl_hours' => $ttlHours,
            'scope_count' => count($scopes),
        ]);
    }

    private function validateSchedulerHeartbeatConfig(): array
    {
        $ttl = (int) config('booking.scheduler_heartbeat_ttl_seconds', 0);
        $stale = (int) config('booking.scheduler_heartbeat_stale_seconds', 0);

        if ($ttl <= 0 || $stale <= 0) {
            return $this->error('Scheduler heartbeat TTL and stale threshold must be greater than 0.', [
                'scheduler_heartbeat_ttl_seconds' => $ttl,
                'scheduler_heartbeat_stale_seconds' => $stale,
            ]);
        }

        if ($ttl <= $stale) {
            return $this->error('scheduler_heartbeat_ttl_seconds must be greater than scheduler_heartbeat_stale_seconds to avoid false missing-heartbeat failures.', [
                'scheduler_heartbeat_ttl_seconds' => $ttl,
                'scheduler_heartbeat_stale_seconds' => $stale,
            ]);
        }

        return $this->ok('Scheduler heartbeat configuration looks valid.', [
            'scheduler_heartbeat_ttl_seconds' => $ttl,
            'scheduler_heartbeat_stale_seconds' => $stale,
        ]);
    }

    private function validateReservationLockConfig(): array
    {
        $ttl = (int) config('booking.reservation_lock_ttl_seconds', 0);
        $wait = (int) config('booking.reservation_lock_wait_seconds', 0);
        $tablePrefix = trim((string) config('booking.reservation_lock_prefix', ''));
        $reservationPrefix = trim((string) config('booking.reservation_lock_reservation_prefix', ''));

        if ($ttl <= 0) {
            return $this->error('booking.reservation_lock_ttl_seconds must be greater than 0.', [
                'reservation_lock_ttl_seconds' => $ttl,
            ]);
        }

        if ($wait < 0) {
            return $this->error('booking.reservation_lock_wait_seconds must be greater than or equal to 0.', [
                'reservation_lock_wait_seconds' => $wait,
            ]);
        }

        if ($ttl <= $wait) {
            return $this->warning('reservation_lock_ttl_seconds is less than or equal to wait_seconds; long operations may lose the distributed lock early.', [
                'reservation_lock_ttl_seconds' => $ttl,
                'reservation_lock_wait_seconds' => $wait,
            ]);
        }

        if ($tablePrefix === '' || $reservationPrefix === '') {
            return $this->error('Reservation lock prefixes must not be empty.', [
                'reservation_lock_prefix' => $tablePrefix,
                'reservation_lock_reservation_prefix' => $reservationPrefix,
            ]);
        }

        return $this->ok('Reservation lock configuration looks valid.', [
            'reservation_lock_ttl_seconds' => $ttl,
            'reservation_lock_wait_seconds' => $wait,
        ]);
    }

    private function validateDatabaseConfig(): array
    {
        $default = trim((string) config('database.default', ''));

        if ($default === '') {
            return $this->error('database.default must not be empty.');
        }

        $connection = config("database.connections.{$default}");
        if (! is_array($connection)) {
            return $this->error('Default database connection config is missing or invalid.', [
                'database.default' => $default,
            ]);
        }

        $driver = trim((string) ($connection['driver'] ?? $default));
        $meta = [
            'database.default' => $default,
            'database.driver' => $driver,
        ];

        if ($driver === 'sqlite') {
            return $this->warning('Configured database driver is sqlite; this is acceptable for local/testing, but production booking workloads should use mysql or mariadb.', $meta);
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->warning('Configured database driver is not mysql/mariadb. Booking features may work, but the project is primarily hardened for MySQL-compatible engines.', $meta);
        }

        $timezone = trim((string) ($connection['timezone'] ?? '+00:00'));
        if ($timezone !== '+00:00') {
            $meta['timezone'] = $timezone;

            return $this->warning('Database timezone is not +00:00; ensure timestamp normalization is handled consistently.', $meta);
        }

        $meta['timezone'] = $timezone;

        return $this->ok('Database configuration looks valid for booking workloads.', $meta);
    }

    private function validateQueueConfig(): array
    {
        $defaultDb = trim((string) config('database.default', ''));
        $queueDefault = trim((string) config('queue.default', ''));
        $queueDatabaseConnection = trim((string) config('queue.connections.database.connection', ''));
        $batchDatabase = trim((string) config('queue.batching.database', $defaultDb));
        $failedDriver = trim((string) config('queue.failed.driver', ''));
        $failedDatabase = trim((string) config('queue.failed.database', $defaultDb));

        if ($defaultDb === '') {
            return $this->error('database.default must not be empty before validating queue storage.');
        }

        if ($queueDatabaseConnection !== '' && $queueDatabaseConnection !== $defaultDb) {
            return $this->error('queue.connections.database.connection must be empty or match database.default.', [
                'queue.connections.database.connection' => $queueDatabaseConnection,
                'database.default' => $defaultDb,
            ]);
        }

        if ($batchDatabase !== $defaultDb) {
            return $this->error('queue.batching.database must match database.default.', [
                'queue.batching.database' => $batchDatabase,
                'database.default' => $defaultDb,
            ]);
        }

        if (in_array($failedDriver, ['database', 'database-uuids'], true) && $failedDatabase !== $defaultDb) {
            return $this->error('queue.failed.database must match database.default when failed jobs are stored in the database.', [
                'queue.failed.driver' => $failedDriver,
                'queue.failed.database' => $failedDatabase,
                'database.default' => $defaultDb,
            ]);
        }

        return $this->ok('Queue storage configuration looks aligned with the primary database.', [
            'queue.default' => $queueDefault,
            'database.default' => $defaultDb,
            'queue.connections.database.connection' => $queueDatabaseConnection === '' ? '(default connection)' : $queueDatabaseConnection,
            'queue.batching.database' => $batchDatabase,
            'queue.failed.driver' => $failedDriver,
            'queue.failed.database' => $failedDatabase,
        ]);
    }

    private function validateRedisConfig(): array
    {
        $requireRedis = (bool) config('booking.require_redis_for_booking_api', true);
        $redisStore = config('cache.stores.redis');
        $redisDefault = config('database.redis.default');

        if (! $requireRedis) {
            return $this->warning('Booking API is allowed to run without mandatory Redis; this weakens distributed locks and idempotency durability.');
        }

        if (! is_array($redisStore)) {
            return $this->error('cache.stores.redis is not configured as an array.');
        }

        if (($redisStore['driver'] ?? null) !== 'redis') {
            return $this->error('cache.stores.redis.driver must be redis.', [
                'driver' => $redisStore['driver'] ?? null,
            ]);
        }

        if (! is_array($redisDefault) || trim((string) ($redisDefault['host'] ?? '')) === '') {
            return $this->error('database.redis.default.host is missing.');
        }

        return $this->ok('Redis configuration looks valid for booking workloads.', [
            'redis_host' => (string) ($redisDefault['host'] ?? ''),
            'redis_port' => (int) ($redisDefault['port'] ?? 0),
        ]);
    }

    private function validateNotificationsConfig(): array
    {
        $enabled = (bool) config('notifications.outbox.enabled', true);
        if (! $enabled) {
            return $this->warning('Notification outbox is disabled. Reservation reminders and email notifications will not be processed.');
        }

        $mailer = trim((string) config('notifications.outbox.mailer', ''));
        $batchSize = (int) config('notifications.outbox.batch_size', 0);
        $lockSeconds = (int) config('notifications.outbox.lock_seconds', 0);
        $maxAttempts = (int) config('notifications.outbox.max_attempts', 0);
        $retryBackoff = config('notifications.outbox.retry_backoff_minutes', []);
        $emailEnabled = (bool) config('notifications.channels.email.enabled', true);
        $emailDriver = trim((string) config('notifications.channels.email.driver', 'mail'));
        $smsEnabled = (bool) config('notifications.channels.sms.enabled', false);
        $smsDriver = trim((string) config('notifications.channels.sms.driver', 'stub'));
        $zaloEnabled = (bool) config('notifications.channels.zalo.enabled', false);
        $zaloDriver = trim((string) config('notifications.channels.zalo.driver', 'stub'));

        if ($emailEnabled && $emailDriver === 'mail' && $mailer === '') {
            return $this->error('notifications.outbox.mailer must not be empty when outbox is enabled.');
        }

        if ($batchSize <= 0 || $lockSeconds <= 0 || $maxAttempts <= 0) {
            return $this->error('Notification outbox batch_size, lock_seconds, and max_attempts must be greater than 0.', [
                'batch_size' => $batchSize,
                'lock_seconds' => $lockSeconds,
                'max_attempts' => $maxAttempts,
            ]);
        }

        if (! is_array($retryBackoff) || $retryBackoff === []) {
            return $this->warning('notifications.outbox.retry_backoff_minutes is empty; failed messages may retry too aggressively.');
        }

        if ((string) config('app.env') === 'production' && $mailer === 'log') {
            return $this->warning('Notification outbox mailer is set to log in production; emails will not be sent to real recipients.');
        }

        if (($smsEnabled && $smsDriver === 'stub') || ($zaloEnabled && $zaloDriver === 'stub')) {
            return $this->warning('One or more non-email notification channels are enabled with stub drivers. These channels are provider-ready only and will not deliver externally.', [
                'sms_enabled' => $smsEnabled,
                'sms_driver' => $smsDriver,
                'zalo_enabled' => $zaloEnabled,
                'zalo_driver' => $zaloDriver,
            ]);
        }

        return $this->ok('Notification outbox configuration looks valid.', [
            'mailer' => $mailer,
            'batch_size' => $batchSize,
            'lock_seconds' => $lockSeconds,
            'max_attempts' => $maxAttempts,
            'email_enabled' => $emailEnabled,
            'email_driver' => $emailDriver,
            'sms_enabled' => $smsEnabled,
            'sms_driver' => $smsDriver,
            'zalo_enabled' => $zaloEnabled,
            'zalo_driver' => $zaloDriver,
        ]);
    }

    private function validateLoyaltyConfig(): array
    {
        $enabled = (bool) config('booking.loyalty_enabled', true);
        if (! $enabled) {
            return $this->warning('Loyalty module is disabled.');
        }

        $redeem = (float) config('booking.loyalty_redeem_amount_per_point', 0.0);
        $earn = (float) config('booking.loyalty_earn_amount_per_point', 0.0);
        $minRedeem = (int) config('booking.loyalty_min_redeem_points', 0);

        if ($redeem <= 0 || $earn <= 0 || $minRedeem <= 0) {
            return $this->error('Loyalty earn/redeem ratios must be greater than 0.', [
                'loyalty_redeem_amount_per_point' => $redeem,
                'loyalty_earn_amount_per_point' => $earn,
                'loyalty_min_redeem_points' => $minRedeem,
            ]);
        }

        return $this->ok('Loyalty configuration looks valid.', [
            'loyalty_redeem_amount_per_point' => $redeem,
            'loyalty_earn_amount_per_point' => $earn,
            'loyalty_min_redeem_points' => $minRedeem,
        ]);
    }

    private function validateStaffAuthConfig(): array
    {
        $databaseStoreEnabled = (bool) config('staff_auth.database_store_enabled', true);
        $apiKeys = config('staff_auth.api_keys', []);
        $legacyKey = trim((string) config('staff_auth.legacy_key', ''));
        $allowEnvFallback = (bool) config('staff_auth.allow_env_fallback', false);
        $allowUnavailableFallback = (bool) config('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        $allowRoleNameFallback = (bool) config('staff_auth.allow_role_name_fallback', false);
        $fallbackAllowedEnvironments = array_values(array_map('strval', (array) config('staff_auth.env_fallback_allowed_environments', [])));
        $allowedRoleIds = config('staff_auth.allowed_role_ids', []);
        $environment = (string) config('app.env', 'production');
        $productionLikeEnvironments = array_values(array_map(
            'strval',
            (array) config('staff_auth.production_like_environments', ['production', 'staging'])
        ));
        $denyEnvFallbackInProductionLike = (bool) config('staff_auth.deny_env_fallback_in_production_like', true);
        $denyRoleNameFallbackInProductionLike = (bool) config('staff_auth.deny_role_name_fallback_in_production_like', true);
        $isProductionLike = in_array($environment, $productionLikeEnvironments, true);
        $hasEnvBackedFallback = $allowEnvFallback
            || $allowUnavailableFallback
            || $legacyKey !== ''
            || (is_array($apiKeys) && $apiKeys !== []);

        $meta = [
            'database_store_enabled' => $databaseStoreEnabled,
            'api_key_count' => is_array($apiKeys) ? count($apiKeys) : 0,
            'legacy_key_configured' => ($legacyKey !== ''),
            'allow_env_fallback' => $allowEnvFallback,
            'allow_env_fallback_when_database_store_unavailable' => $allowUnavailableFallback,
            'allow_role_name_fallback' => $allowRoleNameFallback,
            'fallback_allowed_environments' => $fallbackAllowedEnvironments,
            'allowed_role_ids' => array_values(array_map('intval', is_array($allowedRoleIds) ? $allowedRoleIds : [])),
            'environment' => $environment,
            'production_like_environments' => $productionLikeEnvironments,
            'is_production_like' => $isProductionLike,
        ];

        if (! is_array($allowedRoleIds) || $allowedRoleIds === []) {
            return $this->error('staff_auth.allowed_role_ids must not be empty.', $meta);
        }

        if (! $databaseStoreEnabled && (! is_array($apiKeys) || $apiKeys === []) && $legacyKey === '') {
            return $this->warning('Database-backed staff API key auth is disabled and no env fallback key is configured; staff endpoints will be unreachable until credentials are provisioned.', $meta);
        }

        if ($isProductionLike && $denyEnvFallbackInProductionLike && $hasEnvBackedFallback) {
            return $this->error('Production-like environment still allows env-backed staff API key fallback. Prefer database-backed hashed keys in staff_api_keys and disable every env fallback after rollout.', $meta);
        }

        if ($isProductionLike && $denyRoleNameFallbackInProductionLike && $allowRoleNameFallback) {
            return $this->error('staff_auth.allow_role_name_fallback is enabled in a production-like environment. Resolve staff permissions by stable role_id values instead of role_name fallback.', $meta);
        }

        if ($allowRoleNameFallback) {
            return $this->warning('staff_auth.allow_role_name_fallback is enabled. Resolve staff permissions by stable role_id values instead of role_name fallback.', $meta);
        }

        return $this->ok('Staff authentication configuration looks valid.', $meta);
    }

    private function validateCustomerAuthConfig(): array
    {
        $enabled = (bool) config('customer_auth.enabled', false);
        $header = trim((string) config('customer_auth.header', ''));
        $purposes = config('customer_auth.allowed_purposes', []);
        $allowedRoleIds = config('customer_auth.allowed_role_ids', []);
        $legacyEnabled = (bool) config('customer_auth.allow_legacy_user_auth_tokens', false);
        $legacyAllowedEnvironments = config('customer_auth.legacy_user_auth_tokens_allowed_environments', []);
        $accessSessionTtlMinutes = (int) config('customer_auth.access_session_ttl_minutes', 0);
        $environment = (string) config('app.env', 'production');
        $meta = [
            'enabled' => $enabled,
            'header' => $header,
            'purpose_count' => is_array($purposes) ? count($purposes) : 0,
            'allowed_role_ids' => array_values(array_map('intval', is_array($allowedRoleIds) ? $allowedRoleIds : [])),
            'allow_legacy_user_auth_tokens' => $legacyEnabled,
            'legacy_allowed_environments' => array_values(array_map('strval', is_array($legacyAllowedEnvironments) ? $legacyAllowedEnvironments : [])),
            'access_session_ttl_minutes' => $accessSessionTtlMinutes,
            'environment' => $environment,
        ];

        if (! $enabled) {
            return $this->warning('Customer auth is disabled. Token-authenticated owner/self-service endpoints remain live in the route surface, but customer access-session tokens will not resolve until customer_auth.enabled is restored.', $meta);
        }

        if ($header === '') {
            return $this->error('customer_auth.header must not be empty when customer auth is enabled.', $meta);
        }

        if ($accessSessionTtlMinutes <= 0) {
            return $this->error('customer_auth.access_session_ttl_minutes must be greater than 0.', $meta);
        }

        if ($legacyEnabled) {
            $severity = $environment === 'production' ? 'error' : 'warning';
            $message = 'Legacy customer auth via user_auth_tokens is enabled. This path is blocked outside explicitly allowed environments and should be replaced with a dedicated access-token/session model.';

            return $severity === 'error'
                ? $this->error($message, $meta)
                : $this->warning($message, $meta);
        }

        return $this->ok('Customer auth is enabled with legacy user_auth_tokens auth disabled.', $meta);
    }

    private function validatePaymentProviderConfig(): array
    {
        /** @var PaymentProviderRolloutConfig $rollout */
        $rollout = app(PaymentProviderRolloutConfig::class);
        $customerSelfPayEnabled = (bool) config('booking.payment_providers.customer_self_pay.enabled', false);
        $depositStatus = $rollout->customerSelfPayStatus(PaymentSessionScope::Deposit);
        $billStatus = $rollout->customerSelfPayStatus(PaymentSessionScope::Bill);
        $meta = [
            'customer_self_pay_enabled' => $customerSelfPayEnabled,
            'default_provider' => $rollout->defaultProviderCode(),
            'deposit' => $depositStatus,
            'bill' => $billStatus,
        ];

        if (! $customerSelfPayEnabled) {
            return $this->ok('Customer self-pay is intentionally disabled; staff settlement remains the day-1 payment path.', $meta);
        }

        if (! ($depositStatus['ok'] ?? false) || ! ($billStatus['ok'] ?? false)) {
            return $this->error('Customer self-pay is enabled but the configured payment provider rollout is not ready for both deposit and bill scopes.', $meta);
        }

        return $this->ok('Payment provider rollout configuration looks valid for customer self-pay.', $meta);
    }
}
