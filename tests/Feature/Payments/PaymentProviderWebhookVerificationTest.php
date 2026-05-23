<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class PaymentProviderWebhookVerificationTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
    }

    public function test_generic_hmac_webhook_with_valid_signature_bypasses_signature_guard_and_fails_with_failed_delivery_for_missing_session(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.secret', 'test-signing-key');
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.max_age_seconds', 300);

        $payload = [
            'provider_session_code' => 'non-existent-session-123',
            'payment_scope' => 'deposit',
            'event_type' => 'payment.session.updated',
            'session_status' => 'paid',
        ];
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $currentTimestamp = Carbon::now('UTC')->toIso8601String();
        $signature = hash_hmac('sha256', $rawBody, 'test-signing-key');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Payment-Signature' => $signature,
            'X-Payment-Timestamp' => $currentTimestamp,
        ])->postJson('/api/v1/payments/providers/generic_http_hmac/webhooks', $payload);

        // Should bypass signature check and return 202 showing Failed delivery status due to scope mismatch
        $response->assertStatus(202)
            ->assertJsonPath('data.delivery_status', 'Failed')
            ->assertJsonPath('data.failure_message', 'Webhook payload payment_scope does not match the stored payment session scope.');
    }

    public function test_generic_hmac_webhook_with_invalid_signature_is_rejected_with_401(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.secret', 'test-signing-key');

        $payload = [
            'provider_session_code' => 'non-existent-session-123',
            'payment_scope' => 'deposit',
            'event_type' => 'payment.session.updated',
            'session_status' => 'paid',
        ];
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $currentTimestamp = Carbon::now('UTC')->toIso8601String();
        // Alter payload signature
        $badSignature = hash_hmac('sha256', $rawBody . 'tampered', 'test-signing-key');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Payment-Signature' => $badSignature,
            'X-Payment-Timestamp' => $currentTimestamp,
        ])->postJson('/api/v1/payments/providers/generic_http_hmac/webhooks', $payload);

        $response->assertStatus(401)
            ->assertJsonPath('error_code', 'invalid_signature')
            ->assertJsonPath('message', 'Webhook signature verification failed.');
    }

    public function test_generic_hmac_webhook_with_missing_signature_is_rejected_with_401(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.secret', 'test-signing-key');

        $payload = [
            'provider_session_code' => 'non-existent-session-123',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/v1/payments/providers/generic_http_hmac/webhooks', $payload);

        $response->assertStatus(401)
            ->assertJsonPath('error_code', 'invalid_signature');
    }

    public function test_generic_hmac_webhook_with_stale_timestamp_is_rejected_with_401(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.secret', 'test-signing-key');
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.max_age_seconds', 300);

        $payload = [
            'provider_session_code' => 'non-existent-session-123',
        ];
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        // Exceed max age window (10 minutes ago)
        $staleTimestamp = Carbon::now('UTC')->subMinutes(10)->toIso8601String();
        $signature = hash_hmac('sha256', $rawBody, 'test-signing-key');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Payment-Signature' => $signature,
            'X-Payment-Timestamp' => $staleTimestamp,
        ])->postJson('/api/v1/payments/providers/generic_http_hmac/webhooks', $payload);

        $response->assertStatus(401)
            ->assertJsonPath('error_code', 'invalid_signature');
    }

    public function test_simulated_webhook_enforces_signature_and_is_rejected_if_invalid(): void
    {
        config()->set('booking.payment_providers.providers.simulated.enabled', true);
        config()->set('booking.payment_providers.providers.simulated.enforce_signature', true);
        config()->set('booking.payment_providers.providers.simulated.webhook_secret', 'sim-secret-key');

        $payload = [
            'provider_session_code' => 'sim-sess-123',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'succeeded',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Payment-Signature' => 'bad-simulated-signature',
        ])->postJson('/api/v1/payments/providers/simulated/webhooks', $payload);

        $response->assertStatus(401)
            ->assertJsonPath('error_code', 'invalid_signature');
    }
}
