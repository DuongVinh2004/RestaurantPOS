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
        // Pha 1: validate actor roi lock table de quyet dinh release tren mot snapshot nhat quan.
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        /** @var RestaurantTable $table */
        $table = $this->locks->withTableLocks([$tableId], function () use ($tableId, $staffUserId, $force, $notes, $expectedRowVersion) {
            return DB::transaction(function () use ($tableId, $staffUserId, $force, $notes, $expectedRowVersion) {
                /** @var RestaurantTable $table */
                $table = RestaurantTable::query()->where('table_id', $tableId)->lockForUpdate()->firstOrFail();
                // Branch scope duoc xac minh som de staff khong release nham table ngoai quyen.
                $tableBranchId = $this->reservationBranchScopeService->resolveTableBranchId(
                    [$table->branch_id],
                    'Table release target must belong to a single branch.',
                    'table_id',
                );
                $this->assertOperationalBranchAccessible($tableBranchId, $staffUserId);

                StaffReservationOperationGuard::assertExpectedTableRowVersion($table, $expectedRowVersion);
                StaffReservationOperationGuard::assertTableReleaseAllowed($table, $this->tableStateService, $force);

                // Pha 2: lock cac reservation active tren table de biet ban co dang trong mot service context hay khong.
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
                $blockingReservationIds = TableReleaseGuard::blockingReservationIds($activeReservations, now('UTC'));

                $activeOrderExists = DB::table('reservation_orders as ro')
                    ->join('reservation_tables as rt', 'rt.reservation_id', '=', 'ro.reservation_id')
                    ->where('rt.table_id', $tableId)
                    ->where('ro.status', ReservationOrderStatus::Active->value)
                    ->lockForUpdate()
                    ->exists();

                // Chi cho release khi khong con reservation active va khong con order active treo tren ban.
                if ($blockingReservationIds !== []) {
                    throw ValidationException::withMessages([
                        'table_id' => [
                            'Cannot release table while reservations are still in an active service context: '
                            .implode(',', $blockingReservationIds)
                            .'. Complete check-in/checkout or close the reservation flow first.',
                        ],
                    ]);
                }

                if ($activeOrderExists) {
                    throw ValidationException::withMessages([
                        'table_id' => ['Cannot release table while a live order still exists for this table. Close or settle the order first.'],
                    ]);
                }

                // Pha 3: chi khi khong con blocker moi mutate table state ve Available.
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

        // Publish sau commit de board/timeline cap nhat dung ket qua cuoi cung.
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
