<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\ReservationStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Support\Carbon;

class StaffReservationTimelineWorkbenchService
{
    public function __construct(?RuntimeSettingService $runtimeSettings = null)
    {
        $this->runtimeSettings = $runtimeSettings ?? app(RuntimeSettingService::class);
    }

    private readonly RuntimeSettingService $runtimeSettings;

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function build(Reservation $reservation, array $context = []): array
    {
        $reservationId = (int) ($reservation->reservation_id ?? 0);
        $rowVersion = (int) ($reservation->row_version ?? 1);
        $status = (string) ($reservation->status?->value ?? $reservation->status ?? '');
        $isCheckedIn = (bool) ($context['is_checked_in'] ?? false);
        $isTerminal = (bool) ($context['is_terminal'] ?? false);
        $hasAssignedTables = (bool) ($context['has_assigned_tables'] ?? false);
        $assignedTables = array_values(array_filter((array) ($context['assigned_tables'] ?? []), static fn (mixed $row): bool => is_array($row)));
        $assignedTableIds = array_values(array_map(static fn (array $table): int => (int) ($table['table_id'] ?? 0), $assignedTables));
        $candidateTables = array_values(array_filter((array) ($context['candidate_tables'] ?? []), static fn (mixed $row): bool => is_array($row)));
        $candidatePreviewLoaded = (bool) ($context['candidate_table_preview_loaded'] ?? false);
        $checkInReadiness = is_array($context['check_in_readiness'] ?? null)
            ? (array) $context['check_in_readiness']
            : null;
        $assignmentRequestContext = is_array($context['assignment_request_context'] ?? null)
            ? array_filter((array) $context['assignment_request_context'], static fn (mixed $value): bool => $value !== null && $value !== '')
            : [];
        $bestFitTable = $candidatePreviewLoaded ? ($candidateTables[0] ?? null) : null;
        $depositFollowUp = (bool) ($context['deposit_follow_up'] ?? false);
        $hasActiveOrder = (bool) ($context['has_active_order'] ?? false);
        $primaryTable = is_array($context['primary_table'] ?? null) ? (array) $context['primary_table'] : null;

        $nowUtc = $this->asCarbon($context['now_utc'] ?? null) ?? Carbon::now('UTC');
        $startUtc = $this->asCarbon($reservation->start_time) ?? $nowUtc->copy();
        $checkInWindowStartUtc = $this->asCarbon(data_get($checkInReadiness, 'window.start_utc'));
        $checkInWindowEndUtc = $this->asCarbon(data_get($checkInReadiness, 'window.end_utc'));
        if (! $checkInWindowStartUtc instanceof Carbon || ! $checkInWindowEndUtc instanceof Carbon) {
            $checkInGraceMinutes = $this->resolveCheckInGraceMinutes();
            $checkInWindowStartUtc = $startUtc->copy()->subMinutes($checkInGraceMinutes);
            $checkInWindowEndUtc = $startUtc->copy()->addMinutes($checkInGraceMinutes);
        }
        $checkInWindowOpen = (bool) ($checkInReadiness['available'] ?? (! $isTerminal
            && ! $isCheckedIn
            && $hasAssignedTables
            && $status === ReservationStatus::Confirmed->value
            && $nowUtc->betweenIncluded($checkInWindowStartUtc, $checkInWindowEndUtc)));
        $checkInBlockedReason = $checkInReadiness !== null
            ? (is_string($checkInReadiness['blocked_reason_code'] ?? null) ? $checkInReadiness['blocked_reason_code'] : null)
            : null;
        $checkInChecks = is_array($checkInReadiness['checks'] ?? null)
            ? (array) $checkInReadiness['checks']
            : [];

        $canAssign = ! $isTerminal
            && ! $isCheckedIn
            && ! $hasAssignedTables
            && $status === ReservationStatus::Confirmed->value;
        $canAssignBestFit = $canAssign;
        $canAssignSuggested = $canAssign && $bestFitTable !== null;
        $canReschedule = ! $isTerminal && ! $isCheckedIn && $status === ReservationStatus::Confirmed->value;
        $canMoveTable = ! $isTerminal && $isCheckedIn && $hasAssignedTables;

        $nextRecommendedAction = null;
        if ($canAssignSuggested) {
            $nextRecommendedAction = 'assign_suggested';
        } elseif ($canAssignBestFit) {
            $nextRecommendedAction = 'assign_best_fit';
        } elseif ($checkInWindowOpen) {
            $nextRecommendedAction = 'check_in';
        } elseif ($canMoveTable) {
            $nextRecommendedAction = 'move_table';
        } elseif ($depositFollowUp) {
            $nextRecommendedAction = 'deposit_preview';
        } elseif ($canReschedule) {
            $nextRecommendedAction = 'reschedule';
        }

        $actions = [];

        $actions[] = $this->makeAction(
            key: 'assign_best_fit',
            uri: sprintf('/api/v1/staff/reservations/%d/timeline/actions/assign-best-fit', $reservationId),
            available: $canAssignBestFit,
            recommended: $nextRecommendedAction === 'assign_best_fit',
            blockedReasonCode: $canAssignBestFit ? null : $this->resolveAssignmentBlockedReason($isTerminal, $isCheckedIn, $hasAssignedTables, $status),
            hint: 'Assign the best-fit table using the current board orchestration rules.',
            requiredFields: ['row_version'],
            payloadDefaults: array_merge(['row_version' => $rowVersion], $assignmentRequestContext),
            context: [
                'candidate_table_preview_loaded' => $candidatePreviewLoaded,
                'candidate_table_count' => $candidatePreviewLoaded ? count($candidateTables) : null,
                'assignment_request_context' => $assignmentRequestContext === [] ? null : $assignmentRequestContext,
            ],
        );

        $actions[] = $this->makeAction(
            key: 'assign_suggested',
            uri: sprintf('/api/v1/staff/reservations/%d/timeline/actions/assign-suggested', $reservationId),
            available: $canAssignSuggested,
            recommended: $nextRecommendedAction === 'assign_suggested',
            blockedReasonCode: $canAssignSuggested
                ? null
                : ($canAssign ? 'suggestion_preview_unavailable' : $this->resolveAssignmentBlockedReason($isTerminal, $isCheckedIn, $hasAssignedTables, $status)),
            hint: 'Commit the primary suggested table currently surfaced on the timeline item.',
            requiredFields: ['row_version', 'table_id'],
            payloadDefaults: array_filter([
                'row_version' => $rowVersion,
                'table_id' => $bestFitTable !== null ? (int) ($bestFitTable['table_id'] ?? 0) : null,
                ...$assignmentRequestContext,
            ], static fn (mixed $value): bool => $value !== null),
            suggestedTable: $bestFitTable,
            context: [
                'candidate_table_preview_loaded' => $candidatePreviewLoaded,
                'candidate_table_count' => $candidatePreviewLoaded ? count($candidateTables) : null,
                'assignment_request_context' => $assignmentRequestContext === [] ? null : $assignmentRequestContext,
            ],
        );

        $actions[] = $this->makeAction(
            key: 'check_in',
            uri: sprintf('/api/v1/staff/reservations/%d/timeline/actions/check-in', $reservationId),
            available: $checkInWindowOpen,
            recommended: $nextRecommendedAction === 'check_in',
            blockedReasonCode: $checkInWindowOpen
                ? null
                : ($checkInBlockedReason ?? $this->resolveCheckInBlockedReason($isTerminal, $isCheckedIn, $hasAssignedTables, $status, $nowUtc, $checkInWindowStartUtc, $checkInWindowEndUtc)),
            hint: 'Check in using the current assigned table set when the grace window is open.',
            requiredFields: ['row_version'],
            payloadDefaults: ['row_version' => $rowVersion],
            context: [
                'assigned_table_ids' => $assignedTableIds,
                'checks' => $checkInChecks === [] ? null : $checkInChecks,
                'check_in_window' => [
                    'open' => $checkInWindowOpen,
                    'time_window_open' => (bool) ($checkInChecks['within_check_in_window'] ?? $checkInWindowOpen),
                    'start_utc' => $checkInWindowStartUtc->toIso8601String(),
                    'end_utc' => $checkInWindowEndUtc->toIso8601String(),
                ],
            ],
        );

        $actions[] = $this->makeAction(
            key: 'move_table',
            uri: sprintf('/api/v1/staff/reservations/%d/move-table', $reservationId),
            available: $canMoveTable,
            recommended: $nextRecommendedAction === 'move_table',
            blockedReasonCode: $canMoveTable ? null : ($isCheckedIn ? 'no_assigned_table' : 'not_checked_in'),
            hint: 'Use the dedicated move-table flow to reassign an in-service reservation.',
            requiredFields: ['row_version', 'from_table_id', 'to_table_id'],
            payloadDefaults: ['row_version' => $rowVersion],
            context: [
                'assigned_table_ids' => $assignedTableIds,
                'primary_table' => $primaryTable,
            ],
        );

        $actions[] = $this->makeAction(
            key: 'reschedule',
            uri: sprintf('/api/v1/staff/reservations/%d/reschedule', $reservationId),
            available: $canReschedule,
            recommended: $nextRecommendedAction === 'reschedule',
            blockedReasonCode: $canReschedule ? null : ($isCheckedIn ? 'checked_in_requires_move_table' : ($isTerminal ? 'terminal_status' : 'status_not_confirmed')),
            hint: 'Use the canonical reservation reschedule flow from the timeline context.',
            requiredFields: ['row_version'],
            payloadDefaults: ['row_version' => $rowVersion],
        );

        $actions[] = $this->makeAction(
            key: 'deposit_preview',
            method: 'GET',
            uri: sprintf('/api/v1/staff/reservations/%d/deposit-preview', $reservationId),
            available: $depositFollowUp,
            recommended: $nextRecommendedAction === 'deposit_preview',
            blockedReasonCode: $depositFollowUp ? null : 'no_deposit_follow_up',
            hint: 'Open the reservation deposit preview for staff follow-up.',
        );

        return [
            'summary' => [
                'service_phase' => $isCheckedIn ? 'in_service' : 'pre_service',
                'assignment_state' => $hasAssignedTables ? 'assigned' : 'unassigned',
                'actionable_now' => collect($actions)->contains(static fn (array $action): bool => (bool) ($action['available'] ?? false)),
                'mutating_actionable_now' => collect($actions)->contains(static fn (array $action): bool => (bool) ($action['available'] ?? false) && strtoupper((string) ($action['method'] ?? 'GET')) !== 'GET'),
                'next_recommended_action' => $nextRecommendedAction,
                'candidate_table_preview_loaded' => $candidatePreviewLoaded,
                'candidate_table_count' => $candidatePreviewLoaded ? count($candidateTables) : null,
                'assigned_table_count' => count($assignedTableIds),
                'has_active_order' => $hasActiveOrder,
                'requires_deposit_follow_up' => $depositFollowUp,
                'check_in_window' => [
                    'open' => $checkInWindowOpen,
                    'start_utc' => $checkInWindowStartUtc->toIso8601String(),
                    'end_utc' => $checkInWindowEndUtc->toIso8601String(),
                ],
            ],
            'actions' => $actions,
        ];
    }

