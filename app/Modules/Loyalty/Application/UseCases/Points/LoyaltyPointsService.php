<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\UseCases\Points;

use App\Enums\ReservationStatus;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Loyalty\Domain\Models\LoyaltyPointTransaction;
use App\Modules\Loyalty\Domain\Models\LoyaltyTier;
use App\Modules\Loyalty\Domain\Models\UserPoint;
use App\Modules\Loyalty\Domain\Models\UserTierHistory;
use App\Modules\Loyalty\Domain\ValueObjects\LoyaltyEarnReconciliation;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use App\Platform\Metrics\Services\MetricsService;
use App\SharedKernel\Money\Money;
use App\Support\AuditEvent;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoyaltyPointsService
{
    public function __construct(
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
        private readonly RuntimeSettingService $runtimeSettings,
    ) {}

    private const REASON_EARN_COMPLETED = 'earn.completed';

    private const REASON_EARN_SYNC_REFUND = 'earn.sync.refund';

    private const REASON_EARN_SYNC_COMPLETE = 'earn.sync.complete';

    private const REASON_REDEEM_APPLY = 'redeem.apply';

    private const REASON_REDEEM_RELEASE = 'redeem.release';

    private const REASON_MANUAL_ADJUST = 'manual.adjust';

    /**
     * @return array<string,mixed>
     */
    public function getUserLoyaltySummary(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        /** @var User $user */
        $user = User::query()
            ->with(['points', 'currentTier'])
            ->where('user_id', $userId)
            ->where('is_deleted', 0)
            ->firstOrFail();

        /** @var EloquentCollection<int,LoyaltyPointTransaction> $transactions */
        $transactions = LoyaltyPointTransaction::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('txn_id')
            ->limit($limit)
            ->get();

        return [
            'user' => $this->buildUserSummary($user),
            'transactions' => $transactions,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function getReservationLoyaltySummary(int $reservationId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        /** @var Reservation $reservation */
        $reservation = Reservation::query()
            ->with(['user.currentTier', 'user.points'])
            ->where('reservation_id', $reservationId)
            ->firstOrFail();

        $snapshot = $this->buildReservationSnapshot($reservation);

        /** @var EloquentCollection<int,LoyaltyPointTransaction> $transactions */
        $transactions = LoyaltyPointTransaction::query()
            ->where('reservation_id', $reservationId)
            ->orderByDesc('created_at')
            ->orderByDesc('txn_id')
            ->limit($limit)
            ->get();

        return [
            'reservation' => $snapshot,
            'transactions' => $transactions,
        ];
    }

    /**
     * @param  Collection<int,Payment>|null  $payments
     * @param  array{subtotal:float,discount:float,total_due:float,currency:string}|null  $billSnapshot
     * @return array<string,mixed>
     */
    public function getReservationLoyaltyPreview(Reservation $reservation, ?Collection $payments = null, ?array $billSnapshot = null): array
    {
        // Do not pre-resolve pointLedger here: buildReservationLoyaltyPayload resolves it
        // lazily via ??=, avoiding a redundant user_points query when the row is absent.
        return $this->buildReservationLoyaltyPayload(
            reservation: $reservation,
            payments: $payments,
            billSnapshot: $billSnapshot,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function redeemReservationPoints(
        int $reservationId,
        int $points,
        ?string $reason = null,
        ?int $expectedRowVersion = null,
        ?int $staffUserId = null,
    ): array {
        $reason = trim((string) $reason);

        return DB::transaction(function () use ($reservationId, $points, $reason, $expectedRowVersion, $staffUserId) {
            /** @var Reservation $reservation */
            $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
            $this->assertReservationEditableForRedeem($reservation, $expectedRowVersion);

            /** @var User $user */
            $user = User::query()
                ->where('user_id', (int) $reservation->user_id)
                ->where('is_deleted', 0)
                ->lockForUpdate()
                ->firstOrFail();

            $pointLedger = $this->lockUserPointLedger($user, $staffUserId);
            $payments = Payment::query()
                ->where('reservation_id', $reservationId)
                ->lockForUpdate()
                ->get();

            $paymentSummary = PaymentSummary::fromPayments($payments);
            if (Money::isPositive($paymentSummary['final_net_amount'] ?? 0)) {
                throw ValidationException::withMessages([
                    'reservation' => ['Cannot change loyalty redemption after final payment has been recorded.'],
                ]);
            }

            $snapshot = $this->buildReservationSnapshot($reservation, $user, $pointLedger, $payments);
            $allowedPoints = (int) ($snapshot['loyalty']['max_redeemable_points'] ?? 0);
            if ($allowedPoints <= 0) {
                throw ValidationException::withMessages([
                    'points' => ['No redeemable loyalty points can be applied to this reservation.'],
                ]);
            }

            if ($points < $this->minRedeemPoints()) {
                throw ValidationException::withMessages([
                    'points' => [sprintf('points must be at least %d.', $this->minRedeemPoints())],
                ]);
            }

            if ($points > $allowedPoints) {
                throw ValidationException::withMessages([
                    'points' => [sprintf('points cannot exceed %d for this reservation right now.', $allowedPoints)],
                ]);
            }

            $redeemAmountMinor = Money::minorUnits($this->redeemAmountPerPoint(), true) * $points;
            $redeemAmount = Money::minorToFloat($redeemAmountMinor);
            if ($redeemAmountMinor <= 0) {
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

            $tx = new LoyaltyPointTransaction;
            $tx->user_id = (int) $user->user_id;
            $tx->reservation_id = $reservationId;
            $tx->txn_type = 'Redeem';
            $tx->points = -$points;
            $tx->amount_basis = $redeemAmount;
            $tx->currency = (string) ($reservation->bill_currency ?: 'VND');
            $tx->reason = $this->composeReason(self::REASON_REDEEM_APPLY, $reason !== '' ? $reason : null);
            $tx->created_at = Carbon::now('UTC');
            $tx->created_by = $staffUserId;
            $tx->save();

            $pointLedger->total_points = $newBalance;
            $pointLedger->updated_by = $staffUserId;
            $pointLedger->save();

            $this->reservationFinancialSyncService->syncReservationDiscountSnapshot(
                reservation: $reservation,
                totalDiscount: Money::minorToFloat(
                    Money::minorUnits($reservation->discount_amount ?? 0, true) + $redeemAmountMinor
                ),
                lockOrders: true,
            );
            $reservation->updated_by = $staffUserId;
            $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $staffUserId);

            $this->syncUserTierLocked($user, $pointLedger, $staffUserId, 'points_redeemed');

            AuditEvent::info('loyalty_points_redeemed', [
                'reservation_id' => $reservationId,
                'user_id' => (int) $user->user_id,
                'points' => $points,
                'amount_basis' => $redeemAmount,
                'remaining_points' => (int) $pointLedger->total_points,
                'actor_user_id' => $staffUserId,
            ]);

            return $this->getReservationLoyaltySummary($reservationId);
        });
    }

    /**
     * @return array<string,mixed>
     */
    public function releaseReservationRedemption(
        int $reservationId,
        ?string $reason = null,
        ?int $expectedRowVersion = null,
        ?int $staffUserId = null,
    ): array {
        $reason = trim((string) $reason);

        return DB::transaction(function () use ($reservationId, $reason, $expectedRowVersion, $staffUserId) {
            /** @var Reservation $reservation */
            $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
            $this->assertReservationEditableForRedeem($reservation, $expectedRowVersion);

            $payments = Payment::query()
                ->where('reservation_id', $reservationId)
                ->lockForUpdate()
                ->get();

            $paymentSummary = PaymentSummary::fromPayments($payments);
            if (Money::isPositive($paymentSummary['final_net_amount'] ?? 0)) {
                throw ValidationException::withMessages([
                    'reservation' => ['Cannot release loyalty redemption after final payment has been recorded.'],
                ]);
            }

            /** @var User $user */
            $user = User::query()
                ->where('user_id', (int) $reservation->user_id)
                ->where('is_deleted', 0)
                ->lockForUpdate()
                ->firstOrFail();

            $pointLedger = $this->lockUserPointLedger($user, $staffUserId);
            $released = $this->releaseReservationRedemptionLocked($reservation, $user, $pointLedger, $staffUserId, $reason !== '' ? $reason : null, true);

            if ($released['released_points'] <= 0) {
                AuditEvent::info('loyalty_redemption_release_noop', [
                    'reservation_id' => $reservationId,
                    'user_id' => (int) $user->user_id,
                    'actor_user_id' => $staffUserId,
                ]);

                return $this->getReservationLoyaltySummary($reservationId);
            }

            return $this->getReservationLoyaltySummary($reservationId);
        });
    }

    /**
     * @return array<string,mixed>
     */
    public function adjustUserPoints(int $userId, int $points, string $reason, ?int $staffUserId = null): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['reason is required.'],
            ]);
        }

        return DB::transaction(function () use ($userId, $points, $reason, $staffUserId) {
            /** @var User $user */
            $user = User::query()
                ->where('user_id', $userId)
                ->where('is_deleted', 0)
                ->lockForUpdate()
                ->firstOrFail();

            $pointLedger = $this->lockUserPointLedger($user, $staffUserId);
            $newBalance = (int) $pointLedger->total_points + $points;
            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'points' => ['points adjustment would make total_points negative.'],
                ]);
            }

            $tx = new LoyaltyPointTransaction;
            $tx->user_id = $userId;
            $tx->reservation_id = null;
            $tx->txn_type = 'Adjust';
            $tx->points = $points;
            $tx->amount_basis = null;
            $tx->currency = 'VND';
            $tx->reason = $this->composeReason(self::REASON_MANUAL_ADJUST, $reason);
            $tx->created_at = Carbon::now('UTC');
            $tx->created_by = $staffUserId;
            $tx->save();

            $pointLedger->total_points = $newBalance;
            $pointLedger->updated_by = $staffUserId;
            $pointLedger->save();

            $this->syncUserTierLocked($user, $pointLedger, $staffUserId, 'manual_adjust');

            AuditEvent::info('loyalty_points_adjusted', [
                'user_id' => $userId,
                'points' => $points,
                'reason' => $reason,
                'new_total_points' => (int) $pointLedger->total_points,
                'actor_user_id' => $staffUserId,
            ]);

            return $this->getUserLoyaltySummary($userId);
        });
    }

    public function syncReservationCompletionLocked(Reservation $reservation, ?int $staffUserId = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $currentStatus = (string) ($reservation->status?->value ?? $reservation->status);
        if ($currentStatus !== ReservationStatus::Completed->value) {
            return;
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('user_id', (int) $reservation->user_id)
            ->where('is_deleted', 0)
            ->lockForUpdate()
            ->first();
        if (! $user) {
            return;
        }

        $pointLedger = $this->lockUserPointLedger($user, $staffUserId);
        $payments = Payment::query()
            ->where('reservation_id', (int) $reservation->reservation_id)
            ->lockForUpdate()
            ->get();

        $summary = PaymentSummary::fromPayments($payments);
        $desired = $this->desiredEarnPointsForReservation($reservation, $summary);
        $current = $this->currentEarnNetPointsForReservation((int) $reservation->reservation_id);
        $delta = $desired - $current;

        if ($delta === 0) {
            return;
        }

        if ($delta > 0) {
            $tx = new LoyaltyPointTransaction;
            $tx->user_id = (int) $user->user_id;
            $tx->reservation_id = (int) $reservation->reservation_id;
            $tx->txn_type = $current === 0 ? 'Earn' : 'Adjust';
            $tx->points = $delta;
            $tx->amount_basis = $this->earnBasisForReservation($reservation, $summary);
            $tx->currency = (string) ($reservation->bill_currency ?: 'VND');
            $tx->reason = $current === 0 ? self::REASON_EARN_COMPLETED : self::REASON_EARN_SYNC_COMPLETE;
            $tx->created_at = Carbon::now('UTC');
            $tx->created_by = $staffUserId;
            $tx->save();

            $pointLedger->total_points = (int) $pointLedger->total_points + $delta;
            $pointLedger->updated_by = $staffUserId;
            $pointLedger->save();
        } else {
            $clawback = min((int) $pointLedger->total_points, abs($delta));
            if ($clawback > 0) {
                $tx = new LoyaltyPointTransaction;
                $tx->user_id = (int) $user->user_id;
                $tx->reservation_id = (int) $reservation->reservation_id;
                $tx->txn_type = 'Adjust';
                $tx->points = -$clawback;
                $tx->amount_basis = $this->earnBasisForReservation($reservation, $summary);
                $tx->currency = (string) ($reservation->bill_currency ?: 'VND');
                $tx->reason = self::REASON_EARN_SYNC_COMPLETE;
                $tx->created_at = Carbon::now('UTC');
                $tx->created_by = $staffUserId;
                $tx->save();

                $pointLedger->total_points = (int) $pointLedger->total_points - $clawback;
                $pointLedger->updated_by = $staffUserId;
                $pointLedger->save();
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

        $this->syncUserTierLocked($user, $pointLedger, $staffUserId, 'reservation_completed');
    }

    public function syncReservationRefundImpactLocked(Reservation $reservation, Collection $payments, ?int $staffUserId = null, bool $cancelled = false): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('user_id', (int) $reservation->user_id)
            ->where('is_deleted', 0)
            ->lockForUpdate()
            ->first();
        if (! $user) {
            return;
        }

        $pointLedger = $this->lockUserPointLedger($user, $staffUserId);

        if ($cancelled) {
            $this->releaseReservationRedemptionLocked($reservation, $user, $pointLedger, $staffUserId, 'cancelled_after_payment', false);
        }

        $currentStatus = (string) ($reservation->status?->value ?? $reservation->status);
        $shouldReconcileEarn = $cancelled || $currentStatus === ReservationStatus::Completed->value;
        if (! $shouldReconcileEarn) {
            $this->syncUserTierLocked($user, $pointLedger, $staffUserId, 'refund_sync');

            return;
        }

        $summary = PaymentSummary::fromPayments($payments);
        $desired = $cancelled ? 0 : $this->desiredEarnPointsForReservation($reservation, $summary);
        $current = $this->currentEarnNetPointsForReservation((int) $reservation->reservation_id);
        $plan = LoyaltyEarnReconciliation::plan($desired, $current, (int) $pointLedger->total_points);
        $adjustmentPoints = (int) ($plan['adjustment_points'] ?? 0);
        $clawbackPoints = (int) ($plan['clawback_points'] ?? 0);
        $shortfallPoints = (int) ($plan['shortfall_points'] ?? 0);

        if ($adjustmentPoints !== 0) {
            $tx = new LoyaltyPointTransaction;
            $tx->user_id = (int) $user->user_id;
            $tx->reservation_id = (int) $reservation->reservation_id;
            $tx->txn_type = 'Adjust';
            $tx->points = $adjustmentPoints;
            $tx->amount_basis = $this->earnBasisForReservation($reservation, $summary);
            $tx->currency = (string) ($reservation->bill_currency ?: 'VND');
            $tx->reason = self::REASON_EARN_SYNC_REFUND;
            $tx->created_at = Carbon::now('UTC');
            $tx->created_by = $staffUserId;
            $tx->save();

            $pointLedger->total_points = (int) $pointLedger->total_points + $adjustmentPoints;
            $pointLedger->updated_by = $staffUserId;
            $pointLedger->save();
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

        $this->syncUserTierLocked($user, $pointLedger, $staffUserId, 'refund_sync');
    }

    public function releaseReservationRedemptionForStatusLocked(Reservation $reservation, ?int $staffUserId = null, ?string $reason = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('user_id', (int) $reservation->user_id)
            ->where('is_deleted', 0)
            ->lockForUpdate()
            ->first();
        if (! $user) {
            return;
        }

        $pointLedger = $this->lockUserPointLedger($user, $staffUserId);
        $released = $this->releaseReservationRedemptionLocked($reservation, $user, $pointLedger, $staffUserId, $reason, false);

        if ($released['released_points'] > 0) {
            $this->syncUserTierLocked($user, $pointLedger, $staffUserId, 'reservation_status_release');
        }
    }

    private function isEnabled(): bool
    {
        return $this->runtimeSettings->bool('loyalty.enabled', (bool) config('booking.loyalty_enabled', true));
    }

    private function assertReservationEditableForRedeem(Reservation $reservation, ?int $expectedRowVersion): void
    {
        $status = (string) ($reservation->status?->value ?? $reservation->status);
        if (! in_array($status, [ReservationStatus::Confirmed->value, ReservationStatus::Reserved->value], true)) {
            throw ValidationException::withMessages([
                'reservation' => ['Loyalty redemption only supports Confirmed or Reserved reservations.'],
            ]);
        }

        if ($reservation->billed_at !== null || $reservation->final_bill_amount !== null) {
            throw ValidationException::withMessages([
                'reservation' => ['Reservation bill has already been closed for payment. Reopen the bill before changing loyalty discounts.'],
            ]);
        }

        if ($expectedRowVersion !== null && (int) ($reservation->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }

    private function lockUserPointLedger(User $user, ?int $staffUserId = null): UserPoint
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

    private function persistReservationWithoutRowVersionBump(Reservation $reservation): void
    {
        $dirty = $reservation->getDirty();
        unset($dirty['row_version']);

        if ($dirty === []) {
            $reservation->syncOriginal();

            return;
        }

        if (! array_key_exists('updated_at', $dirty)) {
            $dirty['updated_at'] = Carbon::now('UTC');
            $reservation->updated_at = $dirty['updated_at'];
        }

        DB::table($reservation->getTable())
            ->where('reservation_id', (int) $reservation->reservation_id)
            ->update($dirty);

        $reservation->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    private function buildUserSummary(User $user): array
    {
        $totalPoints = (int) ($user->points?->total_points ?? 0);
        $currentTier = $user->relationLoaded('currentTier') ? $user->currentTier : null;
        $nextTier = $this->resolveNextTier($totalPoints);

        return [
            'user_id' => (int) $user->user_id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'total_points' => $totalPoints,
            'current_tier' => $currentTier ? [
                'tier_id' => (int) $currentTier->tier_id,
                'tier_code' => (string) $currentTier->tier_code,
                'tier_name' => (string) $currentTier->tier_name,
                'min_points' => (int) $currentTier->min_points,
            ] : null,
            'next_tier' => $nextTier ? [
                'tier_id' => (int) $nextTier->tier_id,
                'tier_code' => (string) $nextTier->tier_code,
                'tier_name' => (string) $nextTier->tier_name,
                'min_points' => (int) $nextTier->min_points,
                'points_to_unlock' => max(0, (int) $nextTier->min_points - $totalPoints),
            ] : null,
        ];
    }

    /**
     * @param  Collection<int,Payment>|null  $payments
     * @return array<string,mixed>
     */
    private function buildReservationSnapshot(
        Reservation $reservation,
        ?User $user = null,
        ?UserPoint $pointLedger = null,
        ?Collection $payments = null,
        ?array $billSnapshot = null,
        ?array $loyaltyTransactionSummary = null,
    ): array {
        $user ??= $reservation->relationLoaded('user') ? $reservation->user : User::query()->with(['points', 'currentTier'])->find($reservation->user_id);
        $pointLedger ??= $user?->relationLoaded('points') ? $user->points : UserPoint::query()->where('user_id', (int) $reservation->user_id)->first();
        $payments ??= $reservation->relationLoaded('payments')
            ? collect($reservation->payments)
            : Payment::query()->where('reservation_id', (int) $reservation->reservation_id)->get();

        $billSnapshot ??= $this->computeReservationSubtotal((int) $reservation->reservation_id, true);
        $subtotalMinor = Money::minorUnits($billSnapshot['subtotal'] ?? 0, true);
        $currency = (string) ($billSnapshot['currency'] ?? 'VND');
        $loyaltyTransactionSummary ??= $this->summarizeReservationLoyaltyTransactions((int) $reservation->reservation_id);
        $loyaltyDiscountMinor = Money::minorUnits($loyaltyTransactionSummary['redeemed_amount'] ?? 0, true);
        $manualDiscountMinor = max(0, Money::minorUnits($reservation->discount_amount ?? 0, true) - $loyaltyDiscountMinor);
        $totalDiscountMinor = $manualDiscountMinor + $loyaltyDiscountMinor;
        $billTotalMinor = max(0, $subtotalMinor - $totalDiscountMinor);
        $availablePoints = (int) ($pointLedger?->total_points ?? 0);
        $remainingRedeemableMinor = max(0, $subtotalMinor - $manualDiscountMinor - $loyaltyDiscountMinor);
        $maxByBill = intdiv($remainingRedeemableMinor, max(1, Money::minorUnits($this->redeemAmountPerPoint(), true)));
        $minRedeemPoints = $this->minRedeemPoints();
        $maxRedeemablePoints = min($availablePoints, max(0, $maxByBill));
        if ($maxRedeemablePoints > 0 && $maxRedeemablePoints < $minRedeemPoints) {
            $maxRedeemablePoints = 0;
        }

        $paymentSummary = PaymentSummary::fromPayments($payments);
        $currentRedeemedPoints = (int) ($loyaltyTransactionSummary['redeemed_points'] ?? 0);
        $currentEarnedPoints = (int) ($loyaltyTransactionSummary['earned_points_current'] ?? 0);
        $finalNetMinor = Money::minorUnits($paymentSummary['final_net_amount'] ?? 0, true);
        $earnPreviewBasisMinor = min($billTotalMinor, $finalNetMinor > 0 ? $finalNetMinor : $billTotalMinor);

        return [
            'reservation_id' => (int) $reservation->reservation_id,
            'reservation_code' => (string) $reservation->reservation_code,
            'user_id' => (int) $reservation->user_id,
            'status' => (string) ($reservation->status?->value ?? $reservation->status),
            'row_version' => (int) ($reservation->row_version ?? 1),
            'bill' => [
                'subtotal_amount' => Money::formatMinor($subtotalMinor),
                'manual_discount_amount' => Money::formatMinor($manualDiscountMinor),
                'loyalty_discount_amount' => Money::formatMinor($loyaltyDiscountMinor),
                'discount_amount' => Money::formatMinor($totalDiscountMinor),
                'payable_amount' => Money::formatMinor($billTotalMinor),
                'currency' => $currency ?: 'VND',
            ],
            'loyalty' => [
                'enabled' => $this->isEnabled(),
                'available_points' => $availablePoints,
                'redeemed_points' => $currentRedeemedPoints,
                'discount_amount' => Money::minorToFloat($loyaltyDiscountMinor),
                'redeem_amount_per_point' => number_format($this->redeemAmountPerPoint(), 0, '.', ''),
                'earn_amount_per_point' => number_format($this->earnAmountPerPoint(), 0, '.', ''),
                'min_redeem_points' => $minRedeemPoints,
                'max_redeemable_points' => max(0, $maxRedeemablePoints),
                'earn_preview_points' => intdiv($earnPreviewBasisMinor, max(1, Money::minorUnits($this->earnAmountPerPoint(), true))),
                'earned_points_current' => $currentEarnedPoints,
                'can_redeem' => $maxRedeemablePoints > 0 && $finalNetMinor <= 0,
                'can_release' => $currentRedeemedPoints > 0 && $finalNetMinor <= 0,
            ],
            'user' => $user ? $this->buildUserSummary($user->loadMissing(['points', 'currentTier'])) : null,
        ];
    }

    /**
     * @return array{subtotal:float,currency:string}
     */
    private function computeReservationSubtotal(int $reservationId, bool $lock = false): array
    {
        $snapshot = $this->reservationFinancialSyncService->computeReservationBillSnapshot(
            reservationId: $reservationId,
            discountAmount: 0.0,
            lockOrders: $lock,
        );

        return [
            'subtotal' => Money::toFloat($snapshot['subtotal'] ?? 0, true),
            'currency' => (string) ($snapshot['currency'] ?? 'VND'),
        ];
    }

    /**
     * @return array{released_points:int,released_amount:float}
     */
    private function releaseReservationRedemptionLocked(Reservation $reservation, User $user, UserPoint $pointLedger, ?int $staffUserId, ?string $reason = null, bool $persistReservation = true): array
    {
        $summary = $this->summarizeReservationLoyaltyTransactions((int) $reservation->reservation_id, true);
        $currentRedeemedPoints = (int) ($summary['redeemed_points'] ?? 0);
        $currentRedeemedAmount = Money::toFloat($summary['redeemed_amount'] ?? 0, true);
        if ($currentRedeemedPoints <= 0 || Money::isZeroOrNegative($currentRedeemedAmount)) {
            return ['released_points' => 0, 'released_amount' => 0.0];
        }

        $tx = new LoyaltyPointTransaction;
        $tx->user_id = (int) $user->user_id;
        $tx->reservation_id = (int) $reservation->reservation_id;
        $tx->txn_type = 'Adjust';
        $tx->points = $currentRedeemedPoints;
        $tx->amount_basis = $currentRedeemedAmount;
        $tx->currency = (string) ($reservation->bill_currency ?: 'VND');
        $tx->reason = $this->composeReason(self::REASON_REDEEM_RELEASE, $reason);
        $tx->created_at = Carbon::now('UTC');
        $tx->created_by = $staffUserId;
        $tx->save();

        $pointLedger->total_points = (int) $pointLedger->total_points + $currentRedeemedPoints;
        $pointLedger->updated_by = $staffUserId;
        $pointLedger->save();

        $this->reservationFinancialSyncService->syncReservationDiscountSnapshot(
            reservation: $reservation,
            totalDiscount: Money::minorToFloat(max(
                0,
                Money::minorUnits($reservation->discount_amount ?? 0, true) - Money::minorUnits($currentRedeemedAmount, true)
            )),
            lockOrders: true,
        );
        $reservation->updated_by = $staffUserId;
        if ($persistReservation) {
            $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $staffUserId);
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

    private function syncUserTierLocked(User $user, UserPoint $pointLedger, ?int $staffUserId, string $reason): void
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

        $history = new UserTierHistory;
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

    private function resolveNextTier(int $totalPoints): ?LoyaltyTier
    {
        return LoyaltyTier::query()
            ->where('is_active', 1)
            ->where('min_points', '>', $totalPoints)
            ->orderBy('min_points')
            ->orderBy('tier_id')
            ->first();
    }

    private function redeemAmountPerPoint(): float
    {
        return max(0.01, $this->runtimeSettings->float('loyalty.redeem_amount_per_point', (float) config('booking.loyalty_redeem_amount_per_point', 1000)));
    }

    private function earnAmountPerPoint(): float
    {
        return max(0.01, $this->runtimeSettings->float('loyalty.earn_amount_per_point', (float) config('booking.loyalty_earn_amount_per_point', 10000)));
    }

    private function minRedeemPoints(): int
    {
        return max(1, $this->runtimeSettings->int('loyalty.min_redeem_points', (int) config('booking.loyalty_min_redeem_points', 1)));
    }

    private function composeReason(string $base, ?string $detail = null): string
    {
        $detail = trim((string) $detail);

        return $detail === '' ? $base : $base.':'.$detail;
    }

    private function desiredEarnPointsForReservation(Reservation $reservation, array $paymentSummary): int
    {
        $basis = $this->earnBasisForReservation($reservation, $paymentSummary);

        return intdiv(Money::minorUnits($basis, true), max(1, Money::minorUnits($this->earnAmountPerPoint(), true)));
    }

    /**
     * @param  array<string,float>  $paymentSummary
     */
    private function earnBasisForReservation(Reservation $reservation, array $paymentSummary): float
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

    private function currentEarnNetPointsForReservation(int $reservationId): int
    {
        return (int) LoyaltyPointTransaction::query()
            ->where('reservation_id', $reservationId)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('txn_type', 'Earn')
                        ->where('reason', self::REASON_EARN_COMPLETED);
                })->orWhere(function ($q) {
                    $q->where('txn_type', 'Adjust')
                        ->where(function ($nested) {
                            $nested->where('reason', self::REASON_EARN_SYNC_REFUND)
                                ->orWhere('reason', self::REASON_EARN_SYNC_COMPLETE);
                        });
                });
            })
            ->sum('points');
    }

    private function currentRedeemedPointsForReservation(int $reservationId, bool $lock = false): int
    {
        $query = LoyaltyPointTransaction::query()
            ->where('reservation_id', $reservationId)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('txn_type', 'Redeem')
                        ->where('reason', 'like', self::REASON_REDEEM_APPLY.'%');
                })->orWhere(function ($q) {
                    $q->where('txn_type', 'Adjust')
                        ->where('reason', 'like', self::REASON_REDEEM_RELEASE.'%');
                });
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        $sum = (int) $query->sum('points');

        return max(0, -$sum);
    }

    private function currentRedeemedAmountForReservation(int $reservationId, bool $lock = false): float
    {
        $query = LoyaltyPointTransaction::query()
            ->where('reservation_id', $reservationId)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('txn_type', 'Redeem')
                        ->where('reason', 'like', self::REASON_REDEEM_APPLY.'%');
                })->orWhere(function ($q) {
                    $q->where('txn_type', 'Adjust')
                        ->where('reason', 'like', self::REASON_REDEEM_RELEASE.'%');
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

    private function resolveReservationPointLedger(Reservation $reservation): ?UserPoint
    {
        if ($reservation->relationLoaded('user') && $reservation->user !== null && $reservation->user->relationLoaded('points')) {
            return $reservation->user->points;
        }

        return UserPoint::query()->where('user_id', (int) $reservation->user_id)->first();
    }

    /**
     * @param  Collection<int,Payment>|null  $payments
     * @param  array{subtotal:float,discount:float,total_due:float,currency:string}|null  $billSnapshot
     * @param  array{redeemed_points:int,redeemed_amount:float,earned_points_current:int}|null  $loyaltyTransactionSummary
     * @return array<string,mixed>
     */
    private function buildReservationLoyaltyPayload(
        Reservation $reservation,
        ?UserPoint $pointLedger = null,
        ?Collection $payments = null,
        ?array $billSnapshot = null,
        ?array $loyaltyTransactionSummary = null,
    ): array {
        $reservationId = (int) $reservation->reservation_id;
        $pointLedger ??= $this->resolveReservationPointLedger($reservation);
        $payments ??= $reservation->relationLoaded('payments')
            ? collect($reservation->payments)
            : Payment::query()->where('reservation_id', $reservationId)->get();
        $billSnapshot ??= $this->computeReservationSubtotal($reservationId, false);
        $loyaltyTransactionSummary ??= $this->summarizeReservationLoyaltyTransactions($reservationId);

        $subtotalMinor = Money::minorUnits($billSnapshot['subtotal'] ?? 0, true);
        $loyaltyDiscountMinor = Money::minorUnits($loyaltyTransactionSummary['redeemed_amount'] ?? 0, true);
        $manualDiscountMinor = max(0, Money::minorUnits($reservation->discount_amount ?? 0, true) - $loyaltyDiscountMinor);
        $billTotalMinor = max(0, $subtotalMinor - $manualDiscountMinor - $loyaltyDiscountMinor);
        $availablePoints = (int) ($pointLedger?->total_points ?? 0);
        $remainingRedeemableMinor = max(0, $subtotalMinor - $manualDiscountMinor - $loyaltyDiscountMinor);
        $maxByBill = intdiv($remainingRedeemableMinor, max(1, Money::minorUnits($this->redeemAmountPerPoint(), true)));
        $minRedeemPoints = $this->minRedeemPoints();
        $maxRedeemablePoints = min($availablePoints, max(0, $maxByBill));
        if ($maxRedeemablePoints > 0 && $maxRedeemablePoints < $minRedeemPoints) {
            $maxRedeemablePoints = 0;
        }

        $paymentSummary = PaymentSummary::fromPayments($payments);
        $currentRedeemedPoints = (int) ($loyaltyTransactionSummary['redeemed_points'] ?? 0);
        $currentEarnedPoints = (int) ($loyaltyTransactionSummary['earned_points_current'] ?? 0);
        $finalNetMinor = Money::minorUnits($paymentSummary['final_net_amount'] ?? 0, true);
        $earnPreviewBasisMinor = min($billTotalMinor, $finalNetMinor > 0 ? $finalNetMinor : $billTotalMinor);

        return [
            'enabled' => $this->isEnabled(),
            'available_points' => $availablePoints,
            'redeemed_points' => $currentRedeemedPoints,
            'discount_amount' => Money::minorToFloat($loyaltyDiscountMinor),
            'redeem_amount_per_point' => number_format($this->redeemAmountPerPoint(), 0, '.', ''),
            'earn_amount_per_point' => number_format($this->earnAmountPerPoint(), 0, '.', ''),
            'min_redeem_points' => $minRedeemPoints,
            'max_redeemable_points' => max(0, $maxRedeemablePoints),
            'earn_preview_points' => intdiv($earnPreviewBasisMinor, max(1, Money::minorUnits($this->earnAmountPerPoint(), true))),
            'earned_points_current' => $currentEarnedPoints,
            'can_redeem' => $maxRedeemablePoints > 0 && $finalNetMinor <= 0,
            'can_release' => $currentRedeemedPoints > 0 && $finalNetMinor <= 0,
        ];
    }

    /**
     * @return array{redeemed_points:int,redeemed_amount:float,earned_points_current:int}
     */
    private function summarizeReservationLoyaltyTransactions(int $reservationId, bool $lock = false): array
    {
        $query = LoyaltyPointTransaction::query()
            ->where('reservation_id', $reservationId)
            ->orderBy('txn_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $redeemedPointsNet = 0;
        $redeemedAmountMinor = 0;
        $earnedPointsCurrent = 0;

        foreach ($query->get(['txn_type', 'points', 'amount_basis', 'reason']) as $tx) {
            $txnType = (string) ($tx->txn_type ?? '');
            $reason = (string) ($tx->reason ?? '');
            $points = (int) ($tx->points ?? 0);
            $amountBasisMinor = Money::minorUnits($tx->amount_basis ?? 0, true);

            if ($txnType === 'Redeem' && str_starts_with($reason, self::REASON_REDEEM_APPLY)) {
                $redeemedPointsNet += $points;
                $redeemedAmountMinor += $amountBasisMinor;

                continue;
            }

            if ($txnType === 'Adjust' && str_starts_with($reason, self::REASON_REDEEM_RELEASE)) {
                $redeemedPointsNet += $points;
                $redeemedAmountMinor -= $amountBasisMinor;

                continue;
            }

            $isEarnCompleted = $txnType === 'Earn' && $reason === self::REASON_EARN_COMPLETED;
            $isEarnAdjustment = $txnType === 'Adjust' && in_array($reason, [self::REASON_EARN_SYNC_REFUND, self::REASON_EARN_SYNC_COMPLETE], true);
            if ($isEarnCompleted || $isEarnAdjustment) {
                $earnedPointsCurrent += $points;
            }
        }

        return [
            'redeemed_points' => max(0, -1 * $redeemedPointsNet),
            'redeemed_amount' => Money::minorToFloat(max(0, $redeemedAmountMinor)),
            'earned_points_current' => $earnedPointsCurrent,
        ];
    }
}
