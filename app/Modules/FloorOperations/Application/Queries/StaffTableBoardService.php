<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\Queries;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\BranchScheduling\Domain\Guards\HoldConflictScope;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\BranchScheduling\Domain\Models\TableHold;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Payments\Application\Queries\StaffReservationDepositOperationalReadService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Dung snapshot table board cho staff:
 * gom ban, reservation, hold, conflict, va goi y thao tac tai cho trong cung mot view model.
 * * Best Practice (BFF - Backend For Frontend):
 * Gom toàn bộ dữ liệu phức tạp từ nhiều bảng (Bàn, Khách đặt, Đơn hàng, Giữ bàn) thành MỘT View Model duy nhất.
 * Giúp Frontend (React) nhẹ gánh, không phải gọi nhiều API và tự ghép nối dữ liệu gây giật lag.
 */
class StaffTableBoardService
{
    public function __construct(
        private readonly TableTimeConflictService $tableTimeConflictService,
        ?RuntimeSettingService $runtimeSettings = null,
        ?StaffCheckInReadinessService $checkInReadinessService = null,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
        ?BranchContextService $branchContextService = null,
    ) {
        $this->runtimeSettings = $runtimeSettings ?? app(RuntimeSettingService::class);
        $this->checkInReadinessService = $checkInReadinessService ?? app(StaffCheckInReadinessService::class);
        $this->reservationBranchScopeService = $reservationBranchScopeService ?? app(ReservationBranchScopeService::class);
        $this->branchContextService = $branchContextService ?? app(BranchContextService::class);
    }

    private readonly RuntimeSettingService $runtimeSettings;

    private readonly StaffCheckInReadinessService $checkInReadinessService;

    private readonly ReservationBranchScopeService $reservationBranchScopeService;

    private readonly BranchContextService $branchContextService;

