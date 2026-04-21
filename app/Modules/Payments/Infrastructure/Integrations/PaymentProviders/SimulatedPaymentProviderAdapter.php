<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Integrations\PaymentProviders;

use App\Modules\Payments\Domain\Models\Payment;
use App\Enums\PaymentSessionScope;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Payments\Infrastructure\Integrations\Drivers\SimulatedCustomerPaymentSessionDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SimulatedPaymentProviderAdapter implements PaymentProviderAdapter
{
    public function __construct(
        private readonly SimulatedCustomerPaymentSessionDriver $driver,
    ) {}

    public function code(): string
    {
        return 'simulated';
    }

    public function createSession(PaymentSessionScope $scope, Reservation $reservation, int $customerUserId, array $payload): array
    {
        $session = $this->driver->createSession(
            sessionPrefix: $scope === PaymentSessionScope::Deposit ? 'sim-dep-' : 'sim-bill-',
            paymentPath: $scope === PaymentSessionScope::Deposit ? 'deposit-payment' : 'bill-payment',
            reservationId: (int) $reservation->reservation_id,
            customerUserId: $customerUserId,
            payload: $payload,
            ttlMinutes: $scope === PaymentSessionScope::Deposit
                ? (int) config('booking.payment_providers.providers.simulated.deposit.session_ttl_minutes', config('booking.customer_deposit_payment_simulated_session_ttl_minutes', 30))
                : (int) config('booking.payment_providers.providers.simulated.bill.session_ttl_minutes', config('booking.customer_bill_payment_simulated_session_ttl_minutes', 30)),
            instructions: 'Use refresh/confirm or provider webhook with simulation_outcome=pending|succeeded|failed|cancelled|expired.',
        );

        $providerPayload = (array) ($session['provider_payload'] ?? []);
        $providerPayload['payment_scope'] = $scope->value;
        $providerPayload['provider_mode'] = 'simulated';
        $session['provider_payload'] = $providerPayload;

        return $session;
    }

    public function refreshSession(PaymentSessionScope $scope, Reservation $reservation, Model $session, array $payload): array
    {
        return $this->mutateSession($session, $payload, false, $scope);
    }

    public function confirmSession(PaymentSessionScope $scope, Reservation $reservation, Model $session, array $payload): array
    {
        return $this->mutateSession($session, $payload, true, $scope);
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $enforce = (bool) config('booking.payment_providers.providers.simulated.enforce_signature', true);
        $headers = array_change_key_case($headers, CASE_LOWER);
        $secret = $this->resolveWebhookSecret();
        $headerName = strtolower($this->resolveWebhookSignatureHeader());
        $signature = trim((string) ($headers[$headerName] ?? ''));

        if (! $enforce) {
            return true;
        }

        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $this->stripSignaturePrefix($signature));
    }

    public function parseWebhook(string $rawBody, array $headers): array
    {
        $headers = array_change_key_case($headers, CASE_LOWER);

        try {
            /** @var array<string,mixed> $payload */
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                'payload' => ['Webhook payload must be valid JSON.'],
            ]);
        }

        $providerSessionCode = trim((string) ($payload['provider_session_code'] ?? ''));
        if ($providerSessionCode === '') {
            throw ValidationException::withMessages([
                'provider_session_code' => ['Webhook payload must include provider_session_code.'],
            ]);
        }

        $declaredScope = strtolower(trim((string) ($payload['payment_scope'] ?? Arr::get($payload, 'metadata.payment_scope', ''))));

        $status = $this->normalizeStatus($payload);
        $providerEventCode = trim((string) ($payload['provider_event_code'] ?? ''));
        if ($providerEventCode === '') {
            $providerEventCode = 'sim-webhook-'.sha1($rawBody);
        }

        $providerPayload = array_merge((array) ($payload['provider_payload'] ?? []), [
            'mode' => 'simulated',
            'webhook' => true,
            'payment_scope' => $declaredScope !== '' ? $declaredScope : null,
            'received_headers' => array_keys($headers),
        ]);

        return [
            'provider_code' => 'simulated',
            'provider_event_code' => $providerEventCode,
            'provider_session_code' => $providerSessionCode,
            'provider_payment_code' => Arr::get($payload, 'provider_payment_code'),
            'payment_scope' => $declaredScope !== '' ? $declaredScope : null,
            'event_type' => trim((string) ($payload['event_type'] ?? 'payment.session.updated')) ?: 'payment.session.updated',
            'session_status' => $status,
            'failure_code' => $status === 'Failed' ? (string) ($payload['failure_code'] ?? 'simulated_failure') : null,
            'failure_message' => $status === 'Failed'
                ? (string) ($payload['failure_message'] ?? 'Simulated provider marked this payment as failed via webhook.')
                : null,
            'provider_payload' => $providerPayload,
            'provider_expires_at' => $this->normalizeDateTime($payload['provider_expires_at'] ?? null),
            'request_signature' => trim((string) ($headers[strtolower($this->resolveWebhookSignatureHeader())] ?? '')),
            'occurred_at' => $this->normalizeDateTime($payload['occurred_at'] ?? Carbon::now('UTC')->toIso8601String()),
        ];
    }

    public function supportsWebhookEventType(string $eventType): bool
    {
        return in_array(trim($eventType), [
            'payment.session.updated',
            'payment.session.succeeded',
            'payment.session.failed',
            'payment.session.cancelled',
            'payment.session.expired',
        ], true);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function mutateSession(Model $session, array $payload, bool $isConfirm, PaymentSessionScope $scope): array
    {
        $currentStatus = trim((string) ($session->session_status?->value ?? $session->session_status ?? 'Pending'));

        $result = $this->driver->mutateSession(
            providerSessionCode: (string) $session->provider_session_code,
            providerPaymentCode: $session->provider_payment_code !== null ? (string) $session->provider_payment_code : null,
            paymentMethod: $session->payment_method !== null ? (string) $session->payment_method : null,
            currentStatus: $currentStatus,
            existingPayload: (array) ($session->provider_payload_json ?? []),
            providerExpiresAt: $session->provider_expires_at,
            payload: $payload,
            isConfirm: $isConfirm,
            failureMessage: $scope === PaymentSessionScope::Deposit
                ? 'Simulated provider marked this deposit payment as failed.'
                : 'Simulated provider marked this bill payment as failed.',
        );

        $providerPayload = (array) ($result['provider_payload'] ?? []);
        $providerPayload['payment_scope'] = $scope->value;
        $result['provider_payload'] = $providerPayload;

        return $result;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function normalizeStatus(array $payload): string
    {
        $status = trim((string) ($payload['session_status'] ?? ''));
        if ($status !== '') {
            if (! in_array($status, ['Created', 'Pending', 'Succeeded', 'Failed', 'Cancelled', 'Expired'], true)) {
                throw ValidationException::withMessages([
                    'session_status' => ['Webhook payload has an unsupported session_status.'],
                ]);
            }

            return $status;
        }

        $outcome = strtolower(trim((string) ($payload['simulation_outcome'] ?? 'pending')));

        return match ($outcome) {
            'succeeded' => 'Succeeded',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'expired' => 'Expired',
            default => 'Pending',
        };
    }

    private function normalizeDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, 'UTC')->utc();
    }

    private function resolveWebhookSecret(): string
    {
        foreach ([
            config('booking.payment_providers.providers.simulated.webhook.secret'),
            config('booking.payment_providers.providers.simulated.webhook_secret'),
        ] as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveWebhookSignatureHeader(): string
    {
        foreach ([
            config('booking.payment_providers.providers.simulated.webhook.signature_header'),
            config('booking.payment_providers.webhook.signature_header', 'X-Payment-Signature'),
        ] as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'X-Payment-Signature';
    }

    private function stripSignaturePrefix(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, '=')) {
            [, $tail] = explode('=', $value, 2);

            return trim($tail);
        }

        return $value;
    }
}
