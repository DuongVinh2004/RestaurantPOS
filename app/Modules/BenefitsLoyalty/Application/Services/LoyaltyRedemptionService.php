<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Application\Services;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Models\User;
use App\Modules\BenefitsLoyalty\Domain\Models\UserPoint;
use App\Modules\CheckoutPayments\Application\Services\ReservationFinancialSyncService;
use App\Modules\CheckoutPayments\Domain\Models\Payment;
use App\Modules\CheckoutPayments\Domain\ValueObjects\PaymentSummary;
use App\Support\AuditEvent;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LoyaltyRedemptionService
{
    public function __construct(
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
        private readonly LoyaltyLedgerWriter $ledgerWriter,
        private readonly LoyaltyTierSyncService $tierSyncService,
        private readonly LoyaltyBalanceService $balanceService,
    ) {}

    /**
     * @param  callable(Reservation,User,UserPoint,Collection<int,Payment>):array<string,mixed>  $buildReservationSnapshot
     */
    public function redeemLocked(
        Reservation $reservation,
        User $user,
        UserPoint $pointLedger,
        Collection $payments,
        int $points,
        ?string $reason,
        ?int $staffUserId,
        callable $buildReservationSnapshot,
    ): void {
        $paymentSummary = PaymentSummary::fromPayments($payments);
        if ((float) ($paymentSummary['final_net_amount'] ?? 0.0) > 0.0001) {
            throw ValidationException::withMessages([
                'reservation' => ['Cannot change loyalty redemption after final payment has been recorded.'],
            ]);
        }

        $snapshot = $buildReservationSnapshot($reservation, $user, $pointLedger, $payments);
        $allowedPoints = (int) ($snapshot['loyalty']['max_redeemable_points'] ?? 0);
        if ($allowedPoints <= 0) {
            throw ValidationException::withMessages([
                'points' => ['No redeemable loyalty points can be applied to this reservation.'],
            ]);
        }

        if ($points < $this->balanceService->minRedeemPoints()) {
            throw ValidationException::withMessages([
                'points' => [sprintf('points must be at least %d.', $this->balanceService->minRedeemPoints())],
            ]);
        }

        if ($points > $allowedPoints) {
            throw ValidationException::withMessages([
                'points' => [sprintf('points cannot exceed %d for this reservation right now.', $allowedPoints)],
            ]);
        }

        $redeemAmount = round($points * $this->balanceService->redeemAmountPerPoint(), 2);
        if ($redeemAmount <= 0.0001) {
            throw ValidationException::withMessages([
                'points' => ['The requested points do not convert into a valid discount amount.'],
            ]);
        }

        $newBalance = (int) $pointLedger->total_points - $points;
        if ($newBalance < 0) {
            throw ValidationException::withMessages([
                'points' => ['User does not have enough points.'],
            ]);
        }

        $this->ledgerWriter->writeTransaction(
            userId: (int) $user->user_id,
            reservationId: (int) $reservation->reservation_id,
            txnType: 'Redeem',
            points: -$points,
            amountBasis: $redeemAmount,
            currency: (string) ($reservation->bill_currency ?: 'VND'),
            reason: self::composeReason('redeem.apply', $reason),
            staffUserId: $staffUserId,
        );
        $this->ledgerWriter->applyPointDelta($pointLedger, -$points, $staffUserId);

        $this->reservationFinancialSyncService->syncReservationDiscountSnapshot(
            reservation: $reservation,
            totalDiscount: round(max(0.0, (float) ($reservation->discount_amount ?? 0.0) + $redeemAmount), 2),
            lockOrders: true,
        );
        $reservation->updated_by = $staffUserId;
        $reservation->save();

        $this->tierSyncService->syncUserTierLocked($user, $pointLedger, $staffUserId, 'points_redeemed');

        AuditEvent::info('loyalty_points_redeemed', [
            'reservation_id' => (int) $reservation->reservation_id,
            'user_id' => (int) $user->user_id,
            'points' => $points,
            'amount_basis' => $redeemAmount,
            'remaining_points' => (int) $pointLedger->total_points,
            'actor_user_id' => $staffUserId,
        ]);
    }

    /**
     * @return array{released_points:int,released_amount:float}
     */
    public function releaseLocked(
        Reservation $reservation,
        User $user,
        UserPoint $pointLedger,
        ?int $staffUserId,
        ?string $reason = null,
        bool $persistReservation = true,
    ): array {
        $currentRedeemedPoints = $this->balanceService->currentRedeemedPointsForReservation((int) $reservation->reservation_id, true);
        $currentRedeemedAmount = $this->balanceService->currentRedeemedAmountForReservation((int) $reservation->reservation_id, true);
        if ($currentRedeemedPoints <= 0 || $currentRedeemedAmount <= 0.0001) {
            return ['released_points' => 0, 'released_amount' => 0.0];
        }

        $this->ledgerWriter->writeTransaction(
            userId: (int) $user->user_id,
            reservationId: (int) $reservation->reservation_id,
            txnType: 'Adjust',
            points: $currentRedeemedPoints,
            amountBasis: $currentRedeemedAmount,
            currency: (string) ($reservation->bill_currency ?: 'VND'),
            reason: self::composeReason('redeem.release', $reason),
            staffUserId: $staffUserId,
        );
        $this->ledgerWriter->applyPointDelta($pointLedger, $currentRedeemedPoints, $staffUserId);

        $this->reservationFinancialSyncService->syncReservationDiscountSnapshot(
            reservation: $reservation,
            totalDiscount: max(0.0, round((float) ($reservation->discount_amount ?? 0.0) - $currentRedeemedAmount, 2)),
            lockOrders: true,
        );
        $reservation->updated_by = $staffUserId;
        if ($persistReservation) {
            $reservation->save();
        }

        AuditEvent::info('loyalty_redemption_released', [
            'reservation_id' => (int) $reservation->reservation_id,
            'user_id' => (int) $user->user_id,
            'released_points' => $currentRedeemedPoints,
            'released_amount' => $currentRedeemedAmount,
            'actor_user_id' => $staffUserId,
            'reason' => $reason,
        ]);

        return [
            'released_points' => $currentRedeemedPoints,
            'released_amount' => $currentRedeemedAmount,
        ];
    }

    private static function composeReason(string $base, ?string $detail = null): string
    {
        $detail = trim((string) $detail);

        return $detail === '' ? $base : $base.':'.$detail;
    }
}
