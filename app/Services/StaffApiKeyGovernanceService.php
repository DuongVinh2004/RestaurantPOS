<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StaffApiKey;
use App\Models\User;
use App\Support\AuditEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffApiKeyGovernanceService
{
    /**
     * @return array{record: StaffApiKey, plaintext_key: string}
     */
    public function issueKey(int $userId, string $label, ?CarbonInterface $expiresAt = null): array
    {
        $label = $this->normalizeLabel($label);
        $this->assertIssuableUser($userId);
        $plaintextKey = $this->generatePlaintextKey();

        /** @var StaffApiKey $record */
        $record = StaffApiKey::query()->create([
            'user_id' => $userId,
            'label' => $label,
            'key_hash' => $this->hashKey($plaintextKey),
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'last_used_at' => null,
        ]);

        AuditEvent::info('staff_api_key_issued', [
            'staff_api_key_id' => (int) $record->getKey(),
            'user_id' => $userId,
            'label' => $label,
            'expires_at' => $expiresAt?->toIso8601String(),
        ]);

        return [
            'record' => $record->fresh() ?? $record,
            'plaintext_key' => $plaintextKey,
        ];
    }

    public function revokeKey(int $staffApiKeyId, ?string $reason = null): StaffApiKey
    {
        return DB::transaction(function () use ($staffApiKeyId, $reason): StaffApiKey {
            /** @var StaffApiKey|null $record */
            $record = StaffApiKey::query()->lockForUpdate()->find($staffApiKeyId);
            if (! $record instanceof StaffApiKey) {
                throw (new ModelNotFoundException())->setModel(StaffApiKey::class, [$staffApiKeyId]);
            }

            if ($record->revoked_at === null) {
                $record->revoked_at = now('UTC');
                $record->save();
            }

            AuditEvent::warning('staff_api_key_revoked', [
                'staff_api_key_id' => (int) $record->getKey(),
                'user_id' => (int) ($record->user_id ?? 0),
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            ]);

            return $record->fresh() ?? $record;
        });
    }

    /**
     * @return array{revoked: StaffApiKey, record: StaffApiKey, plaintext_key: string}
     */
    public function rotateKey(int $staffApiKeyId, ?string $replacementLabel = null, ?CarbonInterface $expiresAt = null): array
    {
        return DB::transaction(function () use ($staffApiKeyId, $replacementLabel, $expiresAt): array {
            /** @var StaffApiKey|null $record */
            $record = StaffApiKey::query()->lockForUpdate()->find($staffApiKeyId);
            if (! $record instanceof StaffApiKey) {
                throw (new ModelNotFoundException())->setModel(StaffApiKey::class, [$staffApiKeyId]);
            }

            if ($record->revoked_at !== null) {
                throw ValidationException::withMessages([
                    'staff_api_key_id' => ['Staff API key has already been revoked and cannot be rotated.'],
                ]);
            }

            $record->revoked_at = now('UTC');
            $record->save();

            $replacementExpiresAt = $expiresAt ?? $record->expires_at;
            $replacement = $this->issueKey(
                userId: (int) $record->user_id,
                label: $replacementLabel !== null && trim($replacementLabel) !== '' ? $replacementLabel : (string) $record->label,
                expiresAt: $replacementExpiresAt,
            );

            AuditEvent::warning('staff_api_key_rotated', [
                'revoked_staff_api_key_id' => (int) $record->getKey(),
                'replacement_staff_api_key_id' => (int) ($replacement['record']->getKey() ?? 0),
                'user_id' => (int) ($record->user_id ?? 0),
            ]);

            return [
                'revoked' => $record->fresh() ?? $record,
                'record' => $replacement['record'],
                'plaintext_key' => $replacement['plaintext_key'],
            ];
        });
    }

    /**
     * @return array<int,StaffApiKey>
     */
    public function listKeys(?int $userId = null, bool $includeRevoked = false): array
    {
        /** @var EloquentCollection<int,StaffApiKey> $records */
        $records = StaffApiKey::query()
            ->with('user.role')
            ->when($userId !== null && $userId > 0, static fn ($query) => $query->where('user_id', $userId))
            ->when(! $includeRevoked, static fn ($query) => $query->active())
            ->orderByDesc('created_at')
            ->orderByDesc('staff_api_key_id')
            ->get();

        return $records->all();
    }

    public function showKey(int $staffApiKeyId): StaffApiKey
    {
        /** @var StaffApiKey $record */
        $record = StaffApiKey::query()
            ->with('user.role')
            ->findOrFail($staffApiKeyId);

        return $record;
    }

    private function normalizeLabel(string $label): string
    {
        $normalized = trim($label);
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'label' => ['Staff API key label must not be empty.'],
            ]);
        }

        return Str::limit($normalized, 100, '');
    }

    private function generatePlaintextKey(): string
    {
        return 'spk_' . Str::lower(Str::random(48));
    }

    private function hashKey(string $plaintextKey): string
    {
        return hash('sha256', $plaintextKey);
    }

    private function assertIssuableUser(int $userId): void
    {
        /** @var User|null $user */
        $user = User::query()->notDeleted()->find($userId);
        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'user_id' => ['Staff API key target user does not exist or is deleted.'],
            ]);
        }

        $allowedRoleIds = array_values(array_filter(array_map(
            static fn ($value): int => (int) $value,
            (array) config('staff_auth.allowed_role_ids', [])
        ), static fn (int $value): bool => $value > 0));

        if ($allowedRoleIds === []) {
            throw ValidationException::withMessages([
                'staff_auth' => ['staff_auth.allowed_role_ids must not be empty when issuing staff API keys.'],
            ]);
        }

        if (! in_array((int) ($user->role_id ?? 0), $allowedRoleIds, true)) {
            throw ValidationException::withMessages([
                'user_id' => ['Staff API keys can only be issued for configured staff/admin roles.'],
            ]);
        }
    }
}
