<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\UseCases\Points;

use App\SharedKernel\Money\Money;
use Illuminate\Support\Facades\DB;

class ReservationLoyaltySummaryReader
{
    public function __construct(
        private readonly LoyaltyPointsService $loyaltyPointsService,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function getReservationSummary(int $reservationId): array
    {
        return $this->loyaltyPointsService->getReservationLoyaltySummary($reservationId);
    }

    public function currentDiscountAmount(int $reservationId, bool $lock = false): float
    {
        $query = DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where(function ($nested): void {
                $nested->where(function ($q): void {
                    $q->where('txn_type', 'Redeem')
                        ->where('reason', 'like', 'redeem.apply%');
                })->orWhere(function ($q): void {
                    $q->where('txn_type', 'Adjust')
                        ->where('reason', 'like', 'redeem.release%');
                });
            })
            ->orderBy('txn_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $amountMinor = 0;
        foreach ($query->get(['txn_type', 'amount_basis']) as $transaction) {
            $basisMinor = Money::minorUnits($transaction->amount_basis ?? 0, true);
            if ($basisMinor <= 0) {
                continue;
            }

            if ((string) ($transaction->txn_type ?? '') === 'Redeem') {
                $amountMinor += $basisMinor;

                continue;
            }

            $amountMinor -= $basisMinor;
        }

        return Money::minorToFloat(max(0, $amountMinor));
    }
}
