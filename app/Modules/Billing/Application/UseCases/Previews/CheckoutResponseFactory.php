<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\UseCases\Previews;

use App\Enums\PaymentStatus;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;

class CheckoutResponseFactory
{
    public function __construct(
        private readonly SettlementAmountCalculator $amountCalculator,
    ) {}

    // --- BƯỚC 1: ĐÍNH KÈM TỔNG TIỀN VÀO ORDER ---
    // Nghiệp vụ: Sau khi tính toán xong các khoản phụ phí, giảm giá, thuế, hàm này sẽ gắn các con số cuối cùng
    // vào object Order để chuẩn bị trả về cho màn hình thu ngân hoặc khách hàng xem trước.
    public function attachTotals(
        ReservationOrder $order,
        ?float $subtotal = null,
        ?float $discount = null,
        ?float $totalDue = null,
        ?string $currency = null
    ): ReservationOrder {
        // [BEST PRACTICE]: Dependency Delegation
        // Giao phó logic tính toán tiền tệ phức tạp cho một service chuyên trách (SettlementAmountCalculator).
        // Class Factory này chỉ tập trung vào việc định dạng (format) dữ liệu đầu ra (Single Responsibility Principle).
        return $this->amountCalculator->attachTotals($order, $subtotal, $discount, $totalDue, $currency);
    }

    /**
     * @return array<string,mixed>
     */

    // --- BƯỚC 2: XÂY DỰNG KẾT QUẢ TRẢ VỀ CHO LUỒNG HOÀN TIỀN (REFUND) ---
    // Nghiệp vụ: Khi khách hàng hủy bàn đã cọc, hoặc thu ngân thao tác trả lại tiền thừa cho khách,
    // API cần trả về một bản tóm tắt chi tiết: Đã trả qua phương thức nào, tổng bao nhiêu tiền, còn nợ lại gì không.
    /**
     * @param  array<string,float>  $summary
     * @param  array<int,int>  $refundPaymentIds
     * @return array<string,mixed>
     */
    public function buildRefundResponse(
        Reservation $reservation,
        array $summary,
        array $refundPaymentIds,
        float $refundAmountThisCall,
        string $refundScope,
        bool $cancelled,
        string $currency
    ): array {
        return [
            'reservation' => $reservation,
            'refund' => [
                'refund_payment_ids' => array_values(array_map('intval', $refundPaymentIds)),
                // [BEST PRACTICE]: Consistent Currency Formatting
                // Bắt buộc mọi con số tiền tệ trả về cho Client phải đi qua lớp Money để format.
                // Ngăn chặn lỗi hiển thị kiểu "100000.000001 VND" thay vì "100,000 VND".
                'refund_amount' => Money::format($refundAmountThisCall, true),
                'currency' => $this->amountCalculator->normalizeCurrencyCode($currency, (string) ($reservation->bill_currency ?? 'VND')),
                'refund_scope' => $refundScope,
                'cancelled' => $cancelled,
                'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
                // Báo cáo chi tiết luồng tiền cọc (Deposit) vs luồng tiền thanh toán cuối (Final)
                'payment_summary' => [
                    'deposit_captured' => Money::format($summary['deposit_captured_amount'] ?? 0, true),
                    'deposit_refunded' => Money::format($summary['deposit_refunded_amount'] ?? 0, true),
                    'deposit_net' => Money::format($summary['deposit_net_amount'] ?? 0, true),
                    'final_captured' => Money::format($summary['final_captured_amount'] ?? 0, true),
                    'final_refunded' => Money::format($summary['final_refunded_amount'] ?? 0, true),
                    'final_net' => Money::format($summary['final_net_amount'] ?? 0, true),
                    'captured_total' => Money::format($summary['captured_amount'] ?? 0, true),
                    'refunded_total' => Money::format($summary['refunded_amount'] ?? 0, true),
                    'net_paid_total' => Money::format($summary['net_paid_amount'] ?? 0, true),
                ],
            ],
        ];
    }