    private function resolveAssignmentBlockedReason(bool $isTerminal, bool $isCheckedIn, bool $hasAssignedTables, string $status): string
    {
        if ($isTerminal) {
            return 'terminal_status';
        }

        if ($isCheckedIn) {
            return 'checked_in_requires_move_table';
        }

        if ($hasAssignedTables) {
            return 'already_assigned';
        }

        if ($status !== ReservationStatus::Confirmed->value) {
            return 'status_not_confirmed';
        }

        return 'assignment_not_available';
    }

    private function resolveCheckInBlockedReason(
        bool $isTerminal,
        bool $isCheckedIn,
        bool $hasAssignedTables,
        string $status,
        Carbon $nowUtc,
        Carbon $checkInWindowStartUtc,
        Carbon $checkInWindowEndUtc,
    ): string {
        if ($isTerminal) {
            return 'terminal_status';
        }

        if ($isCheckedIn) {
            return 'already_checked_in';
        }

        if (! $hasAssignedTables) {
            return 'assignment_required';
        }

        if ($status !== ReservationStatus::Confirmed->value) {
            return 'status_not_confirmed';
        }

        if ($nowUtc->lt($checkInWindowStartUtc)) {
            return 'check_in_window_not_open';
        }

        if ($nowUtc->gt($checkInWindowEndUtc)) {
            return 'check_in_window_closed';
        }

        return 'check_in_not_available';
    }

