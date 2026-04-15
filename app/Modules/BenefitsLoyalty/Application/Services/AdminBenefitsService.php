<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Application\Services;

use App\Modules\BenefitsLoyalty\Domain\Models\LoyaltyTier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminBenefitsService
{
    public function listTiers(): Collection
    {
        return LoyaltyTier::query()
            ->orderBy('min_points')
            ->orderBy('tier_id')
            ->get();
    }

    public function createTier(array $payload): LoyaltyTier
    {
        return DB::transaction(function () use ($payload): LoyaltyTier {
            $tier = new LoyaltyTier;
            $tier->tier_code = (string) $payload['tier_code'];
            $tier->tier_name = (string) $payload['tier_name'];
            $tier->min_points = (int) $payload['min_points'];
            $tier->benefits_json = $this->normalizeBenefits($payload['benefits_json'] ?? []);
            $tier->is_active = (bool) ($payload['is_active'] ?? true);
            $tier->save();

            return $tier->fresh();
        });
    }

    public function updateTier(int $tierId, array $payload, ?int $expectedRowVersion = null): LoyaltyTier
    {
        return DB::transaction(function () use ($tierId, $payload, $expectedRowVersion): LoyaltyTier {
            /** @var LoyaltyTier $tier */
            $tier = LoyaltyTier::query()->whereKey($tierId)->lockForUpdate()->firstOrFail();
            $this->assertRowVersion($tier->row_version ?? null, $expectedRowVersion);

            foreach (['tier_code', 'tier_name'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $tier->{$field} = (string) $payload[$field];
                }
            }

            if (array_key_exists('min_points', $payload)) {
                $tier->min_points = (int) $payload['min_points'];
            }

            if (array_key_exists('benefits_json', $payload)) {
                $tier->benefits_json = $this->normalizeBenefits($payload['benefits_json']);
            }

            if (array_key_exists('is_active', $payload)) {
                $nextIsActive = (bool) $payload['is_active'];
                if ($nextIsActive === false && (bool) ($tier->is_active ?? true) === true) {
                    $assignedUserCount = DB::table('users')
                        ->where('current_tier_id', (int) $tier->getKey())
                        ->count();

                    if ($assignedUserCount > 0) {
                        throw ValidationException::withMessages([
                            'is_active' => ['Cannot deactivate a loyalty tier that is currently assigned to customers.'],
                        ]);
                    }
                }

                $tier->is_active = $nextIsActive;
            }

            $tier->save();

            return $tier->fresh();
        });
    }

    public function getSettingsSnapshot(): array
    {
        $tiers = $this->listTiers();

        return [
            'loyalty_tiers' => $tiers,
            'meta' => [
                'active_tier_count' => $tiers->where('is_active', true)->count(),
                'tier_count' => $tiers->count(),
            ],
        ];
    }

    private function normalizeBenefits(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages([
                'benefits_json' => ['benefits_json must be a JSON object or array.'],
            ]);
        }

        return Arr::undot($value) ?: $value;
    }

    private function assertRowVersion(mixed $actual, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($actual ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }
}
