<?php

declare(strict_types=1);

namespace App\Modules\Waitlist\Http\Resources\Customer;

use App\Enums\WaitingListCustomerResponseState;
use App\Enums\WaitingListCustomerResponseStatus;
use App\Enums\WaitingListStatus;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class WaitlistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status?->value ?? (string) $this->status;
        $notifiedAt = $this->asUtcCarbon($this->notified_at);
        $notifyExpiresAt = $this->asUtcCarbon($this->notify_expires_at);
        $responseState = $this->responseState();
        $notifiedWindowOpen = $status === WaitingListStatus::Notified->value
            && $notifiedAt !== null
            && $notifyExpiresAt !== null
            && Carbon::now('UTC')->lt($notifyExpiresAt);

        $canAccept = $notifiedWindowOpen;
        $canDecline = $notifiedWindowOpen;
        $canConfirmArrival = $notifiedWindowOpen;
        $canCancel = $status === WaitingListStatus::Waiting->value || $notifiedWindowOpen;
        $staffSeatRequired = $status === WaitingListStatus::Notified->value;

        return [
            'waiting_id' => (int) $this->waiting_id,
            'branch_id' => $this->branch_id !== null ? (int) $this->branch_id : null,
            'guest_name' => $this->guest_name,
            'phone' => $this->phone,
            'guest_count' => (int) $this->guest_count,
            'requested_at' => $this->toIso8601($this->requested_at),
            'status' => $status,
            'priority' => (int) ($this->priority ?? 0),
            'notified_at' => $this->toIso8601($this->notified_at),
            'notify_expires_at' => $this->toIso8601($this->notify_expires_at),
            'seated_at' => $this->toIso8601($this->seated_at),
            'cancelled_at' => $this->toIso8601($this->cancelled_at),
            'cancel_reason' => $this->cancel_reason,
            'notes' => $this->notes,
            'row_version' => (int) ($this->row_version ?? 1),
            'response_state' => $responseState->value,
            'can_accept' => $canAccept,
            'can_decline' => $canDecline,
            'can_confirm_arrival' => $canConfirmArrival,
            'can_cancel' => $canCancel,
            'notify_window' => [
                'is_open' => $notifiedWindowOpen,
                'expires_at' => $this->toIso8601($this->notify_expires_at),
            ],
            'window' => [
                'is_notified_window_open' => $notifiedWindowOpen,
            ],
            'available_actions' => [
                'accept' => $canAccept,
                'decline' => $canDecline,
                'confirm_arrival' => $canConfirmArrival,
                'cancel' => $canCancel,
            ],
            'staff_seat_required' => $staffSeatRequired,
            'next_step' => $this->nextStep($status, $notifiedWindowOpen),
            'arrival_confirmation' => [
                'supported' => true,
                'staff_seat_required' => $staffSeatRequired,
                'message' => $staffSeatRequired
                    ? 'Customers only confirm arrival. Staff still completes seating.'
                    : null,
            ],
        ];
    }

    private function nextStep(string $status, bool $notifiedWindowOpen): ?string
    {
        if ($status === WaitingListStatus::Waiting->value) {
            return 'await_notification';
        }

        if ($status === WaitingListStatus::Notified->value) {
            return $notifiedWindowOpen ? 'await_staff_seating' : 'notify_window_closed';
        }

        if ($status === WaitingListStatus::Seated->value) {
            return 'already_seated';
        }

        if ($status === WaitingListStatus::Cancelled->value) {
            return 'closed';
        }

        return null;
    }

    private function responseState(): WaitingListCustomerResponseState
    {
        if ($this->asUtcCarbon($this->customer_confirmed_arrival_at) !== null) {
            return WaitingListCustomerResponseState::ArrivalConfirmed;
        }

        $customerResponseStatus = $this->customer_response_status;
        $responseStatus = $customerResponseStatus instanceof WaitingListCustomerResponseStatus
            ? $customerResponseStatus->value
            : (string) $customerResponseStatus;

        return match ($responseStatus) {
            WaitingListCustomerResponseStatus::Accepted->value => WaitingListCustomerResponseState::Accepted,
            WaitingListCustomerResponseStatus::Declined->value => WaitingListCustomerResponseState::Declined,
            default => WaitingListCustomerResponseState::None,
        };
    }

    private function toIso8601(mixed $value): ?string
    {
        $carbon = $this->asUtcCarbon($value);

        return $carbon?->toIso8601String();
    }

    private function asUtcCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->utc();
    }
}
