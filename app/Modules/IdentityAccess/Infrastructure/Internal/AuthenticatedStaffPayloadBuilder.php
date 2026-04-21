<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Internal;

use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Modules\IdentityAccess\Domain\Models\User;

class AuthenticatedStaffPayloadBuilder
{
    public function __construct(
        private readonly StaffStartupContextBuilder $staffStartupContextBuilder,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function build(StaffApiKey $record, ?string $plainTextKey): array
    {
        $record->loadMissing('user.role');

        $capabilityContext = $this->staffStartupContextBuilder->buildCapabilityContext($record->user);
        $startupContext = $this->staffStartupContextBuilder->buildStartupContext($record->user, $capabilityContext);

        return [
            'auth_mode' => 'staff_api_key',
            'token_type' => 'opaque',
            'auth_header' => 'X-Staff-Key',
            'access_token' => $plainTextKey,
            'staff_api_key_id' => (int) $record->getKey(),
            'expires_at_utc' => $record->expires_at?->utc()->toIso8601String(),
            'user' => $this->formatUserPayload($record->user),
            'capabilities' => $capabilityContext['capabilities'],
            'known_capabilities' => $capabilityContext['known_capabilities'],
            'capability_source' => $capabilityContext['source'],
            'startup' => $startupContext,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildRevoked(StaffApiKey $record): array
    {
        return [
            'auth_mode' => 'staff_api_key',
            'staff_api_key_id' => (int) $record->getKey(),
            'revoked_at_utc' => $record->revoked_at?->utc()->toIso8601String(),
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
