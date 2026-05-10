<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\UseCases\Previews;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Chot bill tam thoi cho reservation dang phuc vu:
 * dong bang snapshot tong tien de tu luc nay order item khong duoc sua tu do nua.
 */
class BillLockService
{
    public function __construct(
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
    ) {}

    public function lockBill(
        int $orderId,
        ?float $discountAmount,
        string $notes,
        ?int $staffUserId,
        ?int $expectedRowVersion,
        callable $assertExpectedOrderRowVersion,
        callable $currentLoyaltyDiscountAmount,
        callable $currentVoucherDiscountAmount,
        callable $attachTotals,
        bool $bumpReservationVersion = true,
    ): ReservationOrder {
        // --- BƯỚC 1: KHÓA DỮ LIỆU ĐỒNG THỜI (PESSIMISTIC LOCKING) ---
        // Nghiệp vụ: Chặn đứng mọi hành động thêm/sửa/xóa món ăn của nhân viên phục vụ
        // trong lúc thu ngân đang tiến hành chốt bill.
        // [BEST PRACTICE]: Pessimistic Locking
        // Sử dụng lockForUpdate() cho cả Order và Reservation để ngăn ngừa tình trạng Race Condition.
        // Nếu không có lock này, phục vụ có thể lén thêm món vào đúng tíc tắc thu ngân in hóa đơn,
        // dẫn đến việc nhà hàng bị thất thoát doanh thu (khách ăn nhưng bill không ghi nhận).
        /** @var ReservationOrder $order */
        $order = ReservationOrder::query()->where('order_id', $orderId)->lockForUpdate()->firstOrFail();

        // [BEST PRACTICE]: Optimistic Locking Validation
        // Đảm bảo client (Frontend) đang thao tác trên phiên bản dữ liệu mới nhất.
        $assertExpectedOrderRowVersion($order, $expectedRowVersion);

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->where('reservation_id', $order->reservation_id)->lockForUpdate()->firstOrFail();

        // --- BƯỚC 2: KIỂM TRA ĐIỀU KIỆN NGHIỆP VỤ (DOMAIN INVARIANTS) ---
        // Nghiệp vụ: Đảm bảo chỉ những bàn đang ăn (Reserved) và đơn hàng đang mở (Active) mới được chốt bill.
        if (($order->status?->value ?? (string) $order->status) !== ReservationOrderStatus::Active->value) {
            throw ValidationException::withMessages(['order_id' => 'Only active orders can be closed.']);
        }
        if (($reservation->status?->value ?? (string) $reservation->status) !== ReservationStatus::Reserved->value) {
            throw ValidationException::withMessages(['reservation' => 'Reservation must be in service (Reserved) to close bill.']);
        }
        if (($order->order_type?->value ?? (string) $order->order_type) !== ReservationOrderType::OnSpot->value) {
            throw ValidationException::withMessages(['order_id' => 'Only on-spot orders can be used as checkout anchor.']);
        }

        // --- BƯỚC 3: XỬ LÝ CHIẾT KHẤU AN TOÀN (SAFE DISCOUNT CALCULATION) ---
        // Nghiệp vụ: Thu thập và tổng hợp các loại khuyến mãi đang áp dụng cho bàn này (Thành viên, Voucher, Giảm tay).
        $reservationId = (int) $reservation->reservation_id;

        // [BEST PRACTICE]: Minor Units Pattern
        // Đưa toàn bộ số tiền chiết khấu về dạng số nguyên (minor units) để tính toán, loại bỏ rủi ro sai số dấu phẩy động.
        // Bạn truyền hàm dạng callable để lấy discount, giúp decouple (giảm kết dính) BillLockService khỏi các module Loyalty hay Voucher.
        $loyaltyDiscountMinor = Money::minorUnits($currentLoyaltyDiscountAmount($reservationId), true);
        $voucherDiscountMinor = Money::minorUnits($currentVoucherDiscountAmount($reservationId, true), true);

        // Tránh tình trạng giảm giá lố (Double-dipping):
        // Lấy giảm giá cũ trừ đi phần loyalty, lấy max với 0 để không bị số âm.
        $currentNonLoyaltyDiscountMinor = max(0, Money::minorUnits($reservation->discount_amount ?? 0, true) - $loyaltyDiscountMinor);

        $requestedNonLoyaltyDiscountMinor = $discountAmount !== null
            ? Money::minorUnits($discountAmount, true)
            : $currentNonLoyaltyDiscountMinor;

        // Đảm bảo chiết khấu áp dụng không nhỏ hơn giá trị của Voucher đã nhập.
        $effectiveNonLoyaltyDiscountMinor = max($requestedNonLoyaltyDiscountMinor, $voucherDiscountMinor);
        $effectiveDiscount = Money::minorToFloat($effectiveNonLoyaltyDiscountMinor + $loyaltyDiscountMinor);

        // --- BƯỚC 4: TẠO SNAPSHOT TÀI CHÍNH VÀ LƯU TRẠNG THÁI ---
        // Snapshot nay se tro thanh bill chinh thuc de payment va settlement bam theo.
        // Pha 2: tinh snapshot bill tren order items da lock; day la bill chinh thuc payment se bam vao.
        $snapshot = $this->reservationFinancialSyncService->computeReservationBillSnapshot($reservationId, $effectiveDiscount, true);
        $subtotal = (float) ($snapshot['subtotal'] ?? 0.0);
        $discount = (float) ($snapshot['discount'] ?? 0.0);
        $totalDue = (float) ($snapshot['total_due'] ?? 0.0);
        $currencyCode = (string) ($snapshot['currency'] ?? 'VND');

        // Ghi nhận dữ liệu tài chính vào Reservation
        $reservation->discount_amount = $discount;
        $reservation->final_bill_amount = $totalDue;
        $reservation->bill_currency = $currencyCode;
        $reservation->billed_at = Carbon::now('UTC'); // Đánh dấu mốc thời gian chốt bill
        $reservation->updated_by = $staffUserId;

        // [BEST PRACTICE]: Real-time Mutation Tracking
        // touchFinancialMutation giup moi client dang mo bill thay duoc reservation bill state vua doi.
        // Kích hoạt thay đổi version để báo hiệu cho các thiết bị khác (Tablet, màn hình bếp) biết rằng
        // bàn này đã chốt bill, tự động vô hiệu hóa nút "Thêm món".
        if ($bumpReservationVersion) {
            $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $staffUserId);
        } else {
            // Lưu ngầm không bắn event nếu không cần thiết
            $reservation->updated_at = Carbon::now('UTC');
            $reservation->saveQuietly();
            $reservation->syncOriginal();
        }

        $order->notes = trim($notes) !== '' ? trim($notes) : $order->notes;
        $order->updated_by = $staffUserId;
        $order->save();

        // Gắn các thông số tính toán được vào object order để trả về cho Client
        /** @var ReservationOrder $result */
        $result = $attachTotals($order, $subtotal, $discount, $totalDue, $currencyCode);

        return $result;
    }
}
