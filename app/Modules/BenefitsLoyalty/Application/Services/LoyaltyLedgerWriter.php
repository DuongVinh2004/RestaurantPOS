<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Application\Services;

use App\Models\User;
use App\Modules\BenefitsLoyalty\Domain\Models\LoyaltyPointTransaction;
use App\Modules\BenefitsLoyalty\Domain\Models\UserPoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LoyaltyLedgerWriter
{
    public function lockUserPointLedger(User $user, ?int $staffUserId = null): UserPoint
    {
        $userId = (int) $user->user_id;

        $pointLedger = UserPoint::query()->where('user_id', $userId)->lockForUpdate()->first();
        if ($pointLedger) {
            return $pointLedger;
        }

        DB::table('user_points')->insertOrIgnore([
            'user_id' => $userId,
            'total_points' => 0,
            'updated_by' => $staffUserId,
        ]);

        /** @var UserPoint $fresh */
        $fresh = UserPoint::query()->where('user_id', $userId)->lockForUpdate()->firstOrFail();

        return $fresh;
    }

    public function writeTransaction(
        int $userId,
        ?int $reservationId,
        string $txnType,
        int $points,
        ?float $amountBasis,
        string $currency,
        ?string $reason,
        ?int $staffUserId = null,
    ): LoyaltyPointTransaction {
        $tx = new LoyaltyPointTransaction;
        $tx->user_id = $userId;
        $tx->reservation_id = $reservationId;
        $tx->txn_type = $txnType;
        $tx->points = $points;
        $tx->amount_basis = $amountBasis !== null ? round(max(0.0, $amountBasis), 2) : null;
        $tx->currency = trim($currency) !== '' ? trim($currency) : 'VND';
        $tx->reason = $reason;
        $tx->created_at = Carbon::now('UTC');
        $tx->created_by = $staffUserId;
        $tx->save();

        return $tx;
    }

    public function applyPointDelta(UserPoint $pointLedger, int $pointsDelta, ?int $staffUserId = null): void
    {
        $pointLedger->total_points = (int) $pointLedger->total_points + $pointsDelta;
        $pointLedger->updated_by = $staffUserId;
        $pointLedger->save();
    }
}
