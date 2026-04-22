<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Queries;

use App\Modules\Reservations\Application\Services\ReservationDepositSelfServiceStateService;
use App\Modules\Reservations\Domain\Models\Reservation;

class StaffReservationDepositOperationalReadService
{
    public function __construct(
        private readonly ReservationDepositSelfServiceStateService $depositSelfServiceStateService,
    ) {}

    /**
     * @param  array<string,mixed>  $paymentSummary
     * @return array<string,mixed>
     */
    public function build(Reservation $reservation, array $paymentSummary = []): array
    {
        $state = $this->depositSelfServiceStateService->buildState($reservation, $paymentSummary);
        $nextStep = (string) ($state['next_step'] ?? 'not_required');

        $state['flags'] = [
            'acknowledged' => (bool) ($state['requirement_acknowledged'] ?? false),
            'intent_submitted' => (string) ($state['intent_status'] ?? 'None') === 'Submitted',
            'intent_revoked' => (string) ($state['intent_status'] ?? 'None') === 'Revoked',
        ];
        $state['follow_up'] = [
            'needs_staff_follow_up' => $this->needsStaffFollowUp($nextStep),
            'priority' => $this->resolvePriority($nextStep),
        ];

        return $state;
    }

    private function needsStaffFollowUp(string $nextStep): bool
    {
        return in_array($nextStep, [
            'awaiting_staff_payment_collection',
            'awaiting_remaining_staff_collection',
            'customer_intent_revoked',
        ], true);
    }

    private function resolvePriority(string $nextStep): string
    {
        return match ($nextStep) {
            'awaiting_staff_payment_collection' => 'high',
            'awaiting_remaining_staff_collection', 'customer_intent_revoked' => 'normal',
            default => 'none',
        };
    }
}
