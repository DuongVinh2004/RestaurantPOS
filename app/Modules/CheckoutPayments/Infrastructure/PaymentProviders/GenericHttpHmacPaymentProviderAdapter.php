<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Infrastructure\PaymentProviders;

use App\Enums\PaymentSessionScope;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenericHttpHmacPaymentProviderAdapter implements PaymentProviderAdapter
{
    public function code(): string
    {
        return 'generic_http_hmac';
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function createSession(PaymentSessionScope $scope, Reservation $reservation, int $customerUserId, array $payload): array
    {
        $endpoint = $this->resolveEndpoint($scope, 'create');

        if ($endpoint === '') {
            throw ValidationException::withMessages([
                'provider_code' => ['Payment provider create endpoint is not configured.'],
            ]);
        }

        $requestPayload = [
            'reservation_id' => (int) $reservation->reservation_id,
            'reservation_code' => (string) ($reservation->reservation_code ?? ''),
            'merchant_reference' => $this->merchantReference($scope, $reservation),
            'customer_user_id' => $customerUserId,
            'payment_scope' => $scope->value,
            'provider_mode' => $this->providerMode(),
            'amount' => $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'payment_method' => $payload['payment_method'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
            'metadata' => [
                'payment_scope' => $scope->value,
                'reservation_id' => (int) $reservation->reservation_id,
                'customer_user_id' => $customerUserId,
                'merchant_reference' => $this->merchantReference($scope, $reservation),
                'provider_mode' => $this->providerMode(),
            ],
        ];

        $requestPayload = array_replace_recursive($requestPayload, (array) Arr::get($payload, 'provider_request_overrides', []));
        $requestPayload = $this->compactNulls($requestPayload);

        $body = $this->dispatchProviderRequest(
            method: 'POST',
            endpoint: $endpoint,
            payload: $requestPayload,
            context: sprintf('%s create session', $scope->value),
        );

        return $this->normalizeProviderSessionPayload(
            scope: $scope,
            payload: $body,
            fallbackSessionCode: 'gph-'.Str::uuid()->toString(),
        );
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function refreshSession(PaymentSessionScope $scope, Reservation $reservation, Model $session, array $payload): array
    {
        $endpoint = $this->resolveEndpoint($scope, 'refresh');
        if ($endpoint !== '') {
            return $this->dispatchSessionMutation($scope, $reservation, $session, $payload, 'refresh', $endpoint);
        }

        return $this->normalizeRefreshOrConfirmPayload($scope, $session, $payload, false);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function confirmSession(PaymentSessionScope $scope, Reservation $reservation, Model $session, array $payload): array
    {
        $endpoint = $this->resolveEndpoint($scope, 'confirm');
        if ($endpoint !== '') {
            return $this->dispatchSessionMutation($scope, $reservation, $session, $payload, 'confirm', $endpoint);
        }

        return $this->normalizeRefreshOrConfirmPayload($scope, $session, $payload, true);
    }

    /**
     * @param  array<string,string>  $headers
     */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $config = $this->providerConfig();
        $headerName = strtolower($this->firstConfiguredString([
            Arr::get($config, 'webhook.signature_header'),
            Arr::get($config, 'signature_header'),
            config('booking.payment_providers.webhook.signature_header', 'X-Payment-Signature'),
        ], 'X-Payment-Signature'));
        $provided = trim((string) ($headers[$headerName] ?? ''));
        $timestampHeader = strtolower($this->firstConfiguredString([
            Arr::get($config, 'webhook.timestamp_header'),
            config('booking.payment_providers.webhook.timestamp_header', 'X-Payment-Timestamp'),
        ], 'X-Payment-Timestamp'));
        $timestamp = trim((string) ($headers[$timestampHeader] ?? ''));
        $secret = $this->firstConfiguredString([
            Arr::get($config, 'webhook.secret'),
            Arr::get($config, 'webhook_secret'),
            Arr::get($config, 'signing_secret'),
            Arr::get($config, 'api_secret'),
        ]);
        $algorithm = strtolower(trim((string) (Arr::get($config, 'webhook.algorithm') ?? 'sha256')));
        $maxAgeSeconds = max(0, (int) Arr::get($config, 'webhook.max_age_seconds', config('booking.payment_providers.webhook.max_age_seconds', 300)));

        if ($secret === '' || $provided === '') {
            return false;
        }

        if ($maxAgeSeconds > 0 && $timestamp !== '') {
            try {
                $timestampAt = Carbon::parse($timestamp, 'UTC')->utc();
            } catch (\Throwable) {
                return false;
            }

            if (abs($timestampAt->diffInSeconds(Carbon::now('UTC'), false)) > $maxAgeSeconds) {
                return false;
            }
        }

        $expected = hash_hmac($algorithm, $rawBody, $secret);

        return hash_equals($expected, $this->stripSignaturePrefix($provided));
    }

    /**
     * @param  array<string,string>  $headers
     * @return array<string,mixed>
     */
    public function parseWebhook(string $rawBody, array $headers): array
    {
        try {
            /** @var array<string,mixed> $payload */
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                'payload' => ['Webhook payload must be valid JSON.'],
            ]);
        }

        $config = $this->providerConfig();
        $scope = strtolower(trim((string) (
            Arr::get($payload, (string) (Arr::get($config, 'webhook.scope_field') ?? 'payment_scope'))
            ?? Arr::get($payload, 'metadata.payment_scope', '')
        )));
        if ($scope !== '' && ! in_array($scope, ['deposit', 'bill'], true)) {
            throw ValidationException::withMessages([
                'payment_scope' => ['Payment provider returned an unsupported payment_scope.'],
            ]);
        }

        $sessionCode = $this->firstString($payload, [
            (string) (Arr::get($config, 'webhook.session_code_field') ?? 'provider_session_code'),
            'session.code',
            'data.provider_session_code',
            'data.session_id',
            'session_id',
            'id',
        ]);

        if ($sessionCode === '') {
            throw ValidationException::withMessages([
                'provider_session_code' => ['Webhook payload must include provider_session_code.'],
            ]);
        }

        $eventType = $this->normalizeEventType(
            $this->firstString($payload, [
                (string) (Arr::get($config, 'webhook.event_type_field') ?? 'event_type'),
                'type',
                'event',
                'name',
            ])
        );

        $status = $this->normalizeStatus(
            value: $this->firstString($payload, [
                (string) (Arr::get($config, 'webhook.status_field') ?? 'session_status'),
                'status',
                'payment_status',
                'data.status',
                'session.status',
            ]),
            eventType: $eventType,
        );

        $providerEventCode = $this->firstString($payload, [
            (string) (Arr::get($config, 'webhook.event_code_field') ?? 'provider_event_code'),
            'event_id',
            'id',
        ]);
        if ($providerEventCode === '') {
            $providerEventCode = 'generic-webhook-'.sha1($rawBody);
        }

        $headerName = strtolower($this->firstConfiguredString([
            Arr::get($config, 'webhook.signature_header'),
            Arr::get($config, 'signature_header'),
            config('booking.payment_providers.webhook.signature_header', 'X-Payment-Signature'),
        ], 'X-Payment-Signature'));

        return [
            'provider_code' => $this->code(),
            'provider_event_code' => $providerEventCode,
            'provider_session_code' => $sessionCode,
            'provider_payment_code' => $this->nullableString($this->firstString($payload, [
                (string) (Arr::get($config, 'webhook.payment_code_field') ?? 'provider_payment_code'),
                'payment_id',
                'payment.code',
                'data.payment_id',
            ])),
            'payment_scope' => $scope !== '' ? $scope : null,
            'event_type' => $eventType,
            'session_status' => $status,
            'failure_code' => $status === 'Failed' ? $this->nullableString($this->firstString($payload, ['failure_code', 'error.code', 'error_code'])) : null,
            'failure_message' => $status === 'Failed' ? $this->nullableString($this->firstString($payload, ['failure_message', 'error.message', 'message'])) : null,
            'provider_payload' => [
                'mode' => 'generic_http_hmac',
                'provider_mode' => $this->providerMode(),
                'webhook' => true,
                'raw' => $payload,
            ],
            'provider_expires_at' => $this->normalizeDateTime($this->firstString($payload, [
                (string) (Arr::get($config, 'webhook.expires_at_field') ?? 'provider_expires_at'),
                'expires_at',
                'session.expires_at',
            ])),
            'request_signature' => trim((string) ($headers[$headerName] ?? '')),
            'occurred_at' => $this->normalizeDateTime($this->firstString($payload, [
                (string) (Arr::get($config, 'webhook.occurred_at_field') ?? 'occurred_at'),
                'created_at',
                'event_created_at',
                'timestamp',
            ])) ?? Carbon::now('UTC'),
        ];
    }

    public function supportsWebhookEventType(string $eventType): bool
    {
        $eventType = trim($eventType);
        if ($eventType === '') {
            return false;
        }

        $supported = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            (array) ($this->providerConfig()['supported_event_types'] ?? [])
        )));

        if ($supported === []) {
            return true;
        }

        return in_array(strtolower($eventType), $supported, true);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalizeRefreshOrConfirmPayload(PaymentSessionScope $scope, Model $session, array $payload, bool $isConfirm): array
    {
        $status = $this->normalizeStatus(
            value: trim((string) ($payload['session_status'] ?? ($isConfirm ? 'Succeeded' : ($session->session_status?->value ?? $session->session_status ?? 'Pending')))),
            eventType: trim((string) ($payload['event_type'] ?? 'payment.session.updated')),
        );

        return [
            'provider_code' => $this->code(),
            'provider_session_code' => (string) $session->provider_session_code,
            'provider_payment_code' => $this->nullableString(trim((string) ($payload['provider_payment_code'] ?? ($session->provider_payment_code !== null ? (string) $session->provider_payment_code : '')))),
            'payment_method' => trim((string) ($session->payment_method ?? $payload['payment_method'] ?? 'Online')) ?: 'Online',
            'session_status' => $status,
            'failure_code' => $status === 'Failed' ? (string) ($payload['failure_code'] ?? 'provider_failed') : null,
            'failure_message' => $status === 'Failed' ? (string) ($payload['failure_message'] ?? 'Payment provider marked the session as failed.') : null,
            'provider_payload' => array_merge((array) ($session->provider_payload_json ?? []), [
                'mode' => 'generic_http_hmac',
                'provider_mode' => $this->providerMode(),
                'payment_scope' => $scope->value,
                'mutated_by' => $isConfirm ? 'confirm' : 'refresh',
            ]),
            'provider_expires_at' => $this->normalizeDateTime($payload['provider_expires_at'] ?? null),
            'event_type' => trim((string) ($payload['event_type'] ?? 'payment.session.updated')) ?: 'payment.session.updated',
            'payment_scope' => $scope->value,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalizeProviderSessionPayload(PaymentSessionScope $scope, array $payload, string $fallbackSessionCode): array
    {
        $status = $this->normalizeStatus(
            value: $this->firstString($payload, [
                'session_status',
                'status',
                'payment_status',
                'data.status',
                'session.status',
            ]),
            eventType: $this->firstString($payload, ['event_type', 'type']),
        );

        $sessionCode = $this->firstString($payload, [
            'provider_session_code',
            'session.code',
            'session_id',
            'id',
            'data.session_id',
        ]);
        if ($sessionCode === '') {
            $sessionCode = $fallbackSessionCode;
        }

        return [
            'provider_code' => $this->code(),
            'provider_session_code' => $sessionCode,
            'provider_payment_code' => $this->nullableString($this->firstString($payload, [
                'provider_payment_code',
                'payment_id',
                'payment.code',
                'data.payment_id',
            ])),
            'payment_method' => trim($this->firstString($payload, ['payment_method', 'method', 'channel'])) ?: 'Online',
            'session_status' => $status,
            'provider_payload' => [
                'mode' => 'generic_http_hmac',
                'provider_mode' => $this->providerMode(),
                'payment_scope' => $scope->value,
                'payment_url' => $this->nullableString($this->firstString($payload, ['payment_url', 'checkout_url', 'redirect_url', 'links.checkout'])),
                'raw' => $payload,
            ],
            'provider_expires_at' => $this->normalizeDateTime($this->firstString($payload, ['provider_expires_at', 'expires_at', 'session.expires_at'])),
            'payment_url' => $this->nullableString($this->firstString($payload, ['payment_url', 'checkout_url', 'redirect_url', 'links.checkout'])),
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function requestHeaders(string $method, string $endpoint, array $payload): array
    {
        $config = $this->providerConfig();
        $timestamp = Carbon::now('UTC')->toIso8601String();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $secret = $this->firstConfiguredString([
            Arr::get($config, 'request.secret'),
            Arr::get($config, 'signing_secret'),
            Arr::get($config, 'api_secret'),
        ]);
        $algorithm = strtolower(trim((string) (Arr::get($config, 'request.algorithm') ?? 'sha256')));
        $headerName = $this->firstConfiguredString([
            Arr::get($config, 'request.signature_header'),
            config('booking.payment_providers.webhook.signature_header', 'X-Payment-Signature'),
        ], 'X-Payment-Signature');
        $keyHeaderName = (string) (Arr::get($config, 'request.key_header') ?? 'X-Payment-Key');
        $timestampHeaderName = (string) (Arr::get($config, 'request.timestamp_header') ?? 'X-Payment-Timestamp');
        $idempotencyHeaderName = trim((string) (Arr::get($config, 'request.idempotency_header') ?? 'X-Idempotency-Key'));
        $merchantIdHeaderName = trim((string) (Arr::get($config, 'request.merchant_id_header') ?? 'X-Merchant-Id'));
        $merchantCodeHeaderName = trim((string) (Arr::get($config, 'request.merchant_code_header') ?? 'X-Merchant-Code'));
        $signingString = strtoupper($method)."\n".ltrim($endpoint, '/')."\n".$timestamp."\n".$body;

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            $timestampHeaderName => $timestamp,
        ];

        $apiKey = trim((string) (Arr::get($config, 'api_key') ?? ''));
        if ($apiKey !== '') {
            $headers[$keyHeaderName] = $apiKey;
        }

        $merchantId = trim((string) (Arr::get($config, 'merchant_id') ?? ''));
        if ($merchantId !== '' && $merchantIdHeaderName !== '') {
            $headers[$merchantIdHeaderName] = $merchantId;
        }

        $merchantCode = trim((string) (Arr::get($config, 'merchant_code') ?? ''));
        if ($merchantCode !== '' && $merchantCodeHeaderName !== '') {
            $headers[$merchantCodeHeaderName] = $merchantCode;
        }

        $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '' && $idempotencyHeaderName !== '') {
            $headers[$idempotencyHeaderName] = $idempotencyKey;
        }

        if ($secret !== '') {
            $headers[$headerName] = hash_hmac($algorithm, $signingString, $secret);
        }

        foreach ((array) Arr::get($config, 'request.headers', []) as $name => $value) {
            if (is_string($name) && $name !== '') {
                $headers[$name] = is_scalar($value) ? (string) $value : json_encode($value);
            }
        }

        return $headers;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function dispatchProviderRequest(string $method, string $endpoint, array $payload, string $context): array
    {
        $config = $this->providerConfig();
        $absoluteUrl = $this->absoluteUrl($endpoint);
        $request = Http::withHeaders($this->requestHeaders($method, $endpoint, $payload))
            ->connectTimeout(max(1, (int) Arr::get($config, 'connect_timeout_seconds', 5)))
            ->timeout(max(1, (int) Arr::get($config, 'timeout_seconds', 15)))
            ->acceptJson();

        $retryAttempts = max(0, (int) Arr::get($config, 'retry.attempts', 0));
        if ($retryAttempts > 0) {
            $request = $request->retry(
                $retryAttempts,
                max(0, (int) Arr::get($config, 'retry.sleep_ms', 250)),
                static fn (\Exception $exception, $request): bool => true,
                false,
            );
        }

        $response = $request->send(strtoupper($method), $absoluteUrl, [
            'json' => $payload,
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'provider_code' => [sprintf('Payment provider %s request failed with HTTP %d.', $context, $response->status())],
            ]);
        }

        /** @var array<string,mixed> $body */
        $body = $response->json() ?? [];

        return $body;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function dispatchSessionMutation(
        PaymentSessionScope $scope,
        Reservation $reservation,
        Model $session,
        array $payload,
        string $action,
        string $endpoint
    ): array {
        $interpolatedEndpoint = $this->interpolateEndpoint($endpoint, $session);
        $requestPayload = [
            'reservation_id' => (int) $reservation->reservation_id,
            'reservation_code' => (string) ($reservation->reservation_code ?? ''),
            'customer_user_id' => $session->customer_user_id !== null ? (int) $session->customer_user_id : null,
            'provider_session_code' => (string) $session->provider_session_code,
            'provider_payment_code' => $session->provider_payment_code !== null ? (string) $session->provider_payment_code : null,
            'payment_scope' => $scope->value,
            'provider_mode' => $this->providerMode(),
            'merchant_reference' => $this->merchantReference($scope, $reservation),
            'amount' => $payload['amount'] ?? $session->amount,
            'currency' => $payload['currency'] ?? $session->currency,
            'payment_method' => $payload['payment_method'] ?? $session->payment_method,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
            'metadata' => [
                'payment_scope' => $scope->value,
                'reservation_id' => (int) $reservation->reservation_id,
                'provider_session_code' => (string) $session->provider_session_code,
                'provider_mode' => $this->providerMode(),
            ],
        ];

        $requestPayload = array_replace_recursive($requestPayload, (array) Arr::get($payload, 'provider_request_overrides', []));
        $requestPayload = $this->compactNulls($requestPayload);
        $responsePayload = $this->dispatchProviderRequest(
            method: 'POST',
            endpoint: $interpolatedEndpoint,
            payload: $requestPayload,
            context: sprintf('%s %s session', $scope->value, $action),
        );

        return $this->normalizeRefreshOrConfirmPayload(
            $scope,
            $session,
            array_merge($payload, $responsePayload),
            $action === 'confirm',
        );
    }

    private function resolveEndpoint(PaymentSessionScope $scope, string $action): string
    {
        $config = $this->providerConfig();

        return $this->firstConfiguredString([
            $this->resolveScopeConfig($scope, $action.'_endpoint'),
            $this->resolveScopeConfig($scope, 'session_'.$action.'_endpoint'),
            $this->resolveScopeConfig($scope, 'endpoints.'.$action),
            Arr::get($config, $action.'_endpoint'),
            Arr::get($config, 'session_'.$action.'_endpoint'),
            Arr::get($config, 'endpoints.'.$action),
        ]);
    }

    private function merchantReference(PaymentSessionScope $scope, Reservation $reservation): string
    {
        $prefix = trim((string) ($this->resolveScopeConfig($scope, 'merchant_reference_prefix') ?? ''));
        if ($prefix === '') {
            $prefix = $scope === PaymentSessionScope::Deposit ? 'reservation-deposit-' : 'reservation-bill-';
        }

        return $prefix.(int) $reservation->reservation_id;
    }

    private function providerMode(): string
    {
        return trim((string) Arr::get($this->providerConfig(), 'mode', 'sandbox')) ?: 'sandbox';
    }

    private function interpolateEndpoint(string $endpoint, Model $session): string
    {
        return str_replace(
            ['{provider_session_code}', ':provider_session_code', '{session_id}', ':session_id'],
            [(string) $session->provider_session_code, (string) $session->provider_session_code, (string) $session->provider_session_code, (string) $session->provider_session_code],
            $endpoint,
        );
    }

    private function absoluteUrl(string $endpoint): string
    {
        $baseUrl = rtrim((string) (Arr::get($this->providerConfig(), 'base_url') ?? ''), '/');
        $endpoint = '/'.ltrim($endpoint, '/');

        return $baseUrl === '' ? $endpoint : $baseUrl.$endpoint;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function compactNulls(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->compactNulls($value);
            }
            if ($payload[$key] === null) {
                unset($payload[$key]);
            }
        }

        return $payload;
    }

    private function normalizeStatus(string $value, string $eventType = ''): string
    {
        $value = trim($value);
        if ($value === '' && $eventType !== '') {
            $value = $eventType;
        }

        if ($value === '') {
            return 'Pending';
        }

        $canonical = [
            'Created', 'Pending', 'Succeeded', 'Failed', 'Cancelled', 'Expired',
        ];
        if (in_array($value, $canonical, true)) {
            return $value;
        }

        $normalizedKey = $this->statusKey($value);
        $statusMap = (array) Arr::get($this->providerConfig(), 'status_map', []);
        foreach ($statusMap as $providerValue => $mappedStatus) {
            if ($this->statusKey((string) $providerValue) === $normalizedKey) {
                $mappedStatus = trim((string) $mappedStatus);
                if (in_array($mappedStatus, $canonical, true)) {
                    return $mappedStatus;
                }
            }
        }

        $fallbackMap = [
            'created' => 'Created',
            'new' => 'Created',
            'initialized' => 'Created',
            'initialised' => 'Created',
            'open' => 'Pending',
            'pending' => 'Pending',
            'processing' => 'Pending',
            'requiresaction' => 'Pending',
            'requirespaymentmethod' => 'Pending',
            'awaitingpayment' => 'Pending',
            'authorized' => 'Pending',
            'authorised' => 'Pending',
            'succeeded' => 'Succeeded',
            'success' => 'Succeeded',
            'paid' => 'Succeeded',
            'completed' => 'Succeeded',
            'complete' => 'Succeeded',
            'captured' => 'Succeeded',
            'settled' => 'Succeeded',
            'failed' => 'Failed',
            'failure' => 'Failed',
            'declined' => 'Failed',
            'rejected' => 'Failed',
            'error' => 'Failed',
            'errored' => 'Failed',
            'cancelled' => 'Cancelled',
            'canceled' => 'Cancelled',
            'voided' => 'Cancelled',
            'aborted' => 'Cancelled',
            'expired' => 'Expired',
            'timedout' => 'Expired',
            'timeout' => 'Expired',
            'paymentsessionupdated' => 'Pending',
            'paymentsessioncreated' => 'Created',
            'paymentsessioncompleted' => 'Succeeded',
            'paymentsessionsucceeded' => 'Succeeded',
            'paymentsessionfailed' => 'Failed',
            'paymentsessioncancelled' => 'Cancelled',
            'paymentsessionexpired' => 'Expired',
        ];

        if (array_key_exists($normalizedKey, $fallbackMap)) {
            return $fallbackMap[$normalizedKey];
        }

        throw ValidationException::withMessages([
            'session_status' => ['Payment provider returned an unsupported session_status.'],
        ]);
    }

    private function normalizeEventType(string $value): string
    {
        $value = trim($value);

        return $value !== '' ? $value : 'payment.session.updated';
    }

    private function statusKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/i', '', strtolower(trim($value))) ?? '';
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  list<string>  $paths
     */
    private function firstString(array $payload, array $paths): string
    {
        foreach ($paths as $path) {
            $value = Arr::get($payload, $path);
            if ($value === null) {
                continue;
            }
            if (is_scalar($value)) {
                $string = trim((string) $value);
                if ($string !== '') {
                    return $string;
                }
            }
        }

        return '';
    }

    private function nullableString(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeDateTime(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        return Carbon::parse($string, 'UTC')->utc();
    }

    /**
     * @return array<string,mixed>
     */
    private function providerConfig(): array
    {
        return (array) config('booking.payment_providers.providers.generic_http_hmac', []);
    }

    private function resolveScopeConfig(PaymentSessionScope $scope, string $key): mixed
    {
        return Arr::get($this->providerConfig(), $scope->value.'.'.$key);
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

    /**
     * @param  list<mixed>  $candidates
     */
    private function firstConfiguredString(array $candidates, string $default = ''): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }
}
