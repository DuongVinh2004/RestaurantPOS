<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationOrder;
use App\Services\Staff\StaffReservationTimelineWorkbenchService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @mixin Reservation
 */
class StaffReservationTimelineItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Reservation $reservation */
        $reservation = $this->resource;
        $timezone = (string) ($request->attributes->get('staff_reservation_timeline_timezone') ?? config('app.timezone', 'UTC'));
        $nowUtc = $this->asCarbon($request->attributes->get('staff_reservation_timeline_now_utc')) ?? Carbon::now('UTC');
        $dueSoonCutoffUtc = $this->asCarbon($request->attributes->get('staff_reservation_timeline_due_soon_cutoff_utc')) ?? $nowUtc->copy();
        $overdueCutoffUtc = $this->asCarbon($request->attributes->get('staff_reservation_timeline_overdue_cutoff_utc')) ?? $nowUtc->copy();
        $candidateTableMap = $request->attributes->get('staff_reservation_timeline_candidate_tables_by_reservation');
        $candidateTablePreviewLoaded = is_array($candidateTableMap);
        $checkInReadinessMap = $request->attributes->get('staff_reservation_timeline_check_in_readiness_by_reservation');
        $assignmentRequestContext = $request->attributes->get('staff_reservation_timeline_assignment_request_context');

        $base = (new StaffReservationInboxResource($reservation))->toArray($request);
        $startUtc = $this->asCarbon($reservation->start_time) ?? Carbon::now('UTC');
        $endUtc = $this->asCarbon($reservation->end_time) ?? $startUtc->copy();
        $startLocal = $startUtc->copy()->setTimezone($timezone);
        $endLocal = $endUtc->copy()->setTimezone($timezone);
        $status = (string) ($reservation->status?->value ?? $reservation->status ?? '');
        $isCheckedIn = ReservationStatus::isCheckedInDbValue($status) || $reservation->checked_in_at !== null;
        $terminalStatuses = [
            ReservationStatus::Cancelled->value,
            ReservationStatus::Completed->value,
            ReservationStatus::Expired->value,
            ReservationStatus::NoShow->value,
        ];
        $isTerminal = in_array($status, $terminalStatuses, true);
        $isDueSoon = ! $isTerminal && ! $isCheckedIn && $startUtc->greaterThanOrEqualTo($nowUtc) && $startUtc->lessThanOrEqualTo($dueSoonCutoffUtc);
        $isOverdue = ! $isTerminal && ! $isCheckedIn && $startUtc->lessThanOrEqualTo($overdueCutoffUtc);
        $isLate = ! $isTerminal && ! $isCheckedIn && $startUtc->lessThan($nowUtc) && ! $isOverdue;
        $isUpcoming = ! $isTerminal && ! $isCheckedIn && $startUtc->greaterThan($dueSoonCutoffUtc);

        $activeOrder = $this->resolvePrimaryActiveOrder($reservation);
        $depositRequired = round((float) ($reservation->deposit_required_amount ?? 0.0), 2);
        $depositPaid = round((float) ($reservation->deposit_paid_amount ?? 0.0), 2);
        $hasAssignedTables = ! empty($base['tables']);
        $primaryTable = $this->resolvePrimaryTable($base['tables']);
        $primaryZone = $primaryTable !== null
            ? (trim((string) ($primaryTable['zone'] ?? '')) !== '' ? (string) $primaryTable['zone'] : 'Unzoned')
            : null;
        $candidateTables = $candidateTablePreviewLoaded
            ? array_values((array) ($candidateTableMap[(int) $reservation->reservation_id] ?? []))
            : [];
        $checkInReadiness = is_array($checkInReadinessMap)
            ? ($checkInReadinessMap[(int) $reservation->reservation_id] ?? null)
            : null;
        $depositSelfService = is_array($base['deposit_self_service'] ?? null)
            ? (array) $base['deposit_self_service']
            : [];
        $workbench = app(StaffReservationTimelineWorkbenchService::class)->build($reservation, [
            'now_utc' => $nowUtc,
            'is_checked_in' => $isCheckedIn,
            'is_terminal' => $isTerminal,
            'has_assigned_tables' => $hasAssignedTables,
            'assigned_tables' => $base['tables'] ?? [],
            'primary_table' => $primaryTable,
            'candidate_tables' => $candidateTables,
            'candidate_table_preview_loaded' => $candidateTablePreviewLoaded,
            'assignment_request_context' => is_array($assignmentRequestContext) ? $assignmentRequestContext : null,
            'check_in_readiness' => is_array($checkInReadiness) ? $checkInReadiness : null,
            'deposit_follow_up' => (bool) data_get($depositSelfService, 'follow_up.needs_staff_follow_up', false),
            'has_active_order' => $activeOrder !== null,
        ]);

        return [
            'reservation' => $base,
            'timeline' => [
                'start_local' => $startLocal->toIso8601String(),
                'end_local' => $endLocal->toIso8601String(),
                'start_time_label' => $startLocal->format('H:i'),
                'end_time_label' => $endLocal->format('H:i'),
                'date_label' => $startLocal->toDateString(),
                'duration_minutes' => max(0, $startUtc->diffInMinutes($endUtc, false)),
            ],
            'flags' => [
                'upcoming' => $isUpcoming,
                'due_soon' => $isDueSoon,
                'late' => $isLate,
                'overdue' => $isOverdue,
                'checked_in' => $isCheckedIn,
                'cancelled' => $status === ReservationStatus::Cancelled->value,
                'completed' => $status === ReservationStatus::Completed->value,
                'has_active_order' => $activeOrder !== null,
                'needs_assignment' => ! $hasAssignedTables,
                'deposit_acknowledged' => (bool) ($depositSelfService['requirement_acknowledged'] ?? false),
                'deposit_intent_submitted' => (bool) data_get($depositSelfService, 'flags.intent_submitted', false),
                'deposit_self_service_follow_up' => (bool) data_get($depositSelfService, 'follow_up.needs_staff_follow_up', false),
            ],
            'operational_state' => $this->resolveOperationalState($status, $isCheckedIn, $isDueSoon, $isLate, $isOverdue, $isUpcoming),
            'active_order' => $activeOrder ? [
                'order_id' => (int) $activeOrder->order_id,
                'order_type' => (string) ($activeOrder->order_type?->value ?? $activeOrder->order_type),
                'status' => (string) ($activeOrder->status?->value ?? $activeOrder->status),
                'created_at' => $this->iso($activeOrder->created_at),
            ] : null,
            'deposit' => [
                'status' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? ''),
                'required_amount' => number_format($depositRequired, 2, '.', ''),
                'paid_amount' => number_format($depositPaid, 2, '.', ''),
                'outstanding_amount' => number_format(max(0.0, $depositRequired - $depositPaid), 2, '.', ''),
                'currency' => (string) ($reservation->bill_currency ?? 'VND'),
                'self_service' => $depositSelfService,
            ],
            'calendar' => [
                'primary_zone' => $primaryZone,
                'primary_zone_lane_key' => $primaryZone !== null ? 'zone:' . $primaryZone : 'unassigned',
                'primary_table' => $primaryTable,
                'primary_table_lane_key' => $primaryTable !== null ? 'table:' . (int) ($primaryTable['table_id'] ?? 0) : 'unassigned',
                'lane_anchor_policy' => $primaryTable !== null ? 'first_assigned_table_anchor' : 'unassigned_lane',
            ],
            'orchestration' => [
                'needs_assignment' => ! $hasAssignedTables,
                'ready_for_assignment' => ! $isTerminal && ! $hasAssignedTables && $status === ReservationStatus::Confirmed->value,
                'assignment_state' => $hasAssignedTables ? 'assigned' : 'unassigned',
                'candidate_table_preview_loaded' => $candidateTablePreviewLoaded,
                'candidate_table_count' => $candidateTablePreviewLoaded ? count($candidateTables) : null,
                'best_fit_table' => $candidateTablePreviewLoaded ? ($candidateTables[0] ?? null) : null,
                'candidate_tables' => $candidateTablePreviewLoaded ? $candidateTables : [],
                'assignment_request_context' => is_array($assignmentRequestContext) ? $assignmentRequestContext : null,
            ],
            'workbench' => $workbench,
        ];
    }

    private function resolvePrimaryActiveOrder(Reservation $reservation): ?ReservationOrder
    {
        if (! $reservation->relationLoaded('orders') || $reservation->orders === null) {
            return null;
        }

        /** @var ReservationOrder|null $selected */
        $selected = $reservation->orders
            ->sort(function (ReservationOrder $left, ReservationOrder $right): int {
                $leftPriority = ($left->order_type?->value ?? (string) $left->order_type) === ReservationOrderType::OnSpot->value ? 0 : 1;
                $rightPriority = ($right->order_type?->value ?? (string) $right->order_type) === ReservationOrderType::OnSpot->value ? 0 : 1;

                return [$leftPriority, -1 * (int) $left->order_id] <=> [$rightPriority, -1 * (int) $right->order_id];
            })
            ->first();

        return $selected instanceof ReservationOrder ? $selected : null;
    }

    private function resolveOperationalState(string $status, bool $isCheckedIn, bool $isDueSoon, bool $isLate, bool $isOverdue, bool $isUpcoming): string
    {
        if ($status === ReservationStatus::Cancelled->value) {
            return 'cancelled';
        }
        if ($status === ReservationStatus::Completed->value) {
            return 'completed';
        }
        if ($status === ReservationStatus::Expired->value) {
            return 'expired';
        }
        if ($status === ReservationStatus::NoShow->value) {
            return 'no_show';
        }
        if ($isCheckedIn) {
            return 'checked_in';
        }
        if ($isOverdue) {
            return 'overdue';
        }
        if ($isLate) {
            return 'late';
        }
        if ($isDueSoon) {
            return 'due_soon';
        }
        if ($isUpcoming) {
            return 'upcoming';
        }

        return 'scheduled';
    }

    private function iso(mixed $value): ?string
    {
        $carbon = $this->asCarbon($value);

        return $carbon?->utc()->toIso8601String();
    }

    private function asCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse((string) $value);
    }

    /**
     * @param list<array<string,mixed>> $tables
     * @return array<string,mixed>|null
     */
    private function resolvePrimaryTable(array $tables): ?array
    {
        if ($tables === []) {
            return null;
        }

        usort($tables, static function (array $left, array $right): int {
            $leftVector = [
                trim((string) ($left['zone'] ?? '')) !== '' ? (string) $left['zone'] : 'Unzoned',
                (string) ($left['table_code'] ?? ''),
                (int) ($left['table_id'] ?? 0),
            ];
            $rightVector = [
                trim((string) ($right['zone'] ?? '')) !== '' ? (string) $right['zone'] : 'Unzoned',
                (string) ($right['table_code'] ?? ''),
                (int) ($right['table_id'] ?? 0),
            ];

            return $leftVector <=> $rightVector;
        });

        return Arr::only($tables[0], ['table_id', 'table_code', 'zone', 'seats', 'status']);
    }
}