    /**
     * @return list<array<string,mixed>>
     */
    public function getBoard(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        mixed $branchId = null,
        ?string $zone = null,
        bool $includeHolds = true,
        ?array $accessibleBranchIds = null,
    ): array {
        // Wrapper gon cho UI chi can danh sach data, khong can metadata cua snapshot.
        return $this->buildBoardSnapshot($from, $to, $branchId, $zone, $includeHolds, $accessibleBranchIds)['data'];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildBoardSnapshot(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        mixed $branchId = null,
        ?string $zone = null,
        bool $includeHolds = true,
        ?array $accessibleBranchIds = null,
    ): array {
        // Day la ham tong hop lon: dung khung gio board, load table/hold/reservation, va suy ra action staff co the lam.

        // --- BƯỚC 1: KHỞI TẠO BỐI CẢNH (TIME & THRESHOLDS) ---
        // Pha 1: normalize board window, branch scope va cac threshold UI de moi truy van phia sau dung chung mot moc.
        $fromUtc = Carbon::instance(\DateTimeImmutable::createFromInterface($from))->utc();
        $toUtc = Carbon::instance(\DateTimeImmutable::createFromInterface($to))->utc();
        $resolvedBranchId = $this->resolveBranchId($branchId);
        $accessibleBranchIds = $accessibleBranchIds !== null ? $this->normalizeBranchIds($accessibleBranchIds) : null;
        $zone = $this->normalizeZone($zone);
        $nowUtc = Carbon::now('UTC');

        // Lấy cấu hình độ trễ cho phép (Grace Period) từ Runtime Settings (có thể thay đổi nóng không cần deploy)
        $checkInGraceMinutes = $this->resolveCheckInGraceMinutes();
        $noShowGraceMinutes = $this->resolveNoShowGraceMinutes();
        $dueSoonCutoffUtc = $nowUtc->copy()->addMinutes($checkInGraceMinutes);
        $overdueCutoffUtc = $nowUtc->copy()->subMinutes($noShowGraceMinutes);

        // Bảo mật thông tin (PII): Cấu hình các trường thông tin khách hàng được phép hiển thị trên màn hình Sơ đồ bàn
        $visibleUserFields = array_values(array_filter(array_map('strval', (array) Config::get('booking.staff_table_board_user_fields', ['user_id', 'full_name', 'phone']))));
        $closeFitMaxExtraSeats = max(0, (int) config('booking.staff_table_board_close_fit_max_extra_seats', 2));
        $candidatePreviewLimit = max(1, (int) config('booking.staff_table_board_candidate_preview_limit', 5));

        // --- BƯỚC 2: TẢI DỮ LIỆU HÀNG LOẠT (BULK DATA LOADING) ---
        // Best Practice: Load trước toàn bộ dữ liệu cần thiết thay vì truy vấn trong vòng lặp (Chống lỗi N+1 Query)

        // Lay danh sach table nen cua board theo branch/zone truoc, vi day la tap row goc de map reservation/hold vao.
        $tablesQuery = RestaurantTable::query()
            ->notDeleted();
        $this->applyBranchScope($tablesQuery, $resolvedBranchId, $accessibleBranchIds);
        $tables = $tablesQuery
            ->inZone($zone)
            ->with('template')
            ->orderBy('zone')
            ->orderBy('table_code')
            ->get();

        // Reservation active trong khung gio board duoc load rieng de map theo table va sinh action.
        $reservationsQuery = Reservation::query()
            ->inTimeRange($fromUtc, $toUtc);
        $this->applyBranchScope($reservationsQuery, $resolvedBranchId, $accessibleBranchIds);
        $activeReservations = $reservationsQuery
            ->whereIn('status', ReservationStatus::activeDbValues())
            ->with(['user', 'tables', 'payments']) // Eager Load relations
            ->get();

        // Order active duoc map theo reservation de board co the hien thi muc "dang phuc vu" va thao tac lien quan.
        // Nghiệp vụ: Giúp Lễ tân biết bàn nào khách mới vào ngồi và bàn nào đã bắt đầu gọi món (Active Order)
        $reservationIds = $activeReservations->pluck('reservation_id')->map(static fn ($id): int => (int) $id)->all();
        $activeOrdersByReservationId = $reservationIds === []
            ? collect()
            : ReservationOrder::query()
                ->whereIn('reservation_id', $reservationIds)
                ->where('status', ReservationOrderStatus::Active->value)
                ->orderByDesc('order_id')
                ->get()
                ->groupBy(static fn (ReservationOrder $order): int => (int) $order->reservation_id)
                ->map(static fn (Collection $orders): ?ReservationOrder => $orders->first());

        // --- BƯỚC 3: PHÂN LOẠI KHÁCH HÀNG (ASSIGNED VS UNASSIGNED) ---
        $reservationsByTable = [];
        $assignedReservations = collect();
        $unassignedReservations = collect();
        $assignedTableIdsByReservation = [];

        // Tach reservation da gan ban va reservation chua gan ban de board xu ly 2 loai card khac nhau.
        foreach ($activeReservations as $reservation) {
            if ($reservation->tables->isEmpty()) {
                // Nhóm khách chưa được xếp chỗ (Waiting to be seated)
                $unassignedReservations->push($reservation);

                continue;
            }

            // Nhóm khách đã được xếp chỗ vào bàn cụ thể (Pre-assigned / Seated)
            $assignedReservations->push($reservation);
            $assignedTableIdsByReservation[(int) $reservation->reservation_id] = $reservation->tables
                ->pluck('table_id')
                ->map(static fn ($tableId): int => (int) $tableId)
                ->all();
            foreach ($reservation->tables as $table) {
                $reservationsByTable[(int) $table->table_id][] = $reservation;
            }
        }

        // --- BƯỚC 4: XỬ LÝ BLOCK BÀN (TABLE HOLDS) ---
        $holdsByTable = [];
        // Hold duoc load rieng va chi giu lai nhung hold con "co nghia" tren table hien tai.
        // Nghiệp vụ: Bàn đang bị Khóa do Dọn dẹp, Bảo trì thiết bị, hoặc dành riêng cho Khách VIP.
        if ($includeHolds) {
            $holds = TableHold::query()
                ->where('start_time', '<', $toUtc)
                ->where('end_time', '>', $fromUtc)
                ->where(function ($query) use ($nowUtc) {
                    $query
                        ->where('hold_status', 'Confirmed')
                        ->orWhere(function ($subQuery) use ($nowUtc) {
                            // Lọc bỏ những TableHold nháp (Pending/Holding) đã quá hạn (Expired)
                            $subQuery->whereIn('hold_status', ['Holding', 'Pending'])
                                ->where('expire_at', '>', $nowUtc);
                        });
                })
                ->with('tables')
                ->get();

            foreach ($holds as $hold) {
                foreach ($hold->tables as $table) {
                    // Nếu Hold này được sinh ra bởi chính Reservation đang ngồi tại bàn đó thì không cần hiển thị đè lên nhau
                    if (! $this->holdShouldRemainVisibleOnTable(
                        $hold,
                        (int) $table->table_id,
                        $assignedTableIdsByReservation,
                    )) {
                        continue;
                    }

                    $holdsByTable[(int) $table->table_id][] = $hold;
                }
            }
        }

        // --- BƯỚC 5: THUẬT TOÁN GỢI Ý CHỖ NGỒI (SMART SEATING ENGINE) ---
        $unassignedReservationFlags = [];
        $unassignedReservationDeposits = [];
        $candidateSourceReservations = collect();

        // Reservation chua gan ban duoc tinh flag timing, deposit va candidate set de board goi y.
        foreach ($unassignedReservations as $reservation) {
            $reservationId = (int) $reservation->reservation_id;

            // Tính toán Khách đến trễ, Đến sớm, hay Quá hạn No-show
            $flags = $this->reservationTimingFlags($reservation, $nowUtc, $dueSoonCutoffUtc, $overdueCutoffUtc);
            // Kiểm tra trạng thái Thanh toán cọc (Deposit)
            $deposit = $this->depositRead($reservation);

            $unassignedReservationFlags[$reservationId] = $flags;
            $unassignedReservationDeposits[$reservationId] = $deposit;

            // Nếu chưa quá hạn No-show thì mới tìm bàn gợi ý
            if (($flags['overdue'] ?? false) !== true) {
                $candidateSourceReservations->push($reservation);
            }
        }

        // Chạy Engine tìm bàn tối ưu cho nhóm khách đang chờ
        $candidateTablesByReservation = $candidateSourceReservations->isEmpty()
            ? []
            : $this->getCandidateTablesForReservations(
                reservations: $candidateSourceReservations,
                branchId: $resolvedBranchId,
                zone: $zone,
                includeSlotOnly: true,
                boardFrom: $fromUtc,
                boardTo: $toUtc,
                preloadedTables: $tables, // Truyền tables đã load ở trên vào để tái sử dụng
            );
        $candidateReservationsByTable = [];
        $boardVisibleUnassigned = [];

        // Map nguoc candidate reservation theo table de row tung ban biet dang la ung vien cho booking nao.
        foreach ($unassignedReservations as $reservation) {
            $reservationId = (int) $reservation->reservation_id;
            $flags = $unassignedReservationFlags[$reservationId] ?? [];
            $deposit = $unassignedReservationDeposits[$reservationId] ?? [];
            $candidates = $candidateTablesByReservation[$reservationId] ?? [];

            $boardVisibleUnassigned[] = [
                'reservation' => $reservation,
                'flags' => $flags,
                'deposit' => $deposit,
                'candidate_tables' => $candidates,
            ];

            foreach ($candidates as $candidate) {
                $candidateReservationsByTable[(int) ($candidate['table_id'] ?? 0)][] = [
                    'reservation' => $reservation,
                    'flags' => $flags,
                    'deposit' => $deposit,
                    'candidate' => $candidate,
                ];
            }
        }

        // --- BƯỚC 6: RÁP VIEW MODEL CHO TỪNG BÀN TREN BẢN ĐỒ (MAP ASSEMBLY) ---
        $tableRows = [];
        // Pha 2: dung tung row ban tu table + reservation + hold + order + action suggestion.
        foreach ($tables as $table) {
            $tableId = (int) $table->table_id;
            $realtimeStatus = $table->status?->value ?? (string) $table->status;
            $reservationList = $reservationsByTable[$tableId] ?? [];
            usort($reservationList, fn (Reservation $a, Reservation $b): int => $a->start_time <=> $b->start_time);
            $holdList = $holdsByTable[$tableId] ?? [];
            usort($holdList, fn (TableHold $a, TableHold $b): int => $a->start_time <=> $b->start_time);

            $reservation = $reservationList[0] ?? null;
            $hold = $holdList[0] ?? null;
            $capacitySeats = $table->template?->seats !== null ? (int) $table->template->seats : null;

            // Quyết định Trạng thái màu sắc (UI State) của bàn
            $boardState = 'available';
            if (in_array($realtimeStatus, [RestaurantTableStatus::Blocked->value, RestaurantTableStatus::Maintenance->value], true)) {
                $boardState = strtolower($realtimeStatus);
            } elseif ($realtimeStatus === RestaurantTableStatus::Occupied->value) {
                $boardState = 'occupied_now'; // Đang có khách ngồi thật
            } elseif ($reservationList !== []) {
                $boardState = 'reserved_in_range'; // Trống nhưng đã có người đặt trước trong khung giờ
            } elseif ($holdList !== []) {
                $boardState = 'held_in_range'; // Trống nhưng đang bị quản lý Hold
            }

            // Action duoc suy ra tu state hien tai cua table va reservation gan voi no.
            // Best Practice (Action-Driven UI): Backend tự quyết định Nút bấm nào được hiện và Payload gửi đi là gì
            $checkInAction = $this->buildCheckInAction(
                $reservation,
                $realtimeStatus,
                $holdList,
                $reservationsByTable,
                $holdsByTable,
                $nowUtc
            );
            $moveTableAction = $this->buildMoveTableAction($reservation, $realtimeStatus, $tableId);
            $reservationDeposit = $reservation ? $this->depositRead($reservation) : null;
            $currentFit = ($reservation && $capacitySeats !== null)
                ? $this->fitSummary((int) $reservation->guest_count, $capacitySeats, $closeFitMaxExtraSeats)
                : null;
            $activeOrder = $reservation ? $activeOrdersByReservationId->get((int) $reservation->reservation_id) : null;
            $candidateReservations = array_values(array_slice($candidateReservationsByTable[$tableId] ?? [], 0, $candidatePreviewLimit));

            // Cờ cảnh báo: Nhắc Lễ tân ra thu tiền cọc của khách
            $requiresDepositFollowUp = (bool) data_get($reservationDeposit, 'follow_up.needs_staff_follow_up', false)
                || collect($candidateReservations)->contains(static fn (array $row): bool => (bool) data_get($row, 'deposit.follow_up.needs_staff_follow_up', false));

            $tableRows[] = [
                'table_id' => $tableId,
                'table_code' => $table->table_code,
                'zone' => $table->zone,
                'pos_x' => $table->pos_x, // Tọa độ X để Frontend vẽ sơ đồ 2D
                'pos_y' => $table->pos_y, // Tọa độ Y để Frontend vẽ sơ đồ 2D
                'realtime_status' => $realtimeStatus,
                'board_state' => $boardState,
                'reservations' => array_map(fn (Reservation $row): array => $this->presentAssignedReservation($row, $visibleUserFields), $reservationList),
                'holds' => array_map(fn (TableHold $row): array => $this->presentHold($row), $holdList),
                'reservation' => $reservation ? $this->presentAssignedReservation($reservation, $visibleUserFields) : null,
                'hold' => $hold ? $this->presentHold($hold) : null,
                'capacity' => [
                    'template_id' => $table->template_id !== null ? (int) $table->template_id : null,
                    'seats' => $capacitySeats,
                ],
                'availability' => [
                    'accepts_new_assignment' => $boardState === 'available',
                    'is_operationally_blocked' => in_array($realtimeStatus, [RestaurantTableStatus::Blocked->value, RestaurantTableStatus::Maintenance->value], true),
                    'is_realtime_occupied' => $realtimeStatus === RestaurantTableStatus::Occupied->value,
                    'has_reservation_in_range' => $reservationList !== [],
                    'has_hold_in_range' => $holdList !== [],
                    'requires_deposit_follow_up' => $requiresDepositFollowUp,
                ],
                'operational_hints' => [
                    'assignment_candidate' => $boardState === 'available',
                    'preferred_action' => $this->resolvePreferredAction($boardState === 'available', $checkInAction['available'] ?? false, $moveTableAction['available'] ?? false),
                ],
                'actions' => [
                    'check_in' => $checkInAction,
                    'move_table' => $moveTableAction,
                ],
                'candidate_reservations' => array_map(fn (array $row): array => $this->presentCandidateReservation($row['reservation'], $row['candidate'], $row['flags'], $row['deposit']), $candidateReservations),
                'current_fit' => $currentFit,
                'active_order' => $activeOrder ? $this->presentActiveOrder($activeOrder) : null,
            ];
        }

        // Lọc theo Khu vực (Zone Filter)
        if ($zone !== null) {
            $tableRows = array_values(array_filter(
                $tableRows,
                static fn (array $row): bool => trim((string) ($row['zone'] ?? '')) === $zone,
            ));
        }

        // Sắp xếp mặc định của Board
        $tableRows = array_values(collect($tableRows)
            ->keyBy(static fn (array $row): int => (int) ($row['table_id'] ?? 0))
            ->sortBy([
                ['zone', 'asc'],
                ['table_code', 'asc'],
            ])
            ->values()
            ->all());

        // --- BƯỚC 7: TỔNG HỢP SUMMARY & KPI CHO DASHBOARD ---
        $zoneRows = [];
        // Zone summary de UI co so lieu tong hop khong can tu dem lai tung row.
        foreach (collect($tableRows)->groupBy(static fn (array $row): string => (string) ($row['zone'] ?? 'Unzoned')) as $zoneName => $rows) {
            /** @var Collection<int, array<string, mixed>> $zoneTableRows */
            $zoneTableRows = $rows instanceof Collection ? $rows : collect($rows);

            $zoneRows[] = [
                'zone' => $zoneName,
                'summary' => [
                    'table_count' => $zoneTableRows->count(),
                    'available_count' => $zoneTableRows->where('board_state', 'available')->count(),
                    'occupied_now_count' => $zoneTableRows->where('board_state', 'occupied_now')->count(),
                    'reserved_in_range_count' => $zoneTableRows->where('board_state', 'reserved_in_range')->count(),
                    'held_in_range_count' => $zoneTableRows->where('board_state', 'held_in_range')->count(),
                ],
            ];
        }
        usort($zoneRows, static fn (array $left, array $right): int => strcmp((string) $left['zone'], (string) $right['zone']));

        // Unassigned reservation rows mang them request context de UI goi assignment flow dung board window hien tai.
        $unassignedRows = array_map(function (array $row) use ($fromUtc, $toUtc, $resolvedBranchId, $zone): array {
            /** @var Reservation $reservation */
            $reservation = $row['reservation'];
            $deposit = $row['deposit'];
            $flags = $row['flags'];
            $candidateTables = $row['candidate_tables'];
            $assignmentRequestContext = $this->buildAssignmentRequestContext($fromUtc, $toUtc, $resolvedBranchId, $zone, true);
            $requiredMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);
            $paidMinor = Money::minorUnits($reservation->deposit_paid_amount ?? 0, true);

            return [
                'reservation_id' => (int) $reservation->reservation_id,
                'reservation_code' => (string) $reservation->reservation_code,
                'status' => (string) ($reservation->status?->value ?? $reservation->status),
                'row_version' => (int) ($reservation->row_version ?? 1),
                'guest_count' => (int) $reservation->guest_count,
                'start_time' => $this->iso($reservation->start_time),
                'end_time' => $this->iso($reservation->end_time),
                'user' => $this->presentVisibleCustomer($reservation, ['user_id', 'full_name', 'phone', 'email']),
                'guest' => $this->presentGuestSnapshot($reservation),
                'flags' => array_merge($flags, [
                    'deposit_self_service_follow_up' => (bool) data_get($deposit, 'follow_up.needs_staff_follow_up', false),
                ]),
                'deposit' => [
                    'status' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? ''),
                    'required_amount' => Money::formatMinor($requiredMinor),
                    'paid_amount' => Money::formatMinor($paidMinor),
                    'outstanding_amount' => Money::formatMinor(max(0, $requiredMinor - $paidMinor)),
                    'currency' => (string) ($reservation->bill_currency ?? 'VND'),
                    'self_service' => $deposit,
                ],
                'orchestration' => [
                    'candidate_table_count' => count($candidateTables),
                    'candidate_tables' => $candidateTables,
                    'best_fit_table' => $candidateTables[0] ?? null,
                    'assignment_request_context' => $assignmentRequestContext,
                ],
            ];
        }, $boardVisibleUnassigned);