    /**
     * @param list<string> $requiredFields
     * @param array<string,mixed> $payloadDefaults
     * @param array<string,mixed>|null $suggestedTable
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function makeAction(
        string $key,
        string $uri,
        bool $available,
        bool $recommended,
        ?string $blockedReasonCode,
        string $hint,
        array $requiredFields = [],
        array $payloadDefaults = [],
        ?array $suggestedTable = null,
        array $context = [],
        string $method = 'POST',
    ): array {
        return [
            'key' => $key,
            'method' => strtoupper($method),
            'uri' => $uri,
            'available' => $available,
            'recommended' => $recommended,
            'requires_row_version' => in_array('row_version', $requiredFields, true),
            'required_fields' => array_values($requiredFields),
            'payload_defaults' => $payloadDefaults === [] ? null : $payloadDefaults,
            'blocked_reason_code' => $available ? null : $blockedReasonCode,
            'hint' => $hint,
            'suggested_table' => $suggestedTable,
            'context' => $context === [] ? null : $context,
        ];
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

    private function resolveCheckInGraceMinutes(): int
    {
        return max(0, $this->runtimeSettings->int(
            'checkin.grace_minutes',
            $this->runtimeSettings->int('booking.check_in_grace_minutes', (int) config('booking.check_in_grace_minutes', 15))
        ));
    }
}
