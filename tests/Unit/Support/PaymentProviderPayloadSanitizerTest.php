<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Modules\Payments\Infrastructure\Internal\PaymentProviderPayloadSanitizer;
use Tests\TestCase;

final class PaymentProviderPayloadSanitizerTest extends TestCase
{
    public function test_it_sanitizes_session_payloads_for_storage_and_api_presentation(): void
    {
        $payload = [
            'mode' => 'simulated',
            'payment_scope' => 'deposit',
            'payment_url' => 'simulated://deposit-payment/sess-1',
            'instructions' => 'Pay this link',
            'reservation_id' => 55,
            'customer_user_id' => 66,
            'received_headers' => ['x-payment-signature'],
            'raw' => ['secret' => 'provider-secret'],
            '_booking_request' => [
                'idempotency_key' => 'idem-secret',
                'fingerprint' => 'fp-123',
            ],
        ];

        $stored = PaymentProviderPayloadSanitizer::sanitizeSessionPayloadForStorage($payload);

        self::assertSame('simulated', $stored['mode']);
        self::assertSame('deposit', $stored['payment_scope']);
        self::assertSame('simulated://deposit-payment/sess-1', $stored['payment_url']);
        self::assertSame(['fingerprint' => 'fp-123'], $stored['_booking_request']);
        self::assertArrayNotHasKey('reservation_id', $stored);
        self::assertArrayNotHasKey('customer_user_id', $stored);
        self::assertArrayNotHasKey('received_headers', $stored);
        self::assertArrayNotHasKey('raw', $stored);

        $presented = PaymentProviderPayloadSanitizer::sanitizeSessionPayloadForPresentation($payload);

        self::assertSame('simulated', $presented['mode']);
        self::assertSame('simulated://deposit-payment/sess-1', $presented['payment_url']);
        self::assertArrayNotHasKey('_booking_request', $presented);
    }

    public function test_it_sanitizes_payment_response_presentation_without_exposing_internal_request_metadata(): void
    {
        $payload = [
            'source' => 'customer_deposit_payment_session',
            'deposit_payment_session_id' => 91,
            'provider_code' => 'simulated',
            'request_idempotency_key' => 'idem-secret',
            'request_fingerprint' => 'fp-secret',
            'provider_payload' => [
                'mode' => 'simulated',
                'payment_url' => 'simulated://deposit-payment/sess-91',
                '_booking_request' => [
                    'fingerprint' => 'fp-secret',
                ],
                'raw' => ['secret' => 'provider-secret'],
            ],
        ];

        $presented = PaymentProviderPayloadSanitizer::sanitizePaymentResponseForPresentation($payload);

        self::assertIsArray($presented);
        self::assertSame('customer_deposit_payment_session', $presented['source']);
        self::assertSame(91, $presented['deposit_payment_session_id']);
        self::assertArrayNotHasKey('request_idempotency_key', $presented);
        self::assertArrayNotHasKey('request_fingerprint', $presented);
        self::assertSame('simulated://deposit-payment/sess-91', $presented['provider_payload']['payment_url']);
        self::assertArrayNotHasKey('_booking_request', $presented['provider_payload']);
    }

    public function test_it_reduces_webhook_receipt_artifacts_to_redacted_operational_metadata(): void
    {
        $rawBody = json_encode([
            'provider_event_code' => 'evt-1',
            'provider_session_code' => 'sess-1',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'succeeded',
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawBody, 'secret');

        $headers = PaymentProviderPayloadSanitizer::sanitizeWebhookHeaders([
            'X-Payment-Signature' => $signature,
            'X-Payment-Timestamp' => '2026-04-15T10:00:00Z',
            'Content-Type' => 'application/json',
        ]);
        $providerPayload = PaymentProviderPayloadSanitizer::sanitizeWebhookPayloadForStorage(
            [
                'mode' => 'simulated',
                'webhook' => true,
                'received_headers' => ['x-payment-signature'],
                'raw' => ['secret' => 'provider-secret'],
            ],
            [
                'x-payment-signature' => $signature,
                'x-payment-timestamp' => '2026-04-15T10:00:00Z',
            ],
            $rawBody,
            [
                'request_signature' => $signature,
            ],
        );
        $requestBody = PaymentProviderPayloadSanitizer::summarizeWebhookRequestBody($rawBody, [
            'provider_event_code' => 'evt-1',
            'provider_session_code' => 'sess-1',
            'payment_scope' => 'deposit',
            'event_type' => 'payment.session.updated',
            'session_status' => 'Succeeded',
        ]);

        self::assertSame('[redacted]', $headers['x-payment-signature']);
        self::assertSame('2026-04-15T10:00:00Z', $headers['x-payment-timestamp']);
        self::assertSame('simulated', $providerPayload['mode']);
        self::assertTrue((bool) $providerPayload['webhook']);
        self::assertArrayNotHasKey('received_headers', $providerPayload);
        self::assertArrayNotHasKey('raw', $providerPayload);
        self::assertSame(
            PaymentProviderPayloadSanitizer::webhookBodyFingerprint($rawBody),
            $providerPayload['_receipt']['body_fingerprint']
        );
        self::assertSame(
            PaymentProviderPayloadSanitizer::signatureDigest($signature),
            $providerPayload['_receipt']['signature_digest']
        );
        self::assertStringNotContainsString('simulation_outcome', $requestBody);
        self::assertStringContainsString('body_fingerprint', $requestBody);
    }
}
