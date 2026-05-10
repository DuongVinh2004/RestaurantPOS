<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\Payments\Application\UseCases\Capture\StaffReservationDepositService;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Lớp này đóng vai trò như một Proxy/Facade bảo vệ vòng ngoài.
 * Nó tiếp nhận Request từ Customer API, kiểm tra quyền truy cập (Authorization),
 * và gọi xuống tầng Core Payment để lấy dữ liệu.
 */
class CustomerReservationDepositService
{
    public function __construct(
        private readonly StaffReservationDepositService $staffReservationDepositService,
        private readonly ReservationSessionAccessWorkflow $customerSessionAccessService,
    ) {}

    /**
     * --- HÀM 1: XEM TRƯỚC TIỀN CỌC CHO MỌI ĐỐI TƯỢNG (ACCESSIBLE) ---
     * Dùng cho trang Check-out hoặc trang Chi tiết đơn hàng khi khách truy cập bằng Link qua Email/SMS.
     *
     * * @return array<string,mixed>
     */
    public function previewAccessibleReservationDeposit(int $reservationId, ?int $userId, ?string $sessionId, string $fallbackCurrency = 'VND'): array
    {
        // --- BƯỚC 1: ĐIỀU HƯỚNG THEO ĐỊNH DANH (ROUTING) ---
        // Nếu Request có đính kèm User ID (tức là khách hàng đã đăng nhập vào App/Web),
        // lập tức chuyển luồng sang hàm xử lý riêng cho Khách có tài khoản (Owned).
        if ($userId !== null) {
            return $this->previewOwnedReservationDeposit($reservationId, $userId, $fallbackCurrency);
        }

        // --- BƯỚC 2: XÁC THỰC KHÁCH VÃNG LAI (GUEST AUTHENTICATION) ---
        // Nếu không có User ID, hệ thống hiểu đây là Khách vãng lai bấm vào link tracking.
        $resolvedSessionId = trim((string) $sessionId);
        $reservation = Reservation::query()->where('reservation_id', $reservationId)->first();

        // Bảo mật IDOR: Không được phép xem nếu truyền bừa một reservation_id.
        // Phải có Session ID hợp lệ khớp với thuật toán mã hóa của ReservationSessionAccessWorkflow.
        if (! $reservation instanceof Reservation || $resolvedSessionId === '' || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $resolvedSessionId)) {
            // Best Practice: Ném lỗi 404 (Not Found) thay vì 403 (Forbidden) hoặc 401 (Unauthorized).
            // Kỹ thuật này gọi là "Obfuscation" - che giấu sự tồn tại của dữ liệu. Hacker sẽ không biết
            // id=123 là không có quyền xem, hay id=123 thực sự không tồn tại trong CSDL.
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        // --- BƯỚC 3: ỦY QUYỀN TÍNH TOÁN (SINGLE SOURCE OF TRUTH) ---
        // Kỹ thuật cực hay: Bạn KHÔNG viết lại logic tính tiền cọc (cộng trừ nhân chia) ở đây.
        // Bạn gọi trực tiếp qua `StaffReservationDepositService`.
        // Nhờ vậy, Đảm bảo tính nhất quán (Consistency): Khách hàng nhìn thấy màn hình báo cọc 500k,
        // thì nhân viên mở máy POS lên xem cũng phải ra đúng 500k. Tuyệt đối không bị lệch số liệu!
        return $this->staffReservationDepositService->previewDeposit($reservationId, $fallbackCurrency);
    }

    /**
     * --- HÀM 2: XEM TRƯỚC TIỀN CỌC CHO KHÁCH CÓ TÀI KHOẢN (OWNED) ---
     *
     * * @return array<string,mixed>
     */
    public function previewOwnedReservationDeposit(int $reservationId, int $userId, string $fallbackCurrency = 'VND'): array
    {
        // --- BƯỚC 1: XÁC THỰC CHỦ SỞ HỮU (OWNERSHIP VALIDATION) ---
        // Truy vấn CSDL để đảm bảo Đơn đặt bàn này thực sự thuộc về Khách hàng đang đăng nhập.
        // Nếu Khách hàng A (user_id = 1) cố tình truyền lên reservation_id = 999 (của Khách hàng B),
        // query này sẽ fail và trả về 404 tự động qua hàm `firstOrFail()`.
        Reservation::query()
            ->where('reservation_id', $reservationId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // --- BƯỚC 2: ỦY QUYỀN TÍNH TOÁN ---
        // Giống hệt hàm trên, tái sử dụng Core Domain Logic của StaffService để tính toán.
        return $this->staffReservationDepositService->previewDeposit($reservationId, $fallbackCurrency);
    }
}
