<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Platform\Health\Services\BookingEnvironmentValidator;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class BookingEnvironmentValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'testing');
        config()->set('app.key', 'base64:K2Y5M2FhYjI3MDkxNGRiYjgxOWQ2OTZlY2MxZDIwYWE=');
        config()->set('booking.idempotency_ttl_hours', 24);
        config()->set('booking.idempotency_required_scopes', ['reservations', 'staff.checkout']);
        config()->set('booking.scheduler_heartbeat_ttl_seconds', 300);
        config()->set('booking.scheduler_heartbeat_stale_seconds', 180);
        config()->set('booking.reservation_lock_ttl_seconds', 60);
        config()->set('booking.reservation_lock_wait_seconds', 10);
        config()->set('booking.reservation_lock_prefix', 'booking:lock:table');
        config()->set('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation');
        config()->set('booking.require_redis_for_booking_api', true);
        config()->set('cache.stores.redis', [
            'driver' => 'redis',
            'connection' => 'default',
        ]);
        config()->set('database.redis.default', [
            'host' => '127.0.0.1',
            'port' => 6379,
        ]);
        config()->set('notifications.outbox.enabled', true);
        config()->set('notifications.outbox.mailer', 'smtp');
        config()->set('notifications.outbox.batch_size', 20);
        config()->set('notifications.outbox.lock_seconds', 90);
        config()->set('notifications.outbox.max_attempts', 5);
        config()->set('notifications.outbox.retry_backoff_minutes', [1, 5, 15]);
        config()->set('booking.loyalty_enabled', true);
        config()->set('booking.loyalty_redeem_amount_per_point', 1000);
        config()->set('booking.loyalty_earn_amount_per_point', 10000);
        config()->set('booking.loyalty_min_redeem_points', 1);
        config()->set('staff_auth.database_store_enabled', true);
        config()->set('staff_auth.api_keys', ['integration-staff-key' => 2]);
        config()->set('staff_auth.legacy_key', '');
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.allow_role_name_fallback', false);
        config()->set('staff_auth.env_fallback_allowed_environments', ['local', 'testing']);
        config()->set('staff_auth.production_like_environments', ['production', 'staging']);
        config()->set('staff_auth.deny_env_fallback_in_production_like', true);
        config()->set('staff_auth.deny_role_name_fallback_in_production_like', true);
        config()->set('staff_auth.allowed_role_ids', [1, 2]);
        config()->set('staff_capabilities.production_like_environments', ['production', 'staging']);
        config()->set('staff_capabilities.deny_operational_role_branch_fallback_in_production_like', true);
        config()->set('staff_capabilities.operational_branch_assignment_roles', ['Staff', 'Server', 'Waiter', 'Cashier', 'Kitchen']);
        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.header', 'X-Customer-Token');
        config()->set('customer_auth.jwt_secret', 'CJwx8ZRr4d7qvN0sP5uK9hLm2aT6yB3c');
        config()->set('customer_auth.allowed_purposes', ['VerifyEmail']);
        config()->set('customer_auth.allowed_role_ids', [3]);
        config()->set('customer_auth.allow_legacy_user_auth_tokens', false);
        config()->set('booking.payment_providers.customer_self_pay.enabled', false);
        config()->set('cors.allowed_origins', ['https://staff.example.test', 'https://customer.example.test']);
    }

    #[Group('booking-smoke')]
    public function test_it_passes_when_core_booking_configuration_is_valid(): void
    {
        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['errors']);
        $this->assertArrayHasKey('notifications.outbox', $result['checks']);
        $this->assertTrue($result['checks']['notifications.outbox']['ok']);
    }

    #[Group('booking-smoke')]
    public function test_it_reports_errors_for_invalid_scheduler_and_outbox_configuration(): void
    {
        config()->set('booking.scheduler_heartbeat_ttl_seconds', 60);
        config()->set('booking.scheduler_heartbeat_stale_seconds', 60);
        config()->set('notifications.outbox.batch_size', 0);

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['checks']['booking.scheduler_heartbeat']['ok']);
        $this->assertFalse($result['checks']['notifications.outbox']['ok']);
        $this->assertNotEmpty($result['errors']);
    }

    #[Group('booking-smoke')]
    public function test_it_warns_based_on_configured_driver_not_connection_alias(): void
    {
        config()->set('database.default', 'primary');
        config()->set('database.connections.primary', [
            'driver' => 'pgsql',
            'timezone' => '+00:00',
        ]);

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertFalse($result['checks']['database.connection']['ok']);
        $this->assertSame('warning', $result['checks']['database.connection']['severity']);
        $this->assertSame('primary', $result['checks']['database.connection']['meta']['database.default']);
        $this->assertSame('pgsql', $result['checks']['database.connection']['meta']['database.driver']);
    }

    #[Group('booking-smoke')]
    public function test_it_reports_warnings_for_optional_but_risky_configuration(): void
    {
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.api_keys', []);
        config()->set('staff_auth.legacy_key', '');
        config()->set('notifications.outbox.mailer', 'log');
        config()->set('app.env', 'testing');

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertFalse($result['checks']['staff_auth']['ok']);
        $this->assertSame('warning', $result['checks']['staff_auth']['severity']);
    }

    #[Group('booking-smoke')]
    public function test_it_errors_when_production_still_uses_env_backed_staff_api_keys(): void
    {
        $this->configureProductionLikeBaseline('production');
        config()->set('staff_auth.database_store_enabled', true);
        config()->set('staff_auth.api_keys', ['integration-staff-key' => 2]);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['checks']['staff_auth']['ok']);
        $this->assertSame('error', $result['checks']['staff_auth']['severity']);
        $this->assertTrue($result['checks']['staff_auth']['meta']['allow_env_fallback_when_database_store_unavailable']);
    }

    #[Group('booking-smoke')]
    public function test_it_errors_when_role_name_fallback_is_enabled_in_production_like_environment(): void
    {
        $this->configureProductionLikeBaseline('production');
        config()->set('staff_auth.allow_role_name_fallback', true);

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['checks']['staff_auth']['ok']);
        $this->assertSame('error', $result['checks']['staff_auth']['severity']);
        $this->assertTrue($result['checks']['staff_auth']['meta']['allow_role_name_fallback']);
    }

    #[Group('booking-smoke')]
    public function test_it_warns_when_role_name_fallback_is_enabled_outside_production_like_environment(): void
    {
        config()->set('app.env', 'testing');
        config()->set('staff_auth.allow_role_name_fallback', true);

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['checks']['staff_auth']['ok']);
        $this->assertSame('warning', $result['checks']['staff_auth']['severity']);
        $this->assertTrue($result['checks']['staff_auth']['meta']['allow_role_name_fallback']);
    }

    #[Group('booking-smoke')]
    public function test_it_errors_when_production_like_allows_operational_staff_branch_scope_fallback(): void
    {
        $this->configureProductionLikeBaseline('production');
        config()->set('staff_auth.api_keys', []);
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_capabilities.deny_operational_role_branch_fallback_in_production_like', false);

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['checks']['staff_branch_assignment_policy']['ok']);
        $this->assertSame('error', $result['checks']['staff_branch_assignment_policy']['severity']);
        $this->assertTrue($result['checks']['staff_branch_assignment_policy']['meta']['is_production_like']);
    }

    #[Group('booking-smoke')]
    public function test_it_errors_when_customer_access_session_ttl_is_non_positive(): void
    {
        config()->set('customer_auth.access_session_ttl_minutes', 0);

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['checks']['customer_auth']['ok']);
        $this->assertSame('error', $result['checks']['customer_auth']['severity']);
    }

    #[Group('booking-smoke')]
    public function test_it_warns_when_customer_auth_is_disabled_because_owner_token_routes_remain_live(): void
    {
        config()->set('customer_auth.enabled', false);

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['checks']['customer_auth']['ok']);
        $this->assertSame('warning', $result['checks']['customer_auth']['severity']);
        $this->assertNotEmpty($result['warnings']);
    }

    #[Group('booking-smoke')]
    public function test_it_errors_when_production_customer_auth_still_enables_legacy_user_auth_tokens(): void
    {
        $this->configureProductionLikeBaseline('production');
        config()->set('customer_auth.allow_legacy_user_auth_tokens', true);

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['checks']['customer_auth']['ok']);
        $this->assertSame('error', $result['checks']['customer_auth']['severity']);
    }

    #[Group('booking-smoke')]
    public function test_it_keeps_day_one_payment_rollout_valid_when_customer_self_pay_is_disabled_intentionally(): void
    {
        $this->configureProductionLikeBaseline('production');
        config()->set('booking.payment_providers.customer_self_pay.enabled', false);

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertTrue($result['checks']['payment_providers']['ok']);
        $this->assertTrue($result['ok']);
    }

    #[Group('booking-smoke')]
    public function test_it_errors_when_customer_self_pay_is_enabled_without_ready_live_provider_config(): void
    {
        $this->configureProductionLikeBaseline('production');
        config()->set('booking.payment_providers.customer_self_pay.enabled', true);
        config()->set('booking.payment_providers.scopes.deposit.default_provider', 'generic_http_hmac');
        config()->set('booking.payment_providers.scopes.bill.default_provider', 'generic_http_hmac');
        config()->set('booking.payment_providers.default_provider', 'generic_http_hmac');
        config()->set('booking.payment_providers.providers.simulated.enabled', false);
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.generic_http_hmac.base_url', '');
        config()->set('booking.payment_providers.providers.generic_http_hmac.request.secret', '');
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.secret', '');
        config()->set('booking.payment_providers.providers.generic_http_hmac.deposit.create_endpoint', '');
        config()->set('booking.payment_providers.providers.generic_http_hmac.bill.create_endpoint', '');

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['checks']['payment_providers']['ok']);
        $this->assertSame('error', $result['checks']['payment_providers']['severity']);
    }

    #[Group('booking-smoke')]
    public function test_it_rejects_dangerous_production_like_environment_configuration(): void
    {
        config()->set('app.env', 'staging');
        config()->set('app.debug', true);
        config()->set('app.key', 'base64:testtesttesttesttesttesttesttesttest=');
        config()->set('customer_auth.jwt_secret', 'change-me');
        config()->set('cors.allowed_origins', ['*', 'https://staff.example.test/', 'https://customer.example.test/api']);
        config()->set('queue.default', 'sync');
        config()->set('cache.default', 'file');
        config()->set('session.driver', 'file');
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.api_keys', ['staff-key' => 2]);
        config()->set('notifications.channels.sms.enabled', true);
        config()->set('notifications.channels.sms.driver', 'stub');

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertFalse($result['ok']);
        $this->assertTrue((bool) $result['checks']['app.environment']['meta']['is_production_like']);
        $this->assertSame('error', $result['checks']['app.debug']['severity']);
        $this->assertSame('error', $result['checks']['app.key']['severity']);
        $this->assertSame('error', $result['checks']['customer_auth']['severity']);
        $this->assertSame('error', $result['checks']['cors.allowed_origins']['severity']);
        $this->assertSame(3, $result['checks']['cors.allowed_origins']['meta']['invalid_origin_count']);
        $this->assertSame('error', $result['checks']['runtime.drivers']['severity']);
        $this->assertSame('error', $result['checks']['cache.redis']['severity']);
        $this->assertSame('error', $result['checks']['notifications.outbox']['severity']);
        $this->assertSame('error', $result['checks']['credentials.production_readiness']['severity']);
    }

    #[Group('booking-smoke')]
    public function test_it_keeps_local_testing_drivers_usable_with_warnings_only(): void
    {
        config()->set('app.env', 'local');
        config()->set('queue.default', 'sync');
        config()->set('cache.default', 'file');
        config()->set('session.driver', 'file');
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('booking.doctor.allow_local_bypass', false);
        config()->set('cors.allowed_origins', []);
        config()->set('customer_auth.jwt_secret', '');

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertTrue($result['ok']);
        $this->assertTrue((bool) $result['checks']['runtime.drivers']['ok']);
        $this->assertTrue((bool) $result['checks']['cors.allowed_origins']['ok']);
        $this->assertSame('warning', $result['checks']['cache.redis']['severity']);
        $this->assertSame('warning', $result['checks']['customer_auth']['severity']);
    }

    #[Group('booking-smoke')]
    public function test_it_passes_observability_validation_outside_production(): void
    {
        config()->set('app.env', 'testing');
        config()->set('services.sentry.dsn', '');
        config()->set('logging.channels.slack.url', '');

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['checks']['observability.sentry']['ok']);
        $this->assertTrue($result['checks']['observability.alerting']['ok']);
    }

    #[Group('booking-smoke')]
    public function test_it_fails_observability_validation_in_production_like_when_empty(): void
    {
        $this->configureProductionLikeBaseline('production');
        config()->set('services.sentry.dsn', '');
        config()->set('logging.channels.slack.url', '');

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['checks']['observability.sentry']['ok']);
        $this->assertFalse($result['checks']['observability.alerting']['ok']);
        $this->assertSame('error', $result['checks']['observability.sentry']['severity']);
        $this->assertSame('error', $result['checks']['observability.alerting']['severity']);
    }

    #[Group('booking-smoke')]
    public function test_it_passes_observability_validation_in_production_like_when_configured(): void
    {
        $this->configureProductionLikeBaseline('production');
        config()->set('services.sentry.dsn', 'https://12345@sentry.io/1');
        config()->set('logging.channels.slack.url', 'https://slack.myorg.org/hooks/123/456');

        $result = app(BookingEnvironmentValidator::class)->validate();

        $this->assertTrue($result['checks']['observability.sentry']['ok']);
        $this->assertTrue($result['checks']['observability.alerting']['ok']);
        $this->assertTrue($result['ok']);
    }

    private function configureProductionLikeBaseline(string $environment): void
    {
        config()->set('app.env', $environment);
        config()->set('app.debug', false);
        config()->set('app.key', 'base64:K2Y5M2FhYjI3MDkxNGRiYjgxOWQ2OTZlY2MxZDIwYWE=');
        config()->set('services.sentry.dsn', 'https://12345@sentry.io/1');
        config()->set('logging.channels.slack.url', 'https://slack.myorg.org/hooks/123/456');
        config()->set('queue.default', 'database');
        config()->set('cache.default', 'redis');
        config()->set('session.driver', 'database');
        config()->set('booking.require_redis_for_booking_api', true);
        config()->set('staff_auth.api_keys', []);
        config()->set('staff_auth.legacy_key', '');
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.allow_role_name_fallback', false);
        config()->set('staff_capabilities.deny_operational_role_branch_fallback_in_production_like', true);
        config()->set('customer_auth.jwt_secret', 'CJwx8ZRr4d7qvN0sP5uK9hLm2aT6yB3c');
        config()->set('customer_auth.allow_legacy_user_auth_tokens', false);
        config()->set('cors.allowed_origins', ['https://staff.example.test', 'https://customer.example.test']);
        config()->set('notifications.outbox.enabled', true);
        config()->set('notifications.outbox.mailer', 'smtp');
        config()->set('notifications.channels.sms.enabled', false);
        config()->set('notifications.channels.sms.driver', 'stub');
        config()->set('notifications.channels.zalo.enabled', false);
        config()->set('notifications.channels.zalo.driver', 'stub');
    }
}
