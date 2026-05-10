<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\UseCases\Synchronization;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use App\Support\ValidationExceptionFactory;
use Illuminate\Support\Facades\DB;

/**
 * Dong bo cac snapshot tai chinh cua reservation:
 * subtotal, discount, total_due, deposit state, va dau vet mutation.
 */
class ReservationFinancialSyncService
{
    // --- BƯỚC 1: TÍNH TOÁN TỔNG THIỆT HẠI CỦA BÀN (COMPUTE SNAPSHOT) ---
    // Nghiệp vụ: Chụp lại một "bức ảnh" tài chính của bàn ăn ngay tại thời điểm gọi hàm.
    // Nó sẽ gom toàn bộ các món ăn khách đã gọi (không tính món đã hủy), cộng dồn lại để ra số tiền cuối.
    /**
     * @return array{subtotal:float,discount:float,total_due:float,currency:string}
     */
    public function computeReservationBillSnapshot(int $reservationId, float $discountAmount, bool $lockOrders = true): array
    {
        // Snapshot bill duoc tinh tu order item chua bi cancel va chi chap nhan mot loai currency.
        $query = DB::table('reservation_orders as ro')
            ->join('reservation_order_items as roi', 'roi.order_id', '=', 'ro.order_id')
            ->where('ro.reservation_id', $reservationId)
            ->whereIn('ro.status', [ReservationOrderStatus::Active->value, ReservationOrderStatus::Completed->value])
            ->select(['roi.line_total', 'roi.currency', 'roi.status']);

        // [BEST PRACTICE]: On-Demand Pessimistic Locking
        // Cho phép người gọi quyết định có khóa dòng hay không. Nếu đang trong giao dịch thanh toán,
        // cờ này được bật lên để cấm phục vụ thêm/bớt món vào order trong lúc đang tính toán.
        if ($lockOrders) {
            $query->lockForUpdate();
        }

        $items = $query->get();
        $subtotalMinor = 0;
        $currency = null;

        foreach ($items as $item) {
            // Cancelled items khong con dong gop vao subtotal, nhung van duoc giu trong lich su order.
            if ((string) ($item->status ?? '') === ReservationOrderItemStatus::Cancelled->value) {
                continue;
            }

            // [BEST PRACTICE]: Minor Units Pattern
            // Ép tất cả tiền tệ về số nguyên (tiền lẻ) để cộng dồn.
            $subtotalMinor += Money::minorUnits($item->line_total ?? 0, true);
            $itemCurrency = trim((string) ($item->currency ?? ''));

            // [BEST PRACTICE]: Domain Invariant (Rào chắn bất biến)
            // Quét và phát hiện ngay nếu trong cùng 1 bàn mà món A tính USD, món B tính VND.
            // Chặn đứng việc cộng gộp sai tỷ giá trước khi nó kịp lưu vào sổ sách kế toán.
            if ($itemCurrency !== '') {
                if ($currency === null) {
                    $currency = $itemCurrency;
                } elseif ($currency !== $itemCurrency) {
                    throw ValidationExceptionFactory::make([
                        'currency' => sprintf('Mixed currency is not supported for reservation %d (%s vs %s).', $reservationId, $currency, $itemCurrency),
                    ]);
                }
            }
        }

        // Snapshot luon doi ve minor units truoc de tranh sai so khi cong/tru bill.
        $discountMinor = Money::minorUnits($discountAmount, true);
        $totalDueMinor = max(0, $subtotalMinor - $discountMinor); // Khóa trần dưới bằng 0

        return [
            'subtotal' => Money::minorToFloat($subtotalMinor),
            'discount' => Money::minorToFloat($discountMinor),
            'total_due' => Money::minorToFloat($totalDueMinor),
            'currency' => $currency ?: 'VND',
        ];
    }

    // --- BƯỚC 2: ĐỒNG BỘ CHIẾT KHẤU VÀ CHỐT SỔ (SYNC DISCOUNT) ---
    // Nghiệp vụ: Cập nhật số tiền giảm giá mới vào bàn ăn. Đặc biệt, nếu bàn này đã "chốt bill" (billed_at != null),
    // việc thay đổi chiết khấu bắt buộc phải kích hoạt tính toán lại tổng tiền cuối cùng (final_bill_amount).
    public function syncReservationDiscountSnapshot(Reservation $reservation, float $totalDiscount, bool $lockOrders = true): void
    {
        // Khi bill da duoc lock, doi discount phai keo theo final_bill_amount va bill_currency.
        $reservation->discount_amount = Money::format($totalDiscount, true);

        // Neu bill chua lock thi chi can cap nhat discount_amount; final snapshot se duoc tinh sau.
        // Tối ưu hiệu năng: Bàn đang ăn chưa cần tính chính xác final bill làm gì cho tốn tài nguyên.
        if ($reservation->billed_at === null && $reservation->final_bill_amount === null) {
            return;
        }

        $snapshot = $this->computeReservationBillSnapshot((int) $reservation->reservation_id, (float) $reservation->discount_amount, $lockOrders);
        $reservation->final_bill_amount = (float) ($snapshot['total_due'] ?? 0.0);

        $currency = trim((string) ($snapshot['currency'] ?? ''));
        if ($currency !== '') {
            $reservation->bill_currency = $currency;
        }
    }

