<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\ReservationDepositPaymentSession;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Service này chịu trách nhiệm xây dựng "Bức tranh toàn cảnh" (Snapshot) về tình trạng Tiền Cọc của một Đơn đặt bàn.
 * Kiến trúc Read Model (CQRS): Tách biệt logic tính toán hiển thị ra khỏi logic lưu trữ,
 * giúp Frontend nhận được một mảng JSON (DTO) đã được tính toán sẵn mọi trạng thái, không cần phải tự tính toán cộng trừ ở Client.
 */
class ReservationDepositReadService
{
    public function __construct(
        private readonly ReservationDepositSelfServiceStateService $depositSelfServiceStateService,
    ) {}

    /**
     * --- BƯỚC 1: XÂY DỰNG BỨC TRANH TOÀN CẢNH (BUILD SNAPSHOT) ---
     *
     * * @param  iterable<int,Payment>|null  $payments
     * @param  iterable<int,ReservationDepositPaymentSession>|null  $paymentSessions
     * @param  array<string,mixed>|null  $paymentSummary
     * @return array<string,mixed>
     */
    public function buildSnapshot(
        Reservation $reservation,
        ?iterable $payments = null,
        ?iterable $paymentSessions = null,
        ?array $paymentSummary = null,
        string $fallbackCurrency = 'VND',
        bool $includeSelfService = true,
    ): array {
        // --- 1.1 Chuẩn hóa Dữ liệu đầu vào (Defensive Programming) ---
        // Đề phòng trường hợp truyền vào một array rác, hệ thống tự động lọc chỉ lấy đúng Object Payment/Session.
        $paymentCollection = $this->normalizePayments($payments);
        $sessionCollection = $this->normalizeSessions($paymentSessions);

        // Tái sử dụng PaymentSummary nếu lớp gọi (caller) đã tính rồi, nếu chưa thì tự tính.
        $summary = $paymentSummary ?? PaymentSummary::fromPayments($paymentCollection);

        // --- 1.2 Tính toán Tài chính chuẩn Enterprise (Currency/Money Pattern) ---
        // Best Practice: Luôn dùng Minor Units (đơn vị nhỏ nhất, VD: cent, đồng) để tính toán cộng trừ.
        // Tuyệt đối KHÔNG dùng số thập phân (float/double) để tính tiền vì lỗi sai số nhị phân (VD: 0.1 + 0.2 = 0.300000004).
        $requiredAmountMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);

        // Tiền đã cọc = Số tiền thực nhận (net_amount) hoặc lấy tạm từ snapshot của bảng reservations
        $paidAmountMinor = Money::minorUnits($summary['deposit_net_amount'] ?? $reservation->deposit_paid_amount ?? 0, true);

        // Tiền còn thiếu = max(0, Cần - Đã trả). Dùng max(0) để tránh ra số âm nếu khách lỡ chuyển khoản dư.
        $remainingAmountMinor = max(0, $requiredAmountMinor - $paidAmountMinor);

        // Chỉ lấy những giao dịch liên quan đến "Tiền Cọc" (Bỏ qua các giao dịch thanh toán hóa đơn ăn uống bình thường)
        $depositPayments = $paymentCollection
            ->filter(fn (Payment $payment): bool => $this->isDepositRelatedPayment($payment))
            ->values();

        // Xử lý đa tiền tệ (Multi-currency): Khách cọc bằng USD nhưng hóa đơn VND
        $currencyMeta = PaymentSummary::summarizeCurrencies(
            $depositPayments,
            (string) ($reservation->bill_currency ?: $fallbackCurrency)
        );

        // Lấy trạng thái cho phép Khách hàng tự thanh toán (Khách có đang bị khóa nút Thanh toán không?)
        $state = $includeSelfService
            ? $this->depositSelfServiceStateService->buildState($reservation, $summary)
            : [];

        // Tính toán lịch sử các lần "cố gắng" thanh toán (VD: KH đã bấm qua VNPay 3 lần, 2 lần xịt, 1 lần đang chờ)
        $sessionSummary = $this->buildPaymentSessionSummary($sessionCollection);

