<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Policies\ReservationStatusTransitionPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Service chuyên biệt để thực thi thao tác Hủy (Cancel) Hóa đơn và Bàn
 * ĐƯỢC GỌI SAU KHI luồng đối soát tài chính (Hoàn tiền cọc, hủy bill) đã thành công.
 */
class ReservationCancellationService
{
    public function __construct(
        private readonly RestaurantTableStateService $tableStateService,
    ) {}

    /**
     * --- BƯỚC 1: TIẾP NHẬN & KIỂM TRA TRẠNG THÁI ---
     *
     * @param  Collection<int,ReservationOrder>  $orders
     * @param  array<int,int>  $tableIds
     *                                    * Best Practice: Hậu tố "Locked" trong tên hàm (cancelAfterPaymentLocked) là một quy ước
     *                                    giao tiếp ngầm định cực hay trong Clean Code. Nó báo hiệu cho các lập trình viên khác rằng:
     *                                    "Hàm này KHÔNG tự mở DB::transaction. Hãy đảm bảo bạn đã lock các dòng dữ liệu
     *                                    trước khi gọi tôi!".
     */
    public function cancelAfterPaymentLocked(
        Reservation $reservation,
        Collection $orders,
        array $tableIds,
        ?int $staffUserId,
        ?string $cancelReason,
        ?Carbon $now = null,
    ): void {
        $currentStatus = (string) ($reservation->status?->value ?? $reservation->status);
        $now ??= Carbon::now('UTC');

        // Thẩm định logic: Chỉ cho phép dọn dẹp nếu bàn đang "Confirmed" (Chờ khách tới)
        // hoặc "Checked-in / Reserved" (Khách đang ngồi ăn).
        // Không thể hủy một bàn đã Completed (Chốt sổ cuối ngày).
        if (! in_array($currentStatus, ReservationStatus::activeDbValues(), true)) {
            throw ValidationException::withMessages([
                'reservation' => 'cancel-after-payment only supports Confirmed or checked-in (Reserved) reservations.',
            ]);
        }

        // Kiểm tra Máy trạng thái (State Machine):
        // Nếu khách đang ngồi ăn (Checked-in) mà lại đòi Hủy bàn, biến thứ 3 truyền vào sẽ là `true` (Ép buộc - Force).
        // Vì bình thường hệ thống cấm chuyển từ Checked-in sang Cancelled.
        ReservationStatusTransitionPolicy::assertTransitionAllowed(
            $currentStatus,
            ReservationStatus::Cancelled,
            ReservationStatus::isCheckedInDbValue($currentStatus),
            'reservation'
        );

        // --- BƯỚC 2: RÚT LỆNH NHÀ BẾP (KITCHEN REVERSAL) ---
        // Vòng lặp duyệt qua tất cả các Hóa đơn (Order) đang mở của bàn này.
        foreach ($orders as $order) {
            if ((string) ($order->status?->value ?? $order->status) !== ReservationOrderStatus::Active->value) {
                continue;
            }

            // Lấy ra các món ăn chưa bị Hủy và CHƯA PHỤC VỤ (Not Served).
            // Nếu món đã nấu xong bưng ra bàn (Served) thì khách phải trả tiền, không dọn dẹp được nữa.
            $items = ReservationOrderItem::query()
                ->where('order_id', $order->order_id)
                ->whereNotIn('status', [ReservationOrderItemStatus::Cancelled->value, ReservationOrderItemStatus::Served->value])
                ->lockForUpdate() // Pessimistic Lock: Tránh việc Bếp vừa bấm "Nấu xong" thì Thu ngân lại bấm "Hủy"
                ->get();

            foreach ($items as $item) {
                // Việc đổi trạng thái món thành Cancelled ở đây sẽ trigger (kích hoạt)
                // các Event/Realtime gửi xuống màn hình Tablet của Bếp để Bếp dừng nấu ngay lập tức,
                // đồng thời có thể hoàn lại số lượng nguyên liệu (Inventory) vào kho.
                $item->status = ReservationOrderItemStatus::Cancelled;
                $item->updated_by = $staffUserId;
                $item->updated_at = $now;
                $item->save();
            }

            // Hủy luôn Hóa đơn (Order) chứa các món đó
            $order->status = ReservationOrderStatus::Cancelled;
            $order->updated_by = $staffUserId;
            $order->updated_at = $now;
            $order->save();
        }

        // --- BƯỚC 3: GIẢI PHÓNG BÀN VẬT LÝ ---
        // Domain Logic: Nếu khách ĐÃ CHECK-IN (đã vào ngồi), hệ thống sơ đồ bàn (Floor Plan)
        // đang hiển thị bàn này là "Màu Đỏ - Đang có khách".
        // Khi hủy bàn, ta phải trả nó về "Màu Xanh - Trống" để đón khách khác.
        if ($currentStatus === ReservationStatus::checkedInDbValue() && $tableIds !== []) {
            $this->tableStateService->releaseTablesSafely(
                $tableIds,
                $now,
                $staffUserId,
                [
                    'reservation_id' => (int) $reservation->reservation_id,
                    'source' => 'reservation_cancel_after_payment',
                    'reason' => 'cancel_after_payment',
                ]
            );
        }

        // --- BƯỚC 4: CHỐT TRẠNG THÁI ĐƠN ĐẶT BÀN ---
        // Hoàn tất quá trình dọn dẹp bằng cách đánh dấu chính Đơn Đặt Bàn (Reservation) là đã Hủy.
        $reservation->status = ReservationStatus::Cancelled;
        $reservation->cancelled_at = $now;
        $reservation->cancelled_by = $staffUserId;

        // Lưu lại lý do hủy. Nếu Thu ngân không nhập, hệ thống tự động ghi chú
        // "Cancelled after payment/refund flow" để Quản lý sau này biết rằng ca hủy này có liên quan đến tiền bạc.
        $reservation->cancel_reason = $cancelReason !== null && trim($cancelReason) !== ''
            ? trim($cancelReason)
            : ($reservation->cancel_reason ?: 'Cancelled after payment/refund flow');
    }
}
