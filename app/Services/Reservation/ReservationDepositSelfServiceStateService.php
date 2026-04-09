<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Enums\ReservationDepositIntentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReservationDepositSelfServiceStateService
{
    /**
     * @param array<string,mixed> $paymentSummary
     * @return array<string,mixed>
     */
    public function buildState(Reservation $reservation, array $paymentSummary = []): array
    {
        $requiredAmount = round(max(0.0, (float) ($reservation->deposit_required_amount ?? 0.0)), 2);
        $depositNet = round(max(0.0, (float) ($paymentSummary['deposit_net_amount'] ?? $reservation->deposit_paid_amount ?? 0.0)), 2);
        $finalNet = round(max(0.0, (float) ($paymentSummary['final_net_amount'] ?? 0.0)), 2);
        $outstandingAmount = round(max(0.0, $requiredAmount - $depositNet), 2);
        $status = (string) ($reservation->status?->value ?? $reservation->status ?? '');
        $intentStatus = $this->resolveIntentStatus($reservation);
        $acknowledgedAt = $this->normalizeDate($reservation->deposit_requirement_acknowledged_at ?? null);
        $intentSubmittedAt = $this->normalizeDate($reservation->deposit_intent_submitted_at ?? null);
        $intentRevokedAt = $this->normalizeDate($reservation->deposit_intent_revoked_at ?? null);

        $depositRequired = $requiredAmount > 0.0001;
        $isActiveReservation = in_array($status, ReservationStatus::activeDbValues(), true);
        $actualPaymentRecorded = $depositNet > 0.0001;
        $finalPaymentRecorded = $finalNet > 0.0001;
        $hasOutstandingAmount = $outstandingAmount > 0.0001;
        $actionable = $depositRequired && $isActiveReservation && ! $finalPaymentRecorded && $hasOutstandingAmount && ! $actualPaymentRecorded;
        $canAcknowledge = $actionable && $acknowledgedAt === null;
        $canSubmitIntent = $actionable && $acknowledgedAt !== null && $intentStatus !== ReservationDepositIntentStatus::Submitted;
        $canRevokeIntent = $actionable && $intentStatus === ReservationDepositIntentStatus::Submitted;

        return [
            'supported' => true,
            'deposit_required' => $depositRequired,
            'outstanding_amount' => number_format($outstandingAmount, 2, '.', ''),
            'requirement_acknowledged' => $acknowledgedAt !== null,
            'acknowledged_at' => $this->iso($acknowledgedAt),
            'intent_status' => $intentStatus->value,
            'intent_submitted_at' => $this->iso($intentSubmittedAt),
            'intent_revoked_at' => $this->iso($intentRevokedAt),
            'actionable' => $actionable,
            'can_acknowledge' => $canAcknowledge,
            'can_submit_intent' => $canSubmitIntent,
            'can_revoke_intent' => $canRevokeIntent,
            'actual_payment_recorded' => $actualPaymentRecorded,
            'final_payment_recorded' => $finalPaymentRecorded,
            'requires_staff_payment_collection' => $depositRequired && $hasOutstandingAmount,
            'next_step' => $this->resolveNextStep(
                depositRequired: $depositRequired,
                isActiveReservation: $isActiveReservation,
                finalPaymentRecorded: $finalPaymentRecorded,
                actualPaymentRecorded: $actualPaymentRecorded,
                hasOutstandingAmount: $hasOutstandingAmount,
                acknowledgedAt: $acknowledgedAt,
                intentStatus: $intentStatus,
            ),
        ];
    }

    /**
     * @param array<string,mixed> $paymentSummary
     */
    public function assertCanAcknowledge(Reservation $reservation, array $paymentSummary = []): void
    {
        $state = $this->buildState($reservation, $paymentSummary);

        if ((bool) ($state['requirement_acknowledged'] ?? false)) {
            return;
        }

        if (! (bool) ($state['can_acknowledge'] ?? false)) {
            throw ValidationException::withMessages([
                'reservation' => [$this->acknowledgeFailureMessage($reservation, $state)],
            ]);
        }
    }

    /**
     * @param array<string,mixed> $paymentSummary
     */
    public function assertCanSubmitIntent(Reservation $reservation, array $paymentSummary = []): void
    {
        $state = $this->buildState($reservation, $paymentSummary);

        if (($state['intent_status'] ?? ReservationDepositIntentStatus::None->value) === ReservationDepositIntentStatus::Submitted->value) {
            return;
        }

        if (! (bool) ($state['requirement_acknowledged'] ?? false)) {
            throw ValidationException::withMessages([
                'reservation' => ['Please acknowledge the deposit requirement before submitting payment intent.'],
            ]);
        }

        if (! (bool) ($state['can_submit_intent'] ?? false)) {
            throw ValidationException::withMessages([
                'reservation' => [$this->submitIntentFailureMessage($reservation, $state)],
            ]);
        }
    }

    /**
     * @param array<string,mixed> $paymentSummary
     */
    public function assertCanRevokeIntent(Reservation $reservation, array $paymentSummary = []): void
    {
        $state = $this->buildState($reservation, $paymentSummary);
        $intentStatus = (string) ($state['intent_status'] ?? ReservationDepositIntentStatus::None->value);

        if ($intentStatus === ReservationDepositIntentStatus::Revoked->value) {
            return;
        }

        if ($intentStatus !== ReservationDepositIntentStatus::Submitted->value) {
            throw ValidationException::withMessages([
                'reservation' => ['There is no submitted deposit intent to revoke.'],
            ]);
        }

        if (! (bool) ($state['can_revoke_intent'] ?? false)) {
            throw ValidationException::withMessages([
                'reservation' => [$this->revokeIntentFailureMessage($reservation, $state)],
            ]);
        }
    }

    public function resolveIntentStatus(Reservation $reservation): ReservationDepositIntentStatus
    {
        $raw = $reservation->deposit_intent_status;
        if ($raw instanceof ReservationDepositIntentStatus) {
            return $raw;
        }

        return ReservationDepositIntentStatus::tryFrom((string) $raw) ?? ReservationDepositIntentStatus::None;
    }

    private function acknowledgeFailureMessage(Reservation $reservation, array $state): string
    {
        if (! (bool) ($state['deposit_required'] ?? false)) {
            return 'This reservation does not require a deposit acknowledgement.';
        }

        if (! $this->isActiveReservation($reservation)) {
            return 'Only active reservations can acknowledge deposit requirements.';
        }

        if ((bool) ($state['final_payment_recorded'] ?? false)) {
            return 'Cannot acknowledge deposit requirement after final payment has been recorded.';
        }

        if ((bool) ($state['actual_payment_recorded'] ?? false)) {
            return 'Deposit payment has already been recorded for this reservation.';
        }

        return 'Deposit acknowledgement is not available for this reservation.';
    }

    private function submitIntentFailureMessage(Reservation $reservation, array $state): string
    {
        if (! (bool) ($state['deposit_required'] ?? false)) {
            return 'This reservation does not require a deposit.';
        }

        if (! $this->isActiveReservation($reservation)) {
            return 'Only active reservations can submit deposit intent.';
        }

        if ((bool) ($state['final_payment_recorded'] ?? false)) {
            return 'Cannot submit deposit intent after final payment has been recorded.';
        }

        if ((bool) ($state['actual_payment_recorded'] ?? false)) {
            return 'Deposit payment has already been recorded for this reservation.';
        }

        return 'Deposit intent is not available for this reservation.';
    }

    private function revokeIntentFailureMessage(Reservation $reservation, array $state): string
    {
        if (! $this->isActiveReservation($reservation)) {
            return 'Only active reservations can revoke deposit intent.';
        }

        if ((bool) ($state['actual_payment_recorded'] ?? false)) {
            return 'Cannot revoke deposit intent after deposit payment has been recorded.';
        }

        if ((bool) ($state['final_payment_recorded'] ?? false)) {
            return 'Cannot revoke deposit intent after final payment has been recorded.';
        }

        return 'Deposit intent is not revocable for this reservation.';
    }

    private function isActiveReservation(Reservation $reservation): bool
    {
        $status = (string) ($reservation->status?->value ?? $reservation->status ?? '');

        return in_array($status, ReservationStatus::activeDbValues(), true);
    }

    private function resolveNextStep(
        bool $depositRequired,
        bool $isActiveReservation,
        bool $finalPaymentRecorded,
        bool $actualPaymentRecorded,
        bool $hasOutstandingAmount,
        ?Carbon $acknowledgedAt,
        ReservationDepositIntentStatus $intentStatus,
    ): string {
        if (! $depositRequired) {
            return 'not_required';
        }

        if (! $isActiveReservation) {
            return 'reservation_not_actionable';
        }

        if ($finalPaymentRecorded) {
            return 'final_payment_recorded';
        }

        if (! $hasOutstandingAmount) {
            return 'deposit_fully_paid';
        }

        if ($actualPaymentRecorded) {
            return 'awaiting_remaining_staff_collection';
        }

        if ($acknowledgedAt === null) {
            return 'awaiting_customer_acknowledgement';
        }

        return match ($intentStatus) {
            ReservationDepositIntentStatus::Submitted => 'awaiting_staff_payment_collection',
            ReservationDepositIntentStatus::Revoked => 'customer_intent_revoked',
            ReservationDepositIntentStatus::None => 'awaiting_customer_intent',
        };
    }

    private function normalizeDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        return Carbon::parse((string) $value)->utc();
    }

    private function iso(?Carbon $value): ?string
    {
        return $value?->copy()->utc()->toIso8601String();
    }
}
