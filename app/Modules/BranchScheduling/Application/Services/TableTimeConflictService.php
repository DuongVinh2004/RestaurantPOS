<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Application\Services;

use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Domain\Guards\HoldConflictScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tim danh sach ban dang va cham theo reservation hoac hold trong mot khoang thoi gian.
 */
class TableTimeConflictService
{
    /**
     * @param  array<int,int>  $tableIds
     * @return array<int,int>
     */
    public function findReservationConflictTableIds(
        array $tableIds,
        Carbon $start,
        Carbon $end,
        ?int $ignoreReservationId = null,
        bool $lock = false,
    ): array {
        // Dung cho booking da xac nhan; ket qua tra ve la table id de caller tu quyet cach bao loi.
        $tableIds = $this->normalizeTableIds($tableIds);
        if ($tableIds === []) {
            return [];
        }

        // Reservation conflict query chi can tra ve table ids, de caller tu quyet cach xu ly/block message.
        $query = DB::table('reservation_tables as rt')
            ->join('reservations as r', 'r.reservation_id', '=', 'rt.reservation_id')
            ->whereIn('rt.table_id', $tableIds)
            ->whereIn('r.status', ReservationStatus::activeDbValues())
            ->where('r.start_time', '<', $end)
            ->where('r.end_time', '>', $start);

        if ($ignoreReservationId !== null && $ignoreReservationId > 0) {
            $query->where('r.reservation_id', '!=', $ignoreReservationId);
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->select('rt.table_id')
            ->distinct()
            ->limit(100)
            ->pluck('rt.table_id')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();
    }

    /**
     * @param  array<int,int>  $tableIds
     * @param  array<int,string>  $ignoredHoldIds
     * @return array<int,int>
     */
    public function findHoldConflictTableIds(
        array $tableIds,
        Carbon $start,
        Carbon $end,
        array $ignoredHoldIds = [],
        ?string $ignoreSessionId = null,
        bool $lock = false,
        ?int $ignoreConfirmedReservationId = null,
    ): array {
        // Trusted hold cho phep bo qua hold cua chinh session hien tai khi dang tiep tuc flow.
        $tableIds = $this->normalizeTableIds($tableIds);
        if ($tableIds === []) {
            return [];
        }

        // Hold conflict query co them trusted hold/session-ignore hooks de phuc vu continue-flow scenarios.
        $query = DB::table('table_hold_details as thd')
            ->join('table_holds as th', 'th.hold_id', '=', 'thd.hold_id')
            ->whereIn('thd.table_id', $tableIds)
            ->where('th.start_time', '<', $end)
            ->where('th.end_time', '>', $start);

        HoldConflictScope::apply($query, 'th', Carbon::now('UTC'));

        $ignoredHoldIds = array_values(array_unique(array_filter(array_map('strval', $ignoredHoldIds), static fn (string $value) => $value !== '')));
        if ($ignoredHoldIds !== []) {
            $query->whereNotIn('th.hold_id', $ignoredHoldIds);
        }

        $ignoreSessionId = $ignoreSessionId !== null ? trim($ignoreSessionId) : null;
        if ($ignoreSessionId !== null && $ignoreSessionId !== '') {
            $query->where('th.session_id', '<>', $ignoreSessionId);
        }

        if ($ignoreConfirmedReservationId !== null && $ignoreConfirmedReservationId > 0) {
            $query->where(function ($subQuery) use ($ignoreConfirmedReservationId) {
                $subQuery->whereNull('th.confirmed_reservation_id')
                    ->orWhere('th.confirmed_reservation_id', '<>', $ignoreConfirmedReservationId);
            });
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->select('thd.table_id')
            ->distinct()
            ->limit(100)
            ->pluck('thd.table_id')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();
    }

    /**
     * @param  array<int,int>  $tableIds
     * @return array<int,int>
     */
    private function normalizeTableIds(array $tableIds): array
    {
        $tableIds = array_values(array_unique(array_map('intval', $tableIds)));
        sort($tableIds);

        return $tableIds;
    }
}