    // --- BƯỚC 3: XÂY DỰNG KẾT QUẢ TRẢ VỀ CHO LUỒNG THANH TOÁN (CHECKOUT) ---
    // Nghiệp vụ: Đây là data đẩy về cho Frontend để hiển thị hóa đơn thanh toán cuối cùng của bàn ăn.
    public function buildCheckoutResponse(ReservationOrder $order, string $fallbackCurrency = 'VND'): array
    {
        // [BEST PRACTICE]: Minor Units Pattern
        // Tiếp tục áp dụng quy tắc đổi ra tiền lẻ (minor units) để so sánh các mốc thanh toán,
        // đảm bảo so sánh "Đã thanh toán" >= "Tổng tiền" chính xác tuyệt đối, không bị lệch do số thập phân.
        $totalDueMinor = Money::minorUnits($order->getAttribute('total_due_amount') ?? 0, true);
        $paidMinor = Money::minorUnits($order->getAttribute('paid_amount') ?? 0, true);

        $totalDue = Money::minorToFloat($totalDueMinor);
        $paid = Money::minorToFloat($paidMinor);
        $depositApplied = Money::toFloat($order->getAttribute('deposit_applied_amount') ?? 0, true);
        $finalPaid = Money::toFloat($order->getAttribute('final_paid_amount') ?? 0, true);

        // Tính toán số tiền khách còn nợ (Outstanding). Nếu Database chưa kịp cập nhật,
        // sẽ tự động tính = Tổng tiền - Đã trả (nhưng lấy max với 0 để không ra số âm nếu khách trả dư).
        $outstanding = $order->getAttribute('outstanding_amount') !== null
            ? Money::toFloat($order->getAttribute('outstanding_amount'), true)
            : Money::minorToFloat(max(0, $totalDueMinor - $paidMinor));

        return [
            'order_id' => (int) $order->order_id,
            'reservation_id' => (int) $order->reservation_id,
            'row_version' => (int) ($order->row_version ?? 1),
            'total_amount' => $totalDue,
            'currency' => (string) ($order->getAttribute('currency') ?? $fallbackCurrency),
            'paid_amount' => $paid,
            'deposit_applied_amount' => $depositApplied, // Tiền cọc đã được trừ vào bill
            'final_paid_amount' => $finalPaid,         // Tiền khách vừa rút ví trả thêm
            'outstanding_amount' => $outstanding,      // Tiền khách còn nợ

            // [BEST PRACTICE]: Derived State Calculation
            // Tự động suy luận trạng thái thanh toán dựa trên số tiền khách đã trả.
            // Tránh việc Backend phải lưu thêm 1 cột trạng thái thừa trong Database dễ bị "lệch pha".
            'payment_status' => $paidMinor >= $totalDueMinor
                ? PaymentStatus::Success->value
                : ($paidMinor > 0 ? PaymentStatus::Partial->value : PaymentStatus::Failed->value),

            'status' => $order->status?->value ?? (string) $order->status,
            'order_status' => $order->status?->value ?? (string) $order->status,
            'reservation_status' => $this->resolveReservationStatus($order),
        ];
    }

    // --- BƯỚC 4: LẤY TRẠNG THÁI CỦA BÀN ĂN (LAZY/EAGER LOADING) ---
    private function resolveReservationStatus(ReservationOrder $order): ?string
    {
        // [BEST PRACTICE]: N+1 Query Prevention
        // Kiểm tra xem relation "reservation" đã được load sẵn (Eager Load) chưa.
        // Nếu đã load sẵn thì lấy luôn từ memory, tiết kiệm 1 query xuống Database.
        if ($order->relationLoaded('reservation') && $order->reservation !== null) {
            $status = $order->reservation->status;

            return $status?->value ?? (is_string($status) ? $status : null);
        }

        // Nếu chưa load, chỉ query ĐÚNG 1 cột 'status' thay vì select * nguyên bảng Reservation.
        $status = Reservation::query()->where('reservation_id', $order->reservation_id)->value('status');

        return is_string($status) ? $status : null;
    }
}
