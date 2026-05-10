<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\Queries\Timeline;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Domain\Models\TableHold;
use App\Modules\Conversations\Application\Services\StaffReservationInboxService;
use App\Modules\FloorOperations\Application\Queries\StaffCheckInReadinessService;
use App\Modules\FloorOperations\Application\Queries\StaffTableBoardService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class StaffReservationTimelineService
{
    public function __construct(
        private readonly StaffReservationInboxService $inboxService,
        private readonly StaffTableBoardService $tableBoardService,
        private readonly StaffCheckInReadinessService $checkInReadinessService,
        private readonly BranchContextService $branchContextService,
        ?RuntimeSettingService $runtimeSettings = null,
    ) {
        $this->runtimeSettings = $runtimeSettings ?? app(RuntimeSettingService::class);
    }

    private readonly RuntimeSettingService $runtimeSettings;

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function buildTimeline(array $filters): array
    {
        // --- BƯỚC 1: KHỞI TẠO BỐI CẢNH (CONTEXT & TIME WINDOW) ---
        // Nghiệp vụ: Lấy múi giờ của chi nhánh để hiển thị Timeline chính xác.
        // Tính toán khoảng thời gian (Window) cần xem (ví dụ: ca sáng từ 08:00 đến 14:00).
        $timezone = (string) config('booking.multi_branch.default_branch_timezone', 'Asia/Ho_Chi_Minh');
        $window = $this->resolveWindow($filters, $timezone);
        $slotMinutes = (int) ($filters['slot_minutes'] ?? 30); // Độ rộng mỗi cột trên UI (thường 30 phút/slot)
        $laneBy = $this->resolveLaneMode((string) ($filters['lane_by'] ?? 'slot'));

        $includeCandidateTables = (bool) ($filters['include_candidate_tables'] ?? false);
        // Nghiệp vụ: Cờ báo hiệu có cần xử lý những khách "Đã đặt bàn nhưng chưa xếp bàn (Unassigned)" hay không.
        $includeUnassignedReservations = $laneBy === 'table' && $includeCandidateTables;

        $resolvedBranchId = $this->resolveBranchId($filters['branch_id'] ?? null);
        $zoneFilter = ! empty($filters['zone']) ? trim((string) $filters['zone']) : null;
        $nowUtc = Carbon::now('UTC');

        // Thời gian châm chước cho khách đến sớm (checkInGrace) và đến muộn (noShowGrace)
        $checkInGrace = $this->resolveCheckInGraceMinutes();
        $noShowGrace = $this->resolveNoShowGraceMinutes();

        // --- BƯỚC 2: TRUY VẤN DỮ LIỆU ĐẶT BÀN GỐC ---
        // Best Practice: Tái sử dụng query từ InboxService để giữ chuẩn chung về scope và filter
        // Eager Loading 'orders' để lấy trạng thái gọi món (Active) nhằm tránh N+1 Query.
        $query = $this->inboxService->newQuery(false)
            ->with([
                'orders' => static function ($builder): void {
                    $builder
                        ->where('status', ReservationOrderStatus::Active->value)
                        ->orderByDesc('order_id');
                },
            ]);

        $this->inboxService->applyCommonFilters($query, [
            'branch_id' => $resolvedBranchId,
            'status' => $filters['status'] ?? null,
            'table_id' => $filters['table_id'] ?? null,
            'q' => $filters['q'] ?? null,
            'deposit_acknowledged' => $filters['deposit_acknowledged'] ?? null,
            'deposit_intent_status' => $filters['deposit_intent_status'] ?? null,
        ]);

        if ($zoneFilter !== null && ! $includeUnassignedReservations) {
            $query->whereHas('tables', static function ($tableQuery) use ($zoneFilter): void {
                $tableQuery->where('restaurant_tables.zone', $zoneFilter);
            });
        }

        // Lọc bỏ các trạng thái không còn hiệu lực trên Timeline (nếu không yêu cầu xem lịch sử)
        if (empty($filters['status'])) {
            $query->whereNotIn('status', [
                ReservationStatus::Cancelled->value,
                ReservationStatus::Completed->value,
                ReservationStatus::Expired->value,
                ReservationStatus::NoShow->value,
            ]);
        }

        // Giới hạn trong khoảng thời gian Window đang xem
        $query
            ->where('start_time', '<', $window['range_end_utc'])
            ->where('end_time', '>', $window['range_start_utc'])
            ->orderBy('start_time')
            ->orderBy('reservation_id');

        /** @var Collection<int,Reservation> $reservations */
        $reservations = $query->get()
            ->unique(static fn (Reservation $reservation): int => (int) $reservation->reservation_id)
            ->values();

        // --- BƯỚC 3: DỰ BÁO BÀN TRỐNG (CANDIDATE TABLES) ---
        // Nghiệp vụ: Đối với những khách sắp đến nơi (trong vòng checkInGrace) nhưng Lễ tân chưa xếp bàn cho họ,
        // hệ thống tự động chạy thuật toán (TableBoardService) để quét và "gợi ý" 3 bàn phù hợp nhất hiện tại.
        $dueSoonCutoffUtc = $nowUtc->copy()->addMinutes($checkInGrace);
        $candidateTablesByReservation = $includeCandidateTables
            ? $this->buildCandidateTablePreviewMap(
                $reservations,
                $filters,
                $resolvedBranchId,
                $window['range_start_utc'],
                $window['range_end_utc'],
                $nowUtc,
                $dueSoonCutoffUtc,
                $nowUtc->copy()->subMinutes($noShowGrace), // Quá giờ no-show thì không gợi ý nữa
            )
            : [];

        // --- BƯỚC 4: LỌC TRONG BỘ NHỚ (IN-MEMORY FILTERING) ---
        // Best Practice: Lọc ở mức Collection để xử lý các logic phức tạp về Zone và Unassigned
        // thay vì viết Raw SQL phức tạp (giúp test dễ hơn và decouple logic).
        $reservations = $reservations
            ->filter(function (Reservation $reservation) use ($includeUnassignedReservations, $candidateTablesByReservation, $zoneFilter): bool {
                if ($reservation->tables->isNotEmpty()) {
                    if ($zoneFilter === null) {
                        return true;
                    }

                    return $reservation->tables->contains(static function ($table) use ($zoneFilter): bool {
                        return trim((string) ($table->zone ?? '')) === $zoneFilter;
                    });
                }

                if (! $includeUnassignedReservations) {
                    return false;
                }

                return array_key_exists((int) $reservation->reservation_id, $candidateTablesByReservation);
            })
            ->values();

        // --- BƯỚC 5: ĐÁNH GIÁ MỨC ĐỘ SẴN SÀNG ĐÓN KHÁCH (CHECK-IN READINESS) ---
        // Nghiệp vụ: Kiểm tra xem cái bàn mà khách được xếp có đang bị "Kẹt" không.
        // Kẹt có thể do: Khách ca trước chưa chịu về, hoặc bàn đang bị Khóa (Hold) để dọn dẹp/sửa chữa.
        $checkInReadinessByReservation = $this->buildCheckInReadinessMap($reservations, $nowUtc);

        // --- BƯỚC 6: CHIA KHUNG GIỜ (SLOTTING) ĐỂ RENDER UI ---
        // Nhóm các booking vào từng cục thời gian (ví dụ: cục 18:00, cục 18:30) để UI vẽ lên Timeline.
        $slots = [];
        foreach ($reservations as $reservation) {
            $startLocal = Carbon::instance($reservation->start_time)->setTimezone($timezone);
            $slotStart = $this->floorToSlot($startLocal, $slotMinutes);
            $slotEnd = $slotStart->copy()->addMinutes($slotMinutes);
            $slotKey = $slotStart->toIso8601String();

            if (! isset($slots[$slotKey])) {
                $slots[$slotKey] = [
                    'slot_start' => $slotStart,
                    'slot_end' => $slotEnd,
                    'reservations' => collect(),
                ];
            }

            $slots[$slotKey]['reservations']->push($reservation);
        }

        ksort($slots);

        // Trả về toàn bộ "Bức tranh toàn cảnh" cho Frontend
        return [
            'timezone' => $timezone,
            'slot_minutes' => $slotMinutes,
            'lane_mode' => $laneBy,
            'range_start_local' => $window['range_start_local'],
            'range_end_local' => $window['range_end_local'],
            'range_start_utc' => $window['range_start_utc'],
            'range_end_utc' => $window['range_end_utc'],
            'now_utc' => $nowUtc,
            'due_soon_cutoff_utc' => $dueSoonCutoffUtc,
            'overdue_cutoff_utc' => $nowUtc->copy()->subMinutes($noShowGrace),
            'candidate_tables_by_reservation' => $candidateTablesByReservation,
            'check_in_readiness_by_reservation' => $checkInReadinessByReservation,
            'filters' => [
                'date' => $filters['date'] ?? null,
                'start_date' => $filters['start_date'] ?? null,
                'end_date' => $filters['end_date'] ?? null,
                'from_time' => $filters['from_time'] ?? null,
                'to_time' => $filters['to_time'] ?? null,
                'branch_id' => $resolvedBranchId,
                'status' => $filters['status'] ?? null,
                'table_id' => isset($filters['table_id']) ? (int) $filters['table_id'] : null,
                'zone' => $filters['zone'] ?? null,
                'q' => $filters['q'] ?? null,
                'deposit_acknowledged' => array_key_exists('deposit_acknowledged', $filters) ? $filters['deposit_acknowledged'] : null,
                'deposit_intent_status' => $filters['deposit_intent_status'] ?? null,
                'lane_by' => $laneBy,
                'include_candidate_tables' => $includeCandidateTables,
            ],
            'reservations' => $reservations,
            'slots' => array_values($slots),
        ];
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{range_start_local:Carbon,range_end_local:Carbon,range_start_utc:Carbon,range_end_utc:Carbon}
     */
    private function resolveWindow(array $filters, string $timezone): array
    {
        $date = isset($filters['date']) && trim((string) $filters['date']) !== '' ? (string) $filters['date'] : null;
        $startDate = (string) ($filters['start_date'] ?? $date ?? Carbon::now($timezone)->toDateString());
        $endDate = (string) ($filters['end_date'] ?? $date ?? $startDate);
        $fromTime = trim((string) ($filters['from_time'] ?? '00:00'));
        $toTime = trim((string) ($filters['to_time'] ?? '23:59'));

        $rangeStartLocal = Carbon::parse($startDate.' '.($fromTime !== '' ? $fromTime : '00:00'), $timezone);
        $rangeEndLocal = Carbon::parse($endDate.' '.($toTime !== '' ? $toTime : '23:59'), $timezone)->addMinute();

        return [
            'range_start_local' => $rangeStartLocal,
            'range_end_local' => $rangeEndLocal,
            'range_start_utc' => $rangeStartLocal->copy()->utc(),
            'range_end_utc' => $rangeEndLocal->copy()->utc(),
        ];
    }

    private function floorToSlot(Carbon $time, int $slotMinutes): Carbon
    {
        $slotMinutes = max(1, $slotMinutes);
        $minutes = (int) $time->minute;
        $flooredMinutes = intdiv($minutes, $slotMinutes) * $slotMinutes;

        return $time->copy()->setTime((int) $time->hour, $flooredMinutes, 0, 0);
    }

    private function resolveLaneMode(string $laneMode): string
    {
        return in_array($laneMode, ['slot', 'zone', 'table'], true) ? $laneMode : 'slot';
    }

    /**
     * @param  Collection<int,Reservation>  $reservations
     * @param  array<string,mixed>  $filters
     * @return array<int,list<array<string,mixed>>>
     */
    private function buildCandidateTablePreviewMap(
        Collection $reservations,
        array $filters,
        ?int $branchId,
        Carbon $boardFromUtc,
        Carbon $boardToUtc,
        Carbon $nowUtc,
        Carbon $dueSoonCutoffUtc,
        Carbon $overdueCutoffUtc,
    ): array {
        $zone = ! empty($filters['zone']) ? trim((string) $filters['zone']) : null;

        // --- BƯỚC 3.1: TÌM ỨNG VIÊN CẦN XẾP BÀN ---
        // Chỉ tìm gợi ý bàn cho những khách:
        // 1. Chưa có bàn ($reservation->tables->isNotEmpty() == false)
        // 2. Booking đã xác nhận (Confirmed)
        // 3. Trong khung thời gian vàng: Chưa quá giờ No-show và sắp đến giờ Check-in.
        $candidateReservations = $reservations
            ->filter(function (Reservation $reservation) use ($dueSoonCutoffUtc, $overdueCutoffUtc): bool {
                if ($reservation->tables->isNotEmpty()) {
                    return false;
                }

                $status = (string) ($reservation->status?->value ?? $reservation->status);
                if ($status !== ReservationStatus::Confirmed->value) {
                    return false;
                }

                $startUtc = Carbon::instance($reservation->start_time)->utc();

                return $startUtc->lessThanOrEqualTo($dueSoonCutoffUtc)
                    && $startUtc->greaterThan($overdueCutoffUtc);
            })
            ->values();

        if ($candidateReservations->isEmpty()) {
            return [];
        }

        $preview = [];
        $candidateMap = $this->tableBoardService->getCandidateTablesForReservations(
            reservations: $candidateReservations,
            branchId: $branchId,
            zone: $zone,
            includeSlotOnly: false,
            boardFrom: $boardFromUtc,
            boardTo: $boardToUtc,
        );

        foreach ($candidateReservations as $reservation) {
            // Lấy ra Top 3 bàn gợi ý ngon nhất cho khách này
            $candidates = array_slice(
                $this->sortCandidateTables($candidateMap[(int) $reservation->reservation_id] ?? []),
                0,
                3,
            );

            if ($candidates !== []) {
                $preview[(int) $reservation->reservation_id] = $candidates;
            }
        }

        return $preview;
    }

    /**
     * @param  list<array<string,mixed>>  $candidates
     * @return list<array<string,mixed>>
     */
    private function sortCandidateTables(array $candidates): array
    {
        // Thuật toán chấm điểm bàn: Ưu tiên bàn fit số lượng người (exact_fit), điểm số, và độ chênh lệch số ghế
        usort($candidates, function (array $left, array $right): int {
            $leftRank = (int) data_get($left, 'rank', PHP_INT_MAX);
            $rightRank = (int) data_get($right, 'rank', PHP_INT_MAX);

            $leftFit = $this->fitPriority((string) data_get($left, 'fit.status', ''));
            $rightFit = $this->fitPriority((string) data_get($right, 'fit.status', ''));

            $leftScore = is_numeric(data_get($left, 'score')) ? (float) data_get($left, 'score') : -INF;
            $rightScore = is_numeric(data_get($right, 'score')) ? (float) data_get($right, 'score') : -INF;

            $leftDelta = $this->candidateSeatDelta($left);
            $rightDelta = $this->candidateSeatDelta($right);

            // Best Practice: Spaceship operator (<=>) để sort đa điều kiện cực kỳ thanh lịch
            return [
                $leftRank,
                $leftFit,
                $leftDelta,
                -$leftScore,
                -1 * (int) ($left['table_id'] ?? 0),
                (string) ($left['table_code'] ?? ''),
            ] <=> [
                $rightRank,
                $rightFit,
                $rightDelta,
                -$rightScore,
                -1 * (int) ($right['table_id'] ?? 0),
                (string) ($right['table_code'] ?? ''),
            ];
        });

        return array_values($candidates);
    }

    private function fitPriority(string $status): int
    {
        return match ($status) {
            'exact_fit' => 0,
            'close_fit' => 1,
            'slot_only_fit' => 2,
            default => 3,
        };
    }

    /**
     * @param  array<string,mixed>  $candidate
     */
    private function candidateSeatDelta(array $candidate): int
    {
        foreach (['fit.extra_seats', 'fit.capacity_delta', 'fit.seat_delta', 'fit.absolute_delta'] as $path) {
            $value = data_get($candidate, $path);
            if (is_numeric($value)) {
                return abs((int) $value);
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * @param  Collection<int,Reservation>  $reservations
     * @return array<int,array<string,mixed>>
     */
    private function buildCheckInReadinessMap(Collection $reservations, Carbon $nowUtc): array
    {
        if ($reservations->isEmpty()) {
            return [];
        }

        // --- BƯỚC 5.1: BÓC TÁCH BÀN ĐANG SỬ DỤNG ---
        // Lấy tất cả các bàn đã được gán cho các Reservation đang Active
        $activeReservationsByTable = [];
        $assignedReservations = $reservations->filter(static fn (Reservation $reservation): bool => $reservation->tables->isNotEmpty())->values();

        foreach ($assignedReservations as $reservation) {
            $status = (string) ($reservation->status?->value ?? $reservation->status ?? '');
            if (! ReservationStatus::isActiveDbValue($status)) {
                continue;
            }

            foreach ($reservation->tables as $table) {
                $activeReservationsByTable[(int) $table->table_id][] = $reservation;
            }
        }

        $assignedTableIds = $assignedReservations
            ->flatMap(static fn (Reservation $reservation) => $reservation->tables->pluck('table_id'))
            ->map(static fn ($tableId): int => (int) $tableId)
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Lấy thông tin các bàn đang bị Khóa (VD: Khóa để setup tiệc, dọn vệ sinh)
        $holdsByTable = $this->loadActiveHoldsByTable($assignedReservations, $assignedTableIds);
        $readinessByReservation = [];

        // --- BƯỚC 5.2: TÌM XUNG ĐỘT (CONFLICT DETECTION) ---
        foreach ($reservations as $reservation) {
            $tableIds = $reservation->tables
                ->pluck('table_id')
                ->map(static fn ($tableId): int => (int) $tableId)
                ->sort()
                ->values()
                ->all();

            $reservationConflictTableIds = [];
            foreach ($tableIds as $tableId) {
                foreach ($activeReservationsByTable[$tableId] ?? [] as $candidate) {
                    if ((int) $candidate->reservation_id === (int) $reservation->reservation_id) {
                        continue;
                    }

                    // Phát hiện trùng lịch (Overlap): Start của người này chen vào giữa giờ của người kia
                    if ($candidate->start_time->lt($reservation->end_time) && $candidate->end_time->gt($reservation->start_time)) {
                        $reservationConflictTableIds[] = $tableId;
                        break;
                    }
                }
            }

            $holdConflictTableIds = [];
            foreach ($tableIds as $tableId) {
                foreach ($holdsByTable[$tableId] ?? [] as $hold) {
                    if ($hold->start_time->lt($reservation->end_time) && $hold->end_time->gt($reservation->start_time)) {
                        $holdConflictTableIds[] = $tableId;
                        break;
                    }
                }
            }

            $readinessByReservation[(int) $reservation->reservation_id] = $this->checkInReadinessService->describe(
                reservation: $reservation,
                checkInAt: $nowUtc,
                assignedTableIds: $tableIds,
                tables: $reservation->tables,
                reservationConflictTableIds: $reservationConflictTableIds,
                holdConflictTableIds: $holdConflictTableIds,
            );
        }

        return $readinessByReservation;
    }

    /**
     * @param  Collection<int,Reservation>  $assignedReservations
     * @param  array<int,int>  $assignedTableIds
     * @return array<int,list<TableHold>>
     */
    private function loadActiveHoldsByTable(Collection $assignedReservations, array $assignedTableIds): array
    {
        if ($assignedReservations->isEmpty() || $assignedTableIds === []) {
            return [];
        }

        $assignedTableIdsByReservation = $assignedReservations
            ->mapWithKeys(static fn (Reservation $reservation): array => [
                (int) $reservation->reservation_id => $reservation->tables
                    ->pluck('table_id')
                    ->map(static fn ($tableId): int => (int) $tableId)
                    ->all(),
            ])
            ->all();

        $rangeStartUtc = $assignedReservations
            ->map(static fn (Reservation $reservation): int => $reservation->start_time->copy()->utc()->getTimestamp())
            ->min();
        $rangeEndUtc = $assignedReservations
            ->map(static fn (Reservation $reservation): int => $reservation->end_time->copy()->utc()->getTimestamp())
            ->max();

        if (! is_int($rangeStartUtc) || ! is_int($rangeEndUtc)) {
            return [];
        }

        $holds = TableHold::query()
            ->notExpired()
            ->where('start_time', '<', Carbon::createFromTimestampUTC($rangeEndUtc))
            ->where('end_time', '>', Carbon::createFromTimestampUTC($rangeStartUtc))
            ->with(['tables' => static function ($query) use ($assignedTableIds): void {
                $query->whereIn('restaurant_tables.table_id', $assignedTableIds);
            }])
            ->get();

        $holdsByTable = [];
        foreach ($holds as $hold) {
            foreach ($hold->tables as $table) {
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

        return $holdsByTable;
    }

    /**
     * @param  array<int,list<int>>  $assignedTableIdsByReservation
     */
    private function holdShouldRemainVisibleOnTable(TableHold $hold, int $tableId, array $assignedTableIdsByReservation): bool
    {
        if (($hold->hold_status?->value ?? (string) $hold->hold_status) !== 'Confirmed') {
            return true;
        }

        $reservationId = (int) ($hold->confirmed_reservation_id ?? 0);
        if ($reservationId <= 0) {
            return false;
        }

        return in_array($tableId, $assignedTableIdsByReservation[$reservationId] ?? [], true);
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

    private function resolveBranchId(mixed $branchId): ?int
    {
        if ($branchId === null || $branchId === '') {
            return null;
        }

        return $this->branchContextService->resolveBranchId($branchId);
    }
}
