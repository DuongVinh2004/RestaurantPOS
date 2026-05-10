<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\UseCases\Boards;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\FloorOperations\Domain\Guards\StaffReservationOperationGuard;
use App\Modules\FloorOperations\Domain\Guards\TableReleaseGuard;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\Support\AuditEvent;
use App\Support\Auth\StaffActorGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nha ban ve Available khi no khong con bi reservation/order dang hoat dong chan lai.
 * Nghiệp vụ: Chuyển trạng thái bàn từ Đang sử dụng (Occupied) về Trống (Available) hoặc Cần dọn dẹp (Needs Cleaning).
 * Ngăn chặn tuyệt đối việc giải phóng bàn khi khách chưa thanh toán hoặc chưa kết thúc dịch vụ.
 */
class StaffTableReleaseService
{
    private readonly ReservationBranchScopeService $reservationBranchScopeService;

    private readonly ?StaffBranchContextService $staffBranchContextService;

    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly RestaurantTableStateService $tableStateService,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
        ?StaffBranchContextService $staffBranchContextService = null,
    ) {
        $this->reservationBranchScopeService = $reservationBranchScopeService ?? app(ReservationBranchScopeService::class);
        $this->staffBranchContextService = $staffBranchContextService;
    }

    public function release(int $tableId, ?int $staffUserId = null, bool $force = false, ?string $notes = null, ?int $expectedRowVersion = null): RestaurantTable
    {
        // Release can lock table va check blockers de tranh nha nham ban dang co nguoi/mon dang song.

        // --- BƯỚC 1: XÁC THỰC VÀ BẬT KHIÊN BẢO VỆ KÉP (DOUBLE-LOCKING) ---
        // Pha 1: validate actor roi lock table de quyet dinh release tren mot snapshot nhat quan.
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        /** @var RestaurantTable $table */
        // Best Practice (Distributed Lock): Dùng Redis Mutex khóa Bàn này lại ở cấp độ Application.
        // Ngăn 2 nhân viên ở 2 iPad khác nhau cùng thao tác nhả bàn một lúc.
        $table = $this->locks->withTableLocks([$tableId], function () use ($tableId, $staffUserId, $force, $notes, $expectedRowVersion) {

            // Best Practice (Database Transaction): Đảm bảo quá trình kiểm tra và cập nhật dữ liệu là All-or-Nothing (ACID).
            return DB::transaction(function () use ($tableId, $staffUserId, $force, $notes, $expectedRowVersion) {

                /** @var RestaurantTable $table */
                // Best Practice (Pessimistic Locking): Dùng lockForUpdate() ép MySQL khóa dòng dữ liệu của Bàn này lại.
                // Các Request khác muốn đọc/ghi Bàn này đều phải đứng chờ cho đến khi Transaction này kết thúc.
                $table = RestaurantTable::query()->where('table_id', $tableId)->lockForUpdate()->firstOrFail();

                // --- BƯỚC 2: RÀNG BUỘC PHÂN QUYỀN VÀ PHIÊN BẢN (AUTHORIZATION & VERSIONING) ---
                // Branch scope duoc xac minh som de staff khong release nham table ngoai quyen.
                // Nghiệp vụ: Chống IDOR. Ngăn nhân viên chi nhánh A truyền API table_id của chi nhánh B để phá hoại.
                $tableBranchId = $this->reservationBranchScopeService->resolveTableBranchId(
                    [$table->branch_id],
                    'Table release target must belong to a single branch.',
                    'table_id',
                );
                $this->assertOperationalBranchAccessible($tableBranchId, $staffUserId);

                // Best Practice (Optimistic Locking): Bảo vệ thao tác nhầm trên giao diện cũ (Stale UI).
                // Nếu nhân viên đang nhìn giao diện từ 5 phút trước, row_version đã cũ, hệ thống sẽ từ chối giải phóng bàn.
                StaffReservationOperationGuard::assertExpectedTableRowVersion($table, $expectedRowVersion);

                // Kiểm tra xem bàn có nằm trong trạng thái ĐƯỢC PHÉP giải phóng không (VD: Bàn đang hỏng thì không thể giải phóng thành Available)
                StaffReservationOperationGuard::assertTableReleaseAllowed($table, $this->tableStateService, $force);

                // --- BƯỚC 3: KIỂM TOÁN CHỐNG THẤT THOÁT DOANH THU (REVENUE LOSS PREVENTION GUARD) ---
                // Pha 2: lock cac reservation active tren table de biet ban co dang trong mot service context hay khong.
                // Truy xuất TẤT CẢ các Booking đang hoạt động trên bàn này, kèm theo khóa (lockForUpdate).
                $activeReservations = DB::table('reservation_tables as rt')
                    ->join('reservations as r', 'r.reservation_id', '=', 'rt.reservation_id')
                    ->where('rt.table_id', $tableId)
                    ->whereIn('r.status', ReservationStatus::activeDbValues())
                    ->select('r.reservation_id', 'r.status', 'r.checked_in_at', 'r.branch_id', 'r.start_time', 'r.end_time')
                    ->lockForUpdate()
                    ->get();

                foreach ($activeReservations as $reservation) {
                    $this->reservationBranchScopeService->assertReservationMatchesTableBranch(
                        $reservation->branch_id ?? null,
                        $tableBranchId,
                        'Reservation branch does not match the table branch being released.',
                        'table_id',
                    );
                }

                // Guard nay tach logic "reservation nao thuc su chan release" khoi service de tai su dung duoc.
                // Nghiệp vụ: Phân tích xem các booking đang Active kia có booking nào "Thực sự đang diễn ra ngay lúc này" không.
                $blockingReservationIds = TableReleaseGuard::blockingReservationIds($activeReservations, now('UTC'));

                // Nghiệp vụ Cốt Lõi: Khách có thể đã rời đi, Reservation đã được đóng, NHƯNG còn Hóa đơn (Order) chưa thanh toán!
                $activeOrderExists = DB::table('reservation_orders as ro')
                    ->join('reservation_tables as rt', 'rt.reservation_id', '=', 'ro.reservation_id')
                    ->where('rt.table_id', $tableId)
                    ->where('ro.status', ReservationOrderStatus::Active->value)
                    ->lockForUpdate()
                    ->exists();

                // Chi cho release khi khong con reservation active va khong con order active treo tren ban.
                // Rào cản 1: Khách vẫn đang ở trong quán (Check-in), chưa checkout.
                if ($blockingReservationIds !== []) {
                    throw ValidationException::withMessages([
                        'table_id' => [
                            'Cannot release table while reservations are still in an active service context: '
                            .implode(',', $blockingReservationIds)
                            .'. Complete check-in/checkout or close the reservation flow first.',
                        ],
                    ]);
                }

                // Rào cản 2: Bàn có Hóa đơn chưa chốt (Chưa thu tiền).
                // Nếu cho phép nhả bàn lúc này, bàn sẽ chuyển thành Trống, khách mới vào ngồi và hóa đơn cũ bị mất dấu.
                if ($activeOrderExists) {
                    throw ValidationException::withMessages([
                        'table_id' => ['Cannot release table while a live order still exists for this table. Close or settle the order first.'],
                    ]);
                }

                // --- BƯỚC 4: THỰC THI THAY ĐỔI & LƯU VẾT (MUTATION & AUDIT) ---
                // Pha 3: chi khi khong con blocker moi mutate table state ve Available.
                // Mọi rào cản đã thông qua, gọi TableStateService để đổi trạng thái bàn (VD: Occupied -> Needs Cleaning)
                $table = $this->tableStateService->releaseModelSafely(
                    $table,
                    null,
                    $staffUserId,
                    [
                        'source' => 'staff_table_release',
                        'reason' => $force ? 'force_release' : 'manual_release',
                        'force' => $force,
                        'notes' => $notes,
                    ]
                );

                // Best Practice (Audit Logging): Ghi nhận hành vi nhả bàn.
                // Lưu ý: Dùng log mức độ `warning` (thay vì info) vì việc Lễ tân ấn nút "Giải phóng bàn thủ công"
                // là một hành động có rủi ro (thường bàn tự động nhả khi thanh toán xong). Cần lưu vết để truy cứu.
                AuditEvent::warning('staff.table.released', [
                    'table_id' => (int) $tableId,
                    'force' => $force,
                    'staff_user_id' => $staffUserId,
                    'notes' => $notes,
                    'result_status' => (string) ($table->status?->value ?? $table->status),
                ]);

                return $table;
            });
        });

        // --- BƯỚC 5: PHÁT SÓNG SỰ KIỆN QUA WEBSOCKET (POST-COMMIT EVENT) ---
        // Publish sau commit de board/timeline cap nhat dung ket qua cuoi cung.
        // Đảm bảo chỉ bắn event khi Database Transaction đã lưu thành công, giúp mọi thiết bị (iPad, Web)
        // của các nhân viên khác lập tức thấy bàn này đã Trống mà không cần F5 (Realtime UI).
        app(OperationalRealtimeService::class)->publishBoardEvent(
            'table.released',
            [
                'table_id' => (int) $tableId,
                'force' => $force,
                'result_status' => (string) ($table->status?->value ?? $table->status),
            ],
            ['board', 'timeline'],
        );

        return $table;
    }

    private function assertOperationalBranchAccessible(int $branchId, ?int $staffUserId): void
    {
        if ($branchId <= 0) {
            return;
        }

        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);
        $this->staffBranchContextService()->assertAccessibleBranch($staffUserId, $branchId);
    }

    private function staffBranchContextService(): StaffBranchContextService
    {
        return $this->staffBranchContextService ?? app(StaffBranchContextService::class);
    }
}
