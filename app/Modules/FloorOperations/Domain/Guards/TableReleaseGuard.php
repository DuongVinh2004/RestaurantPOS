<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Domain\Guards;

use App\Enums\ReservationStatus;
use Illuminate\Support\Carbon;

class TableReleaseGuard
{
    /**
     * --- BƯỚC 1: XÁC ĐỊNH ĐIỀU KIỆN KẸT BÀN (BLOCKING CONDITIONS) ---
     * Đánh giá xem một Đơn đặt bàn CỤ THỂ có đang "chiếm dụng" (blocking) cái bàn này hay không.
     */
    public static function isReservationBlockingRelease(
        ?string $status,
        mixed $checkedInAt = null,
        mixed $startTime = null,
        mixed $endTime = null,
        ?Carbon $now = null,
    ): bool {
        $status = trim((string) ($status ?? ''));

        // Trường hợp 1: Khách đang ngồi sờ sờ ở đó (CheckedIn). Chắc chắn KHÔNG ĐƯỢC nhả bàn.
        if ($status === ReservationStatus::checkedInDbValue()) {
            return true;
        }

        // Trường hợp 2: Lỗi rớt trạng thái (State drift).
        // Trạng thái DB báo là Confirmed, nhưng lại có giờ checked_in_at.
        // Nghiệp vụ: Đôi khi API update status bị rớt mạng giữa chừng, nhưng log check-in đã ghi.
        // Dựa vào thời gian để bảo vệ an toàn (Phòng thủ - Defensive Programming), cứ có giờ check-in là coi như khách đang ngồi.
        if ($status === ReservationStatus::Confirmed->value && $checkedInAt !== null) {
            return true;
        }

        // Nếu trạng thái không phải Confirmed (VD: Cancelled - Đã hủy, NoShow - Khách bùng, Completed - Đã ăn xong)
        // Thì chắc chắn Đơn này KHÔNG CÒN chiếm dụng bàn nữa. Được phép nhả.
        if ($status !== ReservationStatus::Confirmed->value) {
            return false;
        }

        // Trường hợp 3: Khách CHƯA ĐẾN (Confirmed), nhưng CÓ PHẢI ĐANG TRONG GIỜ CỦA HỌ KHÔNG?
        $startUtc = self::normalizeUtc($startTime);
        $endUtc = self::normalizeUtc($endTime);
        if ($startUtc === null || $endUtc === null) {
            return false; // Lỗi dữ liệu thiếu giờ, bypass để không làm kẹt hệ thống
        }

        $now ??= Carbon::now('UTC');

        // Khách chưa check-in, nhưng hiện tại đang nằm giữa Giờ Bắt Đầu và Giờ Kết Thúc của họ!
        // Nghiệp vụ: Khách đặt 19:00 - 21:00. Hiện tại là 19:15 khách chưa tới. Nhân viên thấy bàn trống định nhả ra cho khách Walk-in.
        // Guard này sẽ chặn lại! Vì Khách Confirmed vẫn đang trong quyền sử dụng bàn của họ, không được cướp bàn!
        return $startUtc->lessThanOrEqualTo($now) && $endUtc->greaterThan($now);
    }

    /**
     * --- BƯỚC 2: QUÉT HÀNG LOẠT ĐỂ TÌM "KẺ NGÁNG ĐƯỜNG" (BULK SCANNING) ---
     * Nhận vào một danh sách các Đơn đặt bàn đang gắn với cái bàn này,
     * và trả về Mảng chứa ID của những Đơn đang cản trở việc nhả bàn.
     * * @param  iterable<int,object|array<string,mixed>>  $rows
     * @return array<int,int>
     */
    public static function blockingReservationIds(iterable $rows, ?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');
        $ids = [];

        foreach ($rows as $row) {
            // Best Practice (Đa hình dữ liệu): Sử dụng readValue để đọc linh hoạt bất kể $row là Array (từ DB::table)
            // hay là Object (từ Eloquent Model).
            $reservationId = self::readValue($row, 'reservation_id');
            $status = self::readValue($row, 'status');
            $checkedInAt = self::readValue($row, 'checked_in_at');
            $startTime = self::readValue($row, 'start_time');
            $endTime = self::readValue($row, 'end_time');

            // Gọi logic Phân xử ở Bước 1
            if (! self::isReservationBlockingRelease(
                is_scalar($status) ? (string) $status : null,
                $checkedInAt,
                $startTime,
                $endTime,
                $now,
            )) {
                continue; // Không cản trở -> Bỏ qua
            }

            if ($reservationId === null) {
                continue;
            }

            $ids[] = (int) $reservationId; // Có cản trở -> Thu thập lại ID để báo lỗi cho Lễ tân
        }

        return array_values(array_unique($ids));
    }

    /**
     * --- BƯỚC 3: HÀM TIỆN ÍCH XỬ LÝ ĐA HÌNH (POLYMORPHIC EXTRACTOR) ---
     */
    private static function readValue(object|array $row, string $key): mixed
    {
        // Xử lý nhánh Array thuần túy
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        // Xử lý nhánh Object tiêu chuẩn hoặc Eloquent
        return $row->{$key} ?? null;
    }

    /**
     * --- BƯỚC 4: ĐỒNG NHẤT MÚI GIỜ (TIME NORMALIZATION) ---
     */
    private static function normalizeUtc(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Nếu đã là object ngày tháng, clone và chuyển về múi giờ gốc UTC (Tránh tác động ngược reference)
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        // Nếu là String (từ raw DB), parse và ép về UTC
        return Carbon::parse((string) $value)->utc();
    }
}
