<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Internal;

use App\Modules\IdentityAccess\Application\UseCases\Authentication\ProductAuthConfigurationException;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Support\AuditEvent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordLoginAuthenticator
{
    public function authenticate(string $identifier, string $password, array $allowedRoleIds, string $scope): User
    {
        $allowedRoleIds = $this->normalizeAllowedRoleIds($allowedRoleIds);
        $this->assertRoleConfigurationIsPresent($allowedRoleIds, $scope);
        $user = $this->resolveUserForPasswordLogin($identifier, $allowedRoleIds);

        if (! $user instanceof User || trim((string) ($user->password_hash ?? '')) === '' || ! Hash::check($password, (string) $user->password_hash)) {
            AuditEvent::warning('password_login_failed', [
                'scope' => $scope,
                'user_id' => $user?->user_id !== null ? (int) $user->user_id : null,
            ]);

            throw ValidationException::withMessages([
                'identifier' => ['Invalid credentials.'],
            ]);
        }

        return $user;
    }

    /**
     * @param  list<int>  $allowedRoleIds
     */
    private function assertRoleConfigurationIsPresent(array $allowedRoleIds, string $scope): void
    {
        if ($allowedRoleIds !== []) {
            return;
        }

        if ($scope === 'staff' && $this->shouldBlockStaffRoleNameFallbackInCurrentEnvironment()) {
            AuditEvent::warning('password_login_configuration_blocked', [
                'scope' => $scope,
                'error_code' => 'staff_role_name_fallback_blocked',
                'app_env' => (string) config('app.env', 'production'),
            ]);

            throw new ProductAuthConfigurationException(
                503,
                'staff_role_name_fallback_blocked',
                'Role-name based staff authorization is blocked in the current environment. Configure STAFF_ALLOWED_ROLE_IDS.',
            );
        }

        [$errorCode, $message] = match ($scope) {
            'staff' => [
                'staff_role_configuration_missing',
                'Staff role authorization is misconfigured. Configure STAFF_ALLOWED_ROLE_IDS.',
            ],
            'customer' => [
                'customer_role_configuration_missing',
                'Customer role authorization is misconfigured. Configure CUSTOMER_AUTH_ALLOWED_ROLE_IDS.',
            ],
            default => [
                'role_configuration_missing',
                'Authentication role authorization is misconfigured.',
            ],
        };

        AuditEvent::warning('password_login_configuration_blocked', [
            'scope' => $scope,
            'error_code' => $errorCode,
            'app_env' => (string) config('app.env', 'production'),
        ]);

        throw new ProductAuthConfigurationException(503, $errorCode, $message);
    }

    private function resolveUserForPasswordLogin(string $identifier, array $allowedRoleIds): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        return User::query()
            ->with('role')
            ->notDeleted()
            ->when($allowedRoleIds !== [], static fn ($query) => $query->whereIn('role_id', $allowedRoleIds))
            ->where(function ($query) use ($identifier): void {
                $query->where('username', $identifier)
                    ->orWhere('email', $identifier)
                    ->orWhere('phone', $identifier);
            })
            ->first();
    }

    /**
     * @return list<int>
     */
    private function normalizeAllowedRoleIds(array $allowedRoleIds): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            $allowedRoleIds
        ), static fn (int $value): bool => $value > 0));
    }

    private function shouldBlockStaffRoleNameFallbackInCurrentEnvironment(): bool
    {
        return (bool) config('staff_auth.allow_role_name_fallback', false)
            && in_array((string) config('app.env', 'production'), $this->staffProductionLikeEnvironments(), true)
            && (bool) config('staff_auth.deny_role_name_fallback_in_production_like', true);
    }

    /**
     * @return list<string>
     */
    private function staffProductionLikeEnvironments(): array
    {
        return array_values(array_filter(array_map(
            'strval',
            (array) config('staff_auth.production_like_environments', ['production'])
        )));
    }
}
