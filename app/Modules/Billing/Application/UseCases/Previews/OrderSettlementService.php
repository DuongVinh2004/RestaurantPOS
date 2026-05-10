<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\UseCases\Previews;

use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Collection;

class OrderSettlementService
{
    // --- BƯỚC 1: TIẾP NHẬN VÀ PHÂN RÃ CÁC KHOẢN THANH TOÁN ---
    // Nghiệp vụ: Chốt hạ các con số tài chính cuối cùng của một đơn hàng.
    // Phân bổ rõ ràng tiền cọc và tiền trả thêm để tính ra số tiền thực tế khách còn nợ.
    /**
     * @param  Collection<int,mixed>  $payments
     * @return array{deposit_net_amount:float,deposit_applied_amount:float,final_paid_amount:float,settled_amount:float,remaining_due:float}
     */
    public function buildSettlementAmounts(Collection $payments, mixed $totalDue): array
    {
        // Gộp tất cả các giao dịch thanh toán (có thể khách quét mã QR nhiều lần, hoặc vừa tiền mặt vừa chuyển khoản)
        $summary = PaymentSummary::fromPayments($payments);

        // [BEST PRACTICE]: Minor Units Pattern (Mẫu thiết kế tiền tệ số nguyên)
        // Đưa tất cả số tiền về đơn vị nhỏ nhất (ví dụ: đồng, cent) dưới dạng số nguyên (Integer) để tính toán.
        // Tuyệt đối không dùng Float/Double để thực hiện các phép toán cộng/trừ/so sánh tiền tệ,
        // nhằm loại bỏ hoàn toàn rủi ro sai lệch do cơ chế làm tròn dấu phẩy động của máy tính.
        $totalDueMinor = Money::minorUnits($totalDue, true);
        $depositNetMinor = Money::minorUnits($summary['deposit_net_amount'] ?? 0, true);
        $finalPaidMinor = Money::minorUnits($summary['final_net_amount'] ?? 0, true);

        // --- BƯỚC 2: TÍNH TOÁN CÔNG NỢ VỚI CƠ CHẾ CHỐNG TRÀN (DOMAIN BOUNDS) ---
        // [BEST PRACTICE]: Safe Financial Boundaries (Giới hạn tài chính an toàn)
        // Nghiệp vụ: Tiền cọc được áp dụng (deposit_applied) không bao giờ được phép vượt quá Tổng hóa đơn.
        // Ví dụ: Khách đặt cọc trước 1.000.000đ, nhưng hôm nay ăn hết có 800.000đ.
        // Hàm min() sẽ đảm bảo hệ thống chỉ lấy đúng 800.000đ từ quỹ cọc ra để cấn trừ vào hóa đơn này.
        $depositAppliedMinor = min($totalDueMinor, $depositNetMinor);

        // Tương tự, tổng số tiền được ghi nhận là "Đã thanh toán cho hóa đơn" không được vượt quá số tiền cần trả.
        // Ngăn chặn kịch bản nhân viên thu ngân bấm nhầm số 0, thu của khách 10.000.000đ cho hóa đơn 1.000.000đ
        // làm sai lệch số liệu doanh thu ghi nhận.
        $settledMinor = min($totalDueMinor, $depositAppliedMinor + $finalPaidMinor);

        // --- BƯỚC 3: XUẤT KẾT QUẢ ĐÃ ĐỊNH DẠNG ---
        // Đổi ngược lại từ số nguyên (Minor) về số thực (Float) để Frontend dễ dàng format và hiển thị.
        return [
            'deposit_net_amount' => Money::minorToFloat($depositNetMinor),
            'deposit_applied_amount' => Money::minorToFloat($depositAppliedMinor),
            'final_paid_amount' => Money::minorToFloat($finalPaidMinor),
            'settled_amount' => Money::minorToFloat($settledMinor),

            // Dùng max(0, ...) để khóa trần dưới: Khách hàng không bao giờ "nợ âm" tiền của nhà hàng.
            // Nếu khách trả dư, nợ sẽ đứng ở mức 0, phần dư sẽ được xử lý ở một luồng hoàn tiền (Refund) khác.
            'remaining_due' => Money::minorToFloat(max(0, $totalDueMinor - $settledMinor)),
        ];
    }
}
