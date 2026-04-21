<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\UseCases\Tiers;

use App\Modules\Loyalty\Domain\Models\LoyaltyTier;
use App\Support\AuditEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoyaltyTierManagementService
{
    /**
     * @return list<array<string,mixed>>
     */
    public function list(string $term = ''): array
    {
        return LoyaltyTier::query()
            ->when(trim($term) !== '', function ($query) use ($term) {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('tier_code', 'like', '%'.trim($term).'%')
                        ->orWhere('tier_name', 'like', '%'.trim($term).'%');
                });
            })
            ->orderBy('min_points')
            ->get()
            ->map(fn (LoyaltyTier $tier) => $this->serializeTier($tier))
            ->all();
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function store(array $payload, ?int $actorUserId = null): array
    {
        $tier = new LoyaltyTier;
        $tier->fill($payload);
        $tier->row_version = 1;
        $tier->save();

        AuditEvent::info('admin.loyalty_tier.created', [
            'tier_id' => (int) $tier->tier_id,
            'tier_code' => (string) $tier->tier_code,
            '_audit' => [
                'action' => 'master_data.loyalty_tier.created',
                'entity_type' => 'loyalty_tier',
                'entity_id' => (string) $tier->tier_id,
                'after' => $this->auditSnapshot($tier),
                'summary' => [
                    'tier_code' => (string) $tier->tier_code,
                    'min_points' => (int) $tier->min_points,
                    'is_active' => (bool) $tier->is_active,
                ],
                'actor' => $this->auditActor($actorUserId),
            ],
        ]);

        return $this->serializeTier($tier->fresh());
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function update(int $tierId, array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($tierId, $payload, $actorUserId): array {
            /** @var LoyaltyTier|null $tier */
            $tier = LoyaltyTier::query()->where('tier_id', $tierId)->lockForUpdate()->first();
            if (! $tier) {
                throw (new ModelNotFoundException)->setModel(LoyaltyTier::class, [$tierId]);
            }

            $expectedRowVersion = (int) ($payload['row_version'] ?? 0);
            if ((int) ($tier->row_version ?? 1) !== $expectedRowVersion) {
                throw ValidationException::withMessages([
                    'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
                ]);
            }

            unset($payload['row_version']);
            $before = $this->auditSnapshot($tier);

            if (array_key_exists('is_active', $payload) && (bool) $payload['is_active'] === false) {
                $currentUsers = (int) DB::table('users')
                    ->where('current_tier_id', (int) $tier->tier_id)
                    ->count();

                if ($currentUsers > 0) {
                    throw ValidationException::withMessages([
                        'is_active' => ['Cannot deactivate a loyalty tier that is currently assigned to users.'],
                    ]);
                }
            }

            $tier->fill($payload);
            $tier->row_version = ((int) ($tier->row_version ?? 1)) + 1;
            $tier->save();

            AuditEvent::info('admin.loyalty_tier.updated', [
                'tier_id' => (int) $tier->tier_id,
                'tier_code' => (string) $tier->tier_code,
                '_audit' => [
                    'action' => 'master_data.loyalty_tier.updated',
                    'entity_type' => 'loyalty_tier',
                    'entity_id' => (string) $tier->tier_id,
                    'before' => $before,
                    'after' => $this->auditSnapshot($tier),
                    'summary' => [
                        'tier_code' => (string) $tier->tier_code,
                        'min_points' => (int) $tier->min_points,
                        'is_active' => (bool) $tier->is_active,
                    ],
                    'actor' => $this->auditActor($actorUserId),
                ],
            ]);

            return $this->serializeTier($tier->fresh());
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeTier(LoyaltyTier $tier): array
    {
        return [
            'tier_id' => (int) $tier->tier_id,
            'tier_code' => (string) $tier->tier_code,
            'tier_name' => (string) $tier->tier_name,
            'min_points' => (int) $tier->min_points,
            'benefits_json' => $tier->benefits_json,
            'is_active' => (bool) $tier->is_active,
            'row_version' => (int) ($tier->row_version ?? 1),
            'created_at' => $tier->created_at?->toIso8601String(),
            'updated_at' => $tier->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function auditSnapshot(LoyaltyTier $tier): array
    {
        return [
            'tier_code' => (string) $tier->tier_code,
            'tier_name' => (string) $tier->tier_name,
            'min_points' => (int) $tier->min_points,
            'benefits_json' => $tier->benefits_json,
            'is_active' => (bool) $tier->is_active,
            'row_version' => (int) ($tier->row_version ?? 1),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function auditActor(?int $actorUserId): ?array
    {
        if ($actorUserId === null || $actorUserId <= 0) {
            return null;
        }

        return [
            'type' => 'staff_user',
            'user_id' => $actorUserId,
            'key' => 'staff_user:'.$actorUserId,
        ];
    }
}
