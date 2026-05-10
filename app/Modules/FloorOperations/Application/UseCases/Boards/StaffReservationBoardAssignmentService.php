<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\UseCases\Boards;

use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\FloorOperations\Application\Queries\StaffTableBoardService;
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
 * Gan ban cho reservation tren board staff, theo goi y cu the hoac theo best-fit candidate.
 * Nghiệp vụ: Chịu trách nhiệm "chốt" vị trí ngồi cho khách đặt trước.
 * Hỗ trợ 2 luồng: Lễ tân tự chọn bàn gợi ý (Suggested) HOẶC Hệ thống tự auto-xếp bàn tối ưu nhất (Best-Fit).
 */
class StaffReservationBoardAssignmentService
{
    private readonly ReservationBranchScopeService $reservationBranchScopeService;

    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly RestaurantTableStateService $tableStateService,
        private readonly TableTimeConflictService $tableTimeConflictService,
        private readonly StaffTableBoardService $boardService,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
    ) {
        $this->reservationBranchScopeService = $reservationBranchScopeService ?? app(ReservationBranchScopeService::class);
    }

    /**
     * @return array{reservation:Reservation,assignment:array<string,mixed>}
     */
    public function assignSuggestedTable(
        int $reservationId,
        int $tableId,
        ?int $staffUserId = null,
        ?int $expectedRowVersion = null,
        ?string $zone = null,
        ?\DateTimeInterface $boardFrom = null,
        ?\DateTimeInterface $boardTo = null,
        bool $includeSlotOnlyCandidates = true,
    ): array {
        // Luong nay uu tien dung suggestion tu board de tranh gan ban ngoai ngu canh hien tai.

        // --- BƯỚC 1: XÁC THỰC NGƯỜI DÙNG & ĐẦU VÀO ---
        // Pha 1: validate actor va table target truoc khi check suggestion set.
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        if ($tableId <= 0) {
            throw ValidationException::withMessages([
                'table_id' => ['table_id must be a positive integer.'],
            ]);
        }

        $reservation = Reservation::query()->find($reservationId);
        if (! $reservation) {
            throw new ModelNotFoundException('Reservation not found');
        }

        $assignedTableIds = DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        // --- BƯỚC 2: TỐI ƯU HÓA LŨY ĐẲNG (IDEMPOTENT FAST-PATH) ---
        // Fast path: reservation da gan dung table nay roi thi tra meta assignment, tranh mutate vo ich.
        // Best Practice: Nếu Lễ tân bấm đúp chuột 2 lần vào nút "Xếp bàn" (Hoặc rớt mạng retry),
        // hệ thống phát hiện bàn đã được xếp đúng y như vậy rồi thì bỏ qua không chọc vào DB (Idempotency).
        if ($assignedTableIds !== []) {
            if ($assignedTableIds === [$tableId]
                && ($expectedRowVersion === null || (int) ($reservation->row_version ?? 1) === $expectedRowVersion)
                && ($reservation->status?->value ?? (string) $reservation->status) === ReservationStatus::Confirmed->value
                && $reservation->checked_in_at === null) {
                $reservation->load(['tables', 'user']);

                return [
                    'reservation' => $reservation,
                    'assignment' => $this->buildAssignmentMeta(
                        'suggested_table',
                        $this->fallbackCandidateFromTableId($tableId),
                        true, // Báo cho UI biết đây là kết quả Replay (làm lại) chứ không phải Mutate mới
                    ),
                ];
            }

            // Nếu khách đã được xếp 1 bàn KHÁC, hệ thống vẫn cho phép chạy tiếp xuống commitAssignment,
            // (Tuy nhiên lát nữa hàm commitAssignment sẽ văng lỗi vì luật là "Chỉ được xếp khi chưa có bàn nào")
            $assignmentResult = $this->commitAssignment(
                reservationId: $reservationId,
                tableId: $tableId,
                staffUserId: $staffUserId,
                expectedRowVersion: $expectedRowVersion,
            );

            return [
                'reservation' => $assignmentResult['reservation'],
                'assignment' => $this->buildAssignmentMeta('suggested_table', $this->fallbackCandidateFromTableId($tableId), false),
            ];
        }

        // --- BƯỚC 3: KIỂM SOÁT ĐỘ TRỄ GIAO DIỆN (STALE UI PROTECTION) ---
        // Candidate map duoc chup theo board context hien tai de buoc assignment khong lech ngu canh UI.
        // Tái tạo lại bức tranh toàn cảnh tại chính giây phút Lễ tân bấm nút, xem Bàn này CÒN là Candidate hợp lệ không.
        $candidateMap = collect($this->resolveCandidateTables(
            reservation: $reservation,
            zone: $zone,
            boardFrom: $boardFrom,
            boardTo: $boardTo,
            includeSlotOnlyCandidates: $includeSlotOnlyCandidates,
        ))->keyBy(
            static fn (array $candidate): int => (int) ($candidate['table_id'] ?? 0)
        );

        // Neu table khong nam trong suggestion set hien tai thi bat UI refresh lai board truoc.
        // Nghiệp vụ: Lễ tân mở iPad để đó đi vệ sinh 5 phút mới quay lại bấm "Xếp bàn 5".
        // Nhưng trong 5 phút đó khách khác đã lấy Bàn 5 rồi (Bàn 5 rớt khỏi danh sách CandidateMap).
        // Bắt buộc Lễ tân phải Tải lại sơ đồ (Refresh)!
        if (! $candidateMap->has($tableId)) {
            throw ValidationException::withMessages([
                'table_id' => ['Target table is not in the current board suggestion set for this reservation. Refresh the board and try again.'],
            ]);
        }

        // --- BƯỚC 4: THỰC THI GHI DỮ LIỆU ---
        $candidate = (array) $candidateMap->get($tableId);
        $assignmentResult = $this->commitAssignment(
            reservationId: $reservationId,
            tableId: $tableId,
            staffUserId: $staffUserId,
            expectedRowVersion: $expectedRowVersion,
        );

        return [
            'reservation' => $assignmentResult['reservation'],
            'assignment' => $this->buildAssignmentMeta('suggested_table', $candidate, false),
        ];
    }

    /**
     * @return array{reservation:Reservation,assignment:array<string,mixed>}
     */
    public function assignBestFit(
        int $reservationId,
        ?int $staffUserId = null,
        ?int $expectedRowVersion = null,
        ?string $zone = null,
        ?\DateTimeInterface $boardFrom = null,
        ?\DateTimeInterface $boardTo = null,
        bool $includeSlotOnlyCandidates = true,
    ): array {
        // --- BƯỚC 1: XẾP BÀN TỰ ĐỘNG (AUTO BEST-FIT ASSIGNMENT) ---
        // Best-fit la luong de service tu chon candidate dung top ranking tu board context.
        // Dành cho trường hợp Khách đặt qua Web/App, hệ thống tự động gắp Bàn trống tốt nhất để nhét khách vào.
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        $reservation = Reservation::query()->find($reservationId);
        if (! $reservation) {
            throw new ModelNotFoundException('Reservation not found');
        }

        // Lấy danh sách Candidate và sắp xếp theo điểm số (Score - Xem hàm sortCandidateTables)
        $candidates = $this->sortCandidateTables($this->resolveCandidateTables(
            reservation: $reservation,
            zone: $zone,
            boardFrom: $boardFrom,
            boardTo: $boardTo,
            includeSlotOnlyCandidates: $includeSlotOnlyCandidates,
        ));

        if ($candidates === []) {
            throw ValidationException::withMessages([
                'reservation_id' => ['No board candidate tables are currently available for this reservation.'],
            ]);
        }

        // --- BƯỚC 2: VÒNG LẶP THỬ NGHIỆM (FALLBACK LOOP) ---
        // Best-fit flow cho system tu thu candidate tu tren xuong duoi cho den khi co table commit duoc.
        // Best Practice: Nếu Bàn Tốt Nhất (Top 1) vừa bị ai đó hớt tay trên,
        // catch lỗi Validation, nuốt lỗi đi (continue) và thử tiếp Bàn Tốt Thứ 2 (Top 2). Giúp tăng tỷ lệ Xếp bàn thành công!
        foreach ($candidates as $candidate) {
            // Validation "table_id" o tung candidate chi co nghia la thu table tiep theo; cac loi khac thi fail that su.
            $tableId = (int) ($candidate['table_id'] ?? 0);
            if ($tableId <= 0) {
                continue;
            }

            try {
                $assignmentResult = $this->commitAssignment(
                    reservationId: $reservationId,
                    tableId: $tableId,
                    staffUserId: $staffUserId,
                    expectedRowVersion: $expectedRowVersion,
                );

                return [
                    'reservation' => $assignmentResult['reservation'],
                    'assignment' => $this->buildAssignmentMeta('best_fit', (array) $candidate, false),
                ];
            } catch (ValidationException $e) {
                $errors = $e->errors();
                // Chỉ bỏ qua và thử bàn tiếp theo nếu nguyên nhân lỗi là do BÀN (table_id bị kẹt/bị chiếm).
                if (array_key_exists('table_id', $errors)) {
                    continue;
                }

                // Nếu lỗi do nguyên nhân khác (VD: Row Version sai, Trạng thái Booking bị hủy), ném lỗi thẳng ra ngoài.
                throw $e;
            }
        }

        throw ValidationException::withMessages([
            'reservation_id' => ['No board candidate tables are currently available for this reservation.'],
        ]);
    }

    /**
     * @return array{reservation:Reservation,mutated:bool}
     */
    private function commitAssignment(int $reservationId, int $tableId, ?int $staffUserId, ?int $expectedRowVersion): array
    {
        // --- BƯỚC 1: KHÓA TÀI NGUYÊN (RESOURCE LOCKING) ---
        // Day la diem ghi DB that su: lock reservation/table, verify conflict, cap nhat mapping va audit.
        // Pha 2: diem write thuc su, lock reservation va target table truoc khi xac minh va gan mapping.
        // Khóa Kép: Khóa cả Đơn đặt bàn + Bàn định xếp vào (Redis Mutex) để chống 2 Lễ tân cùng tranh giành.
        $lockKeys = [
            config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation').':'.$reservationId,
            config('booking.reservation_lock_prefix', 'booking:lock:table').':'.$tableId,
        ];

        try {
            $result = $this->locks->withLockKeys($lockKeys, function () use ($reservationId, $tableId, $staffUserId, $expectedRowVersion) {
                return DB::transaction(function () use ($reservationId, $tableId, $staffUserId, $expectedRowVersion) {
                    /** @var Reservation|null $reservation */
                    // Pessimistic Lock ở Database
                    $reservation = Reservation::query()
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->first();

                    if ($reservation === null) {
                        throw new ModelNotFoundException('Reservation not found');
                    }

                    // Optimistic Lock (Bảo vệ giao diện cũ)
                    if ($expectedRowVersion !== null && (int) ($reservation->row_version ?? 1) !== $expectedRowVersion) {
                        throw ValidationException::withMessages([
                            'row_version' => ['Data changed (row_version mismatch). Reload and try again.'],
                        ]);
                    }

                    // --- BƯỚC 2: RÀNG BUỘC NGHIỆP VỤ BẢO VỆ (BUSINESS GUARDS) ---
                    // Phải là booking đã xác nhận (Confirmed)
                    if (($reservation->status?->value ?? (string) $reservation->status) !== ReservationStatus::Confirmed->value) {
                        throw ValidationException::withMessages([
                            'status' => ['Only Confirmed reservations can be assigned from the board.'],
                        ]);
                    }

                    // Chưa đến nhà hàng (Checked-in). Nếu đang ngồi trong quán rồi thì phải dùng tính năng "Chuyển bàn - Move Table"
                    if ($reservation->checked_in_at !== null) {
                        throw ValidationException::withMessages([
                            'status' => ['Checked-in reservations cannot use board assignment. Use move-table flow instead.'],
                        ]);
                    }

                    // Reservation board assignment chi hop le khi reservation con Confirmed va chua co table nao.
                    $assignedTableIds = DB::table('reservation_tables')
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->orderBy('table_id')
                        ->pluck('table_id')
                        ->map(static fn ($value): int => (int) $value)
                        ->all();

                    // Đã có bàn rồi thì không được phép gán đè. Muốn đổi phải dùng Move-table.
                    if ($assignedTableIds !== []) {
                        if ($assignedTableIds === [$tableId]) {
                            return [
                                'reservation' => $reservation->load(['tables', 'user']),
                                'mutated' => false,
                            ];
                        }

                        throw ValidationException::withMessages([
                            'reservation_id' => ['Reservation already has assigned tables. Use reschedule or move-table flow for reassignment.'],
                        ]);
                    }

                    /** @var RestaurantTable|null $table */
                    $table = RestaurantTable::query()
                        ->where('table_id', $tableId)
                        ->notDeleted()
                        ->with('template')
                        ->lockForUpdate()
                        ->first();

                    if ($table === null) {
                        throw ValidationException::withMessages([
                            'table_id' => ['Target table not found.'],
                        ]);
                    }

                    // Không được phép ấn định bàn xuyên chi nhánh (Gian lận vận hành)
                    $this->reservationBranchScopeService->syncReservationBranchOrAssert(
                        $reservation,
                        [$table->branch_id],
                        $staffUserId,
                        'Assigned tables must belong to a single branch.',
                        'Reservation branch does not match the target table branch.',
                        'table_id',
                    );

                    // --- BƯỚC 3: KIỂM TOÁN TÌNH TRẠNG BÀN (TABLE CAPABILITY) ---
                    // Ban dich phai allocatable va du ghe ngoi, neu khong board suggestion da stale.
                    $tableStatus = (string) ($table->status?->value ?? $table->status);
                    if (! $this->tableStateService->isAllocatableForBooking($tableStatus)) {
                        throw ValidationException::withMessages([
                            'table_id' => ['Target table is not currently available for board assignment.'],
                        ]);
                    }

                    // Bàn phải đủ chỗ ngồi cho khách
                    $seats = (int) ($table->template->seats ?? 0);
                    if ($seats < (int) $reservation->guest_count) {
                        throw ValidationException::withMessages([
                            'table_id' => ['Target table does not have enough seats for this reservation.'],
                        ]);
                    }

                    $start = $reservation->start_time->copy()->utc();
                    $end = $reservation->end_time->copy()->utc();

                    // Kiểm tra kẹt lịch kép (Overlap) tại ngay thời điểm Insert cuối cùng vào DB
                    $reservationConflicts = $this->tableTimeConflictService->findReservationConflictTableIds(
                        tableIds: [$tableId],
                        start: $start,
                        end: $end,
                        ignoreReservationId: $reservationId,
                        lock: true,
                    );
                    if ($reservationConflicts !== []) {
                        throw ValidationException::withMessages([
                            'table_id' => ['Target table already has an overlapping reservation.'],
                        ]);
                    }

                    $holdConflicts = $this->tableTimeConflictService->findHoldConflictTableIds(
                        tableIds: [$tableId],
                        start: $start,
                        end: $end,
                        lock: true,
                        ignoreConfirmedReservationId: $reservationId,
                    );
                    if ($holdConflicts !== []) {
                        throw ValidationException::withMessages([
                            'table_id' => ['Target table already has an overlapping active hold.'],
                        ]);
                    }

                    // --- BƯỚC 4: LƯU VÀO DB VÀ XUẤT BÁO CÁO KẾT QUẢ ---
                    // Pha 3: ghi mapping, bump version va audit sau khi target table qua het gate.
                    DB::table('reservation_tables')->insert([
                        'reservation_id' => $reservationId,
                        'table_id' => $tableId,
                    ]);

                    // Bump Row Version của Đơn đặt bàn để báo cho Client rớt nhịp biết
                    $reservation->updated_by = $staffUserId;
                    $reservation->save();

                    // Xoá Cache toàn hệ thống
                    AvailabilityCacheVersion::bump();

                    // Lưu vết Audit Trail (Lịch sử thao tác)
                    AuditEvent::info('staff.reservation.board_assigned', [
                        'reservation_id' => (int) $reservationId,
                        'table_id' => (int) $tableId,
                        'staff_user_id' => $staffUserId,
                        'source' => 'staff_table_board',
                    ]);

                    return [
                        'reservation' => $reservation->load(['tables', 'user']),
                        'mutated' => true,
                    ];
                });
            });

            // --- BƯỚC 5: PHÁT SÓNG REALTIME ---
            if (($result['mutated'] ?? false) === true) {
                // Realtime chi ban sau commit va chi khi co mutate thuc su.
                // Best Practice: Chỉ Push qua WebSocket tới Frontend Sơ đồ bàn (Board) nếu thực sự có thay đổi data dưới DB.
                // Chống Spam WebSocket Traffic!
                app(OperationalRealtimeService::class)->publishBoardEvent(
                    'reservation.board_assignment_committed',
                    [
                        'reservation_id' => (int) $reservationId,
                        'table_id' => (int) $tableId,
                    ],
                    ['board', 'timeline'],
                );
            }

            return $result;
        } catch (QueryException $e) {
            // Mapping DB Lock Exception (ví dụ: Deadlock 1213 hoặc Lock Wait 1205) thành thông báo thân thiện cho User
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    /**
     * @param  list<array<string,mixed>>  $candidates
     * @return list<array<string,mixed>>
     */
    private function sortCandidateTables(array $candidates): array
    {
        // Thuật toán cốt lõi của tính năng Auto Best-Fit
        usort($candidates, static function (array $left, array $right): int {
            $leftRank = (int) data_get($left, 'rank', PHP_INT_MAX);
            $rightRank = (int) data_get($right, 'rank', PHP_INT_MAX);

            // Ưu tiên Sức chứa (Capacity): Exact Fit (Khớp hoàn hảo) > Close Fit (Rộng chút) > Slot Only > Rộng quá mức
            $leftFit = match ((string) data_get($left, 'fit.status', '')) {
                'exact_fit' => 0,
                'close_fit' => 1,
                'slot_only_fit' => 2,
                default => 3,
            };
            $rightFit = match ((string) data_get($right, 'fit.status', '')) {
                'exact_fit' => 0,
                'close_fit' => 1,
                'slot_only_fit' => 2,
                default => 3,
            };

            // Sắp xếp theo Score gốc (Do TableBoardService cung cấp)
            $leftScore = is_numeric(data_get($left, 'score')) ? (float) data_get($left, 'score') : -INF;
            $rightScore = is_numeric(data_get($right, 'score')) ? (float) data_get($right, 'score') : -INF;

            // Tie-breaker (Phân định thắng thua nếu mọi chỉ số bằng nhau):
            // Lấy ID nhỏ xếp trước, hoặc Tên mã bàn xếp trước.
            return [
                $leftRank,
                $leftFit,
                -$leftScore, // Trừ (-) để số cao lên đầu (Descending)
                -1 * (int) ($left['table_id'] ?? 0),
                (string) ($left['table_code'] ?? ''),
            ] <=> [
                $rightRank,
                $rightFit,
                -$rightScore,
                -1 * (int) ($right['table_id'] ?? 0),
                (string) ($right['table_code'] ?? ''),
            ];
        });

        return array_values($candidates);
    }

    private function buildAssignmentMeta(string $mode, array $candidate, bool $idempotentReplay): array
    {
        // Wrapper metadata để trả về cho Frontend biết Bàn này được gán vì lý do gì
        return [
            'mode' => $mode,
            'idempotent_replay' => $idempotentReplay,
            'assigned_table' => [
                'table_id' => (int) ($candidate['table_id'] ?? 0),
                'table_code' => (string) ($candidate['table_code'] ?? ''),
                'zone' => $candidate['zone'] ?? null,
                'board_state' => $candidate['board_state'] ?? null,
            ],
            'rank' => $candidate['rank'] ?? null,
            'fit' => $candidate['fit'] ?? null,
            'score' => $candidate['score'] ?? null,
            'reason_codes' => $candidate['reason_codes'] ?? [],
            'policy_flags' => $candidate['policy_flags'] ?? [],
            'assignment_window' => $candidate['assignment_window'] ?? null,
            'assignment_request_context' => $candidate['assignment_request_context'] ?? null,
            'source' => 'staff_table_board',
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function resolveCandidateTables(
        Reservation $reservation,
        ?string $zone,
        ?\DateTimeInterface $boardFrom,
        ?\DateTimeInterface $boardTo,
        bool $includeSlotOnlyCandidates,
    ): array {
        return $this->boardService->getCandidateTablesForReservation(
            reservation: $reservation,
            zone: $zone,
            includeSlotOnly: $includeSlotOnlyCandidates,
            boardFrom: $boardFrom,
            boardTo: $boardTo,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function fallbackCandidateFromTableId(int $tableId): array
    {
        $table = RestaurantTable::query()
            ->where('table_id', $tableId)
            ->notDeleted()
            ->first();

        return [
            'table_id' => $tableId,
            'table_code' => (string) ($table?->table_code ?? ''),
            'zone' => $table?->zone,
            'board_state' => $table !== null ? strtolower((string) ($table->status?->value ?? $table->status)) : null,
        ];
    }
}
