<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Workflows;

use App\Enums\ReservationBillPaymentSessionStatus;
use App\Enums\ReservationBillPaymentSettlementStatus;
use App\Modules\Payments\Application\UseCases\PaymentSessions\ReservationBillPaymentService;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\ReservationBillPaymentSession;
use App\Modules\Payments\Domain\Policies\PaymentSessionStatusTransitionPolicy;
use App\Modules\Payments\Infrastructure\Internal\PaymentProviderPayloadSanitizer;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dong bo vong doi bill payment session va ap ket qua thanh toan bill vao reservation khi can.
 */
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
        // Chi apply khi event moi thuc su tien len state machine, tranh regression/stale webhook.
        $currentStatus = (string) ($session->session_status?->value ?? $session->session_status ?? ReservationBillPaymentSessionStatus::Created->value);
        $incomingStatusValue = (string) ($providerResult['session_status'] ?? $currentStatus);

        if (! PaymentSessionStatusTransitionPolicy::shouldApply($currentStatus, $incomingStatusValue)) {
            return false;
        }

        $providerAmountMinor = $providerResult['provider_amount_minor'] ?? null;
        if ($providerAmountMinor !== null && $incomingStatusValue === ReservationBillPaymentSessionStatus::Succeeded->value) {
            $sessionAmountMinor = Money::minorUnits($session->amount ?? 0, true);
            if ($providerAmountMinor !== $sessionAmountMinor) {
                // Provider amount mismatch. We must fail the session instead of applying it as succeeded.
                $incomingStatusValue = ReservationBillPaymentSessionStatus::Failed->value;
                $providerResult['failure_code'] = 'amount_mismatch';
                $providerResult['failure_message'] = "Provider amount {$providerAmountMinor} does not match session amount {$sessionAmountMinor}.";
            }
        }

        // Pha 1: map provider status vao enum noi bo va cap nhat metadata session lien quan.
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
        // Session succeeded chi duoc apply mot lan; nhung duplicate webhook van phai replay an toan.
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

        // Pha 2: thu replay payment da tao truoc do truoc khi can nhac capture moi.
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

        // Pha 3: capture final payment tu session succeeded nay.
        // Dù bill có outstanding <= 0, ta vẫn phải capture để ghi nhận tiền thừa, tránh thất thoát dòng tiền (reconciliation mismatch).

        // Pha 3: chi khi bill van con outstanding moi capture final payment tu session succeeded nay.
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
