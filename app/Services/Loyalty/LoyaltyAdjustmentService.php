<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Models\User;
use App\Models\UserPoint;
use App\Support\AuditEvent;
use Illuminate\Validation\ValidationException;

class LoyaltyAdjustmentService
{
    public function __construct(
        private readonly LoyaltyLedgerWriter $ledgerWriter,
        private readonly LoyaltyTierSyncService $tierSyncService,
    ) {}

    public function adjustUserPointsLocked(User $user, UserPoint $pointLedger, int $points, string $reason, ?int $staffUserId = null): void
    {
        $newBalance = (int) $pointLedger->total_points + $points;
        if ($newBalance < 0) {
            throw ValidationException::withMessages([
                'points' => ['points adjustment would make total_points negative.'],
            ]);
        }

        $this->ledgerWriter->writeTransaction(
            userId: (int) $user->user_id,
            reservationId: null,
            txnType: 'Adjust',
            points: $points,
            amountBasis: null,
            currency: 'VND',
            reason: self::composeReason('manual.adjust', $reason),
            staffUserId: $staffUserId,
        );
        $this->ledgerWriter->applyPointDelta($pointLedger, $points, $staffUserId);

        $this->tierSyncService->syncUserTierLocked($user, $pointLedger, $staffUserId, 'manual_adjust');

        AuditEvent::info('loyalty_points_adjusted', [
            'user_id' => (int) $user->user_id,
            'points' => $points,
            'reason' => $reason,
            'new_total_points' => (int) $pointLedger->total_points,
            'actor_user_id' => $staffUserId,
        ]);
    }

    private static function composeReason(string $base, ?string $detail = null): string
    {
        $detail = trim((string) $detail);

        return $detail === '' ? $base : $base . ':' . $detail;
    }
}
