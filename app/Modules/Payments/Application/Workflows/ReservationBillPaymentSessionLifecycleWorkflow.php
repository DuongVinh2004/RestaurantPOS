<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Workflows;

use App\Enums\ReservationBillPaymentSessionStatus;
use App\Enums\ReservationBillPaymentSettlementStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Payments\Application\UseCases\PaymentSessions\ReservationBillPaymentService;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\ReservationBillPaymentSession;
use App\Modules\Payments\Domain\Policies\PaymentSessionStatusTransitionPolicy;
use App\Modules\Payments\Infrastructure\Internal\PaymentProviderPayloadSanitizer;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReservationBillPaymentSessionLifecycleWorkflow
{
    public function __construct(
        private readonly ReservationBillPaymentService $billPaymentService,
    ) {}

    /**
     * @param  array<string,mixed>  $providerResult
     */
    public function applyProviderResult(ReservationBillPaymentSession $session, array $providerResult, ?int $actorUserId = null): bool
    {
        $currentStatus = (string) ($session->session_status?->value ?? $session->session_status ?? ReservationBillPaymentSessionStatus::Created->value);
        $incomingStatusValue = (string) ($providerResult['session_status'] ?? $currentStatus);

        if (! PaymentSessionStatusTransitionPolicy::shouldApply($currentStatus, $incomingStatusValue)) {
            return false;
        }

        $status = ReservationBillPaymentSessionStatus::from($incomingStatusValue);
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

        if ($status === ReservationBillPaymentSessionStatus::Succeeded) {
            $session->confirmed_at ??= $now;
            $session->failed_at = null;
            $session->cancelled_at = null;
            $session->expired_at = null;
        } elseif ($status === ReservationBillPaymentSessionStatus::Failed) {
            $session->failed_at = $now;
        } elseif ($status === ReservationBillPaymentSessionStatus::Cancelled) {
            $session->cancelled_at = $now;
        } elseif ($status === ReservationBillPaymentSessionStatus::Expired) {
            $session->expired_at = $now;
        }

        $session->save();

        return true;
    }

    /**
     * @param  Collection<int,Payment>  $lockedPayments
     */
    public function applySucceededSessionIfNeeded(Reservation $reservation, ReservationBillPaymentSession $session, Collection $lockedPayments, ?int $actorUserId = null): void
    {
        $status = $session->session_status instanceof ReservationBillPaymentSessionStatus
            ? $session->session_status
            : ReservationBillPaymentSessionStatus::from((string) $session->session_status);
        if ($status !== ReservationBillPaymentSessionStatus::Succeeded) {
            return;
        }

        $settlementStatus = $session->settlement_status instanceof ReservationBillPaymentSettlementStatus
            ? $session->settlement_status
            : ReservationBillPaymentSettlementStatus::from((string) $session->settlement_status);
        if ($settlementStatus === ReservationBillPaymentSettlementStatus::Applied && $session->linked_payment_id !== null) {
            return;
        }

        $existingPayment = $this->billPaymentService->replaySucceededCustomerSession(
            reservation: $reservation,
            session: $session,
            lockedPayments: $lockedPayments,
            actorUserId: $actorUserId,
        );
        if ($existingPayment instanceof Payment) {
            $session->linked_payment_id = (int) $existingPayment->payment_id;
            $session->settlement_status = ReservationBillPaymentSettlementStatus::Applied;
            $session->updated_by = $actorUserId;
            $session->save();

            return;
        }

        $bill = $this->billPaymentService->summarizeLockedBill($reservation, $lockedPayments, (string) ($session->currency ?? ''));
        if (Money::minorUnits($bill['outstanding_amount'] ?? 0, true) <= 0) {
            $payload = (array) ($session->provider_payload_json ?? []);
            $payload['settlement_skip_reason'] = 'bill_already_satisfied';
            $session->settlement_status = ReservationBillPaymentSettlementStatus::Skipped;
            $session->provider_payload_json = PaymentProviderPayloadSanitizer::sanitizeSessionPayloadForStorage($payload);
            $session->updated_by = $actorUserId;
            $session->save();

            return;
        }

        $payment = $this->billPaymentService->captureSucceededCustomerSession(
            reservation: $reservation,
            session: $session,
            lockedPayments: $lockedPayments,
            actorUserId: $actorUserId,
        );

        $session->linked_payment_id = (int) $payment->payment_id;
        $session->settlement_status = ReservationBillPaymentSettlementStatus::Applied;
        $session->updated_by = $actorUserId;
        $session->save();
    }
}
