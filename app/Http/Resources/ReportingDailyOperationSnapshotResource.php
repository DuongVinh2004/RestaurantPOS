<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportingDailyOperationSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $turnCount = (int) $this->turn_count;
        $createdCount = (int) $this->waiting_list_created_count;
        $notifiedCount = (int) $this->waiting_list_notified_count;

        return [
            'snapshot_id' => (int) $this->snapshot_id,
            'business_date' => optional($this->business_date)?->format('Y-m-d'),
            'branch_id' => (int) $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn (): array => [
                'branch_id' => (int) $this->branch->branch_id,
                'branch_code' => (string) $this->branch->branch_code,
                'branch_name' => (string) $this->branch->branch_name,
                'is_default' => (bool) $this->branch->is_default,
            ]),
            'reservations' => [
                'scheduled_count' => (int) $this->scheduled_reservation_count,
                'scheduled_guest_count' => (int) $this->scheduled_guest_count,
                'scheduled_minutes_total' => (int) $this->scheduled_minutes_total,
                'checked_in_count' => (int) $this->checked_in_count,
                'completed_count' => (int) $this->completed_count,
                'cancelled_count' => (int) $this->cancelled_count,
                'no_show_count' => (int) $this->no_show_count,
            ],
            'turn_time' => [
                'turn_count' => $turnCount,
                'turn_minutes_total' => (int) $this->turn_minutes_total,
                'avg_turn_minutes' => $turnCount > 0
                    ? round(((int) $this->turn_minutes_total) / $turnCount, 2)
                    : null,
            ],
            'waiting_list' => [
                'created_count' => $createdCount,
                'notified_count' => $notifiedCount,
                'seated_count' => (int) $this->waiting_list_seated_count,
                'cancelled_count' => (int) $this->waiting_list_cancelled_count,
                'confirmed_arrival_count' => (int) $this->waiting_list_confirmed_arrival_count,
                'seated_conversion_rate' => $createdCount > 0
                    ? round(((int) $this->waiting_list_seated_count / $createdCount) * 100, 2)
                    : null,
                'arrival_confirmation_rate' => $notifiedCount > 0
                    ? round(((int) $this->waiting_list_confirmed_arrival_count / $notifiedCount) * 100, 2)
                    : null,
            ],
            'freshness' => [
                'refreshed_at' => $this->refreshed_at?->utc()->toIso8601String(),
            ],
        ];
    }
}
