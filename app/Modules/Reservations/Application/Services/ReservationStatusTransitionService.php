<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\DepositStatus;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service độc quyền chịu trách nhiệm thay đổi Trạng thái của một Đơn Đặt Bàn.
 * Đảm bảo mọi sự kiện thay đổi trạng thái đều kích hoạt đúng các hiệu ứng phụ (Side-effects)
 * liên module như: Nhả Voucher (Promotions), Nhả Bàn (FloorOps), Hủy Món (Ordering), Gửi Email (Notifications)...
 */
class ReservationStatusTransitionService
{
    public function __construct(
        private readonly ReservationLockService $lockService,
        private readonly NotificationOutboxService $notificationOutboxService,
        private readonly LoyaltyPointsService $loyaltyPointsService,
        private readonly RestaurantTableStateService $tableStateService,
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
    ) {}

    /**
     * --- BƯỚC 1: ĐỘNG CƠ CẬP NHẬT TRẠNG THÁI (STATE MUTATOR) ---
     * Hàm này là một "Phễu" (Chokepoint) duy nhất để đổi trạng thái. Mọi user, cronjob đều phải đi qua đây.
     */
    public function updateReservationStatus(
        int $reservationId,
        string $newStatus,
        ?int $expectedRowVersion,
        ?int $actorUserId = null,
        array $options = []
    ): Reservation {

        // --- 1.1 KIỂM DUYỆT ĐẦU VÀO ---
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

        // Chốt chặn An toàn: Không cho phép gọi API Update Status chung chung để "Đóng bàn".
        // Đóng bàn (Completed) liên quan đến ghi nhận doanh thu, nên bắt buộc phải đi qua luồng Thanh toán (Checkout Flow).
        if ($targetEnum === ReservationStatus::Completed) {
            throw ValidationException::withMessages([
                'status' => ['Completed is not allowed via generic status endpoint. Use checkout / settlement flow instead.'],
            ]);
        }

        // --- 1.2 BẮT ĐẦU KHÓA PHÂN TÁN (DISTRIBUTED LOCK) ---
        return $this->lockService->withReservationLock($reservationId, function () use ($reservationId, $targetEnum, $expectedRowVersion, $actorUserId, $force, $cancelReason) {
            $tableIds = ReservationTable::query()
                ->where('reservation_id', $reservationId)
                ->orderBy('table_id')
                ->pluck('table_id')
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();

            // Bao bọc toàn bộ Side-effects vào một Database Transaction
            $work = function () use ($reservationId, $targetEnum, $expectedRowVersion, $actorUserId, $force, $cancelReason, $tableIds) {
                return DB::transaction(function () use ($reservationId, $targetEnum, $expectedRowVersion, $actorUserId, $force, $cancelReason, $tableIds) {

                    /** @var Reservation $reservation */
                    $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);

                    $currentEnum = $reservation->status instanceof ReservationStatus
                        ? $reservation->status
                        : ReservationStatus::from((string) $reservation->getRawOriginal('status'));
                    $current = $currentEnum->value;
                    $target = $targetEnum->value;

                    // Tính lũy đẳng (Idempotency): Nếu request lặp lại thao tác đã làm, chỉ log lại và bỏ qua, không văng lỗi.
                    if ($current === $target) {
                        AuditEvent::info('reservation_status_noop', [
                            'reservation_id' => (int) $reservation->reservation_id,
                            'status' => $current,
                            'expected_row_version' => $expectedRowVersion,
                            'current_row_version' => (int) ($reservation->row_version ?? 1),
                            'force' => $force,
                            'actor_user_id' => $actorUserId,
                        ]);

                        return $reservation; // Trả về entity hiện tại
                    }

                    // Gọi Chuyên gia thẩm định (Policy) để check State Machine (Ví dụ: Đã Cancelled thì không được Confirmed lại)
                    ReservationStatusTransitionPolicy::assertTransitionAllowed($current, $targetEnum, $force);

                    // --- BỔ SUNG: GUARD YÊU CẦU ĐẶT CỌC ---
                    // Không cho phép Check-in (chuyển sang Reserved) nếu chưa đóng đủ tiền cọc (nếu có yêu cầu).
                    if ($target === ReservationStatus::checkedInDbValue()) {
                        $depositStatus = $reservation->deposit_status instanceof DepositStatus
                            ? $reservation->deposit_status->value
                            : (string) $reservation->deposit_status;

                        if ($depositStatus === DepositStatus::Pending->value) {
                            if (! $force) {
                                throw ValidationException::withMessages([
                                    'status' => ['Cannot check-in reservation. A required deposit is still pending.'],
                                ]);
                            }
                        }
                    }

                    // Optimistic Lock: Chống ghi đè từ giao diện cũ (VD: Lễ tân mở máy từ sáng nhưng chiều mới bấm Hủy)
                    $beforeVersion = (int) ($reservation->row_version ?? 1);
                    if ($expectedRowVersion !== null && $beforeVersion !== (int) $expectedRowVersion) {
                        throw ValidationException::withMessages([
                            'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
                        ]);
                    }

                    // Tải toàn bộ Hóa đơn và Thanh toán kèm Lock DB để chuẩn bị dọn dẹp
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

                    // --- 1.3 XỬ LÝ CÁC SIDE-EFFECTS THEO TỪNG KỊCH BẢN ---

                    // KỊCH BẢN A: ÉP HỦY MỘT BÀN ĐANG NGỒI (Force Cancel Checked-In)
                    // Cực kỳ nhạy cảm: Khách đã vào ngồi nhưng xảy ra sự cố (đồ ăn có vấn đề, khách bỏ về...).
                    if ($current === ReservationStatus::checkedInDbValue() && $target === ReservationStatus::Cancelled->value) {
                        if (! $force) {
                            throw ValidationException::withMessages([
                                'status' => ['Checked-in reservations (stored as Reserved) can only be cancelled via force=true after manual confirmation.'],
                            ]);
                        }

                        // Nếu khách đã trả tiền rồi -> Không cho Hủy ngang xương, phải đi luồng Refund để kế toán còn làm việc.
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

                    // KỊCH BẢN B: TRẢ LẠI QUYỀN LỢI KHI ĐƠN BỊ HỦY (Tự hủy, Quá hạn, Bom bàn)
                    if ($target === ReservationStatus::Cancelled->value && in_array($current, ReservationStatus::activeDbValues(), true)) {
                        $this->releaseReservationVoucherForStatusLocked($reservation, $actorUserId); // Nhả Voucher
                        $this->loyaltyPointsService->releaseReservationRedemptionForStatusLocked( // Nhả điểm tích lũy
                            reservation: $reservation,
                            staffUserId: $actorUserId,
                            reason: 'status_cancelled'
                        );
                    }

                    // KỊCH BẢN C: ĐƠN HẾT HẠN (Expired) HOẶC KHÁCH BOM BÀN (NoShow)
                    if ($target === ReservationStatus::Expired->value || $target === ReservationStatus::NoShow->value) {
                        $this->releaseReservationVoucherForStatusLocked($reservation, $actorUserId);
                        $this->loyaltyPointsService->releaseReservationRedemptionForStatusLocked(
                            reservation: $reservation,
                            staffUserId: $actorUserId,
                            reason: $target === ReservationStatus::Expired->value ? 'status_expired' : 'status_no_show'
                        );

                        // Hủy các món bếp đang làm dở (nếu khách có Pre-order)
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

                    // KỊCH BẢN D: HỦY MỘT ĐƠN ĐANG CHỜ (Confirmed -> Cancelled)
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

                    // --- 1.4 CHỐT LƯU VÀ PHÁT SÓNG THÔNG BÁO ---
                    $reservation->status = $targetEnum;
                    $reservation->updated_by = $actorUserId;
                    $reservation->save();

                    // Outbox Pattern: Thả sự kiện vào hàng đợi để gửi SMS/Email, không làm nghẽn API hiện tại
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

                    return $reservation; // Sửa lỗi return type
                });
            };

            // Lock luôn cả những bàn vật lý liên quan nếu đơn này có giữ bàn
            if (! empty($tableIds)) {
                return $this->lockService->withTableLocks($tableIds, $work);
            }

            return $work();
        });
    }

    /**
     * --- BƯỚC 2: CRONJOB (TỰ ĐỘNG ĐÁNH DẤU BOM BÀN) ---
     * Thường được gắn vào Laravel Scheduler chạy mỗi 5 phút.
     */
    public function markNoShows(int $graceMinutes = 15): int
    {
        $graceMinutes = max(0, $graceMinutes);
        // Thời điểm phán xét: Hiện tại trừ đi "Thời gian du di".
        // VD: Quán cho trễ 15p. Khách đặt 19:00. Lúc 19:16 quét sẽ thấy đơn này <= 19:01 -> Phạt NoShow.
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
                // Tái sử dụng (Reuse) phễu updateReservationStatus để hưởng toàn bộ logic nhả voucher, báo bếp...
                $this->updateReservationStatus(
                    reservationId: $reservationId,
                    newStatus: ReservationStatus::NoShow->value,
                    expectedRowVersion: null,
                    actorUserId: null, // Hệ thống tự động nên Actor = null
                    options: ['source' => 'scheduler.no_show']
                );
                $count++;
            } catch (\Throwable $e) {
                // Fault Tolerance: Nếu 1 đơn lỗi (do đang bị nhân viên lock để xử lý),
                // bỏ qua và chạy tiếp đơn khác thay vì sập toàn bộ tiến trình cronjob.
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

    /**
     * --- BƯỚC 3: CÁC HÀM TIỆN ÍCH (CROSS-MODULE SIDE EFFECTS) ---
     */

    /**
     * Gác cổng State Machine nội bộ (Có thể hơi dư thừa nếu đã có ReservationStatusTransitionPolicy,
     * nhưng mang tính chất Fallback bảo vệ 2 lớp).
     */
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

    /**
     * Logic giao tiếp với Module Promotions: Nhả Voucher
     */
    private function releaseReservationVoucherForStatusLocked(Reservation $reservation, ?int $actorUserId = null): void
    {
        $userVoucherId = (int) ($reservation->applied_user_voucher_id ?? 0);
        if ($userVoucherId <= 0) {
            return; // Đơn này không xài voucher
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

        // Ủy quyền thao tác phức tạp (mở khóa voucher & trừ lại tiền hóa đơn) cho Chuyên gia Voucher.
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

    /**
     * Logic giao tiếp với Module Ordering: Hủy hóa đơn nhà bếp
     */
    private function cancelActiveOrders($orders, ?int $actorUserId, Carbon $now): void
    {
        foreach ($orders as $order) {
            if ((string) ($order->status?->value ?? $order->status) !== ReservationOrderStatus::Active->value) {
                continue;
            }

            // Chỉ hủy các món ăn chưa làm xong (Chưa Served)
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
     * Logic giao tiếp với Module Floor Operations: Trả lại bàn lên sơ đồ
     *
     * @param  list<int>  $tableIds
     */
    private function releaseTables(array $tableIds): void
    {
        $this->tableStateService->releaseTablesSafely($tableIds, null, null, ['source' => 'reservation_service', 'reason' => 'reservation_release']);
    }
}
