<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\UseCases\Boards;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\FloorOperations\Domain\Guards\TableReleaseGuard;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\Support\AuditEvent;
use App\Support\Auth\StaffActorGuard;
use App\Support\AvailabilityCacheVersion;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chuyen reservation dang check-in tu ban nay sang ban khac trong khi van giu nguyen service context.
 */
class StaffMoveTableService
{
    private readonly ReservationBranchScopeService $reservationBranchScopeService;

    private readonly ?StaffBranchContextService $staffBranchContextService;

    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly RestaurantTableStateService $tableStateService,
        private readonly TableTimeConflictService $tableTimeConflictService,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
        ?StaffBranchContextService $staffBranchContextService = null,
    ) {
        $this->reservationBranchScopeService = $reservationBranchScopeService ?? app(ReservationBranchScopeService::class);
        $this->staffBranchContextService = $staffBranchContextService;
    }

    public function move(
        int $reservationId,
        int $fromTableId,
        int $toTableId,
        \DateTimeInterface $movedAt,
        ?int $staffUserId = null,
        ?int $expectedRowVersion = null
    ): Reservation {
        // Move-table la thao tac nhay cam nen phai lock ca reservation, ban cu, ban moi.

        // --- BƯỚC 1: XÁC THỰC VÀ TÍNH TOÁN PHẠM VI KHÓA (LOCK SCOPE) ---
        // Pha 1: validate actor va target ids truoc khi tinh lock scope.
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        if ($fromTableId <= 0 || $toTableId <= 0 || $fromTableId === $toTableId) {
            throw ValidationException::withMessages([
                'table_id' => ['Invalid table ids.'],
            ]);
        }

        // Lay bo table hien tai de lock du reservation, ban cu va bat ky ban dang gan nao khac.
        $currentTableIds = DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->pluck('table_id')
            ->map(fn ($value) => (int) $value)
            ->all();

        // Best Practice (Chống Deadlock - Deadlock Prevention):
        // Lấy tất cả ID bàn liên quan (Bàn đang ngồi, bàn chuẩn bị chuyển tới), GỘP lại và SẮP XẾP TĂNG DẦN (sort).
        // Việc luôn Lock các resource theo một thứ tự nhất định sẽ triệt tiêu hoàn toàn khả năng xảy ra Deadlock (Bài toán Triết gia ăn tối).
        $lockTableIds = array_values(array_unique(array_merge($currentTableIds, [$fromTableId, $toTableId])));
        sort($lockTableIds);

        $lockKeys = array_merge(
            [
                config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation').':'.$reservationId,
            ],
            array_map(
                fn (int $id) => config('booking.reservation_lock_prefix', 'booking:lock:table').':'.$id,
                $lockTableIds
            )
        );

        try {
            /** @var Reservation $reservation */
            // --- BƯỚC 2: THIẾT LẬP KHIÊN BẢO VỆ KÉP (DOUBLE-LOCKING) ---
            // Lớp khiên 1 (Distributed Lock): Dùng Redis Mutex để chặn các Request song song ở cấp độ Application.
            $reservation = $this->locks->withLockKeys($lockKeys, function () use (
                $reservationId,
                $fromTableId,
                $toTableId,
                $movedAt,
                $staffUserId,
                $expectedRowVersion
            ) {
                // Lớp khiên 2 (Database Transaction): Đảm bảo tính nguyên vẹn (ACID) ở cấp độ Database.
                return DB::transaction(function () use (
                    $reservationId,
                    $fromTableId,
                    $toTableId,
                    $movedAt,
                    $staffUserId,
                    $expectedRowVersion
                ) {
                    // Trong transaction nay se xac minh checked-in state, conflict, roi moi doi mapping ban.
                    /** @var Reservation|null $reservation */
                    // Pha 2: lock reservation + current mappings + target table trong cung mot transaction.
                    // Lớp khiên 3 (Pessimistic Locking): Dùng "FOR UPDATE" ở cấp độ Row của MySQL.
                    $reservation = Reservation::query()
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->first();

                    if (! $reservation) {
                        throw new ModelNotFoundException('Reservation not found');
                    }

                    // --- BƯỚC 3: KIỂM TRA ĐIỀU KIỆN NGHIỆP VỤ (BUSINESS RULES) ---
                    // Nghiệp vụ: Chỉ khách đã Check-in (đang ngồi ăn) mới được dùng chức năng Move Table.
                    $this->assertMoveTableReservationIsCheckedIn($reservation);

                    // Lớp khiên 4 (Optimistic Locking): So sánh phiên bản dữ liệu.
                    // Nếu màn hình iPad của phục vụ đang hiển thị dữ liệu cũ, hệ thống sẽ từ chối để tránh ghi đè sai.
                    $this->assertReservationRowVersionMatches($reservation, $expectedRowVersion);

                    // Snapshot mapping hien tai la co so de thay fromTable bang toTable mot cach xac dinh.
                    $currentTableIds = DB::table('reservation_tables')
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->pluck('table_id')
                        ->map(fn ($value) => (int) $value)
                        ->all();

                    // Chống gian lận/Lỗi đồng bộ: Đảm bảo khách thực sự ĐANG NGỒI ở bàn cũ (fromTableId) thì mới cho dời đi.
                    if (! in_array($fromTableId, $currentTableIds, true)) {
                        throw ValidationException::withMessages([
                            'from_table_id' => ['Reservation is not assigned to from_table.'],
                        ]);
                    }

                    $tables = RestaurantTable::query()
                        ->whereIn('table_id', array_values(array_unique(array_merge($currentTableIds, [$toTableId]))))
                        ->notDeleted()
                        ->lockForUpdate() // Khóa cả dòng Bàn Cũ và Bàn Mới trong CSDL
                        ->get()
                        ->keyBy('table_id');

                    if ($tables->count() !== count(array_values(array_unique(array_merge($currentTableIds, [$toTableId]))))) {
                        throw ValidationException::withMessages([
                            'reservation_id' => ['Assigned tables or target table were not found.'],
                        ]);
                    }

                    if (! $tables->has($toTableId)) {
                        throw ValidationException::withMessages([
                            'to_table_id' => ['Target table not found.'],
                        ]);
                    }

                    /** @var RestaurantTable $to */
                    $to = $tables->get($toTableId);
                    $toStatus = (string) ($to->status?->value ?? $to->status);

                    // Ban dich phai dang allocatable theo board state hien tai, neu khong move se tao side-effect sai.
                    // Nghiệp vụ: Bàn mới phải đang "Sẵn sàng phục vụ" (Không bị hỏng hóc hay kẹt).
                    if (! $this->tableStateService->isAllocatableForBooking($toStatus)) {
                        throw ValidationException::withMessages([
                            'to_table_id' => ['Target table is not currently Available.'],
                        ]);
                    }

                    // targetTableIds la tap table sau khi thay ban cu bang ban moi; no duoc dung cho capacity + branch checks.
                    $targetTableIds = array_values(array_unique(array_map(
                        fn (int $id) => $id === $fromTableId ? $toTableId : $id,
                        $currentTableIds
                    )));
                    sort($targetTableIds);

                    // Ngăn chặn việc dời bàn chạy xuyên từ Chi nhánh A sang Chi nhánh B (Sai phạm vận hành nghiêm trọng)
                    $this->reservationBranchScopeService->syncReservationBranchOrAssert(
                        $reservation,
                        array_map(
                            static fn (int $tableId): mixed => $tables->get($tableId)?->branch_id,
                            $targetTableIds,
                        ),
                        $staffUserId,
                        'Assigned tables must belong to a single branch.',
                        'Reservation branch does not match the assigned table branch.',
                        'reservation_id',
                    );

                    // Kiểm tra Bàn mới có đủ chỗ cho nhóm khách này không
                    $capacity = DB::table('restaurant_tables as rt')
                        ->leftJoin('table_templates as tt', 'tt.template_id', '=', 'rt.template_id')
                        ->whereIn('rt.table_id', $targetTableIds)
                        ->sum(DB::raw('COALESCE(tt.seats, 0)'));

                    if ((int) $capacity < (int) $reservation->guest_count) {
                        throw ValidationException::withMessages([
                            'to_table_id' => ['Target table combination does not have enough seats for this reservation.'],
                        ]);
                    }

                    // Kiem tra conflict chi can nhin vao ban moi, vi ban cu da la ban dang thuoc reservation nay.
                    $reservationConflictIds = $this->tableTimeConflictService->findReservationConflictTableIds(
                        tableIds: [$toTableId],
                        start: $reservation->start_time->copy()->utc(),
                        end: $reservation->end_time->copy()->utc(),
                        ignoreReservationId: $reservationId,
                        lock: true,
                    );

                    if ($reservationConflictIds !== []) {
                        throw ValidationException::withMessages([
                            'to_table_id' => ['Target table already has an overlapping reservation.'],
                        ]);
                    }

                    $holdConflictIds = $this->tableTimeConflictService->findHoldConflictTableIds(
                        tableIds: [$toTableId],
                        start: $reservation->start_time->copy()->utc(),
                        end: $reservation->end_time->copy()->utc(),
                        lock: true,
                    );

                    if ($holdConflictIds !== []) {
                        throw ValidationException::withMessages([
                            'to_table_id' => ['Target table already has an overlapping table hold.'],
                        ]);
                    }

                    $this->assertOperationalBranchAccessible(
                        $this->resolveOperationalBranchId(
                            $reservation->branch_id,
                            $tables->get($fromTableId),
                        ),
                        $staffUserId,
                    );

                    // --- BƯỚC 4: THỰC THI THAY ĐỔI (MUTATION & STATE SYNC) ---
                    // Pha 3: mutate mapping reservation_tables theo thu tu xoa ban cu roi chen ban moi neu can.
                    DB::table('reservation_tables')
                        ->where('reservation_id', $reservationId)
                        ->where('table_id', $fromTableId)
                        ->delete();

                    $existsTo = DB::table('reservation_tables')
                        ->where('reservation_id', $reservationId)
                        ->where('table_id', $toTableId)
                        ->exists();

                    if (! $existsTo) {
                        DB::table('reservation_tables')->insert([
                            'reservation_id' => $reservationId,
                            'table_id' => $toTableId,
                        ]);
                    }

                    // Best Practice (An toàn nghiệp vụ):
                    // Bàn cũ vừa bị xóa khách, nhưng khoan hãy báo là "Bàn trống".
                    // Nhỡ đâu lúc nãy bàn này đang có 2 nhóm khách ghép ngồi chung thì sao? Phải kiểm tra thật kỹ trước khi giải phóng!
                    $this->assertFromTableSafeToRelease(
                        tableId: $fromTableId,
                        tableBranchId: (int) ($tables->get($fromTableId)?->branch_id ?? 0),
                        currentReservationId: $reservationId,
                    );

                    // Pha 4: dong bo realtime table state cho ban cu va ban moi.
                    // Giải phóng bàn cũ (Chuyển về Available hoặc Cần dọn dẹp)
                    $this->tableStateService->releaseTablesSafely(
                        [$fromTableId],
                        null,
                        $staffUserId,
                        [
                            'reservation_id' => $reservationId,
                            'source' => 'staff_move_table',
                            'reason' => 'move_from_table',
                            'counterpart_table_id' => $toTableId, // Lưu lại Log: Bàn cũ đã bị nhả ra vì khách chuyển sang bàn ToTable
                        ]
                    );

                    // Đánh dấu bàn mới là Occupied
                    $this->tableStateService->occupyTables(
                        [$toTableId],
                        null,
                        $staffUserId,
                        [
                            'reservation_id' => $reservationId,
                            'source' => 'staff_move_table',
                            'reason' => 'move_to_table',
                            'counterpart_table_id' => $fromTableId, // Lưu lại Log: Bàn mới bị chiếm vì khách chuyển từ bàn FromTable tới
                        ]
                    );

                    $reservation->updated_by = $staffUserId;
                    $reservation->save(); // Tự động kích hoạt tăng row_version

                    // Vô hiệu hóa Cache toàn cục
                    AvailabilityCacheVersion::bump();

                    // Lưu vết kiểm toán (Audit Trail) phục vụ điều tra nếu có khiếu nại
                    AuditEvent::info('staff.reservation.table_moved', [
                        'reservation_id' => $reservationId,
                        'from_table_id' => $fromTableId,
                        'to_table_id' => $toTableId,
                        'moved_at' => $movedAt->format(DATE_ATOM),
                        'staff_user_id' => $staffUserId,
                        'table_ids_after' => $targetTableIds,
                    ]);

                    return $reservation;
                });
            });

            // --- BƯỚC 5: PHÁT SÓNG SỰ KIỆN (POST-COMMIT EVENT) ---
            // Best Practice: Chỉ Publish event qua WebSocket (Pusher/Soketi) KHI VÀ CHỈ KHI Transaction đã Commit thành công.
            // Nếu gửi event ở Bước 4, rủi ro DB bị Rollback nhưng iPad của bếp lại nhận được tin báo "Khách chuyển bàn".
            // Publish sau commit de board/timeline chi nhan event cua mutation da thanh cong.
            app(OperationalRealtimeService::class)->publishBoardEvent(
                'reservation.table_moved',
                [
                    'reservation_id' => (int) $reservationId,
                    'from_table_id' => (int) $fromTableId,
                    'to_table_id' => (int) $toTableId,
                ],
                ['board', 'timeline'],
            );

            return $reservation;
        } catch (QueryException $e) {
            // Xử lý Lỗi Mức Database: Biến các lỗi Lock Timeout (VD: 1205 Lock wait timeout) thành lỗi Validation gọn gàng cho Frontend.
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    private function assertMoveTableReservationIsCheckedIn(Reservation $reservation): void
    {
        if (! $this->isCheckedInReservation($reservation)) {
            throw ValidationException::withMessages([
                'status' => ['Only checked-in reservations can move tables.'],
            ]);
        }
    }

    private function assertReservationRowVersionMatches(Reservation $reservation, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        $currentRowVersion = (int) ($reservation->row_version ?? 1);

        if ($currentRowVersion !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Data changed (row_version mismatch). Reload and try again.'],
            ]);
        }
    }

    private function isCheckedInReservation(Reservation $reservation): bool
    {
        if ($reservation->checked_in_at !== null) {
            return true;
        }

        $rawStatus = $reservation->status instanceof ReservationStatus
            ? $reservation->status->value
            : (string) $reservation->getRawOriginal('status');

        return ReservationStatus::isCheckedInDbValue($rawStatus);
    }

    private function assertFromTableSafeToRelease(int $tableId, int $tableBranchId, int $currentReservationId): void
    {
        // KỊCH BẢN PHỨC TẠP NHẤT:
        // Khách A và Khách B đang GHÉP CHUNG BÀN (Shared Table).
        // Khách A chuyển đi bàn khác. Bàn cũ KHÔNG ĐƯỢC PHÉP chuyển sang màu xanh (Available) vì vẫn còn Khách B ngồi đó!

        $remainingActiveReservations = DB::table('reservation_tables as rt')
            ->join('reservations as r', 'r.reservation_id', '=', 'rt.reservation_id')
            ->where('rt.table_id', $tableId)
            ->where('rt.reservation_id', '!=', $currentReservationId) // Loại trừ ông khách vừa chuyển đi
            ->whereIn('r.status', ReservationStatus::activeDbValues())
            ->select('r.reservation_id', 'r.status', 'r.checked_in_at', 'r.branch_id', 'r.start_time', 'r.end_time')
            ->lockForUpdate() // Khóa tiếp những booking đang dính líu đến bàn này
            ->get();

        foreach ($remainingActiveReservations as $reservation) {
            $this->reservationBranchScopeService->assertReservationMatchesTableBranch(
                $reservation->branch_id ?? null,
                $tableBranchId,
                'Reservation branch does not match the source table branch being released.',
                'from_table_id',
            );
        }

        // TableReleaseGuard phân tích xem còn ai đang ngồi không
        $blockingReservationIds = TableReleaseGuard::blockingReservationIds($remainingActiveReservations, now('UTC'));
        if ($blockingReservationIds !== []) {
            throw ValidationException::withMessages([
                'from_table_id' => [
                    'Original table still has another active service context: '
                    .implode(',', $blockingReservationIds)
                    .'. Resolve that reservation before retrying the move.',
                ],
            ]);
        }

        // Tương tự, nếu khách đã rời đi nhưng Order gọi món của bàn đó VẪN CHƯA THANH TOÁN (Active)
        // -> Không được phép xé lẻ/nhả bàn cũ ra, phải chuyển bill hoặc thanh toán xong mới được đi.
        $activeOrderExists = DB::table('reservation_orders as ro')
            ->join('reservation_tables as rt', 'rt.reservation_id', '=', 'ro.reservation_id')
            ->where('rt.table_id', $tableId)
            ->where('rt.reservation_id', '!=', $currentReservationId)
            ->where('ro.status', ReservationOrderStatus::Active->value)
            ->lockForUpdate()
            ->exists();

        if ($activeOrderExists) {
            throw ValidationException::withMessages([
                'from_table_id' => ['Original table still has another live order context and cannot be released.'],
            ]);
        }
    }

    private function resolveOperationalBranchId(mixed $reservationBranchId, ?RestaurantTable $fromTable): ?int
    {
        $resolvedReservationBranchId = (int) ($reservationBranchId ?? 0);
        if ($resolvedReservationBranchId > 0) {
            return $resolvedReservationBranchId;
        }

        $fromTableBranchId = (int) ($fromTable?->branch_id ?? 0);

        return $fromTableBranchId > 0 ? $fromTableBranchId : null;
    }

    private function assertOperationalBranchAccessible(?int $branchId, ?int $staffUserId): void
    {
        if ($branchId === null || $branchId <= 0) {
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
