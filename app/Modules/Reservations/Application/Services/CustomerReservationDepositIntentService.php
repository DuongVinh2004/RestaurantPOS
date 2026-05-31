<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\Payments\Application\UseCases\Capture\StaffReservationDepositService;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Support\AuditEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerReservationDepositIntentService
{
    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly StaffReservationDepositService $staffReservationDepositService,
        private readonly ReservationDepositSelfServiceStateService $stateService,
        private readonly ReservationSessionAccessWorkflow $customerSessionAccessService,
    ) {}

    /**
     * --- NHÓM HÀM FACADE (GIAO DIỆN GỌI) ---
     * Tại sao lại chia làm 2 loại Owned và Accessible?
     * - Owned: Dành cho khách có tài khoản (User) đã đăng nhập.
     * - Accessible: Dành cho khách vãng lai (Guest) đặt bàn không cần tài khoản,
     * nhận dạng qua Session ID (được sinh ra lúc tạo đơn hoặc mã gửi qua email).
     */

    /**
     * @return array<string,mixed>
     */
    public function acknowledgeDepositRequirementForOwnedReservation(int $reservationId, int $userId, ?int $expectedRowVersion = null): array
    {
        // Khách hàng bấm "Tôi đồng ý với chính sách đặt cọc"
        return $this->mutateAccessibleReservation(
            reservationId: $reservationId,
            userId: $userId,
            sessionId: null,
            expectedRowVersion: $expectedRowVersion,
            action: 'acknowledge',
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function acknowledgeDepositRequirementForAccessibleReservation(int $reservationId, ?int $userId, ?string $sessionId, ?int $expectedRowVersion = null): array
    {
        return $this->mutateAccessibleReservation(
            reservationId: $reservationId,
            userId: $userId,
            sessionId: $sessionId,
            expectedRowVersion: $expectedRowVersion,
            action: 'acknowledge',
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function submitDepositIntentForOwnedReservation(int $reservationId, int $userId, ?int $expectedRowVersion = null): array
    {
        // Khách hàng bấm "Thanh toán ngay", hệ thống ghi nhận Intent (Ý định) để chuẩn bị gọi cổng thanh toán
        return $this->mutateAccessibleReservation(
            reservationId: $reservationId,
            userId: $userId,
            sessionId: null,
            expectedRowVersion: $expectedRowVersion,
            action: 'submit_intent',
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function submitDepositIntentForAccessibleReservation(int $reservationId, ?int $userId, ?string $sessionId, ?int $expectedRowVersion = null): array
    {
        return $this->mutateAccessibleReservation(
            reservationId: $reservationId,
            userId: $userId,
            sessionId: $sessionId,
            expectedRowVersion: $expectedRowVersion,
            action: 'submit_intent',
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function revokeDepositIntentForOwnedReservation(int $reservationId, int $userId, ?int $expectedRowVersion = null): array
    {
        // Khách hàng thoát giữa chừng ở cổng thanh toán hoặc quá thời gian thanh toán (Timeout)
        return $this->mutateAccessibleReservation(
            reservationId: $reservationId,
            userId: $userId,
            sessionId: null,
            expectedRowVersion: $expectedRowVersion,
            action: 'revoke_intent',
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function revokeDepositIntentForAccessibleReservation(int $reservationId, ?int $userId, ?string $sessionId, ?int $expectedRowVersion = null): array
    {
        return $this->mutateAccessibleReservation(
            reservationId: $reservationId,
            userId: $userId,
            sessionId: $sessionId,
            expectedRowVersion: $expectedRowVersion,
            action: 'revoke_intent',
        );
    }

    /**
     * --- ĐỘNG CƠ LÕI: THAY ĐỔI TRẠNG THÁI THANH TOÁN (MUTATE CORE) ---
     * Nơi tập trung toàn bộ logic bảo mật và nhất quán dữ liệu.
     *
     * @return array<string,mixed>
     */
    private function mutateAccessibleReservation(int $reservationId, ?int $userId, ?string $sessionId, ?int $expectedRowVersion, string $action): array
    {
        // --- BƯỚC 1: KHÓA PHÂN TÁN (PESSIMISTIC LOCKING) ---
        // Giăng dây bảo vệ cái đơn đặt bàn này bằng Redis/DB Lock.
        // Ngăn chặn việc khách đang bấm trả tiền mà nhân viên lại bấm hủy bàn cùng lúc.
        return $this->locks->withReservationLock($reservationId, function () use ($reservationId, $userId, $sessionId, $expectedRowVersion, $action) {
            DB::transaction(function () use ($reservationId, $userId, $sessionId, $expectedRowVersion, $action): void {

                // --- BƯỚC 2: TẢI & BẢO VỆ DỮ LIỆU CHỐNG IDOR ---
                /** @var Reservation $reservation */
                $reservation = $this->findAccessibleReservationForUpdate($reservationId, $userId, $sessionId);

                // --- BƯỚC 3: KIỂM TRA ĐỒNG THỜI (OPTIMISTIC LOCKING) ---
                $this->assertRowVersion($reservation, $expectedRowVersion);

                // Tải lịch sử thanh toán kèm Lock DB để tính toán số tiền đã cọc/cần cọc
                $payments = Payment::query()
                    ->with('refundOfPayment')
                    ->where('reservation_id', $reservationId)
                    ->orderBy('payment_id')
                    ->lockForUpdate()
                    ->get();

                $paymentSummary = PaymentSummary::fromPayments($payments);
                $beforeVersion = (int) ($reservation->row_version ?? 1);
                $wasDirty = false;
                $now = Carbon::now('UTC');

                // --- BƯỚC 4: THỰC THI STATE MACHINE (MÁY TRẠNG THÁI) ---
                switch ($action) {
                    case 'acknowledge':
                        // Khách xác nhận luật. Kiểm tra xem quy định nhà hàng có bắt cọc đơn này không.
                        $this->stateService->assertCanAcknowledge($reservation, $paymentSummary);
                        if ($reservation->deposit_requirement_acknowledged_at === null) {
                            $reservation->deposit_requirement_acknowledged_at = $now;
                            $wasDirty = true;
                        }
                        break;

                    case 'submit_intent':
                        // Đánh dấu luồng là Submitted để hệ thống biết đang có người làm thủ tục cọc,
                        // không cho phép người thứ 2 (hoặc 1 tab trình duyệt khác) nhảy vào tạo payment rác.
                        $this->stateService->assertCanSubmitIntent($reservation, $paymentSummary);
                        $intentStatus = $this->stateService->resolveIntentStatus($reservation);
                        if ($intentStatus->value !== 'Submitted') {
                            $reservation->deposit_intent_status = 'Submitted';
                            $reservation->deposit_intent_submitted_at = $now;
                            $reservation->deposit_intent_revoked_at = null; // Xóa vết lịch sử hủy (nếu có) để làm lại
                            $wasDirty = true;
                        }
                        break;

                    case 'revoke_intent':
                        // Giải phóng khóa Intent để khách có thể thử thanh toán lại hoặc bằng thẻ khác.
                        $this->stateService->assertCanRevokeIntent($reservation, $paymentSummary);
                        $intentStatus = $this->stateService->resolveIntentStatus($reservation);
                        if ($intentStatus->value !== 'Revoked') {
                            $reservation->deposit_intent_status = 'Revoked';
                            $reservation->deposit_intent_revoked_at = $now;
                            // Sửa lỗi timeline: Nếu chưa hề có giờ submit mà gọi revoke thì ép luôn giờ submit = now
                            if ($reservation->deposit_intent_submitted_at === null) {
                                $reservation->deposit_intent_submitted_at = $now;
                            }
                            $wasDirty = true;
                        }
                        break;

                    default:
                        throw ValidationException::withMessages([
                            'action' => ['Unsupported deposit self-service action.'],
                        ]);
                }

                // --- BƯỚC 5: LƯU & GHI VẾT (AUDIT) ---
                // Chỉ chạm vào database nếu có sự thay đổi thực sự (Giảm tải I/O Database)
                if ($wasDirty) {
                    $reservation->updated_by = $userId;
                    $reservation->save();
                }

                // Ghi lại sự kiện an ninh mạng
                AuditEvent::info('customer.reservation.deposit_self_service_mutated', [
                    'reservation_id' => $reservationId,
                    'user_id' => $userId,
                    'access_scope' => $userId !== null ? 'owner' : 'session',
                    'customer_session_id' => $userId === null ? trim((string) $sessionId) : null,
                    'action' => $action,
                    'before_row_version' => $beforeVersion,
                    'new_row_version' => (int) ($reservation->row_version ?? $beforeVersion),
                    'changed' => $wasDirty,
                    'deposit_intent_status' => (string) ($reservation->deposit_intent_status?->value ?? $reservation->deposit_intent_status ?? 'None'),
                    'deposit_requirement_acknowledged_at' => $reservation->deposit_requirement_acknowledged_at?->utc()->toIso8601String(),
                ]);
            });

            // Tái sử dụng chung một luồng tính toán số tiền cọc cần trả của bên Staff.
            // Điều này đảm bảo Khách hàng và Nhân viên đều thấy chung 1 con số (Single Source of Truth).
            return $this->staffReservationDepositService->previewDeposit($reservationId, 'VND');
        });
    }

    /**
     * --- BẢO MẬT: TRUY XUẤT AN TOÀN ---
     * Ngăn chặn lỗ hổng IDOR (Insecure Direct Object Reference) - Khách A sửa ID trên URL để xem đơn Khách B.
     */
    private function findAccessibleReservationForUpdate(int $reservationId, ?int $userId, ?string $sessionId): Reservation
    {
        // Kịch bản 1: Khách đã đăng nhập (Có User ID)
        if ($userId !== null) {
            /** @var Reservation|null $reservation */
            $reservation = Reservation::query()
                ->where('reservation_id', $reservationId)
                // Đảm bảo đơn này thuộc về đúng User đang request
                ->where('user_id', $userId)
                ->lockForUpdate() // Chống ghi đè
                ->first();

            if ($reservation instanceof Reservation) {
                return $reservation;
            }
        }

        // Kịch bản 2: Khách vãng lai (Guest)
        $resolvedSessionId = trim((string) $sessionId);
        /** @var Reservation|null $reservation */
        $reservation = Reservation::query()
            ->where('reservation_id', $reservationId)
            ->lockForUpdate()
            ->first();

        // Kiểm duyệt kĩ SessionID: Mã bí mật này có khớp với đơn hàng không?
        // Logic kiểm tra hash/token nằm bên trong customerSessionAccessService.
        if (! $reservation instanceof Reservation || $resolvedSessionId === '' || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $resolvedSessionId)) {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        return $reservation;
    }

    /**
     * --- BẢO MẬT: OPTIMISTIC LOCKING ---
     * So sánh version dữ liệu của Frontend gửi lên với Database hiện tại.
     */
    private function assertRowVersion(Reservation $reservation, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        // Nếu khách đang xem trang thanh toán (version 2), nhưng nhân viên vừa vào sửa thêm món
        // làm đổi giá cọc (version nhảy lên 3), thì giao dịch của khách bị chặn lại ngay lập tức.
        if ((int) ($reservation->row_version ?? 1) !== (int) $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }
}
