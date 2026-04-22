<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Promotions\Domain\Models\UserVoucher;
use App\Modules\Promotions\Domain\Policies\ReservationVoucherLifecycleSupport;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Models\ReservationTable;
use App\Modules\Reservations\Domain\Policies\ReservationStatusTransitionPolicy;
use App\SharedKernel\Money\Money;
use App\Support\AuditEvent;
use App\Support\AvailabilityCacheVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationStatusTransitionService
{
    public function __construct(
        private readonly ReservationLockService $lockService,
        private readonly NotificationOutboxService $notificationOutboxService,
        private readonly LoyaltyPointsService $loyaltyPointsService,
        private readonly RestaurantTableStateService $tableStateService,
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
    ) {}

    public function updateReservationStatus(
        int $reservationId,
        string $newStatus,
        ?int $expectedRowVersion,
        ?int $actorUserId = null,
        array $options = []
    ): Reservation {
        $newStatus = trim($newStatus);
        if ($newStatus === '') {
            throw ValidationException::withMessages(['status' => ['status là bắt buộc.']]);
        }

        try {
            $targetEnum = ReservationStatus::from($newStatus);
        } catch (\ValueError) {
            throw ValidationException::withMessages(['status' => ['status không hợp lệ.']]);
        }

        $force = (bool) ($options['force'] ?? false);
        $cancelReason = isset($options['cancel_reason']) ? trim((string) $options['cancel_reason']) : null;

        if ($targetEnum === ReservationStatus::Completed) {
            throw ValidationException::withMessages([
                'status' => ['Completed is not allowed via generic status endpoint. Use checkout / settlement flow instead.'],
            ]);
        }

        return $this->lockService->withReservationLock($reservationId, function () use ($reservationId, $targetEnum, $expectedRowVersion, $actorUserId, $force, $cancelReason) {
            $tableIds = ReservationTable::query()
                ->where('reservation_id', $reservationId)
                ->orderBy('table_id')
                ->pluck('table_id')
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();

            $work = function () use ($reservationId, $targetEnum, $expectedRowVersion, $actorUserId, $force, $cancelReason, $tableIds) {
                DB::transaction(function () use ($reservationId, $targetEnum, $expectedRowVersion, $actorUserId, $force, $cancelReason, $tableIds) {
                    /** @var Reservation $reservation */
                    $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);

                    $currentEnum = $reservation->status instanceof ReservationStatus
                        ? $reservation->status
                        : ReservationStatus::from((string) $reservation->getRawOriginal('status'));
                    $current = $currentEnum->value;
                    $target = $targetEnum->value;

                    if ($current === $target) {
                        AuditEvent::info('reservation_status_noop', [
                            'reservation_id' => (int) $reservation->reservation_id,
                            'status' => $current,
                            'expected_row_version' => $expectedRowVersion,
                            'current_row_version' => (int) ($reservation->row_version ?? 1),
                            'force' => $force,
                            'actor_user_id' => $actorUserId,
                        ]);

                        return;
                    }

                    ReservationStatusTransitionPolicy::assertTransitionAllowed($current, $targetEnum, $force);

                    $beforeVersion = (int) ($reservation->row_version ?? 1);
                    if ($expectedRowVersion !== null && $beforeVersion !== (int) $expectedRowVersion) {
                        throw ValidationException::withMessages([
                            'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
                        ]);
                    }

                    $orders = ReservationOrder::query()
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->get();

                    $payments = Payment::query()
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->get();

                    if (! empty($tableIds)) {
                        RestaurantTable::query()->whereIn('table_id', $tableIds)->lockForUpdate()->get();
                    }

                    $now = Carbon::now('UTC');

                    if ($current === ReservationStatus::checkedInDbValue() && $target === ReservationStatus::Cancelled->value) {
                        if (! $force) {
                            throw ValidationException::withMessages([
                                'status' => ['Checked-in reservations (stored as Reserved) can only be cancelled via force=true after manual confirmation.'],
                            ]);
                        }

                        $paymentSummary = PaymentSummary::fromPayments($payments);
                        if (Money::isPositive($paymentSummary['final_net_amount'] ?? 0)) {
                            throw ValidationException::withMessages([
                                'status' => ['Reservation still has unrefunded final payments. Use refund/cancel-after-payment flow before cancelling.'],
                            ]);
                        }

                        $this->cancelActiveOrders($orders, $actorUserId, $now);
                        $this->releaseTables($tableIds);
                        $reservation->cancelled_at = $now;
                        $reservation->cancelled_by = $actorUserId;
                        $reservation->cancel_reason = $cancelReason !== '' ? $cancelReason : ($reservation->cancel_reason ?? 'Forced staff cancellation');
                    }

                    if ($target === ReservationStatus::Cancelled->value && in_array($current, ReservationStatus::activeDbValues(), true)) {
                        $this->releaseReservationVoucherForStatusLocked($reservation, $actorUserId);
                        $this->loyaltyPointsService->releaseReservationRedemptionForStatusLocked(
                            reservation: $reservation,
                            staffUserId: $actorUserId,
                            reason: 'status_cancelled'
                        );
                    }

                    if ($target === ReservationStatus::Expired->value || $target === ReservationStatus::NoShow->value) {
                        $this->releaseReservationVoucherForStatusLocked($reservation, $actorUserId);
                        $this->loyaltyPointsService->releaseReservationRedemptionForStatusLocked(
                            reservation: $reservation,
                            staffUserId: $actorUserId,
                            reason: $target === ReservationStatus::Expired->value ? 'status_expired' : 'status_no_show'
                        );

                        $activeOrderIds = $orders
                            ->filter(fn ($order) => (string) ($order->status?->value ?? $order->status) === ReservationOrderStatus::Active->value)
                            ->pluck('order_id')
                            ->map(fn ($id) => (int) $id)
                            ->values()
                            ->all();

                        if ($activeOrderIds !== []) {
                            $this->cancelActiveOrders($orders, $actorUserId, $now);

                            AuditEvent::info('reservation_terminal_status_cancelled_active_orders', [
                                'reservation_id' => (int) $reservation->reservation_id,
                                'target_status' => $target,
                                'cancelled_order_ids' => $activeOrderIds,
                                'actor_user_id' => $actorUserId,
                            ]);
                        }
                    }

                    if ($target === ReservationStatus::Cancelled->value && $current === ReservationStatus::Confirmed->value) {
                        $paymentSummary = PaymentSummary::fromPayments($payments);
                        if (Money::isPositive($paymentSummary['final_net_amount'] ?? 0)) {
                            throw ValidationException::withMessages([
                                'status' => ['Reservation still has unrefunded final payments. Use refund/cancel-after-payment flow before cancelling.'],
                            ]);
                        }

                        $this->cancelActiveOrders($orders, $actorUserId, $now);
                        $reservation->cancelled_at = $now;
                        $reservation->cancelled_by = $actorUserId;
                        $reservation->cancel_reason = $cancelReason !== '' ? $cancelReason : $reservation->cancel_reason;
                    }
                    if ($target === ReservationStatus::NoShow->value) {
                        $reservation->no_show_at = $reservation->no_show_at ?? $now;
                    }

                    $reservation->status = $targetEnum;
                    $reservation->updated_by = $actorUserId;
                    $reservation->save();
                    if ($current !== $target && $target === ReservationStatus::Cancelled->value) {
                        $this->notificationOutboxService->enqueueReservationCancelled($reservation);
                    }

                    if ($current !== $target && $target === ReservationStatus::Expired->value) {
                        $this->notificationOutboxService->enqueueReservationExpired($reservation);
                    }

                    if ($current !== $target && $target === ReservationStatus::NoShow->value) {
                        $this->notificationOutboxService->enqueueReservationNoShow($reservation);
                    }

                    AuditEvent::info('reservation_status_changed', [
                        'reservation_id' => (int) $reservation->reservation_id,
                        'from' => $current,
                        'to' => $target,
                        'force' => $force,
                        'cancel_reason' => $cancelReason,
                        'expected_row_version' => $expectedRowVersion,
                        'before_row_version' => $beforeVersion,
                        'new_row_version' => $beforeVersion + 1,
                        'actor_user_id' => $actorUserId,
                    ]);
                });

                AvailabilityCacheVersion::bump();

                return Reservation::query()
                    ->with(['user', 'tables', 'orders.items.item', 'payments'])
                    ->findOrFail($reservationId);
            };

            if (! empty($tableIds)) {
                return $this->lockService->withTableLocks($tableIds, $work);
            }

            return $work();
        });
    }

    public function markNoShows(int $graceMinutes = 15): int
    {
        $graceMinutes = max(0, $graceMinutes);
        $threshold = Carbon::now('UTC')->subMinutes($graceMinutes);

        $reservationIds = Reservation::query()
            ->where('status', ReservationStatus::Confirmed->value)
            ->whereNull('checked_in_at')
            ->where('start_time', '<=', $threshold)
            ->orderBy('reservation_id')
            ->pluck('reservation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $count = 0;
        foreach ($reservationIds as $reservationId) {
            try {
                $this->updateReservationStatus(
                    reservationId: $reservationId,
                    newStatus: ReservationStatus::NoShow->value,
                    expectedRowVersion: null,
                    actorUserId: null,
                    options: ['source' => 'scheduler.no_show']
                );
                $count++;
            } catch (\Throwable $e) {
                AuditEvent::warning('reservation_mark_no_show_failed', [
                    'reservation_id' => (int) $reservationId,
                    'grace_minutes' => $graceMinutes,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
        }

        return $count;
    }

    private function assertStatusTransitionAllowed(string $current, string $target, bool $force = false): void
    {
        if ($current === $target) {
            return;
        }

        $allowed = [
            ReservationStatus::Confirmed->value => [
                ReservationStatus::Cancelled->value,
                ReservationStatus::Expired->value,
                ReservationStatus::NoShow->value,
            ],
            ReservationStatus::checkedInDbValue() => [],
            ReservationStatus::Cancelled->value => [],
            ReservationStatus::Expired->value => [],
            ReservationStatus::Completed->value => [],
            ReservationStatus::NoShow->value => [],
        ];

        if ($current === ReservationStatus::checkedInDbValue() && $target === ReservationStatus::Cancelled->value && $force) {
            return;
        }

        if (! array_key_exists($current, $allowed)) {
            throw ValidationException::withMessages([
                'status' => ["Không cho phép chuyển trạng thái từ '{$current}'."],
            ]);
        }

        if (! in_array($target, $allowed[$current], true)) {
            throw ValidationException::withMessages([
                'status' => ["Transition không hợp lệ: {$current} -> {$target}."],
            ]);
        }
    }

    private function releaseReservationVoucherForStatusLocked(Reservation $reservation, ?int $actorUserId = null): void
    {
        $userVoucherId = (int) ($reservation->applied_user_voucher_id ?? 0);
        if ($userVoucherId <= 0) {
            return;
        }

        /** @var UserVoucher|null $userVoucher */
        $userVoucher = UserVoucher::query()
            ->with('voucher')
            ->where('user_voucher_id', $userVoucherId)
            ->lockForUpdate()
            ->first();

        $orders = ReservationOrder::query()
            ->where('reservation_id', (int) $reservation->reservation_id)
            ->whereIn('status', [ReservationOrderStatus::Active->value, ReservationOrderStatus::Completed->value])
            ->with('items')
            ->lockForUpdate()
            ->get();

        ReservationVoucherLifecycleSupport::releaseVoucherAndDiscountSnapshot(
            reservation: $reservation,
            userVoucher: $userVoucher,
            orders: $orders,
            reservationFinancialSyncService: $this->reservationFinancialSyncService,
            actorUserId: $actorUserId,
            detachReservation: true,
            persistReservation: false,
        );
    }

    private function cancelActiveOrders($orders, ?int $actorUserId, Carbon $now): void
    {
        foreach ($orders as $order) {
            if ((string) ($order->status?->value ?? $order->status) !== ReservationOrderStatus::Active->value) {
                continue;
            }

            $items = ReservationOrderItem::query()
                ->where('order_id', $order->order_id)
                ->whereNotIn('status', [ReservationOrderItemStatus::Cancelled->value, ReservationOrderItemStatus::Served->value])
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $item->status = ReservationOrderItemStatus::Cancelled;
                $item->updated_by = $actorUserId;
                $item->updated_at = $now;
                $item->save();
            }

            $order->status = ReservationOrderStatus::Cancelled;
            $order->updated_by = $actorUserId;
            $order->updated_at = $now;
            $order->save();
        }
    }

    /**
     * @param  list<int>  $tableIds
     */
    private function releaseTables(array $tableIds): void
    {
        $this->tableStateService->releaseTablesSafely($tableIds, null, null, ['source' => 'reservation_service', 'reason' => 'reservation_release']);
    }
}