        $displayedReservationsForSummary = $assignedReservations
            ->merge(collect(array_map(static fn (array $row): Reservation => $row['reservation'], $boardVisibleUnassigned)))
            ->unique(static fn (Reservation $reservation): int => (int) $reservation->reservation_id)
            ->values();

        $summary = [
            'zone_count' => count($zoneRows),
            'active_order_count' => $activeOrdersByReservationId->count(),
            'unassigned_reservation_count' => count($unassignedRows),
            'unassigned_with_slot_only_candidate_count' => collect($unassignedRows)->filter(static fn (array $row): bool => collect((array) ($row['orchestration']['candidate_tables'] ?? []))->contains(static fn (array $candidate): bool => (bool) data_get($candidate, 'policy_flags.slot_only_candidate', false)))->count(),
            'deposit_acknowledged_reservation_count' => collect($displayedReservationsForSummary)->filter(fn (Reservation $reservation): bool => (bool) data_get($this->depositRead($reservation), 'requirement_acknowledged', false))->count(),
            'deposit_intent_submitted_reservation_count' => collect($displayedReservationsForSummary)->filter(fn (Reservation $reservation): bool => (string) data_get($this->depositRead($reservation), 'intent_status', 'None') === 'Submitted')->count(),
            'deposit_self_service_follow_up_count' => collect($displayedReservationsForSummary)->filter(fn (Reservation $reservation): bool => (bool) data_get($this->depositRead($reservation), 'follow_up.needs_staff_follow_up', false))->count(),
        ];

