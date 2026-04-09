<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Models\LoyaltyTier;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\UserTierHistory;
use Illuminate\Support\Carbon;

class LoyaltyTierSyncService
{
    public function syncUserTierLocked(User $user, UserPoint $pointLedger, ?int $staffUserId, string $reason): void
    {
        $totalPoints = max(0, (int) $pointLedger->total_points);
        $targetTier = LoyaltyTier::query()
            ->where('is_active', 1)
            ->where('min_points', '<=', $totalPoints)
            ->orderByDesc('min_points')
            ->orderByDesc('tier_id')
            ->lockForUpdate()
            ->first();

        $currentTierId = $user->current_tier_id !== null ? (int) $user->current_tier_id : null;
        $targetTierId = $targetTier?->tier_id !== null ? (int) $targetTier->tier_id : null;
        if ($currentTierId === $targetTierId) {
            return;
        }

        if ($targetTierId === null) {
            $user->current_tier_id = null;
            $user->save();

            return;
        }

        $history = new UserTierHistory();
        $history->user_id = (int) $user->user_id;
        $history->from_tier_id = $currentTierId;
        $history->to_tier_id = $targetTierId;
        $history->reason = $reason;
        $history->effective_at = Carbon::now('UTC');
        $history->created_by = $staffUserId;
        $history->created_at = Carbon::now('UTC');
        $history->save();

        $user->current_tier_id = $targetTierId;
        $user->save();
    }
}
