<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\UseCases\Previews;

use App\Enums\PaymentStatus;
use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomerReservationBillService
{
    public function __construct(
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
    ) {}

    // --- BƯỚC 1: TRUY XUẤT VÀ KIỂM TRA ĐIỀU KIỆN LẬP HÓA ĐƠN ---
    /**
     * @return array<string,mixed>
     */
    public function getReservationBill(Reservation $reservation): array
    {
        // [BEST PRACTICE]: N+1 Query Prevention (Eager Loading)
        // Chủ động tải trước các quan hệ cần thiết (đơn hàng, chi tiết món, lịch sử thanh toán/hoàn tiền)
        // để tránh việc truy vấn lặp lại trong vòng lặp ở các bước sau.
        $reservation->loadMissing([
            'orders.items.item',
            'payments.refundOfPayment',
        ]);

        // [BEST PRACTICE]: Domain Invariants Enforcement (Bảo vệ tính toàn vẹn nghiệp vụ)
        // Nghiệp vụ: Khách hàng chỉ có thể xem hóa đơn tính tiền khi họ đang ngồi ăn (Reserved)
        // hoặc đã ăn xong (Completed). Các trạng thái như Pending (chưa đến) hay Cancelled (đã hủy)
        // thì không có khái niệm "Hóa đơn tính tiền".
        $status = (string) ($reservation->status?->value ?? $reservation->status);
        if (! in_array($status, [
            ReservationStatus::Reserved->value,
            ReservationStatus::Completed->value,
        ], true)) {
            throw ValidationException::withMessages([
                'reservation' => ['Bill is only available for checked-in or completed reservations.'],
            ]);
        }

        // --- BƯỚC 2: LỌC CÁC ĐƠN HÀNG HỢP LỆ ---
        // Nghiệp vụ: Trong một bữa ăn, khách có thể gọi nhiều lượt (nhiều orders).
        // Cần loại bỏ những order nháp chưa gửi bếp hoặc order đã bị hủy, chỉ giữ lại order Active/Completed.
        $orders = $reservation->orders
            ->filter(function ($order) {
                $orderStatus = (string) ($order->status?->value ?? $order->status);

                return in_array($orderStatus, [
                    ReservationOrderStatus::Active->value,
                    ReservationOrderStatus::Completed->value,
                ], true);
            })
            ->sortBy('order_id')
            ->values();

        if ($orders->isEmpty()) {
            throw ValidationException::withMessages([
                'reservation' => ['No billable reservation orders were found for this reservation.'],
            ]);
        }

        // --- BƯỚC 3: TÍNH TOÁN DỮ LIỆU TÀI CHÍNH THỰC TẾ (REAL-TIME SNAPSHOT) ---
        // Gọi sang service tính toán để lấy tổng tiền món ăn, thuế, giảm giá tính đến thời điểm hiện tại.
        // Chú ý: Tham số cuối là `false` nghĩa là chỉ "xem trước" chứ không ghi đè version của bảng Reservation.
        $snapshot = $this->reservationFinancialSyncService->computeReservationBillSnapshot(
            (int) $reservation->reservation_id,
            (float) ($reservation->discount_amount ?? 0.0),
            false,
        );

        // Tổng hợp lịch sử các khoản khách đã trả (ví dụ: tiền cọc lúc đặt bàn).
        $paymentSummary = PaymentSummary::fromPayments($reservation->payments);
        $currencyMeta = PaymentSummary::summarizeCurrencies($reservation->payments, (string) ($snapshot['currency'] ?? 'VND'));

        // --- BƯỚC 4: ĐỐI SOÁT VÀ TÍNH TOÁN CÔNG NỢ ---
        // [BEST PRACTICE]: Safe Math with Minor Units (Tính toán an toàn với tiền lẻ)
        // Nghiệp vụ: Chuyển đổi mọi thứ về số nguyên (minor units) để trừ cấn trừ công nợ.
        // Phân tách rõ ràng: Tiền phải trả (totalDue) - Tiền cọc (depositNet) - Tiền đã thanh toán thêm (finalNet).
        $totalDueMinor = Money::minorUnits($snapshot['total_due'] ?? 0, true);
        $depositNetMinor = Money::minorUnits($paymentSummary['deposit_net_amount'] ?? 0, true);
        $finalNetMinor = Money::minorUnits($paymentSummary['final_net_amount'] ?? 0, true);

        // Tiền cọc được áp dụng tối đa không vượt quá tổng hóa đơn (đề phòng cọc 1 triệu nhưng ăn có 500k).
        $depositAppliedMinor = min($totalDueMinor, $depositNetMinor);
        $settledMinor = min($totalDueMinor, $depositAppliedMinor + $finalNetMinor);
        $remainingDueMinor = max(0, $totalDueMinor - $settledMinor); // Số tiền khách còn phải trả thêm

        // Đổi ngược lại thành Float để hiển thị cho Frontend
        $totalDue = Money::minorToFloat($totalDueMinor);
        $depositNet = Money::minorToFloat($depositNetMinor);
        $finalNet = Money::minorToFloat($finalNetMinor);
        $depositApplied = Money::minorToFloat($depositAppliedMinor);
        $settled = Money::minorToFloat($settledMinor);
        $remainingDue = Money::minorToFloat($remainingDueMinor);

        // Tự động suy diễn trạng thái thanh toán dựa trên số tiền còn nợ
        $paymentStatus = $settledMinor >= $totalDueMinor
            ? PaymentStatus::Success->value
            : ($settledMinor > 0 ? PaymentStatus::Partial->value : PaymentStatus::Failed->value);

        // --- BƯỚC 5: ĐÓNG GÓI DỮ LIỆU ĐẦU RA ---
        return [
            'reservation' => $reservation,
            'orders' => $orders,
            'bill' => [
                'reservation_id' => (int) $reservation->reservation_id,
                'reservation_status' => $status,
                'scope' => 'reservation',
                'subtotal' => Money::toFloat($snapshot['subtotal'] ?? 0, true),
                'discount' => Money::toFloat($snapshot['discount'] ?? 0, true),
                'total_due' => $totalDue,
                'currency' => (string) ($snapshot['currency'] ?? 'VND'),
                'billed_at' => $reservation->billed_at,
                // is_locked = true nghĩa là thu ngân đã chốt sổ, khách không thể tự do gọi món thêm qua điện thoại.
                'is_locked' => $reservation->billed_at !== null && $reservation->final_bill_amount !== null,
                'locked_total_due' => $reservation->final_bill_amount !== null
                    ? Money::toFloat($reservation->final_bill_amount, true)
                    : null,
                'locked_currency' => $reservation->bill_currency !== null ? (string) $reservation->bill_currency : null,
            ],
            'settlement' => [
                'payment_status' => $paymentStatus,
                'deposit_applied' => $depositApplied,
                'deposit_net' => $depositNet,
                'final_paid' => $finalNet,
                'paid_total' => $settled,
                'remaining_due' => $remainingDue,
                'captured_total' => Money::toFloat($paymentSummary['captured_amount'] ?? 0, true),
                'refunded_total' => Money::toFloat($paymentSummary['refunded_amount'] ?? 0, true),
                'net_paid_total' => Money::toFloat($paymentSummary['net_paid_amount'] ?? 0, true),
                'currency' => $currencyMeta['currency'] ?? (string) ($snapshot['currency'] ?? 'VND'),
                'currencies' => $currencyMeta['currencies'] ?? [],
                'has_mixed_currencies' => (bool) ($currencyMeta['has_mixed_currencies'] ?? false),
            ],
            // [BEST PRACTICE]: HATEOAS / Workflow State
            // Báo cho Frontend biết giới hạn quyền hạn của khách hàng. Ở màn hình này khách
            // chỉ được xem (display_detail_only), không được tự ý tạo session thanh toán mới.
            'workflow' => [
                'settlement_scope' => 'reservation',
                'bill_source' => 'reservation_financial_snapshot',
                'order_role' => 'display_detail_only',
                'payment_session_support' => [
                    'create' => false,
                    'show' => false,
                    'refresh' => false,
                    'confirm' => false,
                ],
            ],
        ];
    }

    // --- TIỆN ÍCH LÀM SẠCH HIỂN THỊ ---
    /**
     * @param  Collection<int,mixed>  $orders
     * @return Collection<int,mixed>
     */
    public function customerVisibleOrders(Collection $orders): Collection
    {
        // Nghiệp vụ: Trải nghiệm khách hàng (UX).
        // Khi khách hàng quét mã QR xem hóa đơn trên điện thoại, hệ thống sẽ ẩn đi tất cả
        // các món ăn đã bị hủy (Cancelled) để hóa đơn nhìn sạch sẽ, không gây hiểu lầm hay hoang mang.
        return $orders->map(function ($order) {
            $items = collect($order->items ?? [])
                ->filter(function ($item) {
                    return (string) ($item->status?->value ?? $item->status) !== ReservationOrderItemStatus::Cancelled->value;
                })
                ->sortBy('order_item_id')
                ->values();

            return [
                'order_id' => (int) $order->order_id,
                'order_type' => $order->order_type?->value ?? (string) $order->order_type,
                'status' => $order->status?->value ?? (string) $order->status,
                'created_at' => $order->created_at,
                'items' => $items,
            ];
        })->values();
    }
}
