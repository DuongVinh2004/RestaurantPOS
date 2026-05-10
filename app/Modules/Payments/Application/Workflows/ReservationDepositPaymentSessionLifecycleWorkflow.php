<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Workflows;

use App\Enums\ReservationDepositPaymentSessionStatus;
use App\Enums\ReservationDepositPaymentSettlementStatus;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\Payments\Application\UseCases\PaymentSessions\ReservationDepositPaymentService;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\ReservationDepositPaymentSession;
use App\Modules\Payments\Domain\Policies\PaymentSessionStatusTransitionPolicy;
use App\Modules\Payments\Infrastructure\Internal\PaymentProviderPayloadSanitizer;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dong bo vong doi deposit payment session va ap ket qua nop coc vao reservation khi hop le.
 */
class ReservationDepositPaymentSessionLifecycleWorkflow
{
    public function __construct(
        private readonly ReservationDepositPaymentService $depositPaymentService,
    ) {}

    /**
     * @param  array<string,mixed>  $providerResult
     */
    public function applyProviderResult(ReservationDepositPaymentSession $session, array $providerResult, ?int $actorUserId = null): bool
    {
        // Provider result chi duoc ghi de khi no hop le theo state machine cua payment session.
        $currentStatus = (string) ($session->session_status?->value ?? $session->session_status ?? ReservationDepositPaymentSessionStatus::Created->value);
        $incomingStatusValue = (string) ($providerResult['session_status'] ?? $currentStatus);

        if (! PaymentSessionStatusTransitionPolicy::shouldApply($currentStatus, $incomingStatusValue)) {
            return false;
        }

        // Pha 1: map provider status vao enum noi bo va cap nhat metadata session lien quan.
        $status = ReservationDepositPaymentSessionStatus::from($incomingStatusValue);
        $now = Carbon::now('UTC');

        $session->provider_payment_code = Arr::get($providerResult, 'provider_payment_code', $session->provider_payment_code);
        $session->payment_method = Arr::get($providerResult, 'payment_method', $session->payment_method);
        $session->session_status = $status;
        $session->failure_code = Arr::get($providerResult, 'failure_code');
        $session->failure_message = Arr::get($providerResult, 'failure_message');
        $session->provider_payload_json = PaymentProviderPayloadSanitizer::sanitizeSessionPayloadForStorage(
            (array) ($providerResult['provider_payload'] ?? $session->provider_payload_json ?? [])
        );
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
        // Deposit succeeded co the replay lai nhieu lan; linked payment va settlement_status giu cho flow idempotent.
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

        // Pha 2: thu replay payment da tao truoc do truoc khi can nhac capture moi.
        $existingPayment = $this->depositPaymentService->replaySucceededCustomerSession(
            reservation: $reservation,
            session: $session,
            lockedPayments: $lockedPayments,
            actorUserId: $actorUserId,
        );
        if ($existingPayment instanceof Payment) {
            $session->linked_payment_id = (int) $existingPayment->payment_id;
            $session->settlement_status = ReservationDepositPaymentSettlementStatus::Applied;
            $session->updated_by = $actorUserId;
            $session->save();

            return $existingPayment;
        }

        $summary = PaymentSummary::fromPayments($lockedPayments);
        $outstanding = $this->calculateOutstandingDeposit($reservation, $summary);
        $outstandingMinor = Money::minorUnits($outstanding, true);
        $finalCapturedMinor = Money::minorUnits($summary['final_captured_amount'] ?? 0, true);
        // Khong apply deposit nua neu coc da du hoac bill cuoi da duoc thu roi.
        if ($outstandingMinor <= 0 || $finalCapturedMinor > 0) {
            $payload = (array) ($session->provider_payload_json ?? []);
            $payload['settlement_skip_reason'] = $finalCapturedMinor > 0
                ? 'final_payment_already_captured'
                : 'deposit_already_satisfied';
            $session->settlement_status = ReservationDepositPaymentSettlementStatus::Skipped;
            $session->provider_payload_json = PaymentProviderPayloadSanitizer::sanitizeSessionPayloadForStorage($payload);
            $session->updated_by = $actorUserId;
            $session->save();

            return null;
        }

        // Pha 3: chi capture deposit khi reservation van con outstanding deposit va chua thu final payment.
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
        $requiredMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);
        $paidMinor = Money::minorUnits($paymentSummary['deposit_net_amount'] ?? 0, true);

        return Money::minorToFloat(max(0, $requiredMinor - $paidMinor));
    }
}
