<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Application\Services;

use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Domain\Guards\HoldConflictScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tim danh sach ban dang va cham theo reservation hoac hold trong mot khoang thoi gian.
 */
class TableTimeConflictService
{
    // --- BƯỚC 1: KIỂM TRA XUNG ĐỘT VỚI LỊCH ĐẶT BÀN ĐÃ CHỐT (RESERVATION CONFLICTS) ---
    /**
     * @param  array<int,int>  $tableIds
     * @return array<int,int>
     */
    public function findReservationConflictTableIds(
        array $tableIds,
        Carbon $start,
        Carbon $end,
        ?int $ignoreReservationId = null,
        bool $lock = false,
    ): array {
        // Dung cho booking da xac nhan; ket qua tra ve la table id de caller tu quyet cach bao loi.
        // [BEST PRACTICE]: Deadlock Prevention (Chống khóa chéo)
        // Luôn luôn chuẩn hóa và sắp xếp mảng ID từ nhỏ đến lớn trước khi thực hiện các truy vấn
        // có khả năng gây lock DB để tránh Deadlock.
        $tableIds = $this->normalizeTableIds($tableIds);
        if ($tableIds === []) {
            return [];
        }

        // Reservation conflict query chi can tra ve table ids, de caller tu quyet cach xu ly/block message.
        // [BEST PRACTICE]: Time Interval Overlap Formula (Công thức phát hiện chồng chéo thời gian)
        // Thay vì dùng hàm BETWEEN, công thức chuẩn xác nhất để kiểm tra 2 khoảng thời gian (A và B)
        // có giao nhau hay không là: (StartA < EndB) AND (EndA > StartB).
        $query = DB::table('reservation_tables as rt')
            ->join('reservations as r', 'r.reservation_id', '=', 'rt.reservation_id')
            ->whereIn('rt.table_id', $tableIds)
            ->whereIn('r.status', ReservationStatus::activeDbValues())
            ->where('r.start_time', '<', $end)
            ->where('r.end_time', '>', $start);

        // Nghiệp vụ: Cập nhật / Đổi lịch (Reschedule)
        // Khi khách hàng muốn dời lịch của chính họ, hệ thống phải bỏ qua (ignore) ID của
        // đơn đặt bàn hiện tại, nếu không hệ thống sẽ báo lỗi "Bàn này đã bị (chính bạn) đặt".
        if ($ignoreReservationId !== null && $ignoreReservationId > 0) {
            $query->where('r.reservation_id', '!=', $ignoreReservationId);
        }

        // [BEST PRACTICE]: On-Demand Pessimistic Locking
        if ($lock) {
            $query->lockForUpdate();
        }

        // [BEST PRACTICE]: Query Performance & DoS Protection
        // Dùng distinct() để loại bỏ các dòng trùng lặp và limit(100) để bảo vệ database khỏi các cuộc tấn công DoS
        // hoặc các câu query quét quá lớn. Caller chỉ cần biết "có lỗi trùng bàn", không cần lấy về hàng vạn dòng.
        return $query->select('rt.table_id')
            ->distinct()
            ->limit(100)
            ->pluck('rt.table_id')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();
    }

    // --- BƯỚC 2: KIỂM TRA XUNG ĐỘT VỚI CÁC BÀN ĐANG BỊ TẠM GIỮ (HOLD CONFLICTS) ---
    /**
     * @param  array<int,int>  $tableIds
     * @param  array<int,string>  $ignoredHoldIds
     * @return array<int,int>
     */
    public function findHoldConflictTableIds(
        array $tableIds,
        Carbon $start,
        Carbon $end,
        array $ignoredHoldIds = [],
        ?string $ignoreSessionId = null,
        bool $lock = false,
        ?int $ignoreConfirmedReservationId = null,
    ): array {
        // Trusted hold cho phep bo qua hold cua chinh session hien tai khi dang tiep tuc flow.
        $tableIds = $this->normalizeTableIds($tableIds);
        if ($tableIds === []) {
            return [];
        }

        // Hold conflict query co them trusted hold/session-ignore hooks de phuc vu continue-flow scenarios.
        // Nghiệp vụ: Giống như việc bạn mua vé xem phim, khi bạn click chọn ghế, ghế đó sẽ bị "tạm giữ" (Hold)
        // trong 5-10 phút để bạn thanh toán. Hàm này kiểm tra xem bàn khách muốn đặt có đang bị ai khác "tạm giữ" không.
        $query = DB::table('table_hold_details as thd')
            ->join('table_holds as th', 'th.hold_id', '=', 'thd.hold_id')
            ->whereIn('thd.table_id', $tableIds)
            ->where('th.start_time', '<', $end)
            ->where('th.end_time', '>', $start);

        // Chỉ quan tâm những Hold đang còn hạn (chưa hết hạn đếm ngược)
        HoldConflictScope::apply($query, 'th', Carbon::now('UTC'));

        $ignoredHoldIds = array_values(array_unique(array_filter(array_map('strval', $ignoredHoldIds), static fn (string $value) => $value !== '')));
        if ($ignoredHoldIds !== []) {
            $query->whereNotIn('th.hold_id', $ignoredHoldIds);
        }

        // [BEST PRACTICE]: Session-Aware Conflict Resolution (Xử lý xung đột theo phiên nhận diện)
        // Nghiệp vụ cốt lõi: Nếu chính tôi là người đang "tạm giữ" bàn này, tôi có quyền chuyển sang bước thanh toán.
        // Hệ thống sẽ so sánh Session ID của trình duyệt/ứng dụng. Nếu khớp, lệnh chặn sẽ được bỏ qua.
        $ignoreSessionId = $ignoreSessionId !== null ? trim($ignoreSessionId) : null;
        if ($ignoreSessionId !== null && $ignoreSessionId !== '') {
            $query->where('th.session_id', '<>', $ignoreSessionId);
        }

        if ($ignoreConfirmedReservationId !== null && $ignoreConfirmedReservationId > 0) {
            $query->where(function ($subQuery) use ($ignoreConfirmedReservationId) {
                $subQuery->whereNull('th.confirmed_reservation_id')
                    ->orWhere('th.confirmed_reservation_id', '<>', $ignoreConfirmedReservationId);
            });
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->select('thd.table_id')
            ->distinct()
            ->limit(100)
            ->pluck('thd.table_id')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();
    }

    // --- BƯỚC 3: CHUẨN HÓA DỮ LIỆU ĐẦU VÀO ---
    /**
     * @param  array<int,int>  $tableIds
     * @return array<int,int>
     */
    private function normalizeTableIds(array $tableIds): array
    {
        // Loại bỏ các ID trùng lặp, ép về kiểu Integer và bắt buộc sắp xếp tăng dần
        // để đảm bảo thứ tự khóa (lock) trong MySQL luôn đồng nhất, ngăn chặn Deadlock.
        $tableIds = array_values(array_unique(array_map('intval', $tableIds)));
        sort($tableIds);

        return $tableIds;
    }
}
