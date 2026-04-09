<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Models\RestaurantTable;
use App\Services\RestaurantTableStateService;
use App\Services\TableTimeConflictService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationConflictValidator
{
    public function __construct(
        private readonly TableTimeConflictService $tableTimeConflictService,
        private readonly RestaurantTableStateService $tableStateService,
    ) {
    }

    /**
     * @param list<int> $tableIds
     */
    public function lockAndLoadTables(array $tableIds): Collection
    {
        return RestaurantTable::query()
            ->whereIn('table_id', $tableIds)
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param Collection<int,RestaurantTable> $tables
     */
    public function assertTablesAllocatableAndCapacity(Collection $tables, array $tableIds, int $guestCount): void
    {
        if ($tables->count() !== count($tableIds)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Có bàn không tồn tại.'],
            ]);
        }

        $deletedTables = $tables->where('is_deleted', 1)->pluck('table_id')->values()->all();
        if (! empty($deletedTables)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Có bàn đã bị xoá: ' . implode(',', $deletedTables)],
            ]);
        }

        $nonAllocatable = $tables->filter(fn ($t) => ! $this->tableStateService->isAllocatableForBooking((string) ($t->status?->value ?? $t->status)))
            ->pluck('table_id')->values()->all();
        if (! empty($nonAllocatable)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Có bàn không ở trạng thái Available: ' . implode(',', $nonAllocatable)],
            ]);
        }

        $this->assertCapacityEnough($tables, $guestCount);
    }

    /**
     * @param list<int> $tableIds
     * @param list<string> $trustedHoldIds
     */
    public function assertNoCreateConflicts(array $tableIds, Carbon $startUtc, Carbon $endUtc, array $trustedHoldIds = []): void
    {
        $holdConflicts = $this->tableTimeConflictService->findHoldConflictTableIds($tableIds, $startUtc, $endUtc, $trustedHoldIds, null, true);
        if (! empty($holdConflicts)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Bàn đang bị giữ chỗ bởi session khác: ' . implode(',', $holdConflicts)],
            ]);
        }

        $conflictTableIds = $this->tableTimeConflictService->findReservationConflictTableIds($tableIds, $startUtc, $endUtc, null, true);
        if (! empty($conflictTableIds)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Bàn bị trùng lịch (overlap reservation): ' . implode(',', $conflictTableIds)],
            ]);
        }
    }

    /**
     * @param Collection<int,RestaurantTable> $tables
     */
    private function assertCapacityEnough(Collection $tables, int $guestCount): void
    {
        $nullTemplate = $tables->whereNull('template_id')->pluck('table_id')->values()->all();
        if (! empty($nullTemplate)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Các bàn thiếu template_id (không tính được seats): ' . implode(',', $nullTemplate)],
            ]);
        }

        $templateIds = $tables->pluck('template_id')->unique()->values()->all();
        $seatsByTemplate = DB::table('table_templates')
            ->whereIn('template_id', $templateIds)
            ->pluck('seats', 'template_id');

        $missingTemplates = [];
        $totalSeats = 0;
        foreach ($tables as $t) {
            $tid = (int) $t->template_id;
            if (! $seatsByTemplate->has($tid)) {
                $missingTemplates[] = $tid;
                continue;
            }
            $totalSeats += (int) $seatsByTemplate->get($tid);
        }

        if (! empty($missingTemplates)) {
            $missingTemplates = array_values(array_unique($missingTemplates));
            throw ValidationException::withMessages([
                'table_ids' => ['Template không tồn tại để tính seats: ' . implode(',', $missingTemplates)],
            ]);
        }

        if ($guestCount > $totalSeats) {
            throw ValidationException::withMessages([
                'guest_count' => ["Số khách ($guestCount) vượt quá sức chứa ($totalSeats seats) của các bàn đã chọn."],
            ]);
        }
    }
}
