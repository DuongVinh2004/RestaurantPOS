<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Enums\TableHoldStatus;
use App\Models\TableHold;
use App\Services\TableHoldService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationTableAssignmentService
{
    public function __construct(
        private readonly TableHoldService $tableHoldService,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<int>
     */
    public function resolveTableIdsFromPayloadOrHold(array $payload, ?string $holdId, ?string $sessionId, Carbon $start, Carbon $end): array
    {
        if (! is_string($holdId) || $holdId === '') {
            return array_values(array_map('intval', $payload['table_ids']));
        }

        if (! is_string($sessionId) || $sessionId === '') {
            throw ValidationException::withMessages([
                'session_id' => ['session_id là bắt buộc khi dùng hold_id.'],
            ]);
        }

        $this->tableHoldService->expireStaleHolds();

        $hold = DB::table('table_holds')
            ->where('hold_id', $holdId)
            ->where('session_id', $sessionId)
            ->first();

        if (! $hold) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không tồn tại hoặc không thuộc session_id.'],
            ]);
        }

        if (! in_array((string) $hold->hold_status, ['Holding', 'Pending'], true)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không ở trạng thái Holding/Pending.'],
            ]);
        }

        if (Carbon::parse((string) $hold->expire_at)->utc()->lte(Carbon::now('UTC'))) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold đã hết hạn.'],
            ]);
        }

        $holdStart = Carbon::parse((string) $hold->start_time)->utc();
        $holdEnd = isset($hold->end_time)
            ? Carbon::parse((string) $hold->end_time)->utc()
            : $holdStart->copy()->addMinutes((int) ($hold->duration_minutes ?? 0));

        if ($holdStart->gt($start) || $holdEnd->lt($end)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không bao phủ đủ khoảng thời gian reservation.'],
            ]);
        }

        $tableIds = DB::table('table_hold_details')
            ->where('hold_id', $holdId)
            ->pluck('table_id')
            ->map(fn ($x) => (int) $x)
            ->values()
            ->all();

        if (empty($tableIds)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không có table_ids.'],
            ]);
        }

        if (isset($payload['table_ids']) && is_array($payload['table_ids'])) {
            $client = array_values(array_map('intval', $payload['table_ids']));
            sort($client);
            $fromHold = $tableIds;
            sort($fromHold);

            if ($client !== $fromHold) {
                throw ValidationException::withMessages([
                    'table_ids' => ['table_ids không khớp với hold_id.'],
                ]);
            }
        }

        return $tableIds;
    }

    public function lockAndAssertActiveHoldForReservation(string $holdId, string $sessionId, Carbon $start, Carbon $end): void
    {
        $hold = DB::table('table_holds')
            ->where('hold_id', $holdId)
            ->where('session_id', $sessionId)
            ->lockForUpdate()
            ->first();

        if (! $hold) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không tồn tại hoặc không thuộc session_id.'],
            ]);
        }

        if (! in_array((string) $hold->hold_status, [TableHoldStatus::Holding->value, TableHoldStatus::Pending->value], true)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold đã thay đổi trạng thái trong lúc tạo reservation.'],
            ]);
        }

        if (Carbon::parse((string) $hold->expire_at)->utc()->lte(Carbon::now('UTC'))) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold đã hết hạn trong lúc tạo reservation.'],
            ]);
        }

        $holdStart = Carbon::parse((string) $hold->start_time)->utc();
        $holdEnd = isset($hold->end_time)
            ? Carbon::parse((string) $hold->end_time)->utc()
            : $holdStart->copy()->addMinutes((int) ($hold->duration_minutes ?? 0));

        if ($holdStart->gt($start) || $holdEnd->lt($end)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không còn bao phủ đủ khoảng thời gian reservation.'],
            ]);
        }
    }

    public function confirmHoldForReservation(string $holdId, string $sessionId, int $reservationId, ?int $actorUserId, Carbon $now): void
    {
        /** @var TableHold|null $hold */
        $hold = TableHold::query()
            ->whereKey($holdId)
            ->where('session_id', $sessionId)
            ->whereIn('hold_status', [TableHoldStatus::Holding->value, TableHoldStatus::Pending->value])
            ->lockForUpdate()
            ->first();

        if ($hold === null) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold đã thay đổi trạng thái trong lúc tạo reservation. Hãy reload rồi thử lại.'],
            ]);
        }

        $hold->hold_status = TableHoldStatus::Confirmed;
        $hold->confirmed_reservation_id = $reservationId;
        $hold->expire_at = $now;
        $hold->updated_at = $now;
        $hold->updated_by = $actorUserId;
        $hold->save();
    }
}
