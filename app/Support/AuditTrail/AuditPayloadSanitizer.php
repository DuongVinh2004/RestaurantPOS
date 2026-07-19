<?php

declare(strict_types=1);

namespace App\Support\AuditTrail;

final class AuditPayloadSanitizer
{
    public function __construct(
        private readonly AuditIdentifierHasher $identifierHasher,
    ) {}

    public function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($value === null) {
            return null;
        }

        $normalizedKey = $this->normalizeKey($key);

        if ($this->isCorrelatableSessionKey($normalizedKey) && is_scalar($value)) {
            $identifier = (string) $value;

            return str_starts_with($identifier, 'hmac-sha256:')
                ? $identifier
                : $this->identifierHasher->hash($identifier);
        }

        if ($normalizedKey !== '' && $this->isSensitiveKey($normalizedKey)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $value = $this->hashCustomerSessionActorKey($value);
            $output = [];

            foreach ($value as $itemKey => $itemValue) {
                $output[$itemKey] = $this->sanitize($itemValue, is_string($itemKey) ? $itemKey : null);
            }

            return $output;
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $this->sanitize($value->toArray(), $key);
            }

            return $this->sanitize((array) $value, $key);
        }

        if (is_resource($value)) {
            return '[redacted]';
        }

        if (! is_string($value)) {
            return $value;
        }

        $sanitized = preg_replace('/\bBearer\s+[^\s,;]+/i', 'Bearer [redacted]', $value) ?? '[redacted]';
        $sanitized = preg_replace('/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/', '[redacted-jwt]', $sanitized) ?? '[redacted]';
        $sanitized = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[redacted-email]', $sanitized) ?? '[redacted]';

        return mb_strlen($sanitized) > 1000 ? mb_substr($sanitized, 0, 1000) : $sanitized;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function hashCustomerSessionActorKey(array $value): array
    {
        if (
            ($value['type'] ?? null) === 'customer_session'
            && is_scalar($value['key'] ?? null)
            && ! str_starts_with((string) $value['key'], 'hmac-sha256:')
        ) {
            $value['key'] = $this->identifierHasher->hash((string) $value['key']);
        }

        return $value;
    }

    private function normalizeKey(?string $key): string
    {
        return strtolower(trim((string) $key));
    }

    private function isCorrelatableSessionKey(string $key): bool
    {
        return in_array($key, [
            'session_id',
            'customer_session_id',
            'reservation_session_id',
            'x_session_id',
            'x-session-id',
        ], true);
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach ([
            'authorization',
            'cookie',
            'idempotency',
            'token',
            'password',
            'secret',
            'signature',
            'request_body',
            'raw_body',
            'provider_payload',
            'guest_name',
            'guest_phone',
            'guest_email',
            'customer_name',
            'customer_phone',
            'customer_email',
            'full_name',
            'phone',
            'email',
            'address',
            'created_ip',
        ] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return $key === 'ip';
    }
}
