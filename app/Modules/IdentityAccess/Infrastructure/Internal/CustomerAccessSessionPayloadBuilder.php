<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Internal;

use App\Modules\IdentityAccess\Domain\Models\CustomerAccessSession;
use App\Modules\IdentityAccess\Domain\Models\User;

class CustomerAccessSessionPayloadBuilder
{
    /**
     * @return array<string,mixed>
     */
    public function build(CustomerAccessSession $session, ?string $plainTextToken): array
    {
        $session->loadMissing('user.role');

        return [
            'auth_mode' => 'customer_access_session',
            'token_type' => 'opaque',
            'auth_header' => (string) config('customer_auth.header', 'X-Customer-Token'),
            'access_token' => $plainTextToken,
            'access_session_id' => (int) $session->getKey(),
            'session_id' => (string) ($session->session_id ?? ''),
            'expires_at_utc' => $session->expires_at?->utc()->toIso8601String(),
            'user' => $this->formatUserPayload($session->user),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildRevoked(CustomerAccessSession $session): array
    {
        return [
            'auth_mode' => 'customer_access_session',
            'access_session_id' => (int) $session->getKey(),
            'revoked_at_utc' => $session->revoked_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function formatUserPayload(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'user_id' => (int) $user->user_id,
            'username' => (string) ($user->username ?? ''),
            'full_name' => (string) ($user->full_name ?? ''),
            'email' => $user->email,
            'phone' => $user->phone,
            'role_id' => $user->role_id !== null ? (int) $user->role_id : null,
            'role_name' => $user->role?->role_name,
        ];
    }
}