        // --- 1.3 Đóng gói DTO (Data Transfer Object) ---
        // Trả về một mảng phẳng, rõ nghĩa, mọi cờ (flags) đã được gán sẵn true/false.
        // Frontend chỉ việc lấy data đổ vào UI (if true -> hiện màu xanh, if false -> hiện màu xám).
        return [
            'status' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? null),
            'required_amount' => $this->money($requiredAmountMinor),
            'paid_amount' => $this->money($paidAmountMinor),
            'remaining_amount' => $this->money($remainingAmountMinor),
            'outstanding_amount' => $this->money($remainingAmountMinor),
            'currency' => $currencyMeta['currency'] ?? (string) ($reservation->bill_currency ?: $fallbackCurrency),
            'currencies' => array_values((array) ($currencyMeta['currencies'] ?? [])),
            'has_mixed_currencies' => (bool) ($currencyMeta['has_mixed_currencies'] ?? false),

            // Domain Logic Flags: Các cờ trạng thái này cực kỳ quan trọng cho vận hành
            'status_flags' => [
                'deposit_required' => $requiredAmountMinor > 0, // Đơn này có bắt cọc không?
                'payment_recorded' => $paidAmountMinor > 0, // Đã nhận đồng nào chưa?
                'requires_collection' => $requiredAmountMinor > 0 && $remainingAmountMinor > 0, // Còn thiếu tiền cọc phải thu?
                'fully_paid' => $requiredAmountMinor > 0 && $remainingAmountMinor <= 0, // Đã đóng đủ cọc?
                'has_refund' => Money::minorUnits($summary['deposit_refunded_amount'] ?? 0, true) > 0, // Đã từng có lệnh hoàn tiền cọc?
                'is_refunded' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? '') === 'Refunded',
                'is_partially_refunded' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? '') === 'PartiallyRefunded',
                'is_forfeited' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? '') === 'Forfeited', // Bị phạt mất cọc do bom bàn
                'has_open_payment_session' => (bool) ($sessionSummary['has_open_session'] ?? false), // Đang có tab thanh toán nào treo không? (Đề phòng khách ấn pay 2 lần)
            ],
            'payment_summary' => [
                'deposit_captured' => Money::format($summary['deposit_captured_amount'] ?? 0, true),
                'deposit_refunded' => Money::format($summary['deposit_refunded_amount'] ?? 0, true),
                'deposit_net' => Money::format($summary['deposit_net_amount'] ?? 0, true),
                'remaining_amount' => $this->money($remainingAmountMinor),
            ],
            'self_service' => $state,
            'payment_session_summary' => $sessionSummary,
            'payments' => $depositPayments,
        ];
    }

    /**
     * --- BƯỚC 2: PHÂN TÍCH LỊCH SỬ PHIÊN THANH TOÁN (PAYMENT SESSIONS) ---
     * Domain Logic: Sự khác biệt giữa Payment (Tiền đã vào túi) và Session (Ý định thanh toán).
     * Khi khách bấm nút Thanh toán -> Sinh ra 1 Session (Created) -> Chuyển sang VNPay (Pending) -> Khách tắt tab web (Failed/Expired)
     * -> Khách làm lại lần 2 -> VNPay trừ tiền -> Webhook trả về thành công -> Đổi thành Applied -> MỚI SINH RA PAYMENT THỰC TẾ.
     *
     * * @param  Collection<int,ReservationDepositPaymentSession>  $sessions
     * @return array<string,mixed>
     */
    private function buildPaymentSessionSummary(Collection $sessions): array
    {
        if ($sessions->isEmpty()) {
            return [
                'total_sessions' => 0,
                'open_session_count' => 0,
                'applied_session_count' => 0,
                'has_open_session' => false,
                'latest_session' => null,
            ];
        }

        // Lấy ra phiên thanh toán mới nhất do khách thực hiện
        $latest = $sessions
            ->sortByDesc(fn (ReservationDepositPaymentSession $session): int => (int) ($session->deposit_payment_session_id ?? 0))
            ->first();

        // Đếm xem có phiên nào đang "treo" (đợi Webhook từ cổng thanh toán) không.
        // Nếu có, UI phải disable nút Thanh Toán để chặn Double Payment (Thanh toán trùng).
        $openSessionCount = $sessions->filter(function (ReservationDepositPaymentSession $session): bool {
            $status = (string) ($session->session_status?->value ?? $session->session_status ?? '');

            return in_array($status, ['Created', 'Pending'], true);
        })->count();

        // Đếm số phiên đã chốt thành công
        $appliedSessionCount = $sessions->filter(function (ReservationDepositPaymentSession $session): bool {
            return (string) ($session->settlement_status?->value ?? $session->settlement_status ?? '') === 'Applied';
        })->count();

        return [
            'total_sessions' => $sessions->count(),
            'open_session_count' => $openSessionCount,
            'applied_session_count' => $appliedSessionCount,
            'has_open_session' => $openSessionCount > 0,
            'latest_session' => $latest instanceof ReservationDepositPaymentSession
                ? [
                    'deposit_payment_session_id' => (int) $latest->deposit_payment_session_id,
                    'session_status' => (string) ($latest->session_status?->value ?? $latest->session_status),
                    'settlement_status' => (string) ($latest->settlement_status?->value ?? $latest->settlement_status),
                    'provider_code' => (string) ($latest->provider_code ?? ''),
                    'payment_method' => $latest->payment_method !== null ? (string) $latest->payment_method : null,
                    'amount' => $latest->amount !== null ? $this->money(Money::minorUnits($latest->amount, true)) : null,
                    'currency' => $latest->currency !== null ? (string) $latest->currency : null,
                    'provider_expires_at' => $this->iso($latest->provider_expires_at),
                    'confirmed_at' => $this->iso($latest->confirmed_at),
                    'failed_at' => $this->iso($latest->failed_at),
                    'cancelled_at' => $this->iso($latest->cancelled_at),
                    'expired_at' => $this->iso($latest->expired_at),
                    'linked_payment_id' => $latest->linked_payment_id !== null ? (int) $latest->linked_payment_id : null,
                    'row_version' => (int) ($latest->row_version ?? 1),
                ]
                : null,
        ];
    }

    /**
     * --- CÁC HÀM TIỆN ÍCH CHUẨN HÓA VÀ ĐỊNH DẠNG ---
     */

    /**
     * Lọc đảm bảo mảng/collection chỉ chứa đúng Model Payment
     *
     * @param  iterable<int,Payment>|null  $payments
     * @return Collection<int,Payment>
     */
    private function normalizePayments(?iterable $payments): Collection
    {
        if ($payments instanceof Collection) {
            return $payments->filter(fn (mixed $payment): bool => $payment instanceof Payment)->values();
        }

        return collect($payments ?? [])->filter(fn (mixed $payment): bool => $payment instanceof Payment)->values();
    }

    /**
     * Lọc đảm bảo mảng/collection chỉ chứa đúng Model Session
     *
     * @param  iterable<int,ReservationDepositPaymentSession>|null  $paymentSessions
     * @return Collection<int,ReservationDepositPaymentSession>
     */
    private function normalizeSessions(?iterable $paymentSessions): Collection
    {
        if ($paymentSessions instanceof Collection) {
            return $paymentSessions->filter(fn (mixed $session): bool => $session instanceof ReservationDepositPaymentSession)->values();
        }

        return collect($paymentSessions ?? [])->filter(fn (mixed $session): bool => $session instanceof ReservationDepositPaymentSession)->values();
    }

    /**
     * Xác định xem một cục Tiền có phải là Tiền cọc (Deposit) hay không.
     * Logic Hoàn tiền (Refund): Lệnh hoàn tiền tự thân nó không có type là Deposit,
     * nên ta phải móc ngoặc (resolveRefundTargetPaymentType) về cục tiền gốc mà nó đang hoàn trả.
     */
    private function isDepositRelatedPayment(Payment $payment): bool
    {
        $paymentType = (string) ($payment->payment_type ?? '');
        if ($paymentType === 'Deposit') {
            return true;
        }

        if ($paymentType !== 'Refund') {
            return false;
        }

        return PaymentSummary::resolveRefundTargetPaymentType($payment) === 'Deposit';
    }

    /**
     * Format số tiền (Minor Units -> Chuỗi float an toàn)
     */
    private function money(int $minorUnits): string
    {
        return Money::formatMinor($minorUnits);
    }

    /**
     * Format thời gian chuẩn ISO 8601 (Chuẩn chung của API Quốc tế)
     */
    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }
}
