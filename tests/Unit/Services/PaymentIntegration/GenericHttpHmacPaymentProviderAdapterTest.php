<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PaymentIntegration;

use App\Enums\PaymentSessionScope;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\GenericHttpHmacPaymentProviderAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GenericHttpHmacPaymentProviderAdapterTest extends TestCase
{
    public function test_create_session_posts_to_provider_and_normalizes_response(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.generic_http_hmac.base_url', 'https://provider.example.test');
        config()->set('booking.payment_providers.providers.generic_http_hmac.signing_secret', 'secret');
        config()->set('booking.payment_providers.providers.generic_http_hmac.merchant_id', 'merchant-1');
        config()->set('booking.payment_providers.providers.generic_http_hmac.merchant_code', 'merchant-code-1');

        Http::fake([
            'https://provider.example.test/sessions' => Http::response([
                'provider_session_code' => 'gen-session-1',
                'provider_payment_code' => 'gen-payment-1',
                'session_status' => 'paid',
                'payment_url' => 'https://provider.example.test/pay/gen-session-1',
                'provider_expires_at' => '2026-03-28T10:15:00Z',
            ], 200),
        ]);

        $reservation = new Reservation();
        $reservation->reservation_id = 123;
        $reservation->reservation_code = 'RSV-123';
        $reservation->branch_id = 2;

        $result = app(GenericHttpHmacPaymentProviderAdapter::class)->createSession(
            PaymentSessionScope::Deposit,
            $reservation,
            44,
            [
                'amount' => 150000,
                'currency' => 'VND',
                'payment_method' => 'Online',
                'idempotency_key' => 'deposit-session-123',
            ],
        );

        Http::assertSent(static function ($request): bool {
            return $request->url() === 'https://provider.example.test/sessions'
                && $request->hasHeader('X-Idempotency-Key', 'deposit-session-123')
                && $request->hasHeader('X-Merchant-Id', 'merchant-1')
                && $request->hasHeader('X-Merchant-Code', 'merchant-code-1');
        });

        self::assertSame('generic_http_hmac', $result['provider_code']);
        self::assertSame('gen-session-1', $result['provider_session_code']);
        self::assertSame('gen-payment-1', $result['provider_payment_code']);
        self::assertSame('Succeeded', $result['session_status']);
        self::assertSame('deposit', $result['provider_payload']['payment_scope']);
    }

    public function test_confirm_session_posts_to_provider_when_confirm_endpoint_is_configured(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.generic_http_hmac.base_url', 'https://provider.example.test');
        config()->set('booking.payment_providers.providers.generic_http_hmac.confirm_endpoint', '/sessions/{provider_session_code}/confirm');
        config()->set('booking.payment_providers.providers.generic_http_hmac.signing_secret', 'secret');

        Http::fake([
            'https://provider.example.test/sessions/gen-session-9/confirm' => Http::response([
                'provider_session_code' => 'gen-session-9',
                'provider_payment_code' => 'gen-payment-9',
                'session_status' => 'paid',
                'event_type' => 'payment.session.completed',
            ], 200),
        ]);

        $reservation = new Reservation();
        $reservation->reservation_id = 321;
        $reservation->reservation_code = 'RSV-321';

        $session = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $guarded = [];
        };
        $session->provider_session_code = 'gen-session-9';
        $session->provider_payment_code = null;
        $session->payment_method = 'Online';
        $session->session_status = 'Pending';
        $session->amount = '75000.00';
        $session->currency = 'VND';
        $session->customer_user_id = 77;
        $session->provider_payload_json = ['payment_scope' => 'deposit'];

        $result = app(GenericHttpHmacPaymentProviderAdapter::class)->confirmSession(
            PaymentSessionScope::Deposit,
            $reservation,
            $session,
            ['idempotency_key' => 'confirm-9'],
        );

        Http::assertSent(static function ($request): bool {
            return $request->url() === 'https://provider.example.test/sessions/gen-session-9/confirm'
                && $request->hasHeader('X-Idempotency-Key', 'confirm-9');
        });

        self::assertSame('generic_http_hmac', $result['provider_code']);
        self::assertSame('gen-payment-9', $result['provider_payment_code']);
        self::assertSame('Succeeded', $result['session_status']);
        self::assertSame('payment.session.completed', $result['event_type']);
    }

    public function test_parse_webhook_normalizes_mapped_status_and_generated_event_code(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.supported_event_types', ['payment.session.updated']);
        config()->set('booking.payment_providers.providers.generic_http_hmac.signing_secret', 'secret');

        $rawBody = json_encode([
            'provider_session_code' => 'gen-session-2',
            'payment_scope' => 'bill',
            'session_status' => 'paid',
            'event_type' => 'payment.session.updated',
        ], JSON_THROW_ON_ERROR);

        $result = app(GenericHttpHmacPaymentProviderAdapter::class)->parseWebhook($rawBody, [
            'x-payment-signature' => hash_hmac('sha256', $rawBody, 'secret'),
        ]);

        self::assertSame('generic_http_hmac', $result['provider_code']);
        self::assertSame('gen-session-2', $result['provider_session_code']);
        self::assertSame('bill', $result['payment_scope']);
        self::assertSame('Succeeded', $result['session_status']);
        self::assertSame('payment.session.updated', $result['event_type']);
        self::assertNotSame('', $result['provider_event_code']);
    }

    public function test_parse_webhook_treats_default_completed_event_as_succeeded_without_explicit_status(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.supported_event_types', ['payment.session.completed']);
        config()->set('booking.payment_providers.providers.generic_http_hmac.signing_secret', 'secret');

        $rawBody = json_encode([
            'provider_session_code' => 'gen-session-3',
            'payment_scope' => 'deposit',
            'event_type' => 'payment.session.completed',
        ], JSON_THROW_ON_ERROR);

        $result = app(GenericHttpHmacPaymentProviderAdapter::class)->parseWebhook($rawBody, [
            'x-payment-signature' => hash_hmac('sha256', $rawBody, 'secret'),
        ]);

        self::assertSame('Succeeded', $result['session_status']);
        self::assertSame('payment.session.completed', $result['event_type']);
    }

    public function test_supported_webhook_event_types_respect_configured_allow_list_case_insensitively(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.supported_event_types', [
            'payment.session.updated',
            'payment.session.completed',
        ]);

        $adapter = app(GenericHttpHmacPaymentProviderAdapter::class);

        self::assertTrue($adapter->supportsWebhookEventType('payment.session.updated'));
        self::assertTrue($adapter->supportsWebhookEventType('PAYMENT.SESSION.COMPLETED'));
        self::assertFalse($adapter->supportsWebhookEventType('provider.healthcheck.ping'));
        self::assertFalse($adapter->supportsWebhookEventType(''));
    }

    public function test_supported_webhook_event_types_allow_any_non_empty_event_when_allow_list_is_empty(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.supported_event_types', []);

        $adapter = app(GenericHttpHmacPaymentProviderAdapter::class);

        self::assertTrue($adapter->supportsWebhookEventType('provider.healthcheck.ping'));
        self::assertFalse($adapter->supportsWebhookEventType(''));
    }

    public function test_verify_webhook_signature_rejects_stale_timestamp_beyond_replay_window(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.secret', 'secret');
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.max_age_seconds', 300);

        $rawBody = json_encode([
            'provider_session_code' => 'gen-session-stale',
            'payment_scope' => 'deposit',
            'event_type' => 'payment.session.updated',
        ], JSON_THROW_ON_ERROR);
        $staleTimestamp = now('UTC')->subMinutes(10)->toIso8601String();

        $verified = app(GenericHttpHmacPaymentProviderAdapter::class)->verifyWebhookSignature($rawBody, [
            'x-payment-signature' => hash_hmac('sha256', $rawBody, 'secret'),
            'x-payment-timestamp' => $staleTimestamp,
        ]);

        self::assertFalse($verified);
    }
}
