<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Internal;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Support\AuditEvent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordLoginAuthenticator
{
    public function authenticate(string $identifier, string $password, array $allowedRoleIds, string $scope): User
    {
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

    private function resolveUserForPasswordLogin(string $identifier, array $allowedRoleIds): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $allowedRoleIds = array_values(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            $allowedRoleIds
        ), static fn (int $value): bool => $value > 0));

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
}
