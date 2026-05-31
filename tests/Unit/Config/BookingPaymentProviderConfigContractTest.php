<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

final class BookingPaymentProviderConfigContractTest extends TestCase
{
    public function test_booking_config_declares_payment_provider_defaults_and_supported_provider_switches(): void
    {
        self::assertSame('simulated', config('booking.customer_deposit_payment_default_provider'));
        self::assertSame('simulated', config('booking.customer_bill_payment_default_provider'));
        self::assertSame(30, config('booking.customer_deposit_payment_simulated_session_ttl_minutes'));
        self::assertSame(30, config('booking.customer_bill_payment_simulated_session_ttl_minutes'));
        self::assertSame('simulated', config('booking.payment_providers.default_provider'));
        self::assertIsBool(config('booking.payment_providers.customer_self_pay.enabled'));
        self::assertFalse((bool) config('booking.payment_providers.customer_self_pay.enabled'));
        self::assertFalse((bool) config('booking.payment_providers.customer_self_pay.allow_simulated_in_production_like'));
        self::assertSame('simulated', config('booking.payment_providers.scopes.deposit.default_provider'));
        self::assertSame('simulated', config('booking.payment_providers.scopes.bill.default_provider'));
        self::assertSame('X-Payment-Signature', config('booking.payment_providers.webhook.signature_header'));
        self::assertSame('X-Payment-Timestamp', config('booking.payment_providers.webhook.timestamp_header'));
        self::assertSame(300, config('booking.payment_providers.webhook.max_age_seconds'));
        self::assertTrue((bool) config('booking.payment_providers.providers.simulated.enabled'));
        self::assertSame('simulated', config('booking.payment_providers.providers.simulated.mode'));
        self::assertFalse((bool) config('booking.payment_providers.providers.generic_http_hmac.enabled'));
        self::assertSame('sandbox', config('booking.payment_providers.providers.generic_http_hmac.mode'));
        self::assertSame('/sessions', config('booking.payment_providers.providers.generic_http_hmac.create_endpoint'));
        self::assertSame('/sessions', config('booking.payment_providers.providers.generic_http_hmac.endpoints.create'));
        self::assertSame(5, config('booking.payment_providers.providers.generic_http_hmac.connect_timeout_seconds'));
        self::assertSame(0, config('booking.payment_providers.providers.generic_http_hmac.retry.attempts'));
        self::assertSame(250, config('booking.payment_providers.providers.generic_http_hmac.retry.sleep_ms'));
        self::assertSame('sha256', config('booking.payment_providers.providers.generic_http_hmac.request.algorithm'));
        self::assertSame('X-Idempotency-Key', config('booking.payment_providers.providers.generic_http_hmac.request.idempotency_header'));
        self::assertSame('sha256', config('booking.payment_providers.providers.generic_http_hmac.webhook.algorithm'));
        self::assertSame('X-Payment-Timestamp', config('booking.payment_providers.providers.generic_http_hmac.webhook.timestamp_header'));
        self::assertSame(300, config('booking.payment_providers.providers.generic_http_hmac.webhook.max_age_seconds'));

        // VNPay config assertions
        self::assertFalse((bool) config('booking.payment_providers.providers.vnpay.enabled'));
        self::assertSame('sandbox', config('booking.payment_providers.providers.vnpay.mode'));
        self::assertSame('', config('booking.payment_providers.providers.vnpay.tmn_code'));
        self::assertSame('', config('booking.payment_providers.providers.vnpay.hash_secret'));

        // MoMo config assertions
        self::assertFalse((bool) config('booking.payment_providers.providers.momo.enabled'));
        self::assertSame('sandbox', config('booking.payment_providers.providers.momo.mode'));
        self::assertSame('', config('booking.payment_providers.providers.momo.partner_code'));
        self::assertSame('', config('booking.payment_providers.providers.momo.access_key'));
        self::assertSame('', config('booking.payment_providers.providers.momo.secret_key'));
    }
}
