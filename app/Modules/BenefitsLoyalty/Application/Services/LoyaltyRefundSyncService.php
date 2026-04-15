<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Application\Services;

use App\Enums\ReservationStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Models\User;
use App\Modules\BenefitsLoyalty\Domain\Models\UserPoint;
use App\Modules\BenefitsLoyalty\Domain\ValueObjects\LoyaltyEarnReconciliation;
use App\Modules\CheckoutPayments\Domain\Models\Payment;
use App\Modules\CheckoutPayments\Domain\ValueObjects\PaymentSummary;
use App\Platform\Metrics\Services\MetricsService;
use App\Support\AuditEvent;
use Illuminate\Support\Collection;

class LoyaltyRefundSyncService
{
    public function __construct(
        private readonly LoyaltyBalanceService $balanceService,
        private readonly LoyaltyLedgerWriter $ledgerWriter,
        private readonly LoyaltyTierSyncService $tierSyncService,
        private readonly LoyaltyRedemptionService $redemptionService,
    ) {}

    /**
     * @param  Collection<int,Payment>  $payments
     */
    public function syncReservationRefundImpactLocked(
        Reservation $reservation,
        User $user,
        UserPoint $pointLedger,
        Collection $payments,
        ?int $staffUserId = null,
        bool $cancelled = false,
    ): void {
        if ($cancelled) {
            $this->redemptionService->releaseLocked(
                reservation: $reservation,
                user: $user,
                pointLedger: $pointLedger,
                staffUserId: $staffUserId,
                reason: 'cancelled_after_payment',
                persistReservation: false,
            );
        }

        $currentStatus = (string) ($reservation->status?->value ?? $reservation->status);
        $shouldReconcileEarn = $cancelled || $currentStatus === ReservationStatus::Completed->value;
        if (! $shouldReconcileEarn) {
            $this->tierSyncService->syncUserTierLocked($user, $pointLedger, $staffUserId, 'refund_sync');

            return;
        }

        $summary = PaymentSummary::fromPayments($payments);
        $desired = $cancelled ? 0 : $this->balanceService->desiredEarnPointsForReservation($reservation, $summary);
        $current = $this->balanceService->currentEarnNetPointsForReservation((int) $reservation->reservation_id);
        $plan = LoyaltyEarnReconciliation::plan($desired, $current, (int) $pointLedger->total_points);
        $adjustmentPoints = (int) ($plan['adjustment_points'] ?? 0);
        $clawbackPoints = (int) ($plan['clawback_points'] ?? 0);
        $shortfallPoints = (int) ($plan['shortfall_points'] ?? 0);

        if ($adjustmentPoints !== 0) {
            $this->ledgerWriter->writeTransaction(
                userId: (int) $user->user_id,
                reservationId: (int) $reservation->reservation_id,
                txnType: 'Adjust',
                points: $adjustmentPoints,
                amountBasis: $this->balanceService->earnBasisForReservation($reservation, $summary),
                currency: (string) ($reservation->bill_currency ?: 'VND'),
                reason: 'earn.sync.refund',
                staffUserId: $staffUserId,
            );
            $this->ledgerWriter->applyPointDelta($pointLedger, $adjustmentPoints, $staffUserId);
        }

        if ($shortfallPoints > 0) {
            AuditEvent::warning('loyalty_points_refund_clawback_shortfall', [
                'reservation_id' => (int) $reservation->reservation_id,
                'user_id' => (int) $user->user_id,
                'desired_earn_points' => $desired,
                'current_earn_net_points' => $current,
                'clawback_points' => $clawbackPoints,
                'shortfall_points' => $shortfallPoints,
                'current_points_balance' => (int) $pointLedger->total_points,
            ]);

            try {
                app(MetricsService::class)->inc('loyalty_clawback_shortfall_events_total', ['reason' => 'insufficient_balance']);
                app(MetricsService::class)->inc('loyalty_clawback_shortfall_points_total', ['reason' => 'insufficient_balance'], $shortfallPoints);
            } catch (\Throwable) {
                // ignore metrics failures inside the loyalty transaction
            }
        }

        $this->tierSyncService->syncUserTierLocked($user, $pointLedger, $staffUserId, 'refund_sync');
    }
}
