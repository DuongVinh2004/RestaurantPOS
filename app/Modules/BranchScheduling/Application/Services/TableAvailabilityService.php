<?php

namespace App\Modules\BranchScheduling\Application\Services;

use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Modules\BranchScheduling\Domain\Guards\HoldConflictScope;
use App\Platform\Metrics\Services\MetricsService;
use App\Support\AvailabilityCacheVersion;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tinh tap ban kha dung cho mot khung gio, co tinh den branch policy, hold, reservation va cache.
 */
class TableAvailabilityService
{
    public function __construct(
        private readonly MetricsService $metrics,
        private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService,
    ) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return array<int, array<string,mixed>>
     */
    public function getAvailable(CarbonInterface $fromUtc, CarbonInterface $toUtc, array $filters = []): array
    {
        // Chuan hoa dau vao som de cache key va overlap query on dinh hon.
        $fromUtc = $fromUtc->copy()->utc()->second(0);
        $toUtc = $toUtc->copy()->utc()->second(0);

        $zone = isset($filters['zone']) && is_string($filters['zone']) && trim($filters['zone']) !== ''
            ? trim((string) $filters['zone'])
            : null;
        $templateId = isset($filters['template_id']) && $filters['template_id'] !== null ? (int) $filters['template_id'] : null;
        $minSeats = isset($filters['min_seats']) && $filters['min_seats'] !== null ? (int) $filters['min_seats'] : null;
        $guestCount = isset($filters['guest_count']) && $filters['guest_count'] !== null ? (int) $filters['guest_count'] : null;
        $suggest = (bool) ($filters['suggest'] ?? false);
        $sessionId = isset($filters['session_id']) && is_string($filters['session_id']) && trim($filters['session_id']) !== ''
            ? trim((string) $filters['session_id'])
            : null;
        $branchId = $this->branchSchedulingPolicyService->resolveBranchId($filters['branch_id'] ?? null);

        // Availability buffer mo rong cua so overlap de tranh booking sat mep thoi gian.
        $buffer = max(0, $this->branchSchedulingPolicyService->availabilityBufferMinutes($branchId));
        $overlapFrom = $buffer > 0 ? $fromUtc->copy()->subMinutes($buffer) : $fromUtc->copy();
        $overlapTo = $buffer > 0 ? $toUtc->copy()->addMinutes($buffer) : $toUtc->copy();

        $cachePayload = [
            'generation' => AvailabilityCacheVersion::current(),
            'branch_id' => $branchId,
            'from' => $fromUtc->toIso8601String(),
            'to' => $toUtc->toIso8601String(),
            'zone' => $zone,
            'template_id' => $templateId,
            'min_seats' => $minSeats,
            'guest_count' => $guestCount,
            'suggest' => $suggest,
            'session_hash' => $sessionId !== null ? sha1($sessionId) : null,
            'buffer' => $buffer,
        ];
        $cacheKey = 'avtbl:'.sha1(json_encode($cachePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $redis = Cache::store('redis');
        $cached = $redis->get($cacheKey);
        if (is_array($cached)) {
            $this->metrics->inc('booking_cache_hit_total', ['route' => 'v1/tables/available'], 1);

            return $cached;
        }
        $this->metrics->inc('booking_cache_miss_total', ['route' => 'v1/tables/available'], 1);

        // Neu policy da chan tu dau thi tra rong som, khong ton query availability phia sau.
        $nowUtc = Carbon::now('UTC');
        $policyEvaluation = $this->branchSchedulingPolicyService->evaluateAvailabilityWindow($branchId, $fromUtc, $toUtc, $nowUtc);
        if (($policyEvaluation['allowed'] ?? false) !== true) {
            $redis->put($cacheKey, [], 15);

            return [];
        }

        // Tap ban ban do reservation giu cho slot nay duoc tinh rieng de merge voi hold conflicts.
        $busyByReservation = DB::table('reservation_tables as rt')
            ->join('reservations as r', 'r.reservation_id', '=', 'rt.reservation_id')
            ->join('restaurant_tables as busy_tables', 'busy_tables.table_id', '=', 'rt.table_id')
            ->where('busy_tables.branch_id', $branchId)
            ->where('busy_tables.is_deleted', 0)
            ->whereIn('r.status', ReservationStatus::activeDbValues())
            ->where('r.start_time', '<', $overlapTo)
            ->where('r.end_time', '>', $overlapFrom)
            ->distinct()
            ->pluck('rt.table_id')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();

        // Hold conflicts co rule "trust same session" nen query nay duoc ap scope rieng.
        $holdQuery = DB::table('table_hold_details as thd')
            ->join('table_holds as th', 'th.hold_id', '=', 'thd.hold_id')
            ->join('restaurant_tables as busy_hold_tables', 'busy_hold_tables.table_id', '=', 'thd.table_id')
            ->where('busy_hold_tables.branch_id', $branchId)
            ->where('busy_hold_tables.is_deleted', 0)
            ->where('th.start_time', '<', $overlapTo)
            ->where('th.end_time', '>', $overlapFrom);
        HoldConflictScope::apply($holdQuery, 'th', $nowUtc);
        if ($sessionId !== null) {
            $holdQuery->where('th.session_id', '<>', $sessionId);
        }

        $busyByHold = $holdQuery->distinct()
            ->pluck('thd.table_id')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();

        $busyIds = array_values(array_unique(array_merge($busyByReservation, $busyByHold)));

        $isRealtimeAvailabilityWindow = $fromUtc->lessThanOrEqualTo($nowUtc->copy()->addMinute())
            && $toUtc->greaterThanOrEqualTo($nowUtc->copy()->subMinute());

        // Cuoi cung moi lay tap table nen va loai cac table dang busy/blocked theo ngu canh realtime hay future slot.
        $query = DB::table('restaurant_tables as t')
            ->leftJoin('table_templates as tt', 'tt.template_id', '=', 't.template_id')
            ->where('t.branch_id', $branchId)
            ->where('t.is_deleted', 0);

        if ($isRealtimeAvailabilityWindow) {
            $query->where('t.status', RestaurantTableStatus::Available->value);
        } else {
            $query->whereNotIn('t.status', [
                RestaurantTableStatus::Blocked->value,
                RestaurantTableStatus::Maintenance->value,
            ]);
        }

        if ($zone !== null) {
            $query->where('t.zone', $zone);
        }
        if ($templateId !== null) {
            $query->where('t.template_id', $templateId);
        }
        if ($minSeats !== null) {
            $query->where('tt.seats', '>=', $minSeats);
        }
        if ($guestCount !== null && ! $suggest) {
            $query->where('tt.seats', '>=', $guestCount);
        }
        if (! empty($busyIds)) {
            $query->whereNotIn('t.table_id', $busyIds);
        }

        $rows = $query->select([
            't.table_id',
            't.table_code',
            't.zone',
            't.status',
            't.price',
            't.template_id',
            DB::raw('tt.seats as seats'),
        ])
            ->orderByRaw("COALESCE(t.zone, '') ASC")
            ->orderBy('t.table_code')
            ->get();

        // Result duoc flatten thanh payload nho, on dinh cho API/cache/client.
        $result = $rows->map(fn ($row) => [
            'table_id' => (int) $row->table_id,
            'branch_id' => $branchId,
            'table_code' => (string) $row->table_code,
            'zone' => $row->zone,
            'status' => RestaurantTableStatus::Available->value,
            'price' => $row->price,
            'template_id' => $row->template_id !== null ? (int) $row->template_id : null,
            'seats' => $row->seats !== null ? (int) $row->seats : null,
        ])->values()->all();

        $redis->put($cacheKey, $result, 15);

        return $result;
    }
}
