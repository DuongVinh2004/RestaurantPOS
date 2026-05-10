<?php

namespace App\Modules\BranchScheduling\Application\Services;

use App\Enums\TableHoldStatus;
use App\Modules\BranchScheduling\Domain\Models\TableHold;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
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

    // --- BƯỚC 1: DỌN DẸP DỮ LIỆU HẾT HẠN (GARBAGE COLLECTION) ---
    public function expireStaleHolds(): int
    {
        $nowUtc = Carbon::now('UTC');

        // Scheduler-style expiry is an explicit maintenance path guarded by the direct-write contract test.
        // Nghiệp vụ: Mỗi khách hàng khi chọn bàn sẽ được cấp một "thời gian giữ chỗ" (TTL - ví dụ 5 phút) để điền thông tin và thanh toán.
        // Nếu qua 5 phút mà khách chưa thanh toán, hệ thống sẽ tự động chuyển trạng thái từ "Holding" sang "Expired"
        // để nhả bàn cho khách khác đặt.
        $count = DB::table('table_holds')
            ->whereIn('hold_status', ['Holding', 'Pending'])
            ->where('expire_at', '<=', $nowUtc)
            ->update([
                'hold_status' => 'Expired',
                'updated_at' => $nowUtc,
                // [BEST PRACTICE]: Raw SQL Atomic Increment
                // Tăng phiên bản dòng dữ liệu trực tiếp bằng DB::raw để tránh việc phải kéo dữ liệu lên PHP (SELECT) rồi mới lưu xuống (UPDATE).
                // Giúp dọn dẹp hàng ngàn Hold hết hạn cùng lúc một cách cực kỳ nhanh chóng.
                'row_version' => DB::raw('COALESCE(row_version, 1) + 1'),
            ]);

        if ($count > 0) {
            AvailabilityCacheVersion::bump(); // Báo hiệu đã có bàn trống mới, yêu cầu xóa cache tìm bàn trống.
            AuditEvent::info('table_holds_expired', ['count' => $count]);
        }

        return (int) $count;
    }

    // --- BƯỚC 2: TIẾP NHẬN YÊU CẦU GIỮ BÀN (CREATE HOLD) ---
    public function createHold(array $payload, ?int $actorUserId = null): array
    {
        // Luôn dọn dẹp các Hold đã chết trước khi tạo Hold mới để đảm bảo tính sẵn sàng tối đa
        $this->expireStaleHolds();

        $sessionId = (string) ($payload['session_id'] ?? '');
        if ($sessionId === '') {
            throw ValidationException::withMessages(['session_id' => ['session_id is required.']]);
        }

        // [BEST PRACTICE]: Actor Spoofing Prevention (Chống giả mạo danh tính)
        // Never trust client-supplied user_id to avoid actor spoofing.
        // Khách hàng có thể cố tình sửa gói tin gửi lên máy chủ để "giữ bàn hộ" một user ID khác.
        // Hệ thống sẽ kiểm tra đối chiếu (Cross-check) giữa Token đăng nhập ($actorUserId) và ID do FE gửi lên.
        $payloadUserId = isset($payload['user_id']) ? (int) $payload['user_id'] : null;
        if ($actorUserId !== null && $payloadUserId !== null && $payloadUserId !== (int) $actorUserId) {
            throw ValidationException::withMessages([
                'user_id' => ['user_id does not match the authenticated user.'],
            ]);
        }
        $userId = $actorUserId !== null ? (int) $actorUserId : null;

        $start = Carbon::parse((string) $payload['start_time'])->utc();
        $end = Carbon::parse((string) $payload['end_time'])->utc();

        if ($end->lte($start)) {
            throw ValidationException::withMessages(['end_time' => ['end_time must be after start_time.']]);
        }

        $durationMinutes = (int) $start->diffInMinutes($end);
        $maxDuration = (int) config('booking.hold_max_duration_minutes', 240);
        if ($durationMinutes < 1 || $durationMinutes > $maxDuration) {
            throw ValidationException::withMessages([
                'end_time' => ["Hold window must be between 1 and {$maxDuration} minutes."],
            ]);
        }

        $tableIds = array_values(array_unique(array_map('intval', (array) ($payload['table_ids'] ?? []))));
        sort($tableIds); // Tránh Deadlock khi lock nhiều bảng
        if (count($tableIds) < 1) {
            throw ValidationException::withMessages(['table_ids' => ['table_ids is required.']]);
        }

        $requestedBranchId = $payload['branch_id'] ?? null;
        $defaultMinutes = max(1, $this->runtimeSettings->int('hold.ttl_minutes', (int) config('booking.hold_default_minutes', 5)));
        $holdMinutes = isset($payload['hold_minutes']) ? (int) $payload['hold_minutes'] : $defaultMinutes;

        if ($holdMinutes < 1 || $holdMinutes > 60) {
            throw ValidationException::withMessages([
                'hold_minutes' => ['hold_minutes must be between 1 and 60 minutes.'],
            ]);
        }

        // Cap the total TTL so refreshes cannot hold tables indefinitely.
        $maxTotalTtl = (int) config('booking.hold_max_total_minutes', 15);
        if ($maxTotalTtl > 0) {
            $holdMinutes = min($holdMinutes, $maxTotalTtl);
        }

        // Serialize theo table_ids (multi-instance)
        try {
            // [BEST PRACTICE]: Phân lớp Lock an toàn (Nested Lock Abstraction)
            // Lệnh withTableLocks() sẽ khóa toàn bộ các Table ID lại theo đúng thứ tự (đã sort ở trên).
            // Đảm bảo không có luồng khác chen vào giữa lúc đang kiểm tra điều kiện.
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

                // Re-check and lock rows inside the DB transaction to reduce TOCTOU risk.
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
                    // Bước 2.1: Kiểm tra tính vật lý của Bàn
                    $tables = DB::table('restaurant_tables')
                        ->whereIn('table_id', $tableIds)
                        ->lockForUpdate()
                        ->select('table_id', 'branch_id', 'status', 'is_deleted')
                        ->get();

                    if ($tables->count() !== count($tableIds)) {
                        throw ValidationException::withMessages(['table_ids' => ['Some selected tables do not exist.']]);
                    }

                    $deleted = $tables->where('is_deleted', 1)->pluck('table_id')->values()->all();
                    if (! empty($deleted)) {
                        throw ValidationException::withMessages(['table_ids' => ['Some selected tables were deleted: '.implode(',', $deleted)]]);
                    }

                    // Bước 2.2: Kiểm tra trạng thái hiện tại (Realtime/Future Status)
                    $nowUtc = Carbon::now('UTC');
                    $isRealtimeHoldWindow = $start->lessThanOrEqualTo($nowUtc->copy()->addMinute())
                        && $end->greaterThanOrEqualTo($nowUtc->copy()->subMinute());
                    $nonAllocatable = $tables->filter(function ($t) use ($isRealtimeHoldWindow): bool {
                        $status = (string) $t->status;

                        if ($isRealtimeHoldWindow) {
                            return ! $this->tableStateService->isAllocatableForBooking($status);
                        }

                        return $this->tableStateService->isOperationallyBlocked($status);
                    })
                        ->pluck('table_id')
                        ->values()
                        ->all();
                    if (! empty($nonAllocatable)) {
                        throw ValidationException::withMessages([
                            'table_ids' => ['Some selected tables cannot be held for this time window: '.implode(',', $nonAllocatable)],
                        ]);
                    }

                    // Bước 2.3: Chặn việc Giữ Bàn chéo chi nhánh
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

                    // Bước 2.4: Kiểm duyệt bằng Bộ quy tắc (Policy Gate)
                    $this->branchSchedulingPolicyService->assertReservationWindowAllowed(
                        $tableBranchId,
                        $start,
                        $end,
                        'start_time',
                        null,
                        'hold',
                        false
                    );

                    // Bước 2.5: Kiểm tra xem Bàn này có bị ai đó Đặt (Reservation) trước chưa
                    $reservationConflictIds = $this->tableTimeConflictService->findReservationConflictTableIds(
                        tableIds: $tableIds,
                        start: $start,
                        end: $end,
                        lock: true,
                    );

                    if ($reservationConflictIds !== []) {
                        throw ValidationException::withMessages(['table_ids' => ['Some selected tables already have an overlapping reservation in this time window: '.implode(',', $reservationConflictIds)]]);
                    }

                    // Bước 2.6: Tái sử dụng phiên giao dịch (Idempotency / Session Continuity)
                    // Nếu khách hàng click "Next", xong ấn "Back", rồi lại ấn "Next" vào cùng một cái bàn.
                    // Việc này sẽ không tạo ra 2 lệnh Hold mới, mà hệ thống sẽ tái sử dụng ID của lệnh Hold trước đó.
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

                    // Nếu khách đổi ý: Chọn bàn A, nhưng sau đó hủy chọn và chuyển sang chọn Bàn B.
                    // Hệ thống sẽ âm thầm hủy lệnh Hold của bàn A trước, sau đó mới cho phép tạo lệnh Hold Bàn B.
                    $sessionActiveHold = $this->findActiveHoldForSession(
                        sessionId: $sessionId,
                        holdIdToIgnore: null,
                        lock: true,
                    );
                    if ($sessionActiveHold !== null) {
                        $this->cancelSessionHoldForReplacement(
                            $sessionActiveHold,
                            $sessionId,
                            $userId,
                            $tableIds,
                            $start,
                            $end,
                        );
                    }

                    // Bước 2.7: Kiểm tra xem Bàn này có bị người khác giữ (Hold) trước chưa
                    $holdConflictIds = $this->tableTimeConflictService->findHoldConflictTableIds(
                        tableIds: $tableIds,
                        start: $start,
                        end: $end,
                        ignoreSessionId: $sessionId,
                        lock: true,
                    );

                    if ($holdConflictIds !== []) {
                        throw ValidationException::withMessages(['table_ids' => ['Some selected tables are held by another session and the hold is still active: '.implode(',', $holdConflictIds)]]);
                    }

                    // Ignore client-supplied user_id for anonymous sessions; protected staff flows should
                    // use a dedicated endpoint when acting on behalf of another customer.

                    // BƯỚC 2.8: GHI NHẬN THÀNH CÔNG VÀ LƯU DATABASE
                    $holdId = (string) Str::uuid();
                    $hold = new TableHold;
                    $hold->hold_id = $holdId;
                    $hold->branch_id = $tableBranchId;
                    $hold->session_id = $sessionId;
                    $hold->user_id = $userId;
                    $hold->start_time = $start;
                    $hold->end_time = $end;
                    $hold->duration_minutes = $durationMinutes;
                    $hold->hold_status = 'Holding';
                    $hold->created_at = $nowUtc;
                    $hold->updated_at = $nowUtc;
                    $hold->expire_at = $nowUtc->copy()->addMinutes($holdMinutes); // Cài đặt đồng hồ đếm ngược
                    $hold->save();

                    $rows = [];
                    foreach ($tableIds as $tid) {
                        $rows[] = [
                            'hold_id' => $holdId,
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
            // Biến lỗi khóa SQL thô kệch thành câu văn dễ hiểu cho Frontend
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }

        AvailabilityCacheVersion::bump();

        return $this->getHold($holdId);
    }

    // --- CÁC HÀM TIỆN ÍCH DÙNG TRONG BƯỚC 2 ---
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
            // Confirmed holds are historical linkage rows after reservation creation.
            // They must not block new live holds for the same session.
            ->whereIn('hold_status', ['Holding', 'Pending'])
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

    /**
     * @param  list<int>  $replacementTableIds
     */
    private function cancelSessionHoldForReplacement(
        object $hold,
        string $sessionId,
        ?int $actorUserId,
        array $replacementTableIds,
        Carbon $replacementStart,
        Carbon $replacementEnd,
    ): void {
        if ((string) ($hold->session_id ?? '') !== $sessionId) {
            throw ValidationException::withMessages([
                'session_id' => ['session_id does not match the active hold being replaced.'],
            ]);
        }

        if (! in_array($this->tableHoldStatusValue($hold->hold_status ?? ''), ['Holding', 'Pending'], true)) {
            return;
        }

        $holdModel = TableHold::query()
            ->whereKey((string) $hold->hold_id)
            ->lockForUpdate()
            ->first();

        if (! $holdModel) {
            throw ValidationException::withMessages(['hold_id' => ['Hold does not exist.']]);
        }

        if (! in_array($this->tableHoldStatusValue($holdModel->hold_status), ['Holding', 'Pending'], true)) {
            return;
        }

        $holdModel->hold_status = TableHoldStatus::Cancelled;
        $holdModel->updated_by = $actorUserId;
        $holdModel->updated_at = Carbon::now('UTC');
        $holdModel->save();

        AuditEvent::info('table_hold_replaced', [
            'hold_id' => (string) $holdModel->hold_id,
            'replacement_table_ids' => $replacementTableIds,
            'replacement_start_time_utc' => $replacementStart->toIso8601String(),
            'replacement_end_time_utc' => $replacementEnd->toIso8601String(),
            'session_hash' => hash_hmac('sha256', $sessionId, (string) config('app.key', 'app')),
            'actor_user_id' => $actorUserId,
        ]);
    }

    private function tableHoldStatusValue(mixed $status): string
    {
        return $status instanceof TableHoldStatus ? $status->value : (string) $status;
    }

    // --- BƯỚC 3: LẤY THÔNG TIN, HỦY VÀ GIA HẠN LỆNH GIỮ BÀN ---

    public function getHold(string $holdId, ?string $sessionId = null): array
    {
        $this->expireStaleHolds();

        $hold = DB::table('table_holds')->where('hold_id', $holdId)->first();
        if (! $hold) {
            throw ValidationException::withMessages(['hold_id' => ['Hold does not exist.']]);
        }

        if ($sessionId !== null && (string) $hold->session_id !== $sessionId) {
            throw ValidationException::withMessages(['session_id' => ['Session is not authorized for this hold.']]);
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
                'table_id' => (int) $t->table_id,
                'table_code' => (string) $t->table_code,
                'zone' => $t->zone,
                'status' => (string) $t->status,
                'template_id' => $t->template_id !== null ? (int) $t->template_id : null,
                'seats' => $t->seats !== null ? (int) $t->seats : null,
            ])
            ->values()
            ->all();

        $branchId = $hold->branch_id ?? null;

        return [
            'hold_id' => (string) $hold->hold_id,
            'branch_id' => $branchId !== null ? (int) $branchId : null,
            'session_id' => (string) $hold->session_id,
            'user_id' => $hold->user_id !== null ? (int) $hold->user_id : null,
            'start_time' => $hold->start_time ? Carbon::parse($hold->start_time)->utc() : null,
            'end_time' => $hold->end_time ? Carbon::parse($hold->end_time)->utc() : null,
            'duration_minutes' => $hold->duration_minutes !== null ? (int) $hold->duration_minutes : null,
            'hold_status' => (string) $hold->hold_status,
            'confirmed_reservation_id' => $hold->confirmed_reservation_id !== null ? (int) $hold->confirmed_reservation_id : null,
            'row_version' => $hold->row_version !== null ? (int) $hold->row_version : null,
            'created_at' => $hold->created_at ? Carbon::parse($hold->created_at)->utc() : null,
            'updated_at' => $hold->updated_at ? Carbon::parse($hold->updated_at)->utc() : null,
            'expire_at' => $hold->expire_at ? Carbon::parse($hold->expire_at)->utc() : null,
            'tables' => $tables,
        ];
    }

    public function cancelHold(
        string $holdId,
        ?string $sessionId = null,
        bool $allowStaffOverride = false,
        ?int $expectedRowVersion = null,
        ?int $actorUserId = null,
    ): array {
        $this->expireStaleHolds();

        $sessionId = $sessionId !== null ? trim($sessionId) : null;

        try {
            DB::transaction(function () use ($holdId, $sessionId, $allowStaffOverride, $expectedRowVersion, $actorUserId) {
                $hold = DB::table('table_holds')->where('hold_id', $holdId)->lockForUpdate()->first();
                if (! $hold) {
                    throw ValidationException::withMessages(['hold_id' => ['Hold does not exist.']]);
                }

                if (! $allowStaffOverride && (string) $hold->session_id !== (string) $sessionId) {
                    throw ValidationException::withMessages(['session_id' => ['session_id does not match the hold.']]);
                }

                $this->assertHoldRowVersion($hold, $expectedRowVersion);

                if ((string) $hold->hold_status === 'Confirmed' || $hold->confirmed_reservation_id !== null) {
                    throw ValidationException::withMessages(['hold_status' => ['Hold has already been confirmed into a reservation and cannot be cancelled directly.']]);
                }

                if (! in_array((string) $hold->hold_status, ['Holding', 'Pending'], true)) {
                    throw ValidationException::withMessages(['hold_status' => ['Hold has expired, was cancelled, or can no longer be cancelled.']]);
                }

                $holdModel = TableHold::query()->whereKey($holdId)->lockForUpdate()->first();
                if (! $holdModel) {
                    throw ValidationException::withMessages([
                        'hold_id' => ['Hold does not exist.'],
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
    ): array {
        // Nghiệp vụ: Tâm lý khách hàng khi thanh toán thường hay nấn ná.
        // Thay vì ép buộc họ phải thanh toán xong ngay trong 5 phút (nếu không hủy bàn),
        // thì mỗi khi họ tương tác trên giao diện, Frontend sẽ gọi API này để "Gia hạn" (Refresh)
        // thêm 5 phút nữa, giữ cho chiếc bàn tiếp tục thuộc về họ một cách mượt mà.
        $this->expireStaleHolds();

        $hold = DB::table('table_holds')->where('hold_id', $holdId)->first();
        if (! $hold) {
            throw ValidationException::withMessages(['hold_id' => ['Hold does not exist.']]);
        }

        $sessionId = $sessionId !== null ? trim($sessionId) : null;
        if (! $allowStaffOverride && (string) $hold->session_id !== (string) $sessionId) {
            throw ValidationException::withMessages(['session_id' => ['session_id does not match the hold.']]);
        }

        if (! in_array((string) $hold->hold_status, ['Holding', 'Pending'], true)) {
            throw ValidationException::withMessages(['hold_status' => ['Hold is not in a state that can be refreshed.']]);
        }

        if ($extendMinutes < 1 || $extendMinutes > 60) {
            throw ValidationException::withMessages(['extend_minutes' => ['extend_minutes must be between 1 and 60 minutes.']]);
        }

        $tableIds = DB::table('table_hold_details')
            ->where('hold_id', $holdId)
            ->pluck('table_id')
            ->map(fn ($x) => (int) $x)
            ->values()
            ->all();

        if (empty($tableIds)) {
            throw ValidationException::withMessages(['hold_id' => ['Hold has no table_ids.']]);
        }

        sort($tableIds);

        try {
            $this->lockService->withTableLocks($tableIds, function () use ($holdId, $sessionId, $extendMinutes, $allowStaffOverride, $expectedRowVersion, $actorUserId) {
                DB::transaction(function () use ($holdId, $sessionId, $extendMinutes, $allowStaffOverride, $expectedRowVersion, $actorUserId) {
                    $hold = DB::table('table_holds')->where('hold_id', $holdId)->lockForUpdate()->first();
                    if (! $hold) {
                        throw ValidationException::withMessages(['hold_id' => ['Hold does not exist.']]);
                    }

                    if (! $allowStaffOverride && (string) $hold->session_id !== (string) $sessionId) {
                        throw ValidationException::withMessages(['session_id' => ['session_id does not match the hold.']]);
                    }

                    $this->assertHoldRowVersion($hold, $expectedRowVersion);

                    if (! in_array((string) $hold->hold_status, ['Holding', 'Pending'], true)) {
                        throw ValidationException::withMessages(['hold_status' => ['Hold is not in a state that can be refreshed.']]);
                    }

                    $nowUtc = Carbon::now('UTC');
                    $createdAt = $hold->created_at ? Carbon::parse($hold->created_at)->utc() : $nowUtc->copy();

                    // [BEST PRACTICE]: TTL Hard Ceiling (Trần bảo vệ thời gian sống)
                    // Dù có gia hạn bao nhiêu lần đi nữa, tổng thời gian giữ bàn (tính từ lúc bắt đầu click chọn)
                    // cũng không bao giờ được vượt quá một ngưỡng nhất định (ví dụ 15 phút), để ngăn khách "câu giờ".
                    $maxTotalTtl = (int) config('booking.hold_max_total_minutes', 15);
                    $maxExpireAt = $createdAt->copy()->addMinutes(max(1, $maxTotalTtl));

                    if ($nowUtc->gte($maxExpireAt)) {
                        throw ValidationException::withMessages([
                            'expire_at' => ['Hold exceeded the maximum TTL. Create a new hold.'],
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
                        throw ValidationException::withMessages(['hold_id' => ['Hold does not exist.']]);
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
                'row_version' => ['Data changed (row_version mismatch). Reload and try again.'],
            ]);
        }
    }
}
