<?php

declare(strict_types=1);

namespace App\Support\AuditTrail;

use App\Support\Auth\RequestActorContext;

final class AuditTrailActorResolver
{
    /**
     * @param  array<string,mixed>  $override
     * @return array{type:?string,key:?string,user_id:?int}
     */
    public function resolve(array $override = []): array
    {
        $resolved = $this->resolveFromRequest();

        if (($override['user_id'] ?? null) !== null) {
            $resolved['user_id'] = max(1, (int) $override['user_id']);
        }

        if (($override['type'] ?? null) !== null) {
            $resolved['type'] = $this->normalizeNullableString($override['type']);
        }

        if (($override['key'] ?? null) !== null) {
            $resolved['key'] = $this->normalizeActorKey(
                $resolved['type'],
                $this->normalizeNullableString($override['key']),
            );
        }

        if ($resolved['type'] === null && app()->runningInConsole()) {
            $resolved['type'] = 'system';
            $resolved['key'] ??= 'system:console';
        }

        if ($resolved['user_id'] !== null && $resolved['user_id'] <= 0) {
            $resolved['user_id'] = null;
        }

        return $resolved;
    }

    /**
     * @return array{type:?string,key:?string,user_id:?int}
     */
    private function resolveFromRequest(): array
    {
        if (! app()->bound('request')) {
            return [
                'type' => null,
                'key' => null,
                'user_id' => null,
            ];
        }

        $actor = RequestActorContext::fromRequest(request());

        $staffUserId = $actor->staffUserId();
        if ($staffUserId !== null) {
            $staffApiKeyId = $actor->staffApiKeyId();

            return [
                'type' => $staffApiKeyId !== null ? 'staff_api_key' : 'staff_user',
                'key' => $staffApiKeyId !== null ? 'staff_api_key:'.$staffApiKeyId : 'staff_user:'.$staffUserId,
                'user_id' => $staffUserId,
            ];
        }

        $customerUserId = $actor->customerUserId();
        if ($customerUserId !== null) {
            $accessSessionId = $actor->customerAccessSessionId();

            return [
                'type' => $accessSessionId !== null ? 'customer_access_session' : 'customer_account',
                'key' => $accessSessionId !== null
                    ? 'customer_access_session:'.$accessSessionId
                    : 'customer_user:'.$customerUserId,
                'user_id' => $customerUserId,
            ];
        }

        $sessionId = $actor->sessionId();
        if ($sessionId !== null) {
            return [
                'type' => 'customer_session',
                'key' => $this->normalizeActorKey('customer_session', $sessionId),
                'user_id' => null,
            ];
        }

        return [
            'type' => null,
            'key' => null,
            'user_id' => null,
        ];
    }

    private function normalizeActorKey(?string $type, ?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        if ($type === 'customer_session') {
            return str_starts_with($key, 'customer_session:')
                ? $key
                : 'customer_session:'.substr(sha1($key), 0, 16);
        }

        return $key;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
