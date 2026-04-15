<?php

namespace App\Modules\BranchScheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class TableHoldResource extends JsonResource
{
    /**
     * @param Request $request
     */
    public function toArray($request): array
    {
        $sessionId = (string) ($this['session_id'] ?? '');

        $out = [
            'hold_id'       => $this['hold_id'],
            // Không expose session_id raw mặc định (tránh leak). Client tự giữ session_id.
            'session_hash'  => $sessionId !== '' ? hash_hmac('sha256', $sessionId, (string) config('app.key', 'app')) : null,
            'start_time'    => $this->serializeDateTime($this['start_time'] ?? null),
            'end_time'      => $this->serializeDateTime($this['end_time'] ?? null),
            'duration_minutes' => isset($this['duration_minutes']) ? (int) $this['duration_minutes'] : null,
            'hold_status'   => $this['hold_status'],
            'confirmed_reservation_id' => isset($this['confirmed_reservation_id']) ? (int) $this['confirmed_reservation_id'] : null,
            'row_version'   => isset($this['row_version']) ? (int) $this['row_version'] : null,
            'created_at'    => $this->serializeDateTime($this['created_at'] ?? null),
            'updated_at'    => $this->serializeDateTime($this['updated_at'] ?? null),
            'expire_at'     => $this->serializeDateTime($this['expire_at'] ?? null),
            'tables'        => $this['tables'] ?? [],
        ];

        if ((bool) config('booking.expose_session_id', false)) {
            $out['session_id'] = $sessionId;
        }

        if ((bool) config('booking.expose_hold_user_id', false)) {
            $out['user_id'] = $this['user_id'];
        }

        return $out;
    }

    private function serializeDateTime(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc()->toIso8601String();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }

}
