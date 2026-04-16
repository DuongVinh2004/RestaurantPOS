<?php

declare(strict_types=1);

namespace App\Modules\WaitingList\Http\Resources;

use App\Enums\WaitingListCustomerResponseStatus;
use App\Enums\WaitingListStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class WaitingListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof WaitingListStatus
            ? $this->status
            : WaitingListStatus::tryFrom((string) $this->status);
        $responseStatus = $this->customer_response_status instanceof WaitingListCustomerResponseStatus
            ? $this->customer_response_status
            : WaitingListCustomerResponseStatus::tryFrom((string) $this->customer_response_status);

        $inviteContext = is_array($this->resource->getAttribute('invite_lifecycle_context'))
            ? $this->resource->getAttribute('invite_lifecycle_context')
            : [];
        $inviteWindow = is_array($inviteContext['invite_window'] ?? null) ? $inviteContext['invite_window'] : [];
        $inviteHold = is_array($inviteContext['hold'] ?? null) ? $inviteContext['hold'] : [];
        $inviteSemantics = is_array($inviteContext['semantics'] ?? null) ? $inviteContext['semantics'] : [];

        $now = Carbon::now('UTC');
        $expiresAt = $this->notify_expires_at ? $this->serializeToCarbon($this->notify_expires_at) : null;
        $windowActive = (bool) ($inviteWindow['is_active'] ?? ($status === WaitingListStatus::Notified && $expiresAt !== null && $expiresAt->isFuture()));
        $windowExpired = (bool) ($inviteWindow['is_expired'] ?? ($status === WaitingListStatus::Notified && $expiresAt !== null && ! $expiresAt->isFuture()));
        $hasArrivalConfirmation = $this->customer_confirmed_arrival_at !== null;

        $currentResponseState = (string) ($inviteContext['current_response_state'] ?? match (true) {
            $windowExpired => 'invite_expired',
            $status === WaitingListStatus::Notified && $hasArrivalConfirmation => 'arrival_confirmed',
            $status === WaitingListStatus::Notified && $responseStatus === WaitingListCustomerResponseStatus::Accepted => 'accepted',
            $status === WaitingListStatus::Notified => 'pending',
            $responseStatus === WaitingListCustomerResponseStatus::Declined => 'declined',
            default => 'none',
        });

        $orchestration = is_array($this->resource->getAttribute('waiting_orchestration_context'))
            ? $this->resource->getAttribute('waiting_orchestration_context')
            : $this->buildFallbackOrchestration($status, $currentResponseState, $inviteSemantics, $inviteHold);

        return [
            'waiting_id' => (int) $this->waiting_id,
            'branch_id' => $this->branch_id !== null ? (int) $this->branch_id : null,
            'user_id' => $this->user_id !== null ? (int) $this->user_id : null,
            'guest_name' => $this->guest_name,
            'phone' => $this->phone,
            'guest_count' => (int) $this->guest_count,
            'requested_at' => $this->serializeUtc($this->requested_at),
            'status' => $status?->value ?? (string) $this->status,
            'priority' => (int) ($this->priority ?? 0),
            'notified_at' => $this->serializeUtc($this->notified_at),
            'notify_expires_at' => $this->serializeUtc($this->notify_expires_at),
            'notified_by' => $this->notified_by !== null ? (int) $this->notified_by : null,
            'seated_at' => $this->serializeUtc($this->seated_at),
            'cancelled_at' => $this->serializeUtc($this->cancelled_at),
            'cancel_reason' => $this->cancel_reason,
            'notes' => $this->notes,
            'updated_by' => $this->updated_by !== null ? (int) $this->updated_by : null,
            'row_version' => (int) $this->row_version,
            'current_response_state' => $currentResponseState,
            'response' => [
                'status' => $responseStatus?->value,
                'responded_at' => $this->serializeUtc($this->customer_responded_at),
                'confirmed_arrival_at' => $this->serializeUtc($this->customer_confirmed_arrival_at),
            ],
            'invite_window' => [
                'notified_at' => $inviteWindow['notified_at'] ?? $this->serializeUtc($this->notified_at),
                'expires_at' => $inviteWindow['expires_at'] ?? $this->serializeUtc($this->notify_expires_at),
                'is_active' => $windowActive,
                'is_expired' => $windowExpired,
                'seconds_remaining' => (int) ($inviteWindow['seconds_remaining'] ?? ($windowActive && $expiresAt !== null ? max(0, $now->diffInSeconds($expiresAt, false)) : 0)),
            ],
            'invite_lifecycle' => [
                'requires_explicit_staff_seat' => (bool) ($inviteSemantics['requires_explicit_staff_seat'] ?? true),
                'auto_convert_to_reservation' => (bool) ($inviteSemantics['auto_convert_to_reservation'] ?? false),
                'seat_readiness' => (string) ($inviteSemantics['seat_readiness'] ?? 'not_notified'),
                'customer_next_step' => (string) ($inviteSemantics['customer_next_step'] ?? 'none'),
                'staff_next_step' => (string) ($inviteSemantics['staff_next_step'] ?? 'none'),
                'can_staff_seat_now' => (bool) ($inviteSemantics['can_staff_seat_now'] ?? false),
            ],
            'invite_hold' => [
                'has_active_hold' => (bool) ($inviteHold['has_active_hold'] ?? false),
                'active' => is_array($inviteHold['active'] ?? null) ? $inviteHold['active'] : null,
                'latest' => is_array($inviteHold['latest'] ?? null) ? $inviteHold['latest'] : null,
            ],
            'orchestration' => $orchestration,
            'user' => $this->relationLoaded('user') && $this->user
                ? [
                    'user_id' => (int) $this->user->user_id,
                    'full_name' => $this->user->full_name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ]
                : null,
        ];
    }

    /**
     * @param array<string,mixed> $inviteSemantics
     * @param array<string,mixed> $inviteHold
     * @return array<string,mixed>
     */
    private function buildFallbackOrchestration(?WaitingListStatus $status, string $currentResponseState, array $inviteSemantics, array $inviteHold): array
    {
        $seatReadiness = (string) ($inviteSemantics['seat_readiness'] ?? 'not_notified');
        $canStaffSeatNow = (bool) ($inviteSemantics['can_staff_seat_now'] ?? ($currentResponseState === 'arrival_confirmed'));
        $hasActiveHold = (bool) ($inviteHold['has_active_hold'] ?? false);

        $actionableState = match (true) {
            $status === WaitingListStatus::Cancelled || $status === WaitingListStatus::Seated => 'closed',
            $currentResponseState === 'arrival_confirmed' || $seatReadiness === 'ready_to_seat' => 'seat_customer',
            $currentResponseState === 'accepted' || $seatReadiness === 'customer_accepted' => 'await_customer_arrival',
            $currentResponseState === 'declined', $currentResponseState === 'invite_expired' => 'advance_queue',
            $seatReadiness === 'notify_hold_missing' => 'investigate_hold_state',
            $currentResponseState === 'pending' => 'await_customer_response',
            $status === WaitingListStatus::Waiting => 'wait_in_queue',
            default => 'none',
        };

        $recommendedAction = match ($actionableState) {
            'seat_customer' => 'seat_current_customer',
            'await_customer_arrival' => 'wait_or_verify_arrival_then_seat',
            'advance_queue' => $hasActiveHold ? 'advance_queue_to_next_candidate' : 'review_released_table_before_advancing_queue',
            'investigate_hold_state' => 'investigate_missing_or_stale_hold',
            'await_customer_response' => 'wait_for_customer_response_or_expiry',
            'wait_in_queue' => 'keep_waiting_in_queue',
            default => 'none',
        };

        $actions = [
            [
                'key' => 'seat',
                'enabled' => $canStaffSeatNow || $currentResponseState === 'arrival_confirmed',
            ],
        ];

        if ($actionableState === 'advance_queue') {
            $actions[] = [
                'key' => 'advance_queue',
                'enabled' => $hasActiveHold,
            ];
        }

        return [
            'mode' => 'semi_automated_waiting_list_orchestration',
            'actionable_state' => $actionableState,
            'recommended_action' => $recommendedAction,
            'actions' => $actions,
        ];
    }

    private function serializeUtc(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->serializeToCarbon($value)->toIso8601String();
    }

    private function serializeToCarbon(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))->utc();
        }

        return Carbon::parse((string) $value)->utc();
    }
}
