<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationConflictValidator
{
    public function __construct(
        private readonly TableTimeConflictService $tableTimeConflictService,
        private readonly RestaurantTableStateService $tableStateService,
    ) {}

    /**
     * --- BƯỚC 1: KHÓA TÀI NGUYÊN (PESSIMISTIC LOCKING) ---
     * Tại sao lại cần hàm này? Trong môi trường nhà hàng đông đúc, 2 nhân viên có thể
     * cùng click chọn bàn số 5 cùng một tích tắc.
     *
     * @param  list<int>  $tableIds
     */
    public function lockAndLoadTables(array $tableIds): Collection
    {
        return RestaurantTable::query()
            ->whereIn('table_id', $tableIds)
            // Lệnh lockForUpdate() sẽ báo cho Database biết: "Khóa các dòng dữ liệu bàn này lại.
            // Nếu có ai khác cũng đang định đọc/sửa mấy bàn này để đặt chỗ, bắt họ phải chờ tôi làm xong!".
            // Đây là Best Practice để chống Race Condition (Tranh chấp dữ liệu đồng thời).
            ->lockForUpdate()
            ->get();
    }

    /**
     * --- BƯỚC 2: KIỂM TRA TRẠNG THÁI VẬT LÝ CỦA BÀN ---
     * Bàn có tồn tại không? Có đang bị hỏng/dọn dẹp không? Sức chứa có đủ không?
     *
     * @param  Collection<int,RestaurantTable>  $tables
     */
    public function assertTablesAllocatableAndCapacity(Collection $tables, array $tableIds, int $guestCount): void
    {
        // Kiểm tra dữ liệu ảo: Khách hàng (hoặc hacker) có thể dùng Postman truyền lên
        // ID bàn không có thực trong hệ thống.
        if ($tables->count() !== count($tableIds)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Some selected tables do not exist.'],
            ]);
        }

        // Kiểm tra Soft Delete: Bàn có thể đã bị Quản lý xóa khỏi sơ đồ (is_deleted = 1)
        // nhưng Frontend chưa kịp cập nhật (hoặc cố tình giữ lại trong cache).
        $deletedTables = $tables->where('is_deleted', 1)->pluck('table_id')->values()->all();
        if (! empty($deletedTables)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Some selected tables were deleted: '.implode(',', $deletedTables)],
            ]);
        }

        // Kiểm tra State Machine: Hàm isAllocatableForBooking() sẽ chặn các bàn đang ở trạng thái
        // không cho phép nhận khách (Ví dụ: Bàn đang gộp, Bàn đang sửa chữa, Bàn đã khóa).
        $nonAllocatable = $tables->filter(fn ($t) => ! $this->tableStateService->isAllocatableForBooking((string) ($t->status?->value ?? $t->status)))
            ->pluck('table_id')->values()->all();
        if (! empty($nonAllocatable)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Some selected tables are not in Available status: '.implode(',', $nonAllocatable)],
            ]);
        }

        // Đi tới hàm nội bộ để tính tổng số ghế.
        $this->assertCapacityEnough($tables, $guestCount);
    }

    /**
     * --- BƯỚC 3: KIỂM TRA TRANH CHẤP THỜI GIAN (TIME CONFLICTS) ---
     * Đảm bảo rằng việc xếp bàn không đè lên một booking khác đã có hoặc đang được giữ.
     *
     * @param  list<int>  $tableIds
     * @param  list<string>  $trustedHoldIds
     */
    public function assertNoCreateConflicts(array $tableIds, Carbon $startUtc, Carbon $endUtc, array $trustedHoldIds = []): void
    {
        // 3.1 - Kiểm tra Soft Holds (Giữ bàn tạm thời)
        // Hệ thống cho phép khách Online giữ bàn trong 5-10 phút khi đang chọn món.
        // Nếu cái bàn này đang nằm trong giỏ hàng của người khác (và không nằm trong danh sách trustedHoldIds của người dùng hiện tại), thì phải chặn lại.
        $holdConflicts = $this->tableTimeConflictService->findHoldConflictTableIds($tableIds, $startUtc, $endUtc, $trustedHoldIds, null, true);
        if (! empty($holdConflicts)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Some selected tables are held by another session: '.implode(',', $holdConflicts)],
            ]);
        }

        // 3.2 - Kiểm tra Hard Reservations (Đặt bàn chính thức)
        // Query kiểm tra xem khoảng thời gian (startUtc -> endUtc) có bị giao nhau (overlap)
        // với bất kỳ một đơn đặt bàn nào đã được "Confirmed" trên DB hay không.
        $conflictTableIds = $this->tableTimeConflictService->findReservationConflictTableIds($tableIds, $startUtc, $endUtc, null, true);
        if (! empty($conflictTableIds)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Some selected tables have overlapping reservations: '.implode(',', $conflictTableIds)],
            ]);
        }
    }

    /**
     * --- BƯỚC 4: TÍNH TOÁN SỨC CHỨA (CAPACITY CHECK) ---
     *
     * @param  Collection<int,RestaurantTable>  $tables
     */
    private function assertCapacityEnough(Collection $tables, int $guestCount): void
    {
        // Thiết kế dữ liệu rất chuẩn: Thông tin số ghế (seats) không nằm trực tiếp ở bảng Tables,
        // mà nằm ở bảng templates (Kiểu dáng bàn: Bàn vuông 4 ghế, Bàn tròn 6 ghế).
        // Phải đảm bảo bàn đã được gán template.
        $nullTemplate = $tables->whereNull('template_id')->pluck('table_id')->values()->all();
        if (! empty($nullTemplate)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Some selected tables are missing template_id and cannot calculate seats: '.implode(',', $nullTemplate)],
            ]);
        }

        // Lấy danh sách ID của các loại mẫu bàn (tránh query trùng lặp)
        $templateIds = $tables->pluck('template_id')->unique()->values()->all();

        // Truy vấn 1 lần (N+1 query avoidance) vào bảng table_templates để lấy thuộc tính `seats`
        $seatsByTemplate = DB::table('table_templates')
            ->whereIn('template_id', $templateIds)
            ->pluck('seats', 'template_id');

        $missingTemplates = [];
        $totalSeats = 0;

        // Vòng lặp cộng dồn tổng số ghế của tất cả các bàn khách vừa chọn
        foreach ($tables as $t) {
            $tid = (int) $t->template_id;

            // Đề phòng trường hợp database lỗi, table được gán 1 template_id không tồn tại
            if (! $seatsByTemplate->has($tid)) {
                $missingTemplates[] = $tid;

                continue;
            }
            $totalSeats += (int) $seatsByTemplate->get($tid);
        }

        if (! empty($missingTemplates)) {
            $missingTemplates = array_values(array_unique($missingTemplates));
            throw ValidationException::withMessages([
                'table_ids' => ['A table template is missing for seat calculation: '.implode(',', $missingTemplates)],
            ]);
        }

        // Chốt chặn cuối cùng: Nếu nhóm 10 người mà cố tình xếp vào 2 cái bàn 4 ghế (tổng 8 chỗ) -> Quăng lỗi.
        if ($guestCount > $totalSeats) {
            throw ValidationException::withMessages([
                'guest_count' => ["Guest count ($guestCount) exceeds selected table capacity ($totalSeats seats)."],
            ]);
        }
    }
}
