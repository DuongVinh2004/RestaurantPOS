<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\UseCases\Points;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Loyalty\Domain\Models\LoyaltyPointTransaction;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use App\SharedKernel\Money\Money;

class LoyaltyBalanceService
{
    public function __construct(
        private readonly RuntimeSettingService $runtimeSettings,
    ) {}

    public function currentEarnNetPointsForReservation(int $reservationId): int
    {
        return (int) LoyaltyPointTransaction::query()
            ->where('reservation_id', $reservationId)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('txn_type', 'Earn')
                        ->where('reason', 'earn.completed');
                })->orWhere(function ($q) {
                    $q->where('txn_type', 'Adjust')
                        ->where(function ($nested) {
                            $nested->where('reason', 'earn.sync.refund')
                                ->orWhere('reason', 'earn.sync.complete');
                        });
                });
            })
            ->sum('points');
    }

    public function currentRedeemedPointsForReservation(int $reservationId, bool $lock = false): int
    {
        $query = LoyaltyPointTransaction::query()
            ->where('reservation_id', $reservationId)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('txn_type', 'Redeem')
                        ->where('reason', 'like', 'redeem.apply%');
                })->orWhere(function ($q) {
                    $q->where('txn_type', 'Adjust')
                        ->where('reason', 'like', 'redeem.release%');
                });
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        $sum = (int) $query->sum('points');

        return max(0, -$sum);
    }

    public function currentRedeemedAmountForReservation(int $reservationId, bool $lock = false): float
    {
        $query = LoyaltyPointTransaction::query()
            ->where('reservation_id', $reservationId)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('txn_type', 'Redeem')
                        ->where('reason', 'like', 'redeem.apply%');
                })->orWhere(function ($q) {
                    $q->where('txn_type', 'Adjust')
                        ->where('reason', 'like', 'redeem.release%');
                });
            })
            ->orderBy('txn_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $amountMinor = 0;
        foreach ($query->get(['txn_type', 'amount_basis', 'reason']) as $tx) {
            $basisMinor = Money::minorUnits($tx->amount_basis ?? 0, true);
            if ($basisMinor <= 0) {
                continue;
            }

            if ((string) $tx->txn_type === 'Redeem') {
                $amountMinor += $basisMinor;

                continue;
            }

            $amountMinor -= $basisMinor;
        }

        return Money::minorToFloat(max(0, $amountMinor));
    }

    /**
     * @param  array<string,float>  $paymentSummary
     */
    public function desiredEarnPointsForReservation(Reservation $reservation, array $paymentSummary): int
    {
        $basis = $this->earnBasisForReservation($reservation, $paymentSummary);

        return intdiv(Money::minorUnits($basis, true), max(1, Money::minorUnits($this->earnAmountPerPoint(), true)));
    }

    /**
     * Loyalty earn basis intentionally excludes deposit net.
     *
     * Points are awarded only on the net final-settlement amount that remains captured
     * for the reservation after refunds. Deposit flows still matter for settlement and
     * refund accounting, but they do not create additional earn basis for loyalty.
     *
     * @param  array<string,float>  $paymentSummary
     */
    public function earnBasisForReservation(Reservation $reservation, array $paymentSummary): float
    {
        $finalNetMinor = Money::minorUnits($paymentSummary['final_net_amount'] ?? 0, true);
        $billMinor = $reservation->final_bill_amount !== null
            ? Money::minorUnits($reservation->final_bill_amount, true)
            : $finalNetMinor;

        if ($billMinor <= 0) {
            return Money::minorToFloat($finalNetMinor);
        }

        return Money::minorToFloat(min($billMinor, $finalNetMinor));
    }

    public function earnAmountPerPoint(): float
    {
        return max(0.01, $this->runtimeSettings->float('loyalty.earn_amount_per_point', (float) config('booking.loyalty_earn_amount_per_point', 10000)));
    }

    public function redeemAmountPerPoint(): float
    {
        return max(0.01, $this->runtimeSettings->float('loyalty.redeem_amount_per_point', (float) config('booking.loyalty_redeem_amount_per_point', 1000)));
    }

    public function minRedeemPoints(): int
    {
        return max(1, $this->runtimeSettings->int('loyalty.min_redeem_points', (int) config('booking.loyalty_min_redeem_points', 1)));
    }
}
