<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Tokenization;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Persistence\CustomerAccessSessionStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CustomerAuthTokenResolver
{
    public function __construct(
        private readonly CustomerAccessSessionStore $accessSessionStore,
    ) {}

    public function extractProvidedToken(Request $request): string
    {
        if (! (bool) config('customer_auth.enabled', false)) {
            return '';
        }

        $headerName = (string) config('customer_auth.header', 'X-Customer-Token');
        $provided = trim((string) ($request->header($headerName) ?? ''));

        if ($provided === '' && (bool) config('customer_auth.allow_bearer', false)) {
            $auth = trim((string) ($request->header('Authorization') ?? ''));
            if (stripos($auth, 'Bearer ') === 0) {
                $provided = trim(substr($auth, 7));
            }
        }

        return $provided;
    }

    public function extractSessionId(Request $request): string
    {
        $candidates = [
            $request->header('X-Session-Id'),
            $request->input('session_id'),
            $request->query('session_id'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array{auth_mode?: string, user?: User, session_id?: string, guest_name?: ?string, phone?: ?string}
     */
    public function resolveContextFromRequest(Request $request): array
    {
        $providedToken = $this->extractProvidedToken($request);
        $providedSessionId = $this->extractSessionId($request);

        if ($providedToken !== '') {
            $accessSession = $this->accessSessionStore->resolveActiveSessionByPlainTextToken($providedToken);
            if ($accessSession) {
                $user = $this->resolveUserById(isset($accessSession->user_id) ? (int) $accessSession->user_id : null);
                $sessionId = trim((string) ($accessSession->session_id ?? $providedSessionId));

                return array_filter([
                    'auth_mode' => $user ? 'customer_access_session' : 'customer_access_guest_session',
                    'user' => $user,
                    'session_id' => $sessionId !== '' ? $sessionId : null,
                    'access_session_id' => isset($accessSession->access_session_id) ? (int) $accessSession->access_session_id : null,
                    'guest_name' => isset($accessSession->guest_name) ? (string) $accessSession->guest_name : null,
                    'phone' => isset($accessSession->phone) ? (string) $accessSession->phone : null,
                ], static fn ($value) => $value !== null && $value !== '');
            }

            $legacyUser = $this->resolveLegacyUserFromToken($providedToken);
            if ($legacyUser instanceof User) {
                return array_filter([
                    'auth_mode' => 'customer_token',
                    'user' => $legacyUser,
                    'session_id' => $providedSessionId !== '' ? $providedSessionId : null,
                ], static fn ($value) => $value !== null && $value !== '');
            }
        }

        if ($providedSessionId !== '') {
            $accessSession = $this->accessSessionStore->resolveActiveSessionBySessionId($providedSessionId);

            return array_filter([
                'auth_mode' => 'customer_session',
                'session_id' => $providedSessionId,
                'access_session_id' => $accessSession && isset($accessSession->access_session_id) ? (int) $accessSession->access_session_id : null,
                'guest_name' => $accessSession && isset($accessSession->guest_name) ? (string) $accessSession->guest_name : null,
                'phone' => $accessSession && isset($accessSession->phone) ? (string) $accessSession->phone : null,
            ], static fn ($value) => $value !== null && $value !== '');
        }

        return [];
    }

    public function resolveUserFromRequest(Request $request): ?User
    {
        $context = $this->resolveContextFromRequest($request);

        return $context['user'] ?? null;
    }

    public function normalizeAllowedCustomerUser(?User $user): ?User
    {
        if (! $user instanceof User) {
            return null;
        }

        if ((bool) ($user->is_deleted ?? false)) {
            return null;
        }

        $allowedRoleIds = array_values(array_filter(
            array_map('intval', (array) config('customer_auth.allowed_role_ids', [3])),
            static fn (int $value) => $value > 0
        ));

        if ($allowedRoleIds !== [] && ! in_array((int) ($user->role_id ?? 0), $allowedRoleIds, true)) {
            return null;
        }

        return $user;
    }

    private function resolveLegacyUserFromToken(string $provided): ?User
    {
        if (! (bool) config('customer_auth.allow_legacy_user_auth_tokens', false)) {
            return null;
        }

        $allowedEnvironments = array_values(array_filter(
            array_map('strval', (array) config('customer_auth.legacy_user_auth_tokens_allowed_environments', ['local', 'testing'])),
            static fn (string $value) => trim($value) !== ''
        ));
        $currentEnvironment = app()->environment();
        if ($allowedEnvironments !== [] && ! in_array($currentEnvironment, $allowedEnvironments, true)) {
            Log::warning('customer_auth_legacy_user_auth_tokens_blocked', [
                'environment' => $currentEnvironment,
                'header' => (string) config('customer_auth.header', 'X-Customer-Token'),
            ]);

            return null;
        }

        if (! Schema::hasTable('user_auth_tokens')) {
            return null;
        }

        $tokenHash = hash('sha256', $provided);
        $query = DB::table('user_auth_tokens')
            ->where('token_hash', $tokenHash)
            ->whereNotNull('user_id')
            ->where('expires_at', '>', now('UTC'));

        $allowedPurposes = array_map('strval', (array) config('customer_auth.allowed_purposes', []));
        if (! empty($allowedPurposes)) {
            $query->whereIn('purpose', $allowedPurposes);
        }

        if ((bool) config('customer_auth.require_unused', true) && Schema::hasColumn('user_auth_tokens', 'used_at')) {
            $query->whereNull('used_at');
        }

        $token = $query->orderByDesc('token_id')->first(['user_id']);
        if (! $token) {
            return null;
        }

        return $this->resolveUserById((int) $token->user_id);
    }

    private function resolveUserById(?int $userId): ?User
    {
        if ($userId === null || $userId <= 0) {
            return null;
        }

        $user = User::query()
            ->with('role')
            ->notDeleted()
            ->where('user_id', $userId)
            ->first();

        if (! $user) {
            return null;
        }

        return $this->normalizeAllowedCustomerUser($user);
    }
}