        return [
            'data' => $tableRows,
            'zones' => $zoneRows,
            'summary' => $summary,
            'unassigned_reservations' => $unassignedRows,
            'orchestration' => [
                'mode' => 'zone_capacity_candidate_matching',
                'write_side' => [
                    'assign_suggested_table_supported' => true,
                    'assign_best_fit_supported' => true,
                    'assign_suggested_table_requires_current_candidate' => true,
                ],
                'capacity_policy' => [
                    'close_fit_max_extra_seats' => $closeFitMaxExtraSeats,
                ],
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getCandidateTablesForReservation(
        Reservation $reservation,
        mixed $branchId = null,
        ?string $zone = null,
        bool $includeSlotOnly = true,
        ?\DateTimeInterface $boardFrom = null,
        ?\DateTimeInterface $boardTo = null,
    ): array {
        return $this->getCandidateTablesForReservations(
            reservations: [$reservation],
            branchId: $branchId,
            zone: $zone,
            includeSlotOnly: $includeSlotOnly,
            boardFrom: $boardFrom,
            boardTo: $boardTo,
        )[(int) $reservation->reservation_id] ?? [];
    }

    /**
     * @param  iterable<mixed>  $reservations
     * @return array<int,list<array<string,mixed>>>
     */
    public function getCandidateTablesForReservations(
        iterable $reservations,
        mixed $branchId = null,
        ?string $zone = null,
        bool $includeSlotOnly = true,
        ?\DateTimeInterface $boardFrom = null,
        ?\DateTimeInterface $boardTo = null,
        ?Collection $preloadedTables = null,
    ): array {
        $resolvedBranchId = $this->resolveBranchId($branchId);
        $zone = $this->normalizeZone($zone);
        $reservations = Collection::make($reservations)
            ->filter(static fn (mixed $reservation): bool => $reservation instanceof Reservation)
            ->values();

        if ($reservations->isEmpty()) {
            return [];
        }

        $sharedBoardFromUtc = $boardFrom ? Carbon::instance(\DateTimeImmutable::createFromInterface($boardFrom))->utc() : null;
        $sharedBoardToUtc = $boardTo ? Carbon::instance(\DateTimeImmutable::createFromInterface($boardTo))->utc() : null;

        $contextFromUtc = $sharedBoardFromUtc
            ?? Carbon::createFromTimestampUTC((int) $reservations
                ->map(static fn (Reservation $reservation): int => Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->start_time))->utc()->getTimestamp())
                ->min());
        $contextToUtc = $sharedBoardToUtc
            ?? Carbon::createFromTimestampUTC((int) $reservations
                ->map(static fn (Reservation $reservation): int => Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->end_time))->utc()->getTimestamp())
                ->max());

        // Best Practice (In-Memory Pre-computation): Xây dựng Context, load toàn bộ Lịch Đặt & Lịch Khóa Bàn của CẢ NHÀ HÀNG vào RAM 1 lần duy nhất
        $context = $this->buildCandidateSearchContext(
            branchId: $resolvedBranchId,
            zone: $zone,
            contextFromUtc: $contextFromUtc,
            contextToUtc: $contextToUtc,
            preloadedTables: $preloadedTables,
        );

        $candidatesByReservation = [];
        foreach ($reservations as $reservation) {
            $reservationBoardFromUtc = $sharedBoardFromUtc ?? Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->start_time))->utc();
            $reservationBoardToUtc = $sharedBoardToUtc ?? Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->end_time))->utc();

            // Gọi Engine tính toán tìm bàn (In-memory, không query Database)
            $candidatesByReservation[(int) $reservation->reservation_id] = $this->buildCandidateTablesForReservationUsingContext(
                reservation: $reservation,
                context: $context,
                zone: $zone,
                includeSlotOnly: $includeSlotOnly,
                boardFromUtc: $reservationBoardFromUtc,
                boardToUtc: $reservationBoardToUtc,
            );
        }

        return $candidatesByReservation;
    }

    /**
     * @param  array<int, string>  $visibleUserFields
     * @return array<string, mixed>
     */
    private function presentAssignedReservation(Reservation $reservation, array $visibleUserFields): array
    {
        $paymentSummary = $reservation->relationLoaded('payments') ? PaymentSummary::fromPayments($reservation->payments) : [];
        $deposit = $this->depositRead($reservation, $paymentSummary);
        $requiredMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);
        $paidMinor = Money::minorUnits($reservation->deposit_paid_amount ?? 0, true);

        return [
            'reservation_id' => (int) $reservation->reservation_id,
            'reservation_code' => (string) $reservation->reservation_code,
            'status' => $reservation->status?->value ?? (string) $reservation->status,
            'row_version' => (int) ($reservation->row_version ?? 1),
            'table_ids' => $reservation->relationLoaded('tables')
                ? $reservation->tables->pluck('table_id')->map(fn ($id) => (int) $id)->values()->all()
                : [],
            'start_time' => $this->iso($reservation->start_time),
            'end_time' => $this->iso($reservation->end_time),
            'guest_count' => (int) $reservation->guest_count,
            'checked_in_at' => $this->iso($reservation->checked_in_at),
            'user' => $this->presentVisibleCustomer($reservation, $visibleUserFields),
            'guest' => $this->presentGuestSnapshot($reservation),
            'deposit' => [
                'status' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? ''),
                'required_amount' => Money::formatMinor($requiredMinor),
                'paid_amount' => Money::formatMinor($paidMinor),
                'outstanding_amount' => Money::formatMinor(max(0, $requiredMinor - $paidMinor)),
                'currency' => (string) ($reservation->bill_currency ?? 'VND'),
                'self_service' => $deposit,
            ],
            'flags' => [
                'deposit_self_service_follow_up' => (bool) data_get($deposit, 'follow_up.needs_staff_follow_up', false),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentHold(TableHold $hold): array
    {
        return [
            'hold_id' => (string) $hold->hold_id,
            'hold_status' => (string) ($hold->hold_status?->value ?? $hold->hold_status ?? ''),
            'row_version' => (int) ($hold->row_version ?? 1),
            'start_time' => $this->iso($hold->start_time),
            'end_time' => $this->iso($hold->end_time),
            'expire_at' => $this->iso($hold->expire_at),
        ];
    }

    /**
     * @param  array<string,mixed>  $candidate
     * @param  array<string,bool>  $flags
     * @param  array<string,mixed>  $deposit
     * @return array<string,mixed>
     */
    private function presentCandidateReservation(Reservation $reservation, array $candidate, array $flags, array $deposit): array
    {
        return [
            'reservation_id' => (int) $reservation->reservation_id,
            'reservation_code' => (string) $reservation->reservation_code,
            'row_version' => (int) ($reservation->row_version ?? 1),
            'guest_count' => (int) $reservation->guest_count,
            'user' => $this->presentVisibleCustomer($reservation, ['user_id', 'full_name', 'phone', 'email']),
            'guest' => $this->presentGuestSnapshot($reservation),
            'flags' => $flags,
            'policy_flags' => (array) ($candidate['policy_flags'] ?? []),
            'deposit' => [
                'self_service' => $deposit,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function presentActiveOrder(ReservationOrder $order): array
    {
        return [
            'order_id' => (int) $order->order_id,
            'status' => (string) ($order->status?->value ?? $order->status),
            'order_type' => (string) ($order->order_type?->value ?? $order->order_type),
            'row_version' => (int) ($order->row_version ?? 1),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function fitSummary(int $guestCount, int $seats, int $closeFitMaxExtraSeats): array
    {
        // Nghiệp vụ (Capacity Matching Rule): Quy định các mức độ tối ưu chỗ ngồi nhằm tránh lãng phí (Wide Fit)
        if ($seats < $guestCount) {
            return [
                'status' => 'insufficient_capacity', // Bàn quá nhỏ
                'extra_seats' => $seats - $guestCount,
                'assignable' => false,
                'reason_code' => 'insufficient_capacity',
            ];
        }

        if ($seats === $guestCount) {
            return [
                'status' => 'exact_fit', // Khớp chính xác hoàn hảo
                'extra_seats' => 0,
                'assignable' => true,
                'reason_code' => 'exact_capacity_match',
            ];
        }

        $extraSeats = $seats - $guestCount;
        if ($extraSeats <= $closeFitMaxExtraSeats) {
            return [
                'status' => 'close_fit', // Rộng 1 chút nhưng trong mức cho phép (VD: 2 khách ngồi bàn 4)
                'extra_seats' => $extraSeats,
                'assignable' => true,
                'reason_code' => 'close_capacity_match',
            ];
        }

        return [
            'status' => 'wide_fit', // Bàn quá rộng (VD: 2 khách ngồi bàn 10 người), gây thất thoát doanh thu (RevPASH)
            'extra_seats' => $extraSeats,
            'assignable' => true,
            'reason_code' => 'capacity_available',
        ];
    }

    /**
     * @return array<string,bool>
     */
    private function reservationTimingFlags(Reservation $reservation, Carbon $nowUtc, Carbon $dueSoonCutoffUtc, Carbon $overdueCutoffUtc): array
    {
        $status = (string) ($reservation->status?->value ?? $reservation->status);
        $startUtc = Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->start_time))->utc();
        $isCheckedIn = ReservationStatus::isCheckedInDbValue($status) || $reservation->checked_in_at !== null;
        $isTerminal = in_array($status, [
            ReservationStatus::Cancelled->value,
            ReservationStatus::Completed->value,
            ReservationStatus::Expired->value,
            ReservationStatus::NoShow->value,
        ], true);

        // Khách sắp đến (sáng màu để nhắc nhở)
        $dueSoon = ! $isTerminal && ! $isCheckedIn && $startUtc->greaterThanOrEqualTo($nowUtc) && $startUtc->lessThanOrEqualTo($dueSoonCutoffUtc);
        // Quá hạn Grace Period, có thể chuyển thành No-show
        $overdue = ! $isTerminal && ! $isCheckedIn && $startUtc->lessThanOrEqualTo($overdueCutoffUtc);
        // Đã trễ giờ nhưng vẫn trong Grace Period
        $late = ! $isTerminal && ! $isCheckedIn && $startUtc->lessThan($nowUtc) && ! $overdue;

        return [
            'due_soon' => $dueSoon,
            'late' => $late,
            'overdue' => $overdue,
        ];
    }

    /**
     * @param  array<int, TableHold>  $holdList
     * @param  array<int, list<Reservation>>  $reservationsByTable
     * @param  array<int, list<TableHold>>  $holdsByTable
     * @return array<string, mixed>|null
     */
    private function buildCheckInAction(
        ?Reservation $reservation,
        string $realtimeStatus,
        array $holdList,
        array $reservationsByTable,
        array $holdsByTable,
        Carbon $nowUtc
    ): ?array {
        if (! $reservation) {
            return null;
        }

        // Tái sử dụng Người Gác Cổng CheckInReadinessService để kiểm tra tính hợp lệ
        $assignedTableIds = $reservation->relationLoaded('tables')
            ? $reservation->tables->pluck('table_id')->map(fn ($id) => (int) $id)->values()->all()
            : [];
        $reservationConflictTableIds = $this->resolveReservationConflictTableIds(
            $reservation,
            $assignedTableIds,
            $reservationsByTable,
        );
        $holdConflictTableIds = $this->resolveHoldConflictTableIds(
            $reservation,
            $assignedTableIds,
            $holdsByTable,
        );
        $readiness = $this->checkInReadinessService->describe(
            reservation: $reservation,
            checkInAt: $nowUtc,
            assignedTableIds: $assignedTableIds,
            tables: $reservation->tables,
            reservationConflictTableIds: $reservationConflictTableIds,
            holdConflictTableIds: $holdConflictTableIds,
        );

        // Trả về lệnh điều khiển Backend-driven UI (HATEOAS): Cung cấp Endpoint, Method, Payload
        return [
            'available' => (bool) ($readiness['available'] ?? false),
            'blocked_reason_code' => $readiness['blocked_reason_code'] ?? null,
            'method' => 'POST',
            'endpoint' => '/api/v1/staff/reservations/'.(int) $reservation->reservation_id.'/check-in',
            'required_payload' => ['row_version'], // Yêu cầu truyền row_version để đảm bảo Optimistic Locking
            'preferred_payload' => [
                'row_version' => (int) ($reservation->row_version ?? 1),
                'table_ids' => $reservation->relationLoaded('tables')
                    ? $reservation->tables->pluck('table_id')->map(fn ($id) => (int) $id)->values()->all()
                    : [],
            ],
            'checks' => array_merge((array) ($readiness['checks'] ?? []), [
                'table_realtime_available' => $realtimeStatus === RestaurantTableStatus::Available->value,
                'has_hold_conflict' => $holdConflictTableIds !== [],
            ]),
        ];
    }

    /**
     * @param  array<int,int>  $assignedTableIds
     * @param  array<int,list<Reservation>>  $reservationsByTable
     * @return array<int,int>
     */
    private function resolveReservationConflictTableIds(
        Reservation $reservation,
        array $assignedTableIds,
        array $reservationsByTable,
    ): array {
        $conflictTableIds = [];

        foreach ($assignedTableIds as $tableId) {
            foreach ($reservationsByTable[$tableId] ?? [] as $candidate) {
                if ((int) $candidate->reservation_id === (int) $reservation->reservation_id) {
                    continue;
                }

                // Phát hiện Override Time (Trùng lịch khách cũ chưa đi, khách mới đã tới)
                if ($candidate->start_time->lt($reservation->end_time) && $candidate->end_time->gt($reservation->start_time)) {
                    $conflictTableIds[] = $tableId;
                    break;
                }
            }
        }

        $conflictTableIds = array_values(array_unique(array_map('intval', $conflictTableIds)));
        sort($conflictTableIds);

        return $conflictTableIds;
    }

    /**
     * @param  array<int,int>  $assignedTableIds
     * @param  array<int,list<TableHold>>  $holdsByTable
     * @return array<int,int>
     */
    private function resolveHoldConflictTableIds(
        Reservation $reservation,
        array $assignedTableIds,
        array $holdsByTable,
    ): array {
        $conflictTableIds = [];

        foreach ($assignedTableIds as $tableId) {
            foreach ($holdsByTable[$tableId] ?? [] as $hold) {
                if ((int) ($hold->confirmed_reservation_id ?? 0) === (int) $reservation->reservation_id) {
                    continue;
                }

                // Phát hiện Trùng lịch Khóa bàn
                if ($hold->start_time->lt($reservation->end_time) && $hold->end_time->gt($reservation->start_time)) {
                    $conflictTableIds[] = $tableId;
                    break;
                }
            }
        }

        $conflictTableIds = array_values(array_unique(array_map('intval', $conflictTableIds)));
        sort($conflictTableIds);

        return $conflictTableIds;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildMoveTableAction(?Reservation $reservation, string $realtimeStatus, int $tableId): ?array
    {
        if (! $reservation) {
            return null;
        }

        $status = $reservation->status?->value ?? (string) $reservation->status;
        // Chỉ hiện nút "Chuyển bàn" nếu Khách đã Check-in và bàn đang có người ngồi (Occupied)
        $available = (ReservationStatus::isCheckedInDbValue($status) || $reservation->checked_in_at !== null)
            && $realtimeStatus === RestaurantTableStatus::Occupied->value;

        return [
            'available' => $available,
            'method' => 'POST',
            'endpoint' => '/api/v1/staff/reservations/'.(int) $reservation->reservation_id.'/move-table',
            'required_payload' => ['from_table_id', 'to_table_id', 'row_version'],
            'preferred_payload' => [
                'from_table_id' => $tableId,
                'row_version' => (int) ($reservation->row_version ?? 1),
            ],
        ];
    }

    private function resolvePreferredAction(bool $assignmentCandidate, bool $canCheckIn, bool $canMoveTable): string
    {
        // Điều hướng Action Chính trên thẻ Table UI
        if ($canCheckIn) {
            return 'check_in';
        }

        if ($canMoveTable) {
            return 'move_table';
        }

        if ($assignmentCandidate) {
            return 'assignment_candidate';
        }

        return 'none';
    }

    /**
     * @param  array<string,float|int>|null  $paymentSummary
     * @return array<string,mixed>
     */
    private function depositRead(Reservation $reservation, ?array $paymentSummary = null): array
    {
        return app(StaffReservationDepositOperationalReadService::class)->build($reservation, $paymentSummary ?? []);
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }

    private function fitPriority(string $status): int
    {
        return match ($status) {
            'exact_fit' => 0,
            'close_fit' => 1,
            'wide_fit' => 2,
            default => 9,
        };
    }

    private function normalizeZone(?string $zone): ?string
    {
        $zone = trim((string) ($zone ?? ''));

        return $zone === '' ? null : $zone;
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     */
    private function applyBranchScope(mixed $query, ?int $branchId, ?array $accessibleBranchIds): void
    {
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);

            return;
        }

        if ($accessibleBranchIds === null) {
            return;
        }

        // Tình huống cực đoan: User không có quyền ở nhánh nào cả -> Ràng buộc false ngay từ SQL (1 = 0)
        if ($accessibleBranchIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('branch_id', $accessibleBranchIds);
    }

    /**
     * @return list<int>
     */
    private function normalizeBranchIds(array $branchIds): array
    {
        $branchIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $branchId): int => is_numeric($branchId) ? (int) $branchId : 0, $branchIds),
            static fn (int $branchId): bool => $branchId > 0,
        )));
        sort($branchIds);

        return $branchIds;
    }

    private function resolveCheckInGraceMinutes(): int
    {
        return max(0, $this->runtimeSettings->int(
            'checkin.grace_minutes',
            $this->runtimeSettings->int('booking.check_in_grace_minutes', (int) config('booking.check_in_grace_minutes', 15))
        ));
    }

    private function resolveNoShowGraceMinutes(): int
    {
        return max(0, $this->runtimeSettings->int(
            'no_show.grace_minutes',
            $this->runtimeSettings->int('booking.no_show_grace_minutes', (int) config('booking.no_show_grace_minutes', 15))
        ));
    }

    /**
     * @return array{
     * tables: Collection<int,RestaurantTable>,
     * reservation_conflicts_by_table: array<int,list<array<string,int>>>,
     * hold_conflicts_by_table: array<int,list<array<string,int|null|string>>>
     * }
     */
    private function buildCandidateSearchContext(
        ?int $branchId,
        ?string $zone,
        Carbon $contextFromUtc,
        Carbon $contextToUtc,
        ?Collection $preloadedTables = null,
    ): array {
        // Tối ưu Hiệu Năng Cực Đoan (In-Memory Indexing):
        // Thay vì mỗi bàn gửi 1 câu query SQL để check trùng lịch, ta Pre-load tất cả bàn và lịch vào RAM một lần duy nhất.
        $tables = $preloadedTables instanceof Collection
            ? $preloadedTables
                ->when($branchId !== null, static fn (Collection $collection): Collection => $collection
                    ->filter(static fn (RestaurantTable $table): bool => (int) ($table->branch_id ?? 0) === $branchId)
                    ->values())
            : RestaurantTable::query()
                ->notDeleted()
                ->when($branchId !== null, static fn ($query) => $query->where('branch_id', $branchId))
                ->inZone($zone)
                ->with('template')
                ->orderBy('zone')
                ->orderBy('table_code')
                ->get();

        if ($zone !== null) {
            $tables = $tables
                ->filter(static fn (RestaurantTable $table): bool => trim((string) ($table->zone ?? '')) === $zone)
                ->values();
        }

        $tableIds = $tables
            ->pluck('table_id')
            ->map(static fn ($tableId): int => (int) $tableId)
            ->filter(static fn (int $tableId): bool => $tableId > 0)
            ->values()
            ->all();

        return [
            'tables' => $tables->values(),
            'reservation_conflicts_by_table' => $this->loadCandidateReservationConflictsByTable($tableIds, $contextFromUtc, $contextToUtc),
            'hold_conflicts_by_table' => $this->loadCandidateHoldConflictsByTable($tableIds, $contextFromUtc, $contextToUtc),
        ];
    }

    /**
     * @param array{
     * tables: Collection<int,RestaurantTable>,
     * reservation_conflicts_by_table: array<int,list<array<string,int>>>,
     * hold_conflicts_by_table: array<int,list<array<string,int|null|string>>>
     * } $context
     * @return list<array<string,mixed>>
     */
    private function buildCandidateTablesForReservationUsingContext(
        Reservation $reservation,
        array $context,
        ?string $zone,
        bool $includeSlotOnly,
        Carbon $boardFromUtc,
        Carbon $boardToUtc,
    ): array {
        // Core Engine tìm bàn trống dựa trên Context in-memory
        $startUtc = Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->start_time))->utc();
        $endUtc = Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->end_time))->utc();
        $startTimestamp = $startUtc->getTimestamp();
        $endTimestamp = $endUtc->getTimestamp();
        $boardFromTimestamp = $boardFromUtc->getTimestamp();
        $boardToTimestamp = $boardToUtc->getTimestamp();
        $reservationId = (int) $reservation->reservation_id;
        $closeFitMaxExtraSeats = max(0, (int) config('booking.staff_table_board_close_fit_max_extra_seats', 2));
        $candidates = [];

        /** @var Collection<int,RestaurantTable> $tables */
        $tables = $context['tables'];
        foreach ($tables as $table) {
            $status = (string) ($table->status?->value ?? $table->status);
            if ($status !== RestaurantTableStatus::Available->value) {
                continue; // Bàn đang hỏng, sửa chữa -> Bỏ qua
            }

            if (! $this->reservationBranchScopeService->reservationMatchesTableBranchInMemory(
                $reservation->branch_id,
                $table->branch_id,
            )) {
                continue; // Sai chi nhánh -> Bỏ qua
            }

            $seats = (int) ($table->template->seats ?? 0);
            $fit = $this->fitSummary((int) $reservation->guest_count, $seats, $closeFitMaxExtraSeats);
            if (($fit['assignable'] ?? false) !== true) {
                continue; // Khách 10 người, bàn 2 người -> Bỏ qua
            }

            $tableId = (int) $table->table_id;

            if ($this->tableHasReservationConflict(
                $context['reservation_conflicts_by_table'][$tableId] ?? [],
                $startTimestamp,
                $endTimestamp,
                $reservationId,
            )) {
                continue; // Khung giờ khách đến, bàn đang kẹt Booking khác -> Bỏ qua
            }

            if ($this->tableHasHoldConflict(
                $context['hold_conflicts_by_table'][$tableId] ?? [],
                $startTimestamp,
                $endTimestamp,
                $reservationId,
            )) {
                continue; // Bàn đang bị Hold -> Bỏ qua
            }

            // Kiểm tra trạng thái rảnh rỗi "cục bộ" trong khoảng thời gian Board đang render
            $busyElsewhereInBoardWindow = false;
            if ($boardFromTimestamp < $boardToTimestamp) {
                $busyElsewhereInBoardWindow = $this->tableHasReservationConflict(
                    $context['reservation_conflicts_by_table'][$tableId] ?? [],
                    $boardFromTimestamp,
                    $boardToTimestamp,
                    $reservationId,
                ) || $this->tableHasHoldConflict(
                    $context['hold_conflicts_by_table'][$tableId] ?? [],
                    $boardFromTimestamp,
                    $boardToTimestamp,
                    $reservationId,
                );
            }

            $boardWindowOpen = ! $busyElsewhereInBoardWindow;
            if (! $includeSlotOnly && ! $boardWindowOpen) {
                continue;
            }

            $candidates[] = [
                'table_id' => $tableId,
                'table_code' => (string) $table->table_code,
                'zone' => $table->zone,
                'board_state' => $boardWindowOpen ? 'available' : 'reserved_in_range',
                'rank' => 0,
                'fit' => Arr::except($fit, ['assignable']),
                'score' => 0,
                'reason_codes' => array_values(array_filter([
                    $fit['reason_code'] ?? null,
                    $boardWindowOpen ? null : 'table_busy_elsewhere_in_board_window_but_free_for_reservation_window',
                ])),
                'policy_flags' => [
                    'board_window_open' => $boardWindowOpen,
                    'slot_only_candidate' => ! $boardWindowOpen,
                ],
                'assignment_window' => [
                    'availability_mode' => $boardWindowOpen
                        ? 'open_for_board_window'
                        : 'slot_available_busy_elsewhere_in_board_window',
                    'reservation_window_start' => $startUtc->toIso8601String(),
                    'reservation_window_end' => $endUtc->toIso8601String(),
                    'board_window_start' => $boardFromUtc->toIso8601String(),
                    'board_window_end' => $boardToUtc->toIso8601String(),
                ],
                'assignment_request_context' => $this->buildAssignmentRequestContext(
                    $boardFromUtc,
                    $boardToUtc,
                    $reservation->branch_id !== null ? (int) $reservation->branch_id : null,
                    $zone,
                    $includeSlotOnly,
                ),
            ];
        }

        // Chấm điểm thuật toán (Scoring) - Ưu tiên những bàn Phù hợp sức chứa, trống suốt ca, và cùng mã Zone
        usort($candidates, function (array $left, array $right): int {
            $leftOpen = (bool) data_get($left, 'policy_flags.board_window_open', false) ? 0 : 1;
            $rightOpen = (bool) data_get($right, 'policy_flags.board_window_open', false) ? 0 : 1;
            $leftFit = $this->fitPriority((string) data_get($left, 'fit.status', ''));
            $rightFit = $this->fitPriority((string) data_get($right, 'fit.status', ''));
            $leftCode = (string) ($left['table_code'] ?? '');
            $rightCode = (string) ($right['table_code'] ?? '');

            return [$leftOpen, $leftFit, $leftCode] <=> [$rightOpen, $rightFit, $rightCode];
        });

        foreach ($candidates as $index => &$candidate) {
            $candidate['rank'] = $index + 1;
            $candidate['score'] = max(0, 100 - ($index * 10)); // Cho điểm Top 1 là 100, Top 2 là 90...
            if ($index === 0) {
                $candidate['reason_codes'] = array_values(array_unique(array_merge(
                    (array) ($candidate['reason_codes'] ?? []),
                    ['primary_recommendation'], // Gắn mác Bàn Gợi Ý Tốt Nhất
                )));
            }
        }
        unset($candidate);

        return $candidates;
    }

    /**
     * @param  array<int,int>  $tableIds
     * @return array<int,list<array<string,int>>>
     */
    private function loadCandidateReservationConflictsByTable(array $tableIds, Carbon $contextFromUtc, Carbon $contextToUtc): array
    {
        if ($tableIds === []) {
            return [];
        }

        // Bulk load bằng Raw DB để tiết kiệm RAM so với Eloquent Model
        $rows = DB::table('reservation_tables as rt')
            ->join('reservations as r', 'r.reservation_id', '=', 'rt.reservation_id')
            ->whereIn('rt.table_id', $tableIds)
            ->whereIn('r.status', ReservationStatus::activeDbValues())
            ->where('r.start_time', '<', $contextToUtc)
            ->where('r.end_time', '>', $contextFromUtc)
            ->orderBy('rt.table_id')
            ->select(['rt.table_id', 'r.reservation_id', 'r.start_time', 'r.end_time'])
            ->get();

        $conflictsByTable = [];
        foreach ($rows as $row) {
            $conflictsByTable[(int) $row->table_id][] = [
                'reservation_id' => (int) $row->reservation_id,
                'start_at' => Carbon::parse((string) $row->start_time, 'UTC')->getTimestamp(),
                'end_at' => Carbon::parse((string) $row->end_time, 'UTC')->getTimestamp(),
            ];
        }

        return $conflictsByTable;
    }

    /**
     * @param  array<int,int>  $tableIds
     * @return array<int,list<array<string,int|null|string>>>
     */
    private function loadCandidateHoldConflictsByTable(array $tableIds, Carbon $contextFromUtc, Carbon $contextToUtc): array
    {
        if ($tableIds === []) {
            return [];
        }

        // Tương tự, load Hold Blocks bằng SQL Native thuần
        $query = DB::table('table_hold_details as thd')
            ->join('table_holds as th', 'th.hold_id', '=', 'thd.hold_id')
            ->whereIn('thd.table_id', $tableIds)
            ->where('th.start_time', '<', $contextToUtc)
            ->where('th.end_time', '>', $contextFromUtc);

        HoldConflictScope::apply($query, 'th', Carbon::now('UTC'));

        $rows = $query
            ->orderBy('thd.table_id')
            ->select(['thd.table_id', 'th.hold_id', 'th.start_time', 'th.end_time', 'th.confirmed_reservation_id'])
            ->get();

        $conflictsByTable = [];
        foreach ($rows as $row) {
            $conflictsByTable[(int) $row->table_id][] = [
                'hold_id' => (string) $row->hold_id,
                'start_at' => Carbon::parse((string) $row->start_time, 'UTC')->getTimestamp(),
                'end_at' => Carbon::parse((string) $row->end_time, 'UTC')->getTimestamp(),
                'confirmed_reservation_id' => $row->confirmed_reservation_id !== null ? (int) $row->confirmed_reservation_id : null,
            ];
        }

        return $conflictsByTable;
    }

    /**
     * @param  list<array<string,int>>  $conflicts
     */
    private function tableHasReservationConflict(array $conflicts, int $rangeStartAt, int $rangeEndAt, int $ignoreReservationId): bool
    {
        foreach ($conflicts as $conflict) {
            if ((int) ($conflict['reservation_id'] ?? 0) === $ignoreReservationId) {
                continue;
            }

            // Thuật toán phát hiện Overlap 2 khoảng thời gian
            if ((int) ($conflict['start_at'] ?? 0) < $rangeEndAt && (int) ($conflict['end_at'] ?? 0) > $rangeStartAt) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string,int|null|string>>  $conflicts
     */
    private function tableHasHoldConflict(array $conflicts, int $rangeStartAt, int $rangeEndAt, int $ignoreConfirmedReservationId): bool
    {
        foreach ($conflicts as $conflict) {
            $confirmedReservationId = $conflict['confirmed_reservation_id'] ?? null;
            if ($confirmedReservationId !== null && (int) $confirmedReservationId === $ignoreConfirmedReservationId) {
                continue;
            }

            if ((int) ($conflict['start_at'] ?? 0) < $rangeEndAt && (int) ($conflict['end_at'] ?? 0) > $rangeStartAt) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildAssignmentRequestContext(
        Carbon $boardFromUtc,
        Carbon $boardToUtc,
        ?int $branchId,
        ?string $zone,
        bool $includeSlotOnlyCandidates,
    ): array {
        return [
            'board_from' => $boardFromUtc->toIso8601String(),
            'board_to' => $boardToUtc->toIso8601String(),
            'branch_id' => $branchId,
            'zone' => $zone,
            'include_slot_only_candidates' => $includeSlotOnlyCandidates,
        ];
    }

    /**
     * @param  array<int,list<int>>  $assignedTableIdsByReservation
     */
    private function holdShouldRemainVisibleOnTable(
        TableHold $hold,
        int $tableId,
        array $assignedTableIdsByReservation,
    ): bool {
        if (($hold->hold_status?->value ?? (string) $hold->hold_status) !== 'Confirmed') {
            return true;
        }

        $reservationId = (int) ($hold->confirmed_reservation_id ?? 0);
        if ($reservationId <= 0) {
            return false;
        }

        return in_array($tableId, $assignedTableIdsByReservation[$reservationId] ?? [], true);
    }

    /**
     * @param  array<int,string>  $visibleUserFields
     * @return array<string,mixed>|null
     */
    private function presentVisibleCustomer(Reservation $reservation, array $visibleUserFields): ?array
    {
        $customer = [
            'user_id' => $reservation->user_id !== null ? (int) $reservation->user_id : null,
            'full_name' => $reservation->customerDisplayName(),
            'phone' => $reservation->customerPhone(),
            'email' => $reservation->customerEmail(),
        ];

        $visible = Arr::only($customer, $visibleUserFields);
        foreach ($visible as $value) {
            if ($value !== null && $value !== '') {
                return $visible;
            }
        }

        return array_key_exists('user_id', $visible) ? $visible : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function presentGuestSnapshot(Reservation $reservation): ?array
    {
        return $reservation->guestSnapshot();
    }

    private function resolveBranchId(mixed $branchId): ?int
    {
        if ($branchId === null || $branchId === '') {
            return null;
        }

        return $this->branchContextService->resolveBranchId($branchId);
    }
}
