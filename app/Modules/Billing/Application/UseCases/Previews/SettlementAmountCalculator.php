<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\UseCases\Previews;

use App\Enums\PaymentStatus;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SettlementAmountCalculator
{
    // --- BƯỚC 1: TÍNH TOÁN PHÂN BỔ THANH TOÁN (SETTLEMENT AMOUNTS) ---
    // Nghiệp vụ: Xác định xem hóa đơn này đã được khách trả bao nhiêu tiền.
    // Tách bạch rõ ràng số tiền khách đã đặt cọc trước và số tiền khách rút ví trả thêm tại quán.
    /**
     * @param  Collection<int,mixed>|null  $payments
     * @return array{deposit_net_amount:float,deposit_applied_amount:float,final_paid_amount:float,settled_amount:float,remaining_due:float}
     */
    public function buildSettlementAmounts(?Collection $payments, mixed $totalDue): array
    {
        $summary = PaymentSummary::fromPayments($payments ?? collect());

        // [BEST PRACTICE]: Integer Money Math (Tính toán tiền tệ số nguyên)
        // Luôn luôn quy đổi số tiền về đơn vị nhỏ nhất (minor units - ví dụ: đồng) trước khi cộng trừ.
        // Tuyệt đối không dùng số thập phân (float) để tránh rủi ro mất mát dữ liệu do sai số Floating Point.
        $totalDueMinor = Money::minorUnits($totalDue, true);
        $depositNetMinor = Money::minorUnits($summary['deposit_net_amount'] ?? 0, true);
        $finalPaidMinor = Money::minorUnits($summary['final_net_amount'] ?? 0, true);

        // [BEST PRACTICE]: Financial Boundary Enforcement (Giới hạn tài chính)
        // Nghiệp vụ: Chống cấn trừ lố (Over-application).
        // Nếu khách cọc 2 triệu, nhưng hôm nay ăn hết có 500k, hệ thống chỉ được phép lấy 500k từ quỹ cọc
        // để đắp vào hóa đơn này (hàm min). Số còn lại sẽ được lưu vào quỹ để xử lý hoàn tiền sau.
        $depositAppliedMinor = min($depositNetMinor, $totalDueMinor);
        $settledMinor = $depositAppliedMinor + $finalPaidMinor;

        return [
            'deposit_net_amount' => Money::minorToFloat($depositNetMinor),
            'deposit_applied_amount' => Money::minorToFloat($depositAppliedMinor),
            'final_paid_amount' => Money::minorToFloat($finalPaidMinor),
            'settled_amount' => Money::minorToFloat($settledMinor),
            // Dùng hàm max(0, ...) để khóa trần dưới: Khách hàng không bao giờ bị ghi nhận là nợ âm tiền.
            'remaining_due' => Money::minorToFloat(max(0, $totalDueMinor - $settledMinor)),
        ];
    }

    // --- BƯỚC 2: TỔNG HỢP VÀ GẮN CHỈ SỐ VÀO ĐƠN HÀNG (ATTACH TOTALS) ---
    // Nghiệp vụ: Đóng gói tất cả các con số tài chính (tổng tiền, giảm giá, nợ) thành các thuộc tính động
    // gắn thẳng vào object Order để truyền ra cho Frontend hiển thị.
    public function attachTotals(
        ReservationOrder $order,
        ?float $subtotal = null,
        ?float $discount = null,
        ?float $totalDue = null,
        ?string $currency = null
    ): ReservationOrder {
        $computedSubtotalMinor = Money::minorUnits($subtotal ?? $order->items()->sum('line_total'), true);
        $computedDiscountMinor = Money::minorUnits($discount ?? 0, true);

        $computedTotalDueMinor = $totalDue !== null
            ? Money::minorUnits($totalDue, true)
            : max(0, $computedSubtotalMinor - $computedDiscountMinor);

        $computedSubtotal = Money::minorToFloat($computedSubtotalMinor);
        $computedDiscount = Money::minorToFloat($computedDiscountMinor);
        $computedTotalDue = Money::minorToFloat($computedTotalDueMinor);
        $currencyCode = $this->normalizeCurrencyCode($currency ?? (string) ($order->reservation?->bill_currency ?? ''), 'VND');

        // [BEST PRACTICE]: N+1 Query Prevention via Memory Check (Chống truy vấn N+1)
        // Hệ thống tự động kiểm tra xem danh sách thanh toán (payments) đã được nạp sẵn vào RAM chưa.
        // Nếu load sẵn rồi thì lấy luôn dùng, nếu chưa thì mới gọi 1 câu query select chính xác các cột cần thiết.
        /** @var Collection<int,mixed> $payments */
        if ($order->relationLoaded('reservation')
            && $order->reservation !== null
            && $order->reservation->relationLoaded('payments')
        ) {
            /** @var Collection<int,mixed> $payments */
            $payments = collect($order->reservation->getRelation('payments'));
        } else {
            $payments = $order->reservation
                ? $order->reservation->payments()->get(['payment_id', 'amount', 'payment_type', 'status', 'provider_response_json', 'currency', 'refund_of_payment_id'])
                : collect();
        }

        $settlement = $this->buildSettlementAmounts($payments, $computedTotalDue);
        $paidAmountMinor = Money::minorUnits($settlement['settled_amount'] ?? 0, true);

        $remainingMinor = array_key_exists('remaining_due', $settlement)
            ? Money::minorUnits($settlement['remaining_due'], true)
            : max(0, $computedTotalDueMinor - $paidAmountMinor);

        $paidAmount = Money::minorToFloat($paidAmountMinor);
        $remaining = Money::minorToFloat($remainingMinor);

        // Gắn số liệu vào object như một thuộc tính ảo (Virtual Attribute), không can thiệp vào DB.
        $order->setAttribute('subtotal_amount', $computedSubtotal);
        $order->setAttribute('discount_amount', $computedDiscount);
        $order->setAttribute('total_due_amount', $computedTotalDue);
        $order->setAttribute('currency', $currencyCode);
        $order->setAttribute('paid_amount', $paidAmount);
        $order->setAttribute('deposit_applied_amount', Money::toFloat($settlement['deposit_applied_amount'] ?? 0, true));
        $order->setAttribute('deposit_net_amount', Money::toFloat($settlement['deposit_net_amount'] ?? 0, true));
        $order->setAttribute('final_paid_amount', Money::toFloat($settlement['final_paid_amount'] ?? 0, true));
        $order->setAttribute('outstanding_amount', $remaining);

        // Tự động nội suy trạng thái thanh toán
        $order->setAttribute(
            'payment_status',
            $paidAmountMinor >= $computedTotalDueMinor
                ? PaymentStatus::Success->value
                : ($paidAmountMinor > 0 ? PaymentStatus::Partial->value : PaymentStatus::Failed->value)
        );

        return $order;
    }

    // --- BƯỚC 3: KIỂM SOÁT VÀ CHUẨN HÓA TIỀN TỆ ---
    public function normalizeCurrencyCode(?string $currency, string $fallback = 'VND'): string
    {
        $normalized = strtoupper(trim((string) $currency));

        $normalizedFallback = strtoupper(trim($fallback));

        return $normalized !== '' ? $normalized : ($normalizedFallback !== '' ? $normalizedFallback : 'VND');
    }

    /**
     * @param  iterable<mixed>  $payments
     */
    public function assertPaymentsSingleCurrency(iterable $payments, ?string $expectedCurrency = null, string $field = 'currency'): ?string
    {
        // [BEST PRACTICE]: Strict Currency Enforcement (Kỷ luật tiền tệ)
        // Nghiệp vụ: Hệ thống từ chối tính toán nếu phát hiện khách hàng dùng trộn lẫn nhiều loại tiền (VND, USD...).
        // Việc trộn lẫn tiền tệ đòi hỏi hệ thống đối soát phải có tỷ giá hối đoái chéo theo thời gian thực,
        // nếu để lọt sẽ gây sai lệch nghiêm trọng báo cáo tài chính.
        // Hàm này quét qua toàn bộ lịch sử trả tiền, nếu thấy trên 2 loại tiền tệ sẽ quăng lỗi ngay lập tức.
        $normalizedExpected = $expectedCurrency !== null
            ? $this->normalizeCurrencyCode($expectedCurrency, 'VND')
            : null;
        $detected = [];

        foreach ($payments as $payment) {
            $currency = $this->normalizeCurrencyCode(data_get($payment, 'currency'), $normalizedExpected ?? 'VND');
            if ($currency === '') {
                continue;
            }

            $detected[$currency] = true;
        }

        $currencies = array_keys($detected);
        sort($currencies);

        if ($normalizedExpected !== null && $currencies !== [] && $currencies !== [$normalizedExpected]) {
            throw ValidationException::withMessages([
                $field => ['Payment currency must match reservation bill currency.'],
            ]);
        }

        if (count($currencies) > 1) {
            throw ValidationException::withMessages([
                $field => ['All payments for one reservation must use the same currency.'],
            ]);
        }

        return $currencies[0] ?? $normalizedExpected;
    }
}