    // --- BƯỚC 3: ĐỒNG BỘ TRẠNG THÁI TIỀN CỌC (SYNC DEPOSIT) ---
    /**
     * @param  array<string,float|int|string|null>  $paymentSummary
     */
    public function syncDepositSnapshot(Reservation $reservation, array $paymentSummary, bool $terminalForfeit = false): void
    {
        // Deposit snapshot giup reservation biet dang no, da du, hay da hoan mot phan.
        // Bảo vệ dữ liệu rác: Không bao giờ có chuyện hoàn tiền ra lớn hơn số tiền đã thu vào.
        if (PaymentSummary::hasOverRefund($paymentSummary)) {
            throw ValidationExceptionFactory::make([
                'payments' => ['Payment state is inconsistent: refunded amount exceeds captured amount.'],
            ]);
        }

        $depositRequiredMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);
        $depositCapturedMinor = Money::minorUnits($paymentSummary['deposit_captured_amount'] ?? 0, true);
        $depositRefundedMinor = Money::minorUnits($paymentSummary['deposit_refunded_amount'] ?? 0, true);
        $depositNetMinor = Money::minorUnits($paymentSummary['deposit_net_amount'] ?? 0, true);

        // deposit_paid_amount va deposit_status luon di cung nhau de UI khong phai tu suy lai.
        // Database Cache: Lưu sẵn trạng thái text ('Paid', 'Pending'...) thẳng vào bảng Reservation.
        // Giúp các câu query filter (VD: Lấy các bàn "Đã cọc") chạy nhanh hơn thay vì phải JOIN và tính toán lại.
        $reservation->deposit_paid_amount = Money::formatMinor($depositNetMinor);
        $reservation->deposit_status = $this->resolveDepositStatus(
            depositRequiredMinor: $depositRequiredMinor,
            depositCapturedMinor: $depositCapturedMinor,
            depositRefundedMinor: $depositRefundedMinor,
            depositNetMinor: $depositNetMinor,
            terminalForfeit: $terminalForfeit,
        );
    }

    // --- BƯỚC 4: KÍCH HOẠT SỰ KIỆN THỜI GIAN THỰC (TOUCH MUTATION) ---
    // Nghiệp vụ: Đánh dấu bàn này vừa có sự thay đổi về tiền bạc.
    public function touchFinancialMutation(Reservation $reservation, ?int $actorUserId = null): void
    {
        // Cham vao reservation de cac client/documents biet bill state vua thay doi.
        // [BEST PRACTICE]: Row Versioning (Optimistic Locking & Sync)
        // Chủ động tăng row_version. Hành động này không chỉ chống ghi đè dữ liệu đồng thời,
        // mà còn đóng vai trò là "tiếng chuông" kích hoạt WebSocket/Polling đẩy dữ liệu mới nhất
        // xuống mọi thiết bị (Tablet của nhân viên, Web của khách) đang xem bàn này.
        $currentVersion = (int) ($reservation->row_version ?? 0);
        $reservation->row_version = $currentVersion > 0 ? $currentVersion + 1 : 1;

        if ($actorUserId !== null) {
            $reservation->updated_by = $actorUserId;
        }

        if (method_exists($reservation, 'freshTimestamp')) {
            $reservation->updated_at = $reservation->freshTimestamp();
        }

        $reservation->save();
    }

    // --- BƯỚC 5: CỖ MÁY TRẠNG THÁI TIỀN CỌC (DEPOSIT STATE MACHINE) ---
    private function resolveDepositStatus(int $depositRequiredMinor, int $depositCapturedMinor, int $depositRefundedMinor, int $depositNetMinor, bool $terminalForfeit): string
    {
        // State machine nay quy doi snapshot payment thanh status de reservation doc/loc nhanh.
        // [BEST PRACTICE]: Deterministic State Machine
        // Logic được bọc kín trong 1 hàm thuần túy (Pure Function) không phụ thuộc external state.
        // Đầu vào luôn cho ra 1 kết quả duy nhất dựa theo thứ tự ưu tiên rất chặt chẽ:

        // 1. Nếu quán không yêu cầu cọc và khách cũng chưa đưa đồng nào -> Không yêu cầu.
        if ($depositRequiredMinor <= 0 && $depositCapturedMinor <= 0 && $depositNetMinor <= 0) {
            return 'NotRequired';
        }

        // 2. Nếu khách bom bàn (Terminal Forfeit):
        if ($terminalForfeit && $depositCapturedMinor > 0) {
            if ($depositNetMinor <= 0) {
                return 'Refunded'; // Đã trả lại tiền cọc cho khách do quán thương tình
            }

            return $depositRefundedMinor > 0 ? 'PartiallyRefunded' : 'Forfeited'; // Phạt cọc (tịch thu)
        }

        // 3. Nếu khách có được hoàn tiền (thông thường):
        if ($depositRefundedMinor > 0) {
            if ($depositNetMinor <= 0) {
                return 'Refunded';
            }

            return 'PartiallyRefunded';
        }

        // 4. Các luồng xử lý cọc bình thường:
        if ($depositRequiredMinor <= 0) {
            return $depositNetMinor > 0 ? 'Paid' : 'NotRequired';
        }

        if ($depositNetMinor >= $depositRequiredMinor) {
            return 'Paid';
        }

        return 'Pending';
    }
}
