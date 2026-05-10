<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Internal\CustomerAccessSessionPayloadBuilder;
use App\Modules\IdentityAccess\Infrastructure\Persistence\CustomerAccessSessionStore;
use App\Support\AuditEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RegisterCustomerHandler
{
    public function __construct(
        private readonly CustomerAccessSessionStore $customerAccessSessionStore,
        private readonly CustomerAccessSessionPayloadBuilder $customerAccessSessionPayloadBuilder,
    ) {}

    /**
     * @param  array<string,mixed>  $data
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function handle(array $data, array $context = []): array
    {
        $roleId = $this->customerRoleId();

        try {
            return DB::transaction(function () use ($data, $context, $roleId): array {
                $user = $this->createCustomerUser($data, $roleId);
                $issued = $this->customerAccessSessionStore->issueForUser(
                    $user,
                    $this->customerSessionExpiry(),
                    $this->customerSessionContext($user, $context),
                );

                AuditEvent::info('customer_registration_succeeded', [
                    'user_id' => (int) $user->user_id,
                    'access_session_id' => (int) $issued['access_session']->getKey(),
                ]);

                return $this->customerAccessSessionPayloadBuilder->build(
                    $issued['access_session'],
                    (string) $issued['plain_text_token'],
                );
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            AuditEvent::warning('customer_registration_failed', [
                'reason' => 'database_write_conflict',
                'sql_state' => $exception->errorInfo[0] ?? null,
            ]);

            throw ValidationException::withMessages($this->registrationConflictMessages($data));
        }
    }

    private function customerRoleId(): int
    {
        if (! (bool) config('customer_auth.enabled', false)) {
            AuditEvent::warning('customer_registration_configuration_blocked', [
                'error_code' => 'customer_auth_disabled',
            ]);

            throw new ProductAuthConfigurationException(
                503,
                'customer_auth_disabled',
                'Customer authentication is disabled.',
            );
        }

        $allowedRoleIds = array_values(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            (array) config('customer_auth.allowed_role_ids', [])
        ), static fn (int $value): bool => $value > 0));

        if ($allowedRoleIds === []) {
            AuditEvent::warning('customer_registration_configuration_blocked', [
                'error_code' => 'customer_role_configuration_missing',
            ]);

            throw new ProductAuthConfigurationException(
                503,
                'customer_role_configuration_missing',
                'Customer role authorization is misconfigured. Configure CUSTOMER_AUTH_ALLOWED_ROLE_IDS.',
            );
        }

        $roleId = (int) $allowedRoleIds[0];
        if (! Role::query()->whereKey($roleId)->exists()) {
            AuditEvent::warning('customer_registration_configuration_blocked', [
                'error_code' => 'customer_role_not_found',
                'role_id' => $roleId,
            ]);

            throw new ProductAuthConfigurationException(
                503,
                'customer_role_not_found',
                'Configured customer role was not found.',
            );
        }

        return $roleId;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function createCustomerUser(array $data, int $roleId): User
    {
        $email = $this->nullableString($data['email'] ?? null);
        $phone = $this->nullableString($data['phone'] ?? null);
        $now = now('UTC');
        $payload = [
            'username' => $email ?: $phone,
            'password_hash' => Hash::make((string) $data['password']),
            'full_name' => trim((string) $data['full_name']),
            'email' => $email,
            'phone' => $phone,
            'role_id' => $roleId,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('users', 'row_version')) {
            $payload['row_version'] = 1;
        }

        $userId = DB::table('users')->insertGetId($payload);

        /** @var User $user */
        $user = User::query()->with('role')->findOrFail($userId);

        return $user;
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function customerSessionContext(User $user, array $context): array
    {
        return [
            'session_id' => isset($context['session_id']) ? trim((string) $context['session_id']) : null,
            'guest_name' => trim((string) ($context['guest_name'] ?? $user->full_name ?? '')) ?: null,
            'phone' => trim((string) ($context['phone'] ?? $user->phone ?? '')) ?: null,
            'session_meta_json' => array_filter([
                'session_label' => trim((string) ($context['session_label'] ?? 'customer_registration')) ?: null,
                'source' => 'customer_registration',
                'device_id' => trim((string) ($context['device_id'] ?? '')) ?: null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'created_ip' => trim((string) ($context['ip'] ?? '')) ?: null,
            'user_agent' => trim((string) ($context['user_agent'] ?? '')) ?: null,
            'source' => 'customer_registration',
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,list<string>>
     */
    private function registrationConflictMessages(array $data): array
    {
        if ($this->nullableString($data['email'] ?? null) !== null) {
            return ['email' => ['The email has already been taken.']];
        }

        if ($this->nullableString($data['phone'] ?? null) !== null) {
            return ['phone' => ['The phone has already been taken.']];
        }

        return ['identifier' => ['Customer account could not be created.']];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return $sqlState === '23000' || in_array($driverCode, ['1062', '19'], true);
    }

    private function customerSessionExpiry(): Carbon
    {
        return now('UTC')->addMinutes(max(1, (int) config('customer_auth.access_session_ttl_minutes', 60 * 24 * 14)));
    }
}
