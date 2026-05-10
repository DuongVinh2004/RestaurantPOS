<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\UseCases\CheckIn;

use App\Enums\ReservationStatus;
use App\Enums\TableHoldStatus;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\FloorOperations\Application\Queries\StaffCheckInReadinessService;
use App\Modules\FloorOperations\Domain\Guards\StaffReservationOperationGuard;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\Support\AuditEvent;
use App\Support\Auth\StaffActorGuard;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Xử lý check-in cho reservation đã có bàn:
 * khóa tài nguyên, xác nhận sẵn sàng, rồi mở service tại bàn.
 * Nghiệp vụ: Chuyển đổi trạng thái khách từ "Đã đặt chỗ" sang "Đang sử dụng dịch vụ tại quán".
 * Mở khóa để khách có thể bắt đầu gọi món (Active Orders).
 */
class StaffCheckInService
{
    private readonly StaffCheckInReadinessService $checkInReadinessService;

    private readonly ?StaffBranchContextService $staffBranchContextService;

    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly NotificationOutboxService $notificationOutboxService,
        private readonly RestaurantTableStateService $tableStateService,
        mixed $checkInReadinessServiceOrConflicts = null,
        ?RuntimeSettingService $runtimeSettings = null,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
        ?StaffBranchContextService $staffBranchContextService = null,
    ) {
        $this->staffBranchContextService = $staffBranchContextService;

        if ($checkInReadinessServiceOrConflicts instanceof StaffCheckInReadinessService) {
            $this->checkInReadinessService = $checkInReadinessServiceOrConflicts;

            return;
        }

        $tableTimeConflictService = $checkInReadinessServiceOrConflicts instanceof TableTimeConflictService
            ? $checkInReadinessServiceOrConflicts
            : app(TableTimeConflictService::class);

        $this->checkInReadinessService = new StaffCheckInReadinessService(
            $this->tableStateService,
            $tableTimeConflictService,
            $runtimeSettings ?? app(RuntimeSettingService::class),
            $reservationBranchScopeService ?? app(ReservationBranchScopeService::class),
        );
    }

    public function checkIn(int $reservationId, ?array $tableIds, \DateTimeInterface $checkedInAt, ?int $staffUserId = null, array $ignoredHoldIds = [], bool $skipLocking = false, ?int $expectedRowVersion = null): Reservation
    {
        // Chuẩn hóa scope check-in để lock đúng reservation, đúng bàn, đúng hold.
        // Pha 1: chuan hoa actor, table ids va trusted hold ids truoc khi vao lock/transaction.

        // --- BƯỚC 1: XÁC THỰC NGƯỜI DÙNG & CHUẨN HÓA ĐẦU VÀO ---
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        // Loại bỏ ID trùng lặp, sắp xếp tăng dần để chuẩn bị cho việc Lock CSDL an toàn (Tránh Deadlock)
        $requestedTableIds = $tableIds === null ? [] : array_values(array_unique(array_map('intval', $tableIds)));
        sort($requestedTableIds);

        // Lọc sạch ID rác
        $ignoredHoldIds = array_values(array_unique(array_filter(array_map('strval', $ignoredHoldIds), static fn (string $value) => $value !== '')));

        // Closure bọc toàn bộ logic để tái sử dụng (Tùy chọn chạy qua Redis Lock hoặc chạy thẳng)
        $runner = function () use ($reservationId, $requestedTableIds, $checkedInAt, $staffUserId, $ignoredHoldIds, $expectedRowVersion) {
            return DB::transaction(function () use ($reservationId, $requestedTableIds, $checkedInAt, $staffUserId, $ignoredHoldIds, $expectedRowVersion) {

                // Khóa reservation và các bàn liên quan để tránh check-in đúp hoặc đổi bàn giữa chừng.
                /** @var Reservation $reservation */
                // Pha 2: lock reservation, mapping reservation_tables va table rows de chot mot snapshot write duy nhat.
                // --- BƯỚC 2: KHÓA TÀI NGUYÊN BẰNG PESSIMISTIC LOCK (MỨC CSDL) ---
                $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->first();
                if (! $reservation) {
                    throw new ModelNotFoundException('Reservation not found');
                }

                // Lấy ra danh sách các bàn ĐÃ ĐƯỢC GÁN cho khách này
                $assignedTableIds = DB::table('reservation_tables')
                    ->where('reservation_id', $reservationId)
                    ->lockForUpdate() // Khóa luôn bảng mapping (Tránh ông nhân viên khác dùng hàm gán bàn đè vào lúc này)
                    ->orderBy('reservation_table_id')
                    ->pluck('table_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $tables = RestaurantTable::query()
                    ->whereIn('table_id', $assignedTableIds)
                    ->notDeleted()
                    ->lockForUpdate() // Khóa luôn thông tin từng cái Bàn một
                    ->get();

                // Branch access duoc chan ngay tai lop write de staff khong check-in lech chi nhanh.
                // Nghiệp vụ: Chống IDOR (Sửa đổi tham số API). Không cho phép nhân viên Quận 1 check-in hộ khách ở Quận 3.
                $this->assertOperationalBranchAccessible(
                    $this->resolveOperationalBranchId($reservation, $tables),
                    $staffUserId,
                );

                // --- BƯỚC 3: KIỂM TRA LŨY ĐẲNG (IDEMPOTENCY GUARD) ---
                if (StaffReservationOperationGuard::isCheckedInReservation($reservation)) {
                    // Idempotent noop: reservation da checked-in roi thi tra ve snapshot hien tai.
                    // Nghiệp vụ: Nếu Lễ tân mạng lag, bấm nút Check-in 2 lần liên tục. Request đầu thành công,
                    // Request sau sẽ lọt vào đây và tự động trả về "Thành công" luôn mà không gây lỗi đỏ loét (500 Server Error)
                    // hay làm hỏng dữ liệu báo cáo (No Operation - NOOP).
                    return [
                        'reservation' => $reservation,
                        'mutated' => false, // Báo cho logic bên dưới biết là KHÔNG CÓ THAY ĐỔI
                        'table_ids' => [],
                    ];
                }

                // --- BƯỚC 4: RÀNG BUỘC NGHIỆP VỤ BẢO VỆ CHÉO (BUSINESS GUARDS) ---
                // Gate nghiep vu cuoi truoc mutate: status hop le va row_version chua stale.
                StaffReservationOperationGuard::assertCheckInAllowed($reservation, $expectedRowVersion);
                $checked = Carbon::instance(\DateTimeImmutable::createFromInterface($checkedInAt));

                // Bắt buộc phải được gán bàn trước thì mới được Check-in.
                if (count($assignedTableIds) === 0) {
                    throw ValidationException::withMessages(['reservation_id' => 'Reservation has no assigned tables to check in.']);
                }

                // Check-in khong duoc doi ban; neu can doi ban phai di qua move-table flow.
                // Nghiệp vụ: Nếu API truyền lên table_id KHÁC với table_id đã gán trong DB, hệ thống sẽ BÁO LỖI.
                // Điều này ép nhân viên phải làm đúng quy trình: Đổi bàn trước (hàm MoveTable) rồi mới Check-in.
                // Tránh tình trạng "Tiền trảm hậu tấu" làm rối tung Sơ đồ bàn.
                if ($requestedTableIds !== []) {
                    $sortedAssignedTableIds = $assignedTableIds;
                    sort($sortedAssignedTableIds);
                    if ($requestedTableIds !== $sortedAssignedTableIds) {
                        throw ValidationException::withMessages(['table_ids' => 'Check-in cannot change assigned tables. Use move-table flow for reassignment.']);
                    }
                }

                $tableIds = $assignedTableIds;

                // Mẹo nhỏ tinh tế: Nếu bàn này ĐANG BỊ KHÓA (Hold) nhưng mã khóa đó LÀ CỦA CHÍNH KHÁCH NÀY đặt từ trước,
                // thì nhét vào danh sách Bỏ qua (Ignored) để không bị tự báo lỗi "Xung đột với chính mình".
                $ignoredHoldIdsForReservation = array_values(array_unique(array_merge(
                    $ignoredHoldIds,
                    $this->resolveConfirmedHoldIdsForReservation($reservation, $tableIds, true),
                )));

                // Chỉ cho check-in khi reservation, hold và trạng thái bàn đều sẵn sàng.
                // Pha 3: readiness service gom du check status, table state, hold va reservation overlap.
                // Gọi tới "Người Gác Cổng Khó Tính" để xét duyệt một lần cuối mọi vấn đề (Trùng lịch, quá giờ...)
                $this->checkInReadinessService->assertReadyForWrite(
                    $reservation,
                    $checked,
                    $tableIds,
                    $tables,
                    ignoredHoldIds: $ignoredHoldIdsForReservation,
                    lock: true,
                    updatedBy: $staffUserId,
                );

                // --- BƯỚC 5: GHI NHẬN TRẠNG THÁI VÀ BÁO CÁO (MUTATION & EVENT DISPATCHING) ---
                // Pha 4: mutate reservation thanh checked-in va ghi actor/thoi diem check-in.
                $reservation->status = ReservationStatus::checkedIn();
                $reservation->checked_in_at = $checkedInAt;
                $reservation->updated_by = $staffUserId;
                $reservation->save(); // Save() sẽ tự động tăng row_version (Version bump)

                // Check-in thành công thì đẩy bàn sang Occupied và phát tín hiệu cho vận hành.
                // Pha 5: dong bo board state, outbox va audit sau khi write reservation thanh cong.
                // Nghiệp vụ: Cập nhật Bàn Vật Lý thành Đang Có Người Ngồi (Occupied).
                $this->tableStateService->occupyTables(
                    $tableIds,
                    Carbon::instance(\DateTimeImmutable::createFromInterface($checkedInAt))->utc(),
                    $staffUserId,
                    [
                        'reservation_id' => (int) $reservationId,
                        'source' => 'staff_check_in',
                        'reason' => 'reservation_check_in',
                    ]
                );

                // Best Practice (Outbox Pattern & EDA):
                // Sau khi check-in, nhét 1 tin nhắn vào Outbox. Một Background Worker sẽ chạy ngầm để đọc Outbox
                // và gửi tin nhắn Zalo/SMS cho Khách Hàng: "Chào mừng quý khách đã đến nhà hàng..."
                // Mà KHÔNG làm chậm quá trình bấm nút Check-in của Lễ tân.
                $reservation->loadMissing('user', 'tables', 'payments');
                $this->notificationOutboxService->enqueueReservationCheckedIn($reservation);

                // Lưu vết kiểm toán
                AuditEvent::info('staff.reservation.checked_in', [
                    'reservation_id' => (int) $reservationId,
                    'table_ids' => $tableIds,
                    'checked_in_at' => $checkedInAt->format(DATE_ATOM),
                    'staff_user_id' => $staffUserId,
                ]);

                return [
                    'reservation' => $reservation,
                    'mutated' => true, // Đánh dấu là CÓ SỰ THAY ĐỔI
                    'table_ids' => $tableIds,
                ];
            });
        };

        try {
            // --- BƯỚC 6: XỬ LÝ KHÓA PHÂN TÁN (DISTRIBUTED LOCK EXECUTION) ---
            if ($skipLocking) {
                // Nếu một Process cấp cao hơn (Parent Job) đã ôm sô việc Lock rồi thì ta có thể bỏ qua để tối ưu tốc độ.
                $result = $runner();
            } else {
                $lockTableIds = $requestedTableIds;
                // Nếu gọi API mà không truyền TableID lên, ta phải chui vào DB đọc xem khách này đang được gán bàn nào
                if ($lockTableIds === []) {
                    $lockTableIds = DB::table('reservation_tables')
                        ->where('reservation_id', $reservationId)
                        ->orderBy('table_id')
                        ->pluck('table_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                }

                // LUẬT SẮP XẾP ID: Chống Deadlock kinh điển! Luôn khóa bàn theo số ID từ nhỏ đến lớn.
                sort($lockTableIds);

                // Ngoai DB lock con co distributed lock theo reservation/table de tranh double-submit giua node/process.
                // Sinh ra chùm chìa khóa cho Redis Mutex
                $lockKeys = array_merge([
                    config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation').':'.$reservationId,
                ], array_map(fn (int $id) => config('booking.reservation_lock_prefix', 'booking:lock:table').':'.$id, $lockTableIds));

                $result = $this->locks->withLockKeys($lockKeys, $runner);
            }

            /** @var Reservation $reservation */
            $reservation = $result['reservation'];

            // --- BƯỚC 7: CẬP NHẬT GIAO DIỆN REAL-TIME ---
            // Chỉ phát realtime khi có thay đổi thực sự, tránh làm nóng board vô ích.
            // Realtime chi ban khi co mutate thuc su de board khong bi nong vi check-in noop.
            if (($result['mutated'] ?? false) === true) {
                app(OperationalRealtimeService::class)->publishBoardEvent(
                    'reservation.checked_in',
                    [
                        'reservation_id' => (int) $reservationId,
                        'table_ids' => array_values(array_map('intval', (array) ($result['table_ids'] ?? []))),
                        'checked_in_at' => Carbon::instance(\DateTimeImmutable::createFromInterface($checkedInAt))->utc()->toIso8601String(),
                    ],
                    ['board', 'timeline'],
                );
            }

            return $reservation;
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    /**
     * @param  array<int,int>  $tableIds
     * @return array<int,string>
     */
    private function resolveConfirmedHoldIdsForReservation(Reservation $reservation, array $tableIds, bool $lock = false): array
    {
        // Hold confirmed cua reservation nay duoc ignore trong readiness, neu khong no se tu xung dot voi chinh no.
        // Giải quyết nghịch lý: Khách đặt bàn sinh ra lệnh KHÓA BÀN (để dành cho khách). Khi khách đến Check-in,
        // chính cái lệnh KHÓA đó lại TỪ CHỐI khách vào bàn. Phải bỏ qua lệnh khóa "của nhà làm" này.
        $tableIds = array_values(array_unique(array_map('intval', $tableIds)));
        sort($tableIds);

        if ($tableIds === []) {
            return [];
        }

        $startUtc = Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->start_time))->utc();
        $endUtc = Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->end_time))->utc();

        $query = DB::table('table_hold_details as thd')
            ->join('table_holds as th', 'th.hold_id', '=', 'thd.hold_id')
            ->whereIn('thd.table_id', $tableIds)
            ->where('th.confirmed_reservation_id', (int) $reservation->reservation_id)
            ->where('th.hold_status', TableHoldStatus::Confirmed->value)
            ->where('th.start_time', '<', $endUtc)
            ->where('th.end_time', '>', $startUtc);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->distinct()
            ->pluck('th.hold_id')
            ->map(static fn ($holdId): string => (string) $holdId)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RestaurantTable>  $tables
     */
    private function resolveOperationalBranchId(Reservation $reservation, Collection $tables): ?int
    {
        $reservationBranchId = (int) ($reservation->branch_id ?? 0);
        if ($reservationBranchId > 0) {
            return $reservationBranchId;
        }

        $tableBranchIds = $tables
            ->pluck('branch_id')
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->filter(static fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->values();

        // Cảnh báo: Bàn bị gán rải rác ở nhiều chi nhánh khác nhau -> Trả về null (Lỗi dữ liệu)
        if ($tableBranchIds->count() !== 1) {
            return null;
        }

        return (int) $tableBranchIds->first();
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
