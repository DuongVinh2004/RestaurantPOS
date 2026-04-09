<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserPoint;
use App\Support\AuditEvent;
use App\Support\PaymentSummary;
use Illuminate\Support\Collection;

class LoyaltyCompletionSyncService
{
    public function __construct(
        private readonly LoyaltyBalanceService $balanceService,
        private readonly LoyaltyLedgerWriter $ledgerWriter,
        private readonly LoyaltyTierSyncService $tierSyncService,
    ) {}

    /**
     * @param Collection<int,Payment> $payments
     */
    public function syncReservationCompletionLocked(
        Reservation $reservation,
        User $user,
        UserPoint $pointLedger,
        Collection $payments,
        ?int $staffUserId = null,
    ): void {
        $summary = PaymentSummary::fromPayments($payments);
        $desired = $this->balanceService->desiredEarnPointsForReservation($reservation, $summary);
        $current = $this->balanceService->currentEarnNetPointsForReservation((int) $reservation->reservation_id);
        $delta = $desired - $current;

        if ($delta === 0) {
            return;
        }

        if ($delta > 0) {
            $this->ledgerWriter->writeTransaction(
                userId: (int) $user->user_id,
                reservationId: (int) $reservation->reservation_id,
                txnType: $current === 0 ? 'Earn' : 'Adjust',
                points: $delta,
                amountBasis: $this->balanceService->earnBasisForReservation($reservation, $summary),
                currency: (string) ($reservation->bill_currency ?: 'VND'),
                reason: $current === 0 ? 'earn.completed' : 'earn.sync.complete',
                staffUserId: $staffUserId,
            );
            $this->ledgerWriter->applyPointDelta($pointLedger, $delta, $staffUserId);
        } else {
            $clawback = min((int) $pointLedger->total_points, abs($delta));
            if ($clawback > 0) {
                $this->ledgerWriter->writeTransaction(
                    userId: (int) $user->user_id,
                    reservationId: (int) $reservation->reservation_id,
                    txnType: 'Adjust',
                    points: -$clawback,
                    amountBasis: $this->balanceService->earnBasisForReservation($reservation, $summary),
                    currency: (string) ($reservation->bill_currency ?: 'VND'),
                    reason: 'earn.sync.complete',
                    staffUserId: $staffUserId,
                );
                $this->ledgerWriter->applyPointDelta($pointLedger, -$clawback, $staffUserId);
            }

            if ($clawback < abs($delta)) {
                AuditEvent::warning('loyalty_points_clawback_shortfall', [
                    'reservation_id' => (int) $reservation->reservation_id,
                    'user_id' => (int) $user->user_id,
                    'desired_delta' => $delta,
                    'actual_clawback' => -$clawback,
                    'current_points_balance' => (int) $pointLedger->total_points,
                ]);
            }
        }

        $this->tierSyncService->syncUserTierLocked($user, $pointLedger, $staffUserId, 'reservation_completed');
    }
}
