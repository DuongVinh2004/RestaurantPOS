<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Persistence;

use App\Modules\IdentityAccess\Domain\Models\CustomerAccessSession;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Support\AuditEvent;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerAccessSessionStore
{
    public function resolveActiveSessionByPlainTextToken(string $plainTextToken): ?object
    {
        $plainTextToken = trim($plainTextToken);
        if ($plainTextToken === '') {
            return null;
        }

        $table = $this->accessSessionTable();
        if (! Schema::hasTable($table)) {
            return null;
        }

        try {
            $session = DB::table($table)
                ->where('token_hash', hash('sha256', $plainTextToken))
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now('UTC'))
                ->orderByDesc($this->accessSessionPrimaryKey())
                ->first();
        } catch (QueryException) {
            return null;
        }

        if (! $session) {
            return null;
        }

        $this->touchLastUsedAt($session);

        return $this->reloadAccessSession($session);
    }

    public function resolveActiveSessionBySessionId(string $sessionId): ?object
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return null;
        }

        $table = $this->accessSessionTable();
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'session_id')) {
            return null;
        }

        try {
            $session = DB::table($table)
                ->where('session_id', $sessionId)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now('UTC'))
                ->orderByDesc($this->accessSessionPrimaryKey())
                ->first();
        } catch (QueryException) {
            return null;
        }

        if (! $session) {
            return null;
        }

        $this->touchLastUsedAt($session);

        return $this->reloadAccessSession($session);
    }

    /**
     * @return array{plain_text_token:string,access_session:CustomerAccessSession}
     */
    public function issueForUser(User $user, Carbon $expiresAt, array $context = []): array
    {
        $table = $this->accessSessionTable();
        if (! Schema::hasTable($table)) {
            throw new \RuntimeException(sprintf('Customer access session table [%s] is missing.', $table));
        }

        $this->assertIssuableUser($user);

        $plainTextToken = Str::random(64);
        $now = now('UTC');
        $payload = [
            'user_id' => (int) $user->user_id,
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => $expiresAt->copy()->utc(),
        ];

        $this->setColumnIfPresent($payload, 'token_last_eight', substr($plainTextToken, -8));
        $this->setColumnIfPresent(
            $payload,
            'session_id',
            isset($context['session_id']) && trim((string) $context['session_id']) !== ''
                ? trim((string) $context['session_id'])
                : $this->generateSessionId()
        );
        $this->setColumnIfPresent($payload, 'guest_name', isset($context['guest_name']) ? (string) $context['guest_name'] : null);
        $this->setColumnIfPresent($payload, 'phone', isset($context['phone']) ? (string) $context['phone'] : null);
        $this->setColumnIfPresent(
            $payload,
            'session_meta_json',
            $this->normalizeSessionMetaPayload($context['session_meta_json'] ?? $this->extractSessionMeta($context))
        );
        $this->setColumnIfPresent($payload, 'created_ip', $this->normalizeCreatedIp($context['created_ip'] ?? null));
        $this->setColumnIfPresent($payload, 'user_agent', isset($context['user_agent']) ? (string) $context['user_agent'] : null);
        $this->setColumnIfPresent($payload, 'row_version', 1);
        $this->setColumnIfPresent($payload, 'created_at', $now);
        $this->setColumnIfPresent($payload, 'updated_at', $now);
        $this->setColumnIfPresent($payload, 'last_used_at', null);
        $this->setColumnIfPresent($payload, 'revoked_at', null);

        $accessSessionId = DB::table($table)->insertGetId($payload);

        /** @var CustomerAccessSession $accessSession */
        $accessSession = CustomerAccessSession::query()->findOrFail($accessSessionId);

        AuditEvent::info('customer_access_session_issued', [
            'access_session_id' => (int) $accessSession->getKey(),
            'user_id' => (int) $user->user_id,
            'session_id' => $accessSession->session_id,
            'expires_at' => $accessSession->expires_at?->utc()->toIso8601String(),
            'source' => is_scalar($context['source'] ?? null) ? (string) $context['source'] : null,
        ]);

        return [
            'plain_text_token' => $plainTextToken,
            'access_session' => $accessSession,
        ];
    }

    public function revokeSession(CustomerAccessSession|int $accessSession): ?CustomerAccessSession
    {
        $table = $this->accessSessionTable();
        if (! Schema::hasTable($table)) {
            return null;
        }

        $accessSessionId = $accessSession instanceof CustomerAccessSession
            ? (int) $accessSession->getKey()
            : (int) $accessSession;

        if ($accessSessionId <= 0) {
            return null;
        }

        $payload = ['revoked_at' => now('UTC')];
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now('UTC');
        }

        DB::table($table)
            ->where($this->accessSessionPrimaryKey(), $accessSessionId)
            ->update($payload);

        /** @var CustomerAccessSession|null $record */
        $record = CustomerAccessSession::query()->with('user.role')->find($accessSessionId);
        if ($record instanceof CustomerAccessSession) {
            AuditEvent::warning('customer_access_session_revoked', [
                'access_session_id' => (int) $record->getKey(),
                'user_id' => (int) ($record->user_id ?? 0),
                'session_id' => $record->session_id,
            ]);
        }

        return $record;
    }

    public function resolveUserFromPlainTextToken(string $plainTextToken): ?User
    {
        $session = $this->resolveActiveSessionByPlainTextToken($plainTextToken);
        $userId = $session && isset($session->user_id) ? (int) $session->user_id : 0;
        if ($userId <= 0) {
            return null;
        }

        return User::query()->with('role')->notDeleted()->where('user_id', $userId)->first();
    }

    /**
     * @return array<int,CustomerAccessSession>
     */
    public function listSessions(?int $userId = null, bool $includeRevoked = false): array
    {
        $table = $this->accessSessionTable();
        if (! Schema::hasTable($table)) {
            return [];
        }

        /** @var EloquentCollection<int,CustomerAccessSession> $sessions */
        $sessions = CustomerAccessSession::query()
            ->with('user.role')
            ->when($userId !== null && $userId > 0, static fn ($query) => $query->where('user_id', $userId))
            ->when(! $includeRevoked, static fn ($query) => $query->active())
            ->orderByDesc('created_at')
            ->orderByDesc('access_session_id')
            ->get();

        return $sessions->all();
    }

    public function showSession(int $accessSessionId): CustomerAccessSession
    {
        /** @var CustomerAccessSession $session */
        $session = CustomerAccessSession::query()
            ->with('user.role')
            ->findOrFail($accessSessionId);

        return $session;
    }

    private function accessSessionTable(): string
    {
        return (string) config('customer_auth.access_session_table', 'customer_access_sessions');
    }

    private function accessSessionPrimaryKey(): string
    {
        return Schema::hasColumn($this->accessSessionTable(), 'access_session_id')
            ? 'access_session_id'
            : 'id';
    }

    private function reloadAccessSession(object $session): object
    {
        $sessionId = isset($session->{$this->accessSessionPrimaryKey()})
            ? (int) $session->{$this->accessSessionPrimaryKey()}
            : 0;

        if ($sessionId <= 0) {
            return $session;
        }

        try {
            return DB::table($this->accessSessionTable())
                ->where($this->accessSessionPrimaryKey(), $sessionId)
                ->first() ?? $session;
        } catch (QueryException) {
            return $session;
        }
    }

    private function touchLastUsedAt(object $session): void
    {
        if (! (bool) config('customer_auth.touch_last_used_at', true)) {
            return;
        }

        $table = $this->accessSessionTable();
        if (! Schema::hasColumn($table, 'last_used_at')) {
            return;
        }

        $accessSessionId = isset($session->{$this->accessSessionPrimaryKey()})
            ? (int) $session->{$this->accessSessionPrimaryKey()}
            : 0;
        if ($accessSessionId <= 0) {
            return;
        }

        $payload = ['last_used_at' => now('UTC')];
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now('UTC');
        }

        try {
            DB::table($table)
                ->where($this->accessSessionPrimaryKey(), $accessSessionId)
                ->update($payload);
        } catch (QueryException) {
            // Ignore touch failures during auth resolution.
        }
    }

    private function setColumnIfPresent(array &$payload, string $column, mixed $value): void
    {
        if (Schema::hasColumn($this->accessSessionTable(), $column)) {
            $payload[$column] = $value;
        }
    }

    private function generateSessionId(): string
    {
        return 'cas_'.Str::lower(Str::random(24));
    }

    private function normalizeCreatedIp(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function extractSessionMeta(array $context): ?array
    {
        $meta = [];

        foreach (['session_label', 'source', 'device_id'] as $key) {
            if (array_key_exists($key, $context) && $context[$key] !== null && $context[$key] !== '') {
                $meta[$key] = $context[$key];
            }
        }

        return $meta === [] ? null : $meta;
    }

    private function normalizeSessionMetaPayload(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        if (is_array($value)) {
            return $value === []
                ? null
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function assertIssuableUser(User $user): void
    {
        if ((bool) ($user->is_deleted ?? false)) {
            throw ValidationException::withMessages([
                'user_id' => ['Customer access sessions cannot be issued for deleted users.'],
            ]);
        }

        $allowedRoleIds = array_values(array_filter(array_map(
            static fn ($value): int => (int) $value,
            (array) config('customer_auth.allowed_role_ids', [])
        ), static fn (int $value): bool => $value > 0));

        if ($allowedRoleIds === []) {
            throw ValidationException::withMessages([
                'customer_auth' => ['customer_auth.allowed_role_ids must not be empty when issuing customer access sessions.'],
            ]);
        }

        if (! in_array((int) ($user->role_id ?? 0), $allowedRoleIds, true)) {
            throw ValidationException::withMessages([
                'user_id' => ['Customer access sessions can only be issued for configured customer roles.'],
            ]);
        }
    }
}
