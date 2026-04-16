<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class PaymentProviderPayloadSanitizer
{
    private const REDACTED = '[redacted]';

    /**
     * @var list<string>
     */
    private const SESSION_STORAGE_KEYS = [
        'mode',
        'provider_mode',
        'payment_scope',
        'provider_action',
        'payment_url',
        'instructions',
        'mutated_by',
        'settlement_skip_reason',
        'last_outcome',
        'webhook',
        'sticky_terminal',
        'confirmed_via_api',
    ];

    /**
     * @return array<string,mixed>
     */
    public static function sanitizeSessionPayloadForStorage(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $sanitized = [];

        foreach (self::SESSION_STORAGE_KEYS as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = self::sanitizeSessionValue($payload[$key]);
            if ($value !== null) {
                $sanitized[$key] = $value;
            }
        }

        $bookingRequest = self::sanitizeBookingRequestMeta($payload['_booking_request'] ?? null);
        if ($bookingRequest !== []) {
            $sanitized['_booking_request'] = $bookingRequest;
        }

        $receiptMeta = self::sanitizeReceiptMeta($payload['_receipt'] ?? null);
        if ($receiptMeta !== []) {
            $sanitized['_receipt'] = $receiptMeta;
        }

        return $sanitized;
    }

    /**
     * @return array<string,mixed>
     */
    public static function sanitizeSessionPayloadForPresentation(mixed $payload): array
    {
        $sanitized = self::sanitizeSessionPayloadForStorage($payload);
        unset($sanitized['_booking_request'], $sanitized['_receipt']);

        return $sanitized;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function sanitizePaymentResponseForStorage(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            if ($key === 'provider_payload') {
                $providerPayload = self::sanitizeSessionPayloadForStorage($value);
                if ($providerPayload !== []) {
                    $sanitized[$key] = $providerPayload;
                }

                continue;
            }

            $normalized = self::sanitizePaymentResponseValue($value, true);
            if ($normalized !== null) {
                $sanitized[$key] = $normalized;
            }
        }

        return $sanitized;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function sanitizePaymentResponseForPresentation(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            if (in_array($key, ['request_idempotency_key', 'request_fingerprint'], true)) {
                continue;
            }

            if ($key === 'provider_payload') {
                $providerPayload = self::sanitizeSessionPayloadForPresentation($value);
                if ($providerPayload !== []) {
                    $sanitized[$key] = $providerPayload;
                }

                continue;
            }

            $normalized = self::sanitizePaymentResponseValue($value, false);
            if ($normalized !== null) {
                $sanitized[$key] = $normalized;
            }
        }

        return $sanitized !== [] ? $sanitized : null;
    }

    /**
     * @param  array<string,string>  $headers
     * @return array<string,string>
     */
    public static function sanitizeWebhookHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $key => $value) {
            $normalizedKey = strtolower(trim((string) $key));
            if ($normalizedKey === '') {
                continue;
            }

            if (self::isSensitiveHeader($normalizedKey)) {
                $sanitized[$normalizedKey] = self::REDACTED;

                continue;
            }

            $sanitized[$normalizedKey] = self::truncate((string) $value, 255);
        }

        ksort($sanitized);

        return $sanitized;
    }

    /**
     * @param  array<string,mixed>  $providerPayload
     * @param  array<string,string>  $headers
     * @param  array<string,mixed>  $event
     * @return array<string,mixed>
     */
    public static function sanitizeWebhookPayloadForStorage(array $providerPayload, array $headers, string $rawBody, array $event): array
    {
        $sanitized = self::sanitizeSessionPayloadForStorage($providerPayload);
        $sanitized['_receipt'] = [
            'body_fingerprint' => self::webhookBodyFingerprint($rawBody),
            'signature_digest' => self::signatureDigest($event['request_signature'] ?? null),
            'header_names' => self::normalizedHeaderNames($headers),
            'body_size_bytes' => strlen($rawBody),
        ];

        return $sanitized;
    }

    /**
     * @param  array<string,mixed>  $event
     */
    public static function summarizeWebhookRequestBody(string $rawBody, array $event): string
    {
        $summary = array_filter([
            'provider_event_code' => self::normalizeString($event['provider_event_code'] ?? null),
            'provider_session_code' => self::normalizeString($event['provider_session_code'] ?? null),
            'payment_scope' => self::normalizeString($event['payment_scope'] ?? null),
            'event_type' => self::normalizeString($event['event_type'] ?? null),
            'session_status' => self::normalizeString($event['session_status'] ?? null),
            'occurred_at' => self::normalizeTimestamp($event['occurred_at'] ?? null),
            'body_fingerprint' => self::webhookBodyFingerprint($rawBody),
            'body_size_bytes' => strlen($rawBody),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return json_encode(
            $summary,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        ) ?: '{}';
    }

    public static function webhookBodyFingerprint(string $rawBody): string
    {
        return 'sha256:'.hash('sha256', self::canonicalizeWebhookRequestBody($rawBody));
    }

    public static function signatureDigest(mixed $signature): ?string
    {
        $normalized = trim((string) ($signature ?? ''));

        return $normalized !== '' ? 'sha256:'.hash('sha256', $normalized) : null;
    }

    public static function storedWebhookBodyFingerprint(mixed $providerPayload): string
    {
        if (! is_array($providerPayload)) {
            return '';
        }

        $receiptMeta = $providerPayload['_receipt'] ?? null;
        if (! is_array($receiptMeta)) {
            return '';
        }

        return trim((string) ($receiptMeta['body_fingerprint'] ?? ''));
    }

    /**
     * @param  array<string,string>  $headers
     * @return list<string>
     */
    private static function normalizedHeaderNames(array $headers): array
    {
        $headerNames = [];

        foreach (array_keys($headers) as $key) {
            $normalized = strtolower(trim((string) $key));
            if ($normalized !== '') {
                $headerNames[$normalized] = true;
            }
        }

        $names = array_keys($headerNames);
        sort($names);

        return $names;
    }

    private static function sanitizeSessionValue(mixed $value): string|bool|int|float|null
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return self::truncate(trim((string) $value), 500);
        }

        return null;
    }

    /**
     * @return array<string,string>
     */
    private static function sanitizeBookingRequestMeta(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $fingerprint = trim((string) ($value['fingerprint'] ?? ''));

        return $fingerprint !== '' ? ['fingerprint' => $fingerprint] : [];
    }

    /**
     * @return array<string,mixed>
     */
    private static function sanitizeReceiptMeta(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $sanitized = [];

        $fingerprint = trim((string) ($value['body_fingerprint'] ?? ''));
        if ($fingerprint !== '') {
            $sanitized['body_fingerprint'] = $fingerprint;
        }

        $signatureDigest = trim((string) ($value['signature_digest'] ?? ''));
        if ($signatureDigest !== '') {
            $sanitized['signature_digest'] = $signatureDigest;
        }

        $headerNames = $value['header_names'] ?? null;
        if (is_array($headerNames)) {
            $sanitizedHeaderNames = array_values(array_filter(array_map(
                static fn (mixed $name): string => strtolower(trim((string) $name)),
                $headerNames
            ), static fn (string $name): bool => $name !== ''));
            if ($sanitizedHeaderNames !== []) {
                $sanitized['header_names'] = $sanitizedHeaderNames;
            }
        }

        if (isset($value['body_size_bytes']) && is_numeric($value['body_size_bytes'])) {
            $sanitized['body_size_bytes'] = max(0, (int) $value['body_size_bytes']);
        }

        return $sanitized;
    }

    private static function sanitizePaymentResponseValue(mixed $value, bool $allowNestedArrays): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if ($value instanceof CarbonInterface || $value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        if (is_scalar($value)) {
            return self::truncate(trim((string) $value), 500);
        }

        if (! is_array($value) || ! $allowNestedArrays) {
            return null;
        }

        if (array_is_list($value)) {
            $items = [];

            foreach ($value as $item) {
                $normalized = self::sanitizePaymentResponseValue($item, false);
                if ($normalized !== null) {
                    $items[] = $normalized;
                }
            }

            return $items !== [] ? $items : null;
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            if (in_array($key, ['request_idempotency_key', 'request_fingerprint'], true)) {
                continue;
            }

            $normalized = self::sanitizePaymentResponseValue($item, false);
            if ($normalized !== null) {
                $sanitized[$key] = $normalized;
            }
        }

        return $sanitized !== [] ? $sanitized : null;
    }

    private static function normalizeString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? self::truncate($normalized, 255) : null;
    }

    private static function normalizeTimestamp(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface || $value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? self::truncate($normalized, 255) : null;
    }

    private static function isSensitiveHeader(string $header): bool
    {
        foreach (['authorization', 'cookie', 'signature', 'token', 'secret', 'password', 'idempotency'] as $fragment) {
            if (str_contains($header, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private static function truncate(string $value, int $maxLength): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength);
    }

    private static function canonicalizeWebhookRequestBody(?string $rawBody): string
    {
        $normalized = trim((string) ($rawBody ?? ''));
        if ($normalized === '') {
            return '';
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($normalized, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $normalized;
        }

        try {
            return json_encode(
                self::sortWebhookPayloadRecursively($decoded),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            return $normalized;
        }
    }

    private static function sortWebhookPayloadRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::sortWebhookPayloadRecursively($item), $value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::sortWebhookPayloadRecursively($item);
        }

        ksort($value);

        return $value;
    }
}
