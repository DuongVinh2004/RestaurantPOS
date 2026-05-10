<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Domain\Guards;

use App\Enums\TableHoldStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

class HoldConflictScope
{
    // --- BƯỚC 1: KHỞI TẠO VÀ CHUẨN HÓA THỜI GIAN ---
    public static function apply(Builder $query, string $holdAlias = 'th', ?Carbon $now = null): Builder
    {
        $now ??= Carbon::now('UTC');

        $holdStatus = $holdAlias.'.hold_status';
        $expireAt = $holdAlias.'.expire_at';

        // --- BƯỚC 2: ÁP DỤNG BỘ LỌC TRẠNG THÁI TẠM GIỮ (QUERY SCOPE) ---
        // Confirmed holds are a linkage artifact after reservation creation.
        // Runtime conflict checks must follow the reservation state and table assignment
        // instead of the original hold row, otherwise stale confirmed holds can keep
        // blocking tables after move/reschedule/cancel flows.
        //
        // [BEST PRACTICE]: Single Source of Truth (Nguồn chân lý duy nhất)
        // Nghiệp vụ: Khi khách đã thanh toán/xác nhận xong, trạng thái Hold sẽ chuyển thành 'Confirmed',
        // và một bản ghi Đặt bàn (Reservation) chính thức được tạo ra. Kể từ giây phút đó, việc kiểm tra
        // "Bàn này có trống không?" phải dựa vào bảng Reservation, chứ không được nhìn vào bảng Hold nữa.
        // Nếu vẫn đếm cả những Hold đã 'Confirmed', thì lỡ sau này khách hàng đổi sang bàn khác hoặc hủy lịch,
        // cái Hold cũ kỹ đó sẽ biến thành "bóng ma" vĩnh viễn khóa chặt cái bàn lại.
        //
        // [BEST PRACTICE]: Query Scope Encapsulation (Đóng gói logic truy vấn)
        // Thay vì phải viết lại cụm where này ở hàng chục file khác nhau (TableAvailabilityService,
        // TableTimeConflictService...), ta gom nó vào một class Guard duy nhất. Khi luật giữ bàn thay đổi
        // (ví dụ thêm một trạng thái mới), chỉ cần sửa ở đây là toàn hệ thống tự động cập nhật.
        return $query->where(function (Builder $subQuery) use ($holdStatus, $expireAt, $now) {
            $subQuery
                ->whereIn($holdStatus, [
                    TableHoldStatus::Holding->value,
                    TableHoldStatus::Pending->value,
                ])
                ->where($expireAt, '>', $now);
        });
    }
}
