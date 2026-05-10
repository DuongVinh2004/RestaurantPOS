<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\ReservationDepositIntentStatus;
use App\Enums\ReservationStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Service này hoạt động như một "Máy trạng thái" (State Machine) và "Động cơ quy tắc" (Rules Engine).
 * Nó tính toán xem Khách hàng ĐƯỢC PHÉP làm gì tiếp theo đối với khoản tiền cọc của họ
 * (Có được xác nhận không? Có được thanh toán không? Có được hủy lệnh không?)
 */
class ReservationDepositSelfServiceStateService
{
    /**
     * --- BƯỚC 1: XÂY DỰNG TRẠNG THÁI (BUILD STATE / VIEW MODEL) ---
     * Biến các con số vô tri vô giác từ Database thành các cờ (boolean flags) mang tính hành động cho Frontend.
     *
     * @param  array<string,mixed>  $paymentSummary
     * @return array<string,mixed>
     */
    public function buildState(Reservation $reservation, array $paymentSummary = []): array
    {
        // 1.1 Tính toán tài chính an toàn (Dùng số nguyên - Minor Units)
        $requiredAmountMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);
        $depositNetMinor = Money::minorUnits($paymentSummary['deposit_net_amount'] ?? $reservation->deposit_paid_amount ?? 0, true);
        // Tiền "Chốt cuối cùng" (Ví dụ khách ăn xong ra về thanh toán nốt phần còn lại)
        $finalNetMinor = Money::minorUnits($paymentSummary['final_net_amount'] ?? 0, true);
        $outstandingAmountMinor = max(0, $requiredAmountMinor - $depositNetMinor);

        // 1.2 Trích xuất các Trạng thái Hiện tại (Current State)
        $status = (string) ($reservation->status?->value ?? $reservation->status ?? '');
        $intentStatus = $this->resolveIntentStatus($reservation);
        $acknowledgedAt = $this->normalizeDate($reservation->deposit_requirement_acknowledged_at ?? null);
        $intentSubmittedAt = $this->normalizeDate($reservation->deposit_intent_submitted_at ?? null);
        $intentRevokedAt = $this->normalizeDate($reservation->deposit_intent_revoked_at ?? null);

        // 1.3 Phân tích Cờ Hành Động (Actionable Flags - Trái tim của State Machine)
        $depositRequired = $requiredAmountMinor > 0;
        $isActiveReservation = in_array($status, ReservationStatus::activeDbValues(), true);
        // actualPaymentRecorded: Tiền cọc đã THỰC SỰ chảy vào tài khoản ngân hàng chưa?
        $actualPaymentRecorded = $depositNetMinor > 0;
        // finalPaymentRecorded: Đơn này đã chốt sổ (thanh toán bill cuối) chưa?
        $finalPaymentRecorded = $finalNetMinor > 0;
        $hasOutstandingAmount = $outstandingAmountMinor > 0;

        // Cờ Actionable: Tổng hợp tất cả điều kiện. Khách chỉ được phép thao tác cọc NẾU:
        // Đơn yêu cầu cọc + Bàn đang hoạt động + Chưa chốt bill cuối + Còn thiếu tiền cọc + Chưa hề nhận được đồng cọc nào.
        $actionable = $depositRequired && $isActiveReservation && ! $finalPaymentRecorded && $hasOutstandingAmount && ! $actualPaymentRecorded;

        // State: Được phép bấm "Tôi đồng ý luật đặt cọc"
        $canAcknowledge = $actionable && $acknowledgedAt === null;
        // State: Được phép bấm "Thanh toán cọc ngay"
        $canSubmitIntent = $actionable && $acknowledgedAt !== null && $intentStatus !== ReservationDepositIntentStatus::Submitted;
        // State: Được phép bấm "Hủy thanh toán / Đổi thẻ khác"
        $canRevokeIntent = $actionable && $intentStatus === ReservationDepositIntentStatus::Submitted;

