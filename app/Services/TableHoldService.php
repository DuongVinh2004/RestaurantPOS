<?php

namespace App\Services;

use App\Models\TableHold;
use App\Services\Branch\BranchContextService;
use App\Services\Branch\BranchSchedulingPolicyService;
use App\Support\AuditEvent;
use App\Support\AvailabilityCacheVersion;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TableHoldService
{
    private readonly BranchContextService $branchContextService;
    private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService;

    public function __construct(
        private readonly ReservationLockService $lockService,
        private readonly RestaurantTableStateService $tableStateService,
        private readonly TableTimeConflictService $tableTimeConflictService,
        private readonly RuntimeSettingService $runtimeSettings,
        ?BranchContextService $branchContextService = null,
        ?BranchSchedulingPolicyService $branchSchedulingPolicyService = null,
    ) {
        $this->branchContextService = $branchContextService ?? app(BranchContextService::class);
        $this->branchSchedulingPolicyService = $branchSchedulingPolicyService ?? app(BranchSchedulingPolicyService::class);
    }

    public function expireStaleHolds(): int
    {
        $nowUtc = Carbon::now('UTC');

        $count = DB::table('table_holds')
            ->whereIn('hold_status', ['Holding', 'Pending'])
            ->where('expire_at', '<=', $nowUtc)
            ->update([
                'hold_status' => 'Expired',
                'updated_at' => $nowUtc,
                'row_version' => DB::raw('COALESCE(row_version, 1) + 1'),
            ]);

        if ($count > 0) {
            AvailabilityCacheVersion::bump();
            AuditEvent::info('table_holds_expired', ['count' => $count]);
        }

        return (int) $count;
    }

    public function createHold(array $payload, ?int $actorUserId = null): array
    {
        $this->expireStaleHolds();

        $sessionId = (string) ($payload['session_id'] ?? '');
        if ($sessionId === '') {
            throw ValidationException::withMessages(['session_id' => ['session_id là bắt buộc.']]);
        }

        // Không tin user_id từ client (chống spoofing)
        $payloadUserId = isset($payload['user_id']) ? (int) $payload['user_id'] : null;
        if ($actorUserId !== null && $payloadUserId !== null && $payloadUserId !== (int) $actorUserId) {
            throw ValidationException::withMessages([
                'user_id' => ['user_id không khớp với user đăng nhập.'],
            ]);
        }
        $userId = $actorUserId !== null ? (int) $actorUserId : null;

        $start = Carbon::parse((string) $payload['start_time'])->utc();
        $end   = Carbon::parse((string) $payload['end_time'])->utc();

        if ($end->lte($start)) {
            throw ValidationException::withMessages(['end_time' => ['end_time phải sau start_time.']]);
        }

        $durationMinutes = (int) $start->diffInMinutes($end);
        $maxDuration = (int) config('booking.hold_max_duration_minutes', 240);
        if ($durationMinutes < 1 || $durationMinutes > $maxDuration) {
            throw ValidationException::withMessages([
                'end_time' => ["Khoảng thời gian đặt bàn phải trong 1..{$maxDuration} phút."],
            ]);
        }

        $tableIds = array_values(array_unique(array_map('intval', (array) ($payload['table_ids'] ?? []))));
        sort($tableIds);
        if (count($tableIds) < 1) {
            throw ValidationException::withMessages(['table_ids' => ['table_ids là bắt buộc.']]);
        }

        $requestedBranchId = $payload['branch_id'] ?? null;
        $defaultMinutes = max(1, $this->runtimeSettings->int('hold.ttl_minutes', (int) config('booking.hold_default_minutes', 5)));
        $holdMinutes = isset($payload['hold_minutes']) ? (int) $payload['hold_minutes'] : $defaultMinutes;

        if ($holdMinutes < 1 || $holdMinutes > 60) {
            throw ValidationException::withMessages([
                'hold_minutes' => ['hold_minutes phải trong khoảng 1..60 phút.'],
            ]);
        }

        // cap TTL tổng để tránh giữ bàn vô hạn bằng refresh
        $maxTotalTtl = (int) config('booking.hold_max_total_minutes', 15);
        if ($maxTotalTtl > 0) {
            $holdMinutes = min($holdMinutes, $maxTotalTtl);
        }

        // Serialize theo table_ids (multi-instance)
        try {
            $holdId = (string) $this->lockService->withTableLocks($tableIds, function () use (
            $sessionId,
            $userId,
            $payloadUserId,
            $requestedBranchId,
            $start,
            $end,
            $durationMinutes,
            $tableIds,
            $holdMinutes
        ) {
            $this->expireStaleHolds();

            // Re-check + lock row trong DB để giảm TOCTOU trong cùng DB instance
            return DB::transaction(function () use (
                $sessionId,
                $userId,
                $payloadUserId,
                $requestedBranchId,
                $start,
                $end,
                $durationMinutes,
                $tableIds,
                $holdMinutes
            ) {
                $tables = DB::table('restaurant_tables')
                    ->whereIn('table_id', $tableIds)
                    ->lockForUpdate()
                    ->select('table_id', 'branch_id', 'status', 'is_deleted')
                    ->get();

                if ($tables->count() !== count($tableIds)) {
                    throw ValidationException::withMessages(['table_ids' => ['Có bàn không tồn tại.']]);
                }

                $deleted = $tables->where('is_deleted', 1)->pluck('table_id')->values()->all();
                if (! empty($deleted)) {
                    throw ValidationException::withMessages(['table_ids' => ['Có bàn đã bị xoá: ' . implode(',', $deleted)]]);
                }

                $nonAllocatable = $tables->filter(fn ($t) => ! $this->tableStateService->isAllocatableForBooking((string) $t->status))
                    ->pluck('table_id')
                    ->values()
                    ->all();
                if (! empty($nonAllocatable)) {
                    throw ValidationException::withMessages([
                        'table_ids' => ['Có bàn không ở trạng thái Available: ' . implode(',', $nonAllocatable)],
                    ]);
                }

                $tableBranchId = $this->branchContextService->assertSingleBranch(
                    $tables->pluck('branch_id')->all(),
                    'Selected tables must belong to a single branch.',
                    'table_ids',
                    false
                );
                if ($requestedBranchId !== null && $requestedBranchId !== '') {
                    $this->branchContextService->assertSameBranch(
                        $requestedBranchId,
                        $tableBranchId,
                        'Selected tables do not belong to the requested branch.',
                        'branch_id',
                        false
                    );
                }

                $this->branchSchedulingPolicyService->assertReservationWindowAllowed(
                    $tableBranchId,
                    $start,
                    $end,
                    'start_time',
                    null,
                    'hold',
                    false
                );

                $reservationConflictIds = $this->tableTimeConflictService->findReservationConflictTableIds(
                    tableIds: $tableIds,
                    start: $start,
                    end: $end,
                    lock: true,
                );

                if ($reservationConflictIds !== []) {
                    throw ValidationException::withMessages(['table_ids' => ['Có bàn bị trùng lịch reservation trong khoảng thời gian này: ' . implode(',', $reservationConflictIds)]]);
                }

                $existingSessionHold = $this->findReusableActiveHoldForSession(
                    sessionId: $sessionId,
                    tableIds: $tableIds,
                    start: $start,
                    end: $end,
                    lock: true,
                );
                if ($existingSessionHold !== null) {
                    return (string) $existingSessionHold->hold_id;
                }

                $sessionActiveHold = $this->findActiveHoldForSession(
                    sessionId: $sessionId,
                    holdIdToIgnore: null,
                    lock: true,
                );
                if ($sessionActiveHold !== null) {
                    throw ValidationException::withMessages([
                        'session_id' => ['Session hiện đã có hold active khác. Hãy refresh/cancel hold cũ hoặc dùng Idempotency-Key cố định để replay đúng request cũ.'],
                    ]);
                }

                $holdConflictIds = $this->tableTimeConflictService->findHoldConflictTableIds(
                    tableIds: $tableIds,
                    start: $start,
                    end: $end,
                    ignoreSessionId: $sessionId,
                    lock: true,
                );

                if ($holdConflictIds !== []) {
                    throw ValidationException::withMessages(['table_ids' => ['Có bàn đang bị giữ chỗ bởi session khác (hold còn hiệu lực): ' . implode(',', $holdConflictIds)]]);
                }

                // Nếu client gửi user_id khi không đăng nhập: ignore (đã chống spoofing);
                // nếu cần staff tạo hold cho user khác thì nên tạo endpoint riêng protected bởi staff key.

                $holdId = (string) Str::uuid();
                $nowUtc = Carbon::now('UTC');

                DB::table('table_holds')->insert([
                    'hold_id'          => $holdId,
                    'branch_id'        => $tableBranchId,
                    'session_id'       => $sessionId,
                    'user_id'          => $userId,
                    'start_time'       => $start,
                    'end_time'         => $end,
                    'duration_minutes' => $durationMinutes,
                    'hold_status'      => 'Holding',
                    'created_at'       => $nowUtc,
                    'updated_at'       => $nowUtc,
                    'expire_at'        => $nowUtc->copy()->addMinutes($holdMinutes),
                ]);

                $rows = [];
                foreach ($tableIds as $tid) {
                    $rows[] = [
                        'hold_id'  => $holdId,
                        'table_id' => $tid,
                    ];
                }
                DB::table('table_hold_details')->insert($rows);

                AuditEvent::info('table_hold_created', [
                    'hold_id' => $holdId,
                    'user_id' => $userId,
                    'payload_user_id' => $payloadUserId,
                    'session_hash' => hash_hmac('sha256', $sessionId, (string) config('app.key', 'app')),
                    'start_time_utc' => $start->toIso8601String(),
                    'end_time_utc' => $end->toIso8601String(),
                    'table_ids' => $tableIds,
                    'hold_minutes' => $holdMinutes,
                    'duration_minutes' => $durationMinutes,
                ]);

                return $holdId;
            });
        });
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }

        AvailabilityCacheVersion::bump();

        return $this->getHold($holdId);
    }

    private function findReusableActiveHoldForSession(string $sessionId, array $tableIds, Carbon $start, Carbon $end, bool $lock = false): ?object
    {
        $hold = $this->findActiveHoldForSession(sessionId: $sessionId, holdIdToIgnore: null, lock: $lock);
        if (! $hold) {
            return null;
        }

        $existingTableIds = DB::table('table_hold_details')
            ->where('hold_id', (string) $hold->hold_id)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();

        sort($existingTableIds);
        $targetTableIds = array_values(array_unique(array_map('intval', $tableIds)));
        sort($targetTableIds);

        $holdStart = Carbon::parse((string) $hold->start_time)->utc();
        $holdEnd = Carbon::parse((string) $hold->end_time)->utc();

        if ($existingTableIds !== $targetTableIds) {
            return null;
        }

        if (! $holdStart->equalTo($start) || ! $holdEnd->equalTo($end)) {
            return null;
        }

        return $hold;
    }

    private function findActiveHoldForSession(string $sessionId, ?string $holdIdToIgnore = null, bool $lock = false): ?object
    {
        $query = DB::table('table_holds')
            ->where('session_id', $sessionId)
            ->whereIn('hold_status', ['Holding', 'Pending', 'Confirmed'])
            ->where('expire_at', '>', Carbon::now('UTC'))
            ->orderByDesc('created_at');

        if ($holdIdToIgnore !== null && trim($holdIdToIgnore) !== '') {
            $query->where('hold_id', '!=', trim($holdIdToIgnore));
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function getHold(string $holdId, ?string $sessionId = null): array
    {
        $this->expireStaleHolds();

        $hold = DB::table('table_holds')->where('hold_id', $holdId)->first();
        if (! $hold) {
            throw ValidationException::withMessages(['hold_id' => ['Hold không tồn tại.']]);
        }

        if ($sessionId !== null && (string) $hold->session_id !== $sessionId) {
            throw ValidationException::withMessages(['session_id' => ['Session không hợp lệ cho hold này.']]);
        }

        $tables = DB::table('table_hold_details as thd')
            ->join('restaurant_tables as rt', 'rt.table_id', '=', 'thd.table_id')
            ->leftJoin('table_templates as tt', 'tt.template_id', '=', 'rt.template_id')
            ->where('thd.hold_id', $holdId)
            ->where('rt.is_deleted', 0)
            ->select('rt.table_id', 'rt.table_code', 'rt.zone', 'rt.status', 'rt.template_id', DB::raw('tt.seats as seats'))
            ->orderBy('rt.table_code')
            ->get()
            ->map(fn ($t) => [
                'table_id'    => (int) $t->table_id,
                'table_code'  => (string) $t->table_code,
                'zone'        => $t->zone,
                'status'      => (string) $t->status,
                'template_id' => $t->template_id !== null ? (int) $t->template_id : null,
                'seats'       => $t->seats !== null ? (int) $t->seats : null,
            ])
            ->values()
            ->all();

        $branchId = $hold->branch_id ?? null;

        return [
            'hold_id'     => (string) $hold->hold_id,
            'branch_id'   => $branchId !== null ? (int) $branchId : null,
            'session_id'  => (string) $hold->session_id,
            'user_id'     => $hold->user_id !== null ? (int) $hold->user_id : null,
            'start_time'  => $hold->start_time ? Carbon::parse($hold->start_time)->utc() : null,
            'end_time'    => $hold->end_time ? Carbon::parse($hold->end_time)->utc() : null,
            'duration_minutes' => $hold->duration_minutes !== null ? (int) $hold->duration_minutes : null,
            'hold_status' => (string) $hold->hold_status,
            'confirmed_reservation_id' => $hold->confirmed_reservation_id !== null ? (int) $hold->confirmed_reservation_id : null,
            'row_version' => $hold->row_version !== null ? (int) $hold->row_version : null,
            'created_at'  => $hold->created_at ? Carbon::parse($hold->created_at)->utc() : null,
            'updated_at'  => $hold->updated_at ? Carbon::parse($hold->updated_at)->utc() : null,
            'expire_at'   => $hold->expire_at ? Carbon::parse($hold->expire_at)->utc() : null,
            'tables'      => $tables,
        ];
    }

    public function cancelHold(
        string $holdId,
        ?string $sessionId = null,
        bool $allowStaffOverride = false,
        ?int $expectedRowVersion = null,
        ?int $actorUserId = null,
    ): array
    {
        $this->expireStaleHolds();

        $sessionId = $sessionId !== null ? trim($sessionId) : null;

        try {
            DB::transaction(function () use ($holdId, $sessionId, $allowStaffOverride, $expectedRowVersion, $actorUserId) {
            $hold = DB::table('table_holds')->where('hold_id', $holdId)->lockForUpdate()->first();
            if (! $hold) {
                throw ValidationException::withMessages(['hold_id' => ['Hold không tồn tại.']]);
            }

            if (! $allowStaffOverride && (string) $hold->session_id !== (string) $sessionId) {
                throw ValidationException::withMessages(['session_id' => ['session_id không khớp với hold.']]);
            }

            $this->assertHoldRowVersion($hold, $expectedRowVersion);

            if ((string) $hold->hold_status === 'Confirmed' || $hold->confirmed_reservation_id !== null) {
                throw ValidationException::withMessages(['hold_status' => ['Hold đã được confirm thành reservation, không thể huỷ trực tiếp.']]);
            }

            if (! in_array((string) $hold->hold_status, ['Holding', 'Pending'], true)) {
                throw ValidationException::withMessages(['hold_status' => ['Hold đã hết hạn, đã bị huỷ hoặc không còn có thể huỷ.']]);
            }

            $holdModel = TableHold::query()->whereKey($holdId)->lockForUpdate()->first();
if (! $holdModel) {
    throw ValidationException::withMessages([
        'hold_id' => ['Hold không tồn tại.'],
    ]);
}

$holdModel->hold_status = 'Cancelled';
$holdModel->updated_by = $actorUserId;
$holdModel->updated_at = Carbon::now('UTC');
$holdModel->save();
        });
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }

        AvailabilityCacheVersion::bump();

        AuditEvent::info('table_hold_cancelled', [
            'hold_id' => $holdId,
            'session_hash' => $sessionId ? hash_hmac('sha256', $sessionId, (string) config('app.key', 'app')) : null,
            'staff_override' => $allowStaffOverride,
            'actor_user_id' => $actorUserId,
        ]);

        return $this->getHold($holdId);
    }

    public function refreshHold(
        string $holdId,
        ?string $sessionId = null,
        int $extendMinutes = 5,
        bool $allowStaffOverride = false,
        ?int $expectedRowVersion = null,
        ?int $actorUserId = null,
    ): array
    {
        $this->expireStaleHolds();

        $hold = DB::table('table_holds')->where('hold_id', $holdId)->first();
        if (! $hold) {
            throw ValidationException::withMessages(['hold_id' => ['Hold không tồn tại.']]);
        }

        $sessionId = $sessionId !== null ? trim($sessionId) : null;
        if (! $allowStaffOverride && (string) $hold->session_id !== (string) $sessionId) {
            throw ValidationException::withMessages(['session_id' => ['session_id không khớp với hold.']]);
        }

        if (! in_array((string) $hold->hold_status, ['Holding', 'Pending'], true)) {
            throw ValidationException::withMessages(['hold_status' => ['Hold không ở trạng thái có thể gia hạn.']]);
        }

        if ($extendMinutes < 1 || $extendMinutes > 60) {
            throw ValidationException::withMessages(['extend_minutes' => ['extend_minutes phải trong khoảng 1..60 phút.']]);
        }

        $tableIds = DB::table('table_hold_details')
            ->where('hold_id', $holdId)
            ->pluck('table_id')
            ->map(fn ($x) => (int) $x)
            ->values()
            ->all();

        if (empty($tableIds)) {
            throw ValidationException::withMessages(['hold_id' => ['Hold không có table_ids.']]);
        }

        sort($tableIds);

        try {
            $this->lockService->withTableLocks($tableIds, function () use ($holdId, $sessionId, $extendMinutes, $allowStaffOverride, $expectedRowVersion, $actorUserId) {
            DB::transaction(function () use ($holdId, $sessionId, $extendMinutes, $allowStaffOverride, $expectedRowVersion, $actorUserId) {
                $hold = DB::table('table_holds')->where('hold_id', $holdId)->lockForUpdate()->first();
                if (! $hold) {
                    throw ValidationException::withMessages(['hold_id' => ['Hold không tồn tại.']]);
                }

                if (! $allowStaffOverride && (string) $hold->session_id !== (string) $sessionId) {
                    throw ValidationException::withMessages(['session_id' => ['session_id không khớp với hold.']]);
                }

                $this->assertHoldRowVersion($hold, $expectedRowVersion);

                if (! in_array((string) $hold->hold_status, ['Holding', 'Pending'], true)) {
                    throw ValidationException::withMessages(['hold_status' => ['Hold không ở trạng thái có thể gia hạn.']]);
                }

                $nowUtc = Carbon::now('UTC');
                $createdAt = $hold->created_at ? Carbon::parse($hold->created_at)->utc() : $nowUtc->copy();

                $maxTotalTtl = (int) config('booking.hold_max_total_minutes', 15);
                $maxExpireAt = $createdAt->copy()->addMinutes(max(1, $maxTotalTtl));

                if ($nowUtc->gte($maxExpireAt)) {
                    throw ValidationException::withMessages([
                        'expire_at' => ['Hold đã vượt quá TTL tối đa, hãy tạo hold mới.'],
                    ]);
                }

                $currentExpireAt = $hold->expire_at ? Carbon::parse((string) $hold->expire_at)->utc() : null;
                $extensionBase = $currentExpireAt !== null && $currentExpireAt->greaterThan($nowUtc)
                    ? $currentExpireAt->copy()
                    : $nowUtc->copy();
                $desired = $extensionBase->copy()->addMinutes($extendMinutes);
                $newExpireAt = $desired->lt($maxExpireAt) ? $desired : $maxExpireAt;

                if ($currentExpireAt !== null && $newExpireAt->equalTo($currentExpireAt)) {
                    AuditEvent::info('table_hold_refresh_noop', [
                        'hold_id' => $holdId,
                        'extend_minutes' => $extendMinutes,
                        'current_expire_at_utc' => $currentExpireAt->toIso8601String(),
                        'max_expire_at_utc' => $maxExpireAt->toIso8601String(),
                        'staff_override' => $allowStaffOverride,
                        'actor_user_id' => $actorUserId,
                    ]);

                    return;
                }

                $holdModel = TableHold::query()->whereKey($holdId)->lockForUpdate()->first();
                if (! $holdModel) {
                    throw ValidationException::withMessages(['hold_id' => ['Hold không tồn tại.']]);
                }

                $holdModel->expire_at = $newExpireAt;
                $holdModel->updated_by = $actorUserId;
                $holdModel->updated_at = $nowUtc;
                $holdModel->save();

                AuditEvent::info('table_hold_refreshed', [
                    'hold_id' => $holdId,
                    'extend_minutes' => $extendMinutes,
                    'new_expire_at_utc' => $newExpireAt->toIso8601String(),
                    'max_expire_at_utc' => $maxExpireAt->toIso8601String(),
                    'staff_override' => $allowStaffOverride,
                    'actor_user_id' => $actorUserId,
                ]);
            });
        });
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }

        AvailabilityCacheVersion::bump();

        return $this->getHold($holdId);
    }

    private function assertHoldRowVersion(?object $hold, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null || $hold === null) {
            return;
        }

        if ((int) ($hold->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }
}
