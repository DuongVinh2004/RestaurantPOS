<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Infrastructure\PaymentProviders;

use App\Enums\ReservationDepositPaymentSessionStatus;
use App\Enums\ReservationDepositPaymentSettlementStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\CheckoutPayments\Application\Services\ReservationDepositPaymentService;
use App\Modules\CheckoutPayments\Domain\Models\Payment;
use App\Modules\CheckoutPayments\Domain\Models\ReservationDepositPaymentSession;
use App\Modules\CheckoutPayments\Domain\Policies\PaymentSessionStatusTransitionPolicy;
use App\Modules\CheckoutPayments\Domain\ValueObjects\PaymentSummary;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReservationDepositPaymentSessionLifecycleService
{
    public function __construct(
        private readonly ReservationDepositPaymentService $depositPaymentService,
    ) {}

    /**
     * @param  array<string,mixed>  $providerResult
     */
    public function applyProviderResult(ReservationDepositPaymentSession $session, array $providerResult, ?int $actorUserId = null): bool
    {
        $currentStatus = (string) ($session->session_status?->value ?? $session->session_status ?? ReservationDepositPaymentSessionStatus::Created->value);
        $incomingStatusValue = (string) ($providerResult['session_status'] ?? $currentStatus);

        if (! PaymentSessionStatusTransitionPolicy::shouldApply($currentStatus, $incomingStatusValue)) {
            return false;
        }

        $status = ReservationDepositPaymentSessionStatus::from($incomingStatusValue);
        $now = Carbon::now('UTC');

        $session->provider_payment_code = Arr::get($providerResult, 'provider_payment_code', $session->provider_payment_code);
        $session->payment_method = Arr::get($providerResult, 'payment_method', $session->payment_method);
        $session->session_status = $status;
        $session->failure_code = Arr::get($providerResult, 'failure_code');
        $session->failure_message = Arr::get($providerResult, 'failure_message');
        $session->provider_payload_json = (array) ($providerResult['provider_payload'] ?? $session->provider_payload_json ?? []);
        $session->provider_expires_at = $providerResult['provider_expires_at'] ?? $session->provider_expires_at;
        $session->last_reconciled_at = $providerResult['occurred_at'] ?? $now;
        $session->updated_by = $actorUserId;

        if ($status === ReservationDepositPaymentSessionStatus::Succeeded) {
            $session->confirmed_at ??= $now;
            $session->failed_at = null;
            $session->cancelled_at = null;
            $session->expired_at = null;
        } elseif ($status === ReservationDepositPaymentSessionStatus::Failed) {
            $session->failed_at = $now;
        } elseif ($status === ReservationDepositPaymentSessionStatus::Cancelled) {
            $session->cancelled_at = $now;
        } elseif ($status === ReservationDepositPaymentSessionStatus::Expired) {
            $session->expired_at = $now;
        }

        $session->save();

        return true;
    }

    /**
     * @param  Collection<int,Payment>  $lockedPayments
     */
    public function applySucceededSessionIfNeeded(Reservation $reservation, ReservationDepositPaymentSession $session, Collection $lockedPayments, ?int $actorUserId = null): ?Payment
    {
        $status = $session->session_status instanceof ReservationDepositPaymentSessionStatus
            ? $session->session_status
            : ReservationDepositPaymentSessionStatus::from((string) $session->session_status);
        if ($status !== ReservationDepositPaymentSessionStatus::Succeeded) {
            return null;
        }

        $settlementStatus = $session->settlement_status instanceof ReservationDepositPaymentSettlementStatus
            ? $session->settlement_status
            : ReservationDepositPaymentSettlementStatus::from((string) $session->settlement_status);
        if ($settlementStatus === ReservationDepositPaymentSettlementStatus::Applied && $session->linked_payment_id !== null) {
            return null;
        }

        $summary = PaymentSummary::fromPayments($lockedPayments);
        $outstanding = $this->calculateOutstandingDeposit($reservation, $summary);
        $finalCaptured = round(max(0.0, (float) ($summary['final_captured_amount'] ?? 0.0)), 2);
        if ($outstanding <= 0.0001 || $finalCaptured > 0.0001) {
            $payload = (array) ($session->provider_payload_json ?? []);
            $payload['settlement_skip_reason'] = $finalCaptured > 0.0001
                ? 'final_payment_already_captured'
                : 'deposit_already_satisfied';
            $session->settlement_status = ReservationDepositPaymentSettlementStatus::Skipped;
            $session->provider_payload_json = $payload;
            $session->updated_by = $actorUserId;
            $session->save();

            return null;
        }

        $payment = $this->depositPaymentService->captureSucceededCustomerSession(
            reservation: $reservation,
            session: $session,
            lockedPayments: $lockedPayments,
            actorUserId: $actorUserId,
        );

        $session->linked_payment_id = (int) $payment->payment_id;
        $session->settlement_status = ReservationDepositPaymentSettlementStatus::Applied;
        $session->updated_by = $actorUserId;
        $session->save();

        return $payment;
    }

    /**
     * @param  array<string,mixed>  $paymentSummary
     */
    private function calculateOutstandingDeposit(Reservation $reservation, array $paymentSummary): float
    {
        $required = round(max(0.0, (float) ($reservation->deposit_required_amount ?? 0.0)), 2);
        $paid = round(max(0.0, (float) ($paymentSummary['deposit_net_amount'] ?? 0.0)), 2);

        return round(max(0.0, $required - $paid), 2);
    }
}