        return [
            'supported' => true,
            'deposit_required' => $depositRequired,
            'outstanding_amount' => Money::formatMinor($outstandingAmountMinor),
            'requirement_acknowledged' => $acknowledgedAt !== null,
            'acknowledged_at' => $this->iso($acknowledgedAt),
            'intent_status' => $intentStatus->value,
            'intent_submitted_at' => $this->iso($intentSubmittedAt),
            'intent_revoked_at' => $this->iso($intentRevokedAt),
            'actionable' => $actionable,
            'can_acknowledge' => $canAcknowledge,
            'can_submit_intent' => $canSubmitIntent,
            'can_revoke_intent' => $canRevokeIntent,
            'actual_payment_recorded' => $actualPaymentRecorded,
            'final_payment_recorded' => $finalPaymentRecorded,
            'requires_staff_payment_collection' => $depositRequired && $hasOutstandingAmount,
            // 1.4 Gợi ý bước tiếp theo cho Frontend điều hướng UI
            'next_step' => $this->resolveNextStep(
                depositRequired: $depositRequired,
                isActiveReservation: $isActiveReservation,
                finalPaymentRecorded: $finalPaymentRecorded,
                actualPaymentRecorded: $actualPaymentRecorded,
                hasOutstandingAmount: $hasOutstandingAmount,
                acknowledgedAt: $acknowledgedAt,
                intentStatus: $intentStatus,
            ),
        ];
    }

    /**
     * --- BƯỚC 2: CÁC HÀM GÁC CỔNG (POLICY ENFORCEMENT) ---
     * Gọi State Machine để lấy kết quả, nếu kết quả trả về `false` thì quăng Exception để chặn đứng API.
     *
     * @param  array<string,mixed>  $paymentSummary
     */
    public function assertCanAcknowledge(Reservation $reservation, array $paymentSummary = []): void
    {
        $state = $this->buildState($reservation, $paymentSummary);

        // Tính lũy đẳng (Idempotency): Nếu khách đã xác nhận rồi mà bấm gọi lại API, ta cứ im lặng cho qua (return)
        if ((bool) ($state['requirement_acknowledged'] ?? false)) {
            return;
        }

        if (! (bool) ($state['can_acknowledge'] ?? false)) {
            throw ValidationException::withMessages([
                'reservation' => [$this->acknowledgeFailureMessage($reservation, $state)],
            ]);
        }
    }

    /**
     * @param  array<string,mixed>  $paymentSummary
     */
    public function assertCanSubmitIntent(Reservation $reservation, array $paymentSummary = []): void
    {
        $state = $this->buildState($reservation, $paymentSummary);

        // Tính lũy đẳng (Idempotency): Nếu đã ở trạng thái Submitted rồi thì gọi lại vẫn thành công
        if (($state['intent_status'] ?? ReservationDepositIntentStatus::None->value) === ReservationDepositIntentStatus::Submitted->value) {
            return;
        }

        if (! (bool) ($state['requirement_acknowledged'] ?? false)) {
            throw ValidationException::withMessages([
                'reservation' => ['Please acknowledge the deposit requirement before submitting payment intent.'],
            ]);
        }

        if (! (bool) ($state['can_submit_intent'] ?? false)) {
            throw ValidationException::withMessages([
                'reservation' => [$this->submitIntentFailureMessage($reservation, $state)],
            ]);
        }
    }

    /**
     * @param  array<string,mixed>  $paymentSummary
     */
    public function assertCanRevokeIntent(Reservation $reservation, array $paymentSummary = []): void
    {
        $state = $this->buildState($reservation, $paymentSummary);
        $intentStatus = (string) ($state['intent_status'] ?? ReservationDepositIntentStatus::None->value);

        if ($intentStatus === ReservationDepositIntentStatus::Revoked->value) {
            return;
        }

        if ($intentStatus !== ReservationDepositIntentStatus::Submitted->value) {
            throw ValidationException::withMessages([
                'reservation' => ['There is no submitted deposit intent to revoke.'],
            ]);
        }

        if (! (bool) ($state['can_revoke_intent'] ?? false)) {
            throw ValidationException::withMessages([
                'reservation' => [$this->revokeIntentFailureMessage($reservation, $state)],
            ]);
        }
    }

    public function resolveIntentStatus(Reservation $reservation): ReservationDepositIntentStatus
    {
        $raw = $reservation->deposit_intent_status;
        if ($raw instanceof ReservationDepositIntentStatus) {
            return $raw;
        }

        return ReservationDepositIntentStatus::tryFrom((string) $raw) ?? ReservationDepositIntentStatus::None;
    }

    /**
     * --- BƯỚC 3: TẠO THÔNG BÁO LỖI UX/DX TỐT ---
     * Thay vì trả về lỗi "Lỗi hệ thống" hay "Không được phép", các hàm này quét ngược lại cờ State
     * để nói cho Khách hàng/Frontend biết CHÍNH XÁC tại sao họ bị chặn.
     */
    private function acknowledgeFailureMessage(Reservation $reservation, array $state): string
    {
        if (! (bool) ($state['deposit_required'] ?? false)) {
            return 'This reservation does not require a deposit acknowledgement.';
        }

        if (! $this->isActiveReservation($reservation)) {
            return 'Only active reservations can acknowledge deposit requirements.';
        }

        if ((bool) ($state['final_payment_recorded'] ?? false)) {
            return 'Cannot acknowledge deposit requirement after final payment has been recorded.';
        }

        if ((bool) ($state['actual_payment_recorded'] ?? false)) {
            return 'Deposit payment has already been recorded for this reservation.';
        }

        return 'Deposit acknowledgement is not available for this reservation.';
    }

    private function submitIntentFailureMessage(Reservation $reservation, array $state): string
    {
        if (! (bool) ($state['deposit_required'] ?? false)) {
            return 'This reservation does not require a deposit.';
        }

        if (! $this->isActiveReservation($reservation)) {
            return 'Only active reservations can submit deposit intent.';
        }

        if ((bool) ($state['final_payment_recorded'] ?? false)) {
            return 'Cannot submit deposit intent after final payment has been recorded.';
        }

        if ((bool) ($state['actual_payment_recorded'] ?? false)) {
            return 'Deposit payment has already been recorded for this reservation.';
        }

        return 'Deposit intent is not available for this reservation.';
    }

    private function revokeIntentFailureMessage(Reservation $reservation, array $state): string
    {
        if (! $this->isActiveReservation($reservation)) {
            return 'Only active reservations can revoke deposit intent.';
        }

        if ((bool) ($state['actual_payment_recorded'] ?? false)) {
            return 'Cannot revoke deposit intent after deposit payment has been recorded.';
        }

        if ((bool) ($state['final_payment_recorded'] ?? false)) {
            return 'Cannot revoke deposit intent after final payment has been recorded.';
        }

        return 'Deposit intent is not revocable for this reservation.';
    }

    private function isActiveReservation(Reservation $reservation): bool
    {
        $status = (string) ($reservation->status?->value ?? $reservation->status ?? '');

        return in_array($status, ReservationStatus::activeDbValues(), true);
    }

    /**
     * --- BƯỚC 4: BỘ ĐIỀU HƯỚNG BƯỚC TIẾP THEO (NEXT STEP RESOLVER) ---
     * Giúp Frontend biết trang web nên hiển thị màn hình nào tiếp theo.
     */
    private function resolveNextStep(
        bool $depositRequired,
        bool $isActiveReservation,
        bool $finalPaymentRecorded,
        bool $actualPaymentRecorded,
        bool $hasOutstandingAmount,
        ?Carbon $acknowledgedAt,
        ReservationDepositIntentStatus $intentStatus,
    ): string {
        // Đơn k bắt cọc -> K làm gì cả
        if (! $depositRequired) {
            return 'not_required';
        }

        // Đơn đã hủy/hết hạn -> Khóa giao diện
        if (! $isActiveReservation) {
            return 'reservation_not_actionable';
        }

        // Đã thanh toán bill cuối -> Xong
        if ($finalPaymentRecorded) {
            return 'final_payment_recorded';
        }

        // Cọc đã đủ -> Không cần thu thêm
        if (! $hasOutstandingAmount) {
            return 'deposit_fully_paid';
        }

        // Đã thu được một phần cọc thực tế -> Chờ nhân viên chốt nốt chứ k cho khách tự pay thêm để tránh loạn
        if ($actualPaymentRecorded) {
            return 'awaiting_remaining_staff_collection';
        }

        // Khách chưa đồng ý luật -> Bắt hiển thị Popup đồng ý
        if ($acknowledgedAt === null) {
            return 'awaiting_customer_acknowledgement';
        }

        // Định tuyến dựa vào trạng thái Ý định thanh toán (Intent)
        return match ($intentStatus) {
            ReservationDepositIntentStatus::Submitted => 'awaiting_staff_payment_collection', // Đang chờ VNPay/Nhân viên quét
            ReservationDepositIntentStatus::Revoked => 'customer_intent_revoked', // Khách đã hủy lệnh
            ReservationDepositIntentStatus::None => 'awaiting_customer_intent', // Mời khách bấm nút Thanh toán
        };
    }

    /**
     * --- TIỆN ÍCH DỮ LIỆU ---
     */
    private function normalizeDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        return Carbon::parse((string) $value)->utc();
    }

    private function iso(?Carbon $value): ?string
    {
        return $value?->copy()->utc()->toIso8601String();
    }
}
