<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\StaffApiKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class StaffActorResolver
{
    private ?bool $databaseStoreReady = null;

    public function extractProvidedKey(Request $request): string
    {
        $headerCandidates = [
            'X-Staff-Key',
            'X-Staff-Api-Key',
            'X-Api-Key',
            'X-API-Key',
            'Api-Key',
            'X-Admin-Key',
            'X-Admin-Api-Key',
        ];

        $provided = '';
        foreach ($headerCandidates as $headerName) {
            $candidate = trim((string) ($request->header($headerName) ?? ''));
            if ($candidate !== '') {
                $provided = $candidate;
                break;
            }
        }

        if ($provided === '' && (bool) config('staff_auth.allow_bearer', false)) {
            $auth = trim((string) ($request->header('Authorization') ?? ''));
            if (stripos($auth, 'Bearer ') === 0) {
                $provided = trim(substr($auth, 7));
            }
        }

        return $provided;
    }

    /**
     * @return array{ok:bool,status:int,error_code?:string,message?:string,user?:User,mode?:string,staff_api_key_id?:int}
     */
    public function resolveFromRequest(Request $request): array
    {
        return $this->resolveFromProvidedKey($this->extractProvidedKey($request));
    }

    /**
     * @return array{ok:bool,status:int,error_code?:string,message?:string,user?:User,mode?:string,staff_api_key_id?:int}
     */
    public function resolveFromProvidedKey(string $provided): array
    {
        $provided = trim($provided);
        if ($provided === '') {
            return [
                'ok' => false,
                'status' => 401,
                'error_code' => 'unauthorized',
                'message' => 'Unauthorized.',
            ];
        }

        $mode = null;
        $staffApiKeyId = null;
        $mappedUserId = $this->findMappedUserIdForKey($provided, $mode, $staffApiKeyId);
        if ($mappedUserId === null) {
            return [
                'ok' => false,
                'status' => 401,
                'error_code' => 'unauthorized',
                'message' => 'Unauthorized.',
            ];
        }

        if ($mappedUserId <= 0) {
            return [
                'ok' => false,
                'status' => 503,
                'error_code' => 'staff_actor_not_configured',
                'message' => 'Staff API key is configured but not bound to a valid staff user.',
            ];
        }

        $user = User::query()
            ->with('role')
            ->notDeleted()
            ->where('user_id', $mappedUserId)
            ->first();

        if (! $user) {
            return [
                'ok' => false,
                'status' => 503,
                'error_code' => 'staff_actor_invalid',
                'message' => 'Configured staff actor user was not found.',
            ];
        }

        $roleId = (int) ($user->role_id ?? 0);
        $allowedRoleIds = array_values(array_filter(array_map('intval', (array) config('staff_auth.allowed_role_ids', [1, 2])), static fn (int $value) => $value > 0));

        if ($allowedRoleIds !== []) {
            if (! in_array($roleId, $allowedRoleIds, true)) {
                return [
                    'ok' => false,
                    'status' => 403,
                    'error_code' => 'forbidden',
                    'message' => 'Resolved actor is not allowed to perform staff operations.',
                ];
            }
        } else {
            if (! (bool) config('staff_auth.allow_role_name_fallback', false)) {
                return [
                    'ok' => false,
                    'status' => 503,
                    'error_code' => 'staff_role_configuration_missing',
                    'message' => 'Staff role authorization is misconfigured. Configure STAFF_ALLOWED_ROLE_IDS.',
                ];
            }

            if ($this->shouldBlockRoleNameFallbackInCurrentEnvironment()) {
                AuditEvent::warning('staff_auth_role_name_fallback_blocked', [
                    'app_env' => (string) config('app.env', 'production'),
                    'production_like_environments' => $this->productionLikeEnvironments(),
                ]);

                return [
                    'ok' => false,
                    'status' => 503,
                    'error_code' => 'staff_role_name_fallback_blocked',
                    'message' => 'Role-name based staff authorization is blocked in the current environment. Configure STAFF_ALLOWED_ROLE_IDS.',
                ];
            }

            $roleName = (string) ($user->role?->role_name ?? '');
            $allowedRoles = array_map('strval', (array) config('staff_auth.allowed_role_names', ['Admin', 'Staff']));
            if (! in_array($roleName, $allowedRoles, true)) {
                return [
                    'ok' => false,
                    'status' => 403,
                    'error_code' => 'forbidden',
                    'message' => 'Resolved actor is not allowed to perform staff operations.',
                ];
            }

            AuditEvent::warning('staff_auth_role_name_fallback_used', [
                'user_id' => (int) ($user->user_id ?? 0),
                'role_id' => $roleId,
                'role_name' => $roleName,
                'app_env' => (string) config('app.env', 'production'),
            ]);
        }

        return [
            'ok' => true,
            'status' => 200,
            'user' => $user,
            'mode' => $mode,
            'staff_api_key_id' => $staffApiKeyId,
        ];
    }

    private function findMappedUserIdForKey(string $provided, ?string &$mode = null, ?int &$staffApiKeyId = null): ?int
    {
        $provided = trim($provided);
        if ($provided === '') {
            return null;
        }

        if ((bool) config('staff_auth.database_store_enabled', true)) {
            $userId = $this->findMappedUserIdForDatabaseKey($provided, $mode, $staffApiKeyId);
            if ($userId !== null) {
                return $userId;
            }
        }

        if ($this->shouldAllowExplicitEnvironmentFallback()) {
            $keyMap = (array) config('staff_auth.api_keys', []);
            if (array_key_exists($provided, $keyMap)) {
                $mode = 'mapped_key_fallback';
                $this->recordEnvironmentFallbackUsage($mode);

                return (int) $keyMap[$provided];
            }
        }

        if ($this->shouldAllowEnvironmentFallbackWhenDatabaseStoreUnavailable()) {
            $legacyKey = trim((string) config('staff_auth.legacy_key', ''));
            if ($legacyKey !== '' && hash_equals($legacyKey, $provided)) {
                $mode = 'legacy_key_fallback';
                AuditEvent::warning('staff_auth_database_store_unavailable_env_fallback_used', [
                    'app_env' => (string) config('app.env', 'production'),
                    'database_store_enabled' => (bool) config('staff_auth.database_store_enabled', true),
                ]);
                $this->recordEnvironmentFallbackUsage($mode);

                return (int) config('staff_auth.legacy_user_id', 0);
            }
        }

        return null;
    }

    private function findMappedUserIdForDatabaseKey(string $provided, ?string &$mode = null, ?int &$staffApiKeyId = null): ?int
    {
        try {
            if (! Schema::hasTable('staff_api_keys')) {
                return null;
            }

            $record = StaffApiKey::query()
                ->active()
                ->where('key_hash', self::hashKey($provided))
                ->first();

            if (! $record) {
                return null;
            }

            $mode = 'database_key';
            $this->databaseStoreReady = true;
            $staffApiKeyId = (int) $record->getKey();

            if ((bool) config('staff_auth.touch_last_used_at', true)) {
                StaffApiKey::query()
                    ->whereKey($record->getKey())
                    ->update(['last_used_at' => now()]);
            }

            return (int) ($record->user_id ?? 0);
        } catch (Throwable $e) {
            AuditEvent::warning('staff_auth_database_store_lookup_failed', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function shouldAllowExplicitEnvironmentFallback(): bool
    {
        if (! (bool) config('staff_auth.allow_env_fallback', false)) {
            return false;
        }

        if ($this->shouldBlockEnvironmentFallbackInCurrentEnvironment()) {
            AuditEvent::warning('staff_auth_env_fallback_blocked', [
                'mode' => 'mapped_key_fallback',
                'app_env' => (string) config('app.env', 'production'),
                'production_like_environments' => $this->productionLikeEnvironments(),
            ]);

            return false;
        }

        return $this->isCurrentEnvironmentAllowListed();
    }

    private function shouldAllowEnvironmentFallbackWhenDatabaseStoreUnavailable(): bool
    {
        if (! (bool) config('staff_auth.allow_env_fallback_when_database_store_unavailable', false)) {
            return false;
        }

        if ($this->shouldBlockEnvironmentFallbackInCurrentEnvironment()) {
            AuditEvent::warning('staff_auth_env_fallback_blocked', [
                'mode' => 'legacy_key_fallback',
                'app_env' => (string) config('app.env', 'production'),
                'production_like_environments' => $this->productionLikeEnvironments(),
            ]);

            return false;
        }

        if (! $this->isCurrentEnvironmentAllowListed()) {
            return false;
        }

        return ! $this->isDatabaseStoreReady();
    }

    private function shouldBlockEnvironmentFallbackInCurrentEnvironment(): bool
    {
        return $this->isProductionLikeEnvironment()
            && (bool) config('staff_auth.deny_env_fallback_in_production_like', true);
    }

    private function shouldBlockRoleNameFallbackInCurrentEnvironment(): bool
    {
        return $this->isProductionLikeEnvironment()
            && (bool) config('staff_auth.deny_role_name_fallback_in_production_like', true);
    }

    private function isCurrentEnvironmentAllowListed(): bool
    {
        $environment = (string) config('app.env', 'production');
        return in_array($environment, $this->fallbackAllowedEnvironments(), true);
    }

    /**
     * @return list<string>
     */
    private function fallbackAllowedEnvironments(): array
    {
        return array_values(array_filter(array_map('strval', (array) config('staff_auth.env_fallback_allowed_environments', ['local', 'testing']))));
    }

    private function isProductionLikeEnvironment(): bool
    {
        return in_array((string) config('app.env', 'production'), $this->productionLikeEnvironments(), true);
    }

    /**
     * @return list<string>
     */
    private function productionLikeEnvironments(): array
    {
        return array_values(array_filter(array_map('strval', (array) config('staff_auth.production_like_environments', ['production']))));
    }

    private function isDatabaseStoreReady(): bool
    {
        if ($this->databaseStoreReady !== null) {
            return $this->databaseStoreReady;
        }

        try {
            if (! Schema::hasTable('staff_api_keys')) {
                return $this->databaseStoreReady = false;
            }

            return $this->databaseStoreReady = StaffApiKey::query()->limit(1)->exists();
        } catch (Throwable $e) {
            AuditEvent::warning('staff_auth_database_store_readiness_check_failed', [
                'message' => $e->getMessage(),
            ]);

            return $this->databaseStoreReady = false;
        }
    }

    private function recordEnvironmentFallbackUsage(string $mode): void
    {
        AuditEvent::warning('staff_auth_env_fallback_used', [
            'mode' => $mode,
            'app_env' => (string) config('app.env', 'production'),
            'database_store_enabled' => (bool) config('staff_auth.database_store_enabled', true),
            'database_store_ready' => $this->isDatabaseStoreReady(),
        ]);
    }

    private static function hashKey(string $provided): string
    {
        return hash('sha256', trim($provided));
    }
}
