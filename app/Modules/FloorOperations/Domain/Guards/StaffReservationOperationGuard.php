<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Domain\Guards;

use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Policies\ReservationStatusTransitionPolicy;
use Illuminate\Validation\ValidationException;

/**
 * Lớp bảo vệ (Guard) quản lý các quy tắc nghiệp vụ khi thao tác trên Reservation và Table.
 * Ngăn chặn các hành động không hợp lệ dựa trên trạng thái (Status) và phiên bản dữ liệu (Row Version).
 */
class StaffReservationOperationGuard
{
    // Thông báo lỗi chuẩn hóa khi phát hiện xung đột dữ liệu (Optimistic Locking)
    private const ROW_VERSION_MESSAGE = 'Data changed (row_version mismatch). Reload and try again.';

    /**
     * Trích xuất giá trị trạng thái thực sự của Reservation (xử lý đa hình giữa Enum và String/Int thô)
     */
    public static function reservationStatusValue(Reservation $reservation): string
    {
        $status = $reservation->status;

        if ($status instanceof ReservationStatus) {
            return $status->value;
        }

        $raw = trim((string) $reservation->getRawOriginal('status'));
        if ($raw !== '') {
            return $raw;
        }

        return trim((string) $status);
    }

    /**
     * Nghiệp vụ: Một Reservation được coi là "Đã Check-in" nếu nó mang trạng thái CheckedIn
     * HOẶC có mốc thời gian checked_in_at (đề phòng trường hợp trạng thái bị trôi nhưng log thời gian vẫn còn).
     */
    public static function isCheckedInReservation(Reservation $reservation): bool
    {
        return ReservationStatus::isCheckedInDbValue(self::reservationStatusValue($reservation))
            || $reservation->checked_in_at !== null;
    }

    /**
     * --- BƯỚC BẢO VỆ 1: OPTIMISTIC LOCKING CHO RESERVATION ---
     * Best Practice: Đây là cơ chế chống ghi đè cực kỳ quan trọng trong môi trường đa người dùng.
     * Mỗi Reservation có một `row_version`. Nếu Client A và Client B cùng mở 1 đơn.
     * B lưu trước -> `row_version` tăng lên.
     * A lưu sau -> `expectedRowVersion` của A gửi lên sẽ nhỏ hơn bản mới trong DB -> Bị Guard này chặn lại.
     */
    public static function assertExpectedReservationRowVersion(Reservation $reservation, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return; // Nếu Client không quan tâm (bỏ qua check), thì cho qua (Thường dùng cho các hệ thống batch ngầm)
        }

        if ((int) ($reservation->row_version ?? 1) !== $expectedRowVersion) {
            // Ném lỗi 422 cho Frontend biết để yêu cầu Lễ tân Tải lại trang (Reload)
            throw ValidationException::withMessages([
                'row_version' => [self::ROW_VERSION_MESSAGE],
            ]);
        }
    }

    /**
     * --- BƯỚC BẢO VỆ 2: OPTIMISTIC LOCKING CHO TABLE ---
     * Tương tự như Reservation, nhưng áp dụng cho Bàn vật lý. Ngăn chặn 2 Lễ tân cùng thao tác
     * xếp khách vào 1 bàn dựa trên dữ liệu cũ của giao diện.
     */
    public static function assertExpectedTableRowVersion(RestaurantTable $table, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($table->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => [self::ROW_VERSION_MESSAGE],
            ]);
        }
    }

    /**
     * --- BƯỚC BẢO VỆ 3: KIỂM SOÁT HÀNH VI CHECK-IN ---
     * Nghiệp vụ: Có được phép Đón Khách (Check-in) hay không?
     */
    public static function assertCheckInAllowed(Reservation $reservation, ?int $expectedRowVersion): void
    {
        self::assertExpectedReservationRowVersion($reservation, $expectedRowVersion);

        // Cần đảm bảo trạng thái hiện tại (VD: Confirmed) ĐƯỢC PHÉP chuyển sang trạng thái CheckedIn.
        // Tránh tình trạng Đơn đã Hủy (Cancelled) mà lại cho phép Check-in vào bàn.
        ReservationStatusTransitionPolicy::assertTransitionAllowed(
            self::reservationStatusValue($reservation),
            ReservationStatus::checkedIn(),
        );
    }

    /**
     * --- BƯỚC BẢO VỆ 4: KIỂM SOÁT HÀNH VI DỜI LỊCH (RESCHEDULE) ---
     * Nghiệp vụ: Khi khách gọi điện xin dời lịch sang giờ khác.
     */
    public static function assertRescheduleAllowed(Reservation $reservation, int $expectedRowVersion): void
    {
        // 1. Chỉ những đơn đã được chốt (Confirmed) mới được dời lịch.
        // Nếu đang Pending (chờ thanh toán cọc) thì phải thanh toán xong mới dời.
        if (self::reservationStatusValue($reservation) !== ReservationStatus::Confirmed->value) {
            throw ValidationException::withMessages([
                'status' => ['Only Confirmed reservations can be rescheduled.'],
            ]);
        }

        // 2. Nếu khách đã vào tận nơi ngồi rồi (CheckedIn) thì KHÔNG được dời lịch nữa.
        // Khách ngồi lâu hơn dự kiến là luồng Extending Time (Gia hạn thời gian), không phải Reschedule.
        if (self::isCheckedInReservation($reservation)) {
            throw ValidationException::withMessages([
                'status' => ['Checked-in reservations cannot be rescheduled. Use move-table/runtime flows instead.'],
            ]);
        }

        self::assertExpectedReservationRowVersion($reservation, $expectedRowVersion);
    }

    /**
     * --- BƯỚC BẢO VỆ 5: KIỂM SOÁT HÀNH VI ĐỔI BÀN (MOVE TABLE) ---
     * Nghiệp vụ: Chuyển khách từ Bàn A sang Bàn B.
     */
    public static function assertMoveTableAllowed(Reservation $reservation, ?int $expectedRowVersion): void
    {
        // 1. Logic cứng: Khách phải đang CÓ MẶT (CheckedIn) tại quán thì mới gọi là "Đổi bàn".
        // Nếu khách gọi điện xin đổi bàn trước khi đến, đó là thao tác "Gán bàn lại" (Re-assign), không phải Move.
        if (! self::isCheckedInReservation($reservation)) {
            throw ValidationException::withMessages([
                'status' => ['Only checked-in reservations can move tables.'],
            ]);
        }

        self::assertExpectedReservationRowVersion($reservation, $expectedRowVersion);
    }

    /**
     * --- BƯỚC BẢO VỆ 6: KIỂM SOÁT HÀNH VI GIẢI PHÓNG BÀN (TABLE RELEASE) ---
     * Nghiệp vụ: Trả bàn về trạng thái Trống sau khi dọn dẹp xong.
     */
    public static function assertTableReleaseAllowed(
        RestaurantTable $table,
        RestaurantTableStateService $tableStateService,
        bool $force
    ): void {
        $status = (string) ($table->status?->value ?? $table->status);

        // 1. Bàn có đang bị Khóa vận hành không? (VD: Bị hỏng chân ghế, đang bảo trì - Maintenance)
        // Nếu bị khóa, thì KHÔNG AI được phép "Giải phóng" nó thành Available (Trống đón khách)
        // TRỪ KHI Quản lý (Admin) sử dụng quyền ép buộc ($force = true) để override.
        if ($tableStateService->isOperationallyBlocked($status) && ! $force) {
            throw ValidationException::withMessages([
                'table_id' => ['Table is blocked/maintenance. Operational states are preserved by release flow.'],
            ]);
        }
    }
}
