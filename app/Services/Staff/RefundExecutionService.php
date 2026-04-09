<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationOrder;
use App\Services\Branch\BranchContextService;
use App\Services\LoyaltyPointsService;
use App\Support\AuditEvent;
use App\Support\PaymentSummary;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RefundExecutionService
{
    private readonly BranchContextService $branchContextService;

    public function __construct(
        private readonly RefundPlannerService $refundPlannerService,
        private readonly SettlementAmountCalculator $amountCalculator,
        private readonly LoyaltyPointsService $loyaltyPointsService,
        ?BranchContextService $branchContextService = null,
    ) {
        $this->branchContextService = $branchContextService ?? app(BranchContextService::class);
    }


    /**
     * @param array{deposit:float,final:float} $availableByScope
     * @return array{deposit:float,final:float,total:float}
     */
    public function buildRefundPlan(string $refundScope, ?float $requestedRefundAmount, array $availableByScope, bool $cancelAfterPayment): array
    {
        return $this->refundPlannerService->buildRefundPlan($refundScope, $requestedRefundAmount, $availableByScope, $cancelAfterPayment);
    }

    /**
     * @param Collection<int,Payment> $payments
     * @return array<int,array{payment:Payment,amount:float}>
     */
    public function allocateRefundPaymentsBySource(Collection $payments, string $targetPaymentType, float $requestedAmount): array
    {
        return $this->refundPlannerService->allocateRefundPaymentsBySource($payments, $targetPaymentType, $requestedAmount);
    }

    /**
     * @param Collection<int,ReservationOrder> $orders
     * @param Collection<int,Payment> $payments
     * @param callable(Reservation,array<string,mixed>,bool):void $syncDepositSnapshot
     * @param callable(Reservation,Collection<int,ReservationOrder>,?int,bool):float $releaseAppliedVoucherLocked
     * @param callable(Reservation,Collection<int,ReservationOrder>,array<int,int>,?int,?string):void $cancelReservationLocked
     * @param callable(QueryException):bool $isDuplicatePaymentIdempotencyConstraint
     * @param callable(QueryException):void $throwIfDuplicatePaymentConstraint
     * @param string|null $requestFingerprint
     * @return array{refund_payment_ids:array<int,int>,summary:array<string,mixed>,refund_amount_this_call:float,currency:string}
     */
    public function executeLocked(
        Reservation $reservation,
        Collection $orders,
        Collection $payments,
        array $tableIds,
        string $paymentMethod,
        string $refundScope,
        ?float $refundAmount,
        string $baseCurrency,
        string $transactionCode,
        string $paymentProvider,
        string $notes,
        ?string $reason,
        bool $cancelAfterPayment,
        ?string $cancelReason,
        ?int $staffUserId,
        string $idempotencyKey,
        callable $syncDepositSnapshot,
        callable $releaseAppliedVoucherLocked,
        callable $cancelReservationLocked,
        callable $isDuplicatePaymentIdempotencyConstraint,
        callable $throwIfDuplicatePaymentConstraint,
        ?string $requestFingerprint = null,
    ): array {
        $reservationId = (int) $reservation->reservation_id;
        $currentStatus = (string) ($reservation->status?->value ?? $reservation->status);
        $this->assertRefundableStatus($currentStatus, $cancelAfterPayment);

        $expectedPaymentCurrency = trim((string) ($reservation->bill_currency ?? '')) !== ''
            ? (string) $reservation->bill_currency
            : $baseCurrency;
        $effectivePaymentCurrency = $this->amountCalculator->assertPaymentsSingleCurrency(
            $payments,
            $expectedPaymentCurrency,
            'currency'
        ) ?? $this->amountCalculator->normalizeCurrencyCode($expectedPaymentCurrency, 'VND');
        if (trim($baseCurrency) !== '' && $this->amountCalculator->normalizeCurrencyCode($baseCurrency, 'VND') !== $effectivePaymentCurrency) {
            throw ValidationException::withMessages([
                'currency' => ['Refund currency must match the reservation payment currency.'],
            ]);
        }

        $summaryBefore = PaymentSummary::fromPayments($payments);
        $reservationBranchId = $this->resolveReservationBranchId($reservation, $payments);
        $reservation->branch_id = $reservationBranchId;
        $availableByScope = [
            'deposit' => (float) ($summaryBefore['deposit_net_amount'] ?? 0.0),
            'final' => (float) ($summaryBefore['final_net_amount'] ?? 0.0),
        ];

        if ($cancelAfterPayment && round(array_sum($availableByScope), 2) <= 0.0001) {
            throw ValidationException::withMessages([
                'refund_amount' => ['cancel-after-payment requires at least one refundable payment.'],
            ]);
        }

        $refundPlan = $this->refundPlannerService->buildRefundPlan(
            refundScope: $refundScope,
            requestedRefundAmount: $refundAmount,
            availableByScope: $availableByScope,
            cancelAfterPayment: $cancelAfterPayment
        );

        $plannedFinalRefund = round((float) ($refundPlan['final'] ?? 0.0), 2);
        $finalNetAfter = max(0.0, round((float) ($summaryBefore['final_net_amount'] ?? 0.0) - $plannedFinalRefund, 2));
        if ($cancelAfterPayment && $finalNetAfter > 0.0001) {
            throw ValidationException::withMessages([
                'refund_amount' => 'cancel-after-payment requires all remaining final payments to be refunded first.',
            ]);
        }

        /** @var array<int,Payment> $refundPayments */
        $refundPayments = [];
        foreach (['deposit' => 'Deposit', 'final' => 'Final'] as $scopeKey => $targetType) {
            $amount = round((float) ($refundPlan[$scopeKey] ?? 0.0), 2);
            if ($amount <= 0.0001) {
                continue;
            }

            $allocations = $this->refundPlannerService->allocateRefundPaymentsBySource($payments, $targetType, $amount);
            foreach ($allocations as $index => $allocation) {
                /** @var Payment $sourcePayment */
                $sourcePayment = $allocation['payment'];
                $allocationAmount = round((float) ($allocation['amount'] ?? 0.0), 2);
                if ($allocationAmount <= 0.0001) {
                    continue;
                }

                $suffix = sprintf('%s.%d', $scopeKey, $index + 1);
                $refundPayment = new Payment();
                $refundPayment->branch_id = $reservationBranchId;
                $refundPayment->reservation_id = $reservationId;
                $refundPayment->refund_of_payment_id = (int) $sourcePayment->payment_id;
                $refundPayment->amount = $allocationAmount;
                $refundPayment->currency = trim((string) ($sourcePayment->currency ?? '')) !== ''
                    ? (string) $sourcePayment->currency
                    : $this->refundPlannerService->resolveRefundCurrency($payments, $targetType, $baseCurrency, $reservation);
                $refundPayment->payment_method = $paymentMethod;
                $refundPayment->payment_provider = trim($paymentProvider) !== '' ? trim($paymentProvider) : 'Other';
                $refundPayment->payment_type = 'Refund';
                $refundPayment->status = PaymentStatus::Refunded;
                $refundPayment->transaction_code = $this->refundPlannerService->makeDerivedKey($transactionCode, $suffix, 200);
                $refundPayment->idempotency_key = $this->refundPlannerService->makeDerivedKey($idempotencyKey, $suffix, 64);
                $refundPayment->created_by = $staffUserId;
                $refundPayment->notes = $notes !== '' ? $notes : null;
                $refundPayment->paid_at = Carbon::now('UTC');
                $refundPayment->provider_response_json = [
                    'action' => $cancelAfterPayment ? 'refund_cancel' : 'refund',
                    'refund_target_payment_type' => $targetType,
                    'refund_reason' => $reason,
                    'cancel_after_payment' => $cancelAfterPayment,
                    'cancel_reason' => $cancelReason,
                    'requested_refund_scope' => $refundScope,
                    'request_idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                    'request_fingerprint' => $requestFingerprint !== null && trim($requestFingerprint) !== '' ? trim($requestFingerprint) : null,
                    'payment_method' => $paymentMethod,
                    'payment_provider' => trim($paymentProvider) !== '' ? trim($paymentProvider) : 'Other',
                    'notes' => $notes !== '' ? $notes : null,
                    'source_payment_id' => (int) $sourcePayment->payment_id,
                    'source_transaction_code' => $sourcePayment->transaction_code,
                ];

                try {
                    $refundPayment->save();
                } catch (QueryException $e) {
                    if ($idempotencyKey !== '' && $isDuplicatePaymentIdempotencyConstraint($e)) {
                        throw $e;
                    }

                    $throwIfDuplicatePaymentConstraint($e);
                    throw $e;
                }

                $refundPayments[] = $refundPayment;
            }
        }

        $paymentsAfter = $refundPayments === []
            ? $payments
            : $payments->concat(collect($refundPayments));
        $summaryAfter = PaymentSummary::fromPayments($paymentsAfter);

        $syncDepositSnapshot($reservation, $summaryAfter, $cancelAfterPayment);

        if ($cancelAfterPayment) {
            $releaseAppliedVoucherLocked($reservation, $orders, $staffUserId, true);
            $cancelReservationLocked($reservation, $orders, $tableIds, $staffUserId, $cancelReason);
        }

        $this->loyaltyPointsService->syncReservationRefundImpactLocked(
            reservation: $reservation,
            payments: $paymentsAfter,
            staffUserId: $staffUserId,
            cancelled: $cancelAfterPayment
        );

        $reservation->updated_by = $staffUserId;
        $reservation->save();

        AuditEvent::info($cancelAfterPayment ? 'staff.reservation.refund_cancelled' : 'staff.reservation.payment_refunded', [
            'reservation_id' => $reservationId,
            'refund_payment_ids' => array_map(fn (Payment $payment) => (int) $payment->payment_id, $refundPayments),
            'refund_scope' => $refundScope,
            'refund_amount' => round(array_sum(array_map(fn (Payment $payment) => (float) ($payment->amount ?? 0.0), $refundPayments)), 2),
            'deposit_refunded_total' => (float) ($summaryAfter['deposit_refunded_amount'] ?? 0.0),
            'final_refunded_total' => (float) ($summaryAfter['final_refunded_amount'] ?? 0.0),
            'deposit_net_total' => (float) ($summaryAfter['deposit_net_amount'] ?? 0.0),
            'final_net_total' => (float) ($summaryAfter['final_net_amount'] ?? 0.0),
            'cancel_after_payment' => $cancelAfterPayment,
            'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
            'cancel_reason' => $cancelReason,
            'refund_reason' => $reason,
            'actor_user_id' => $staffUserId,
        ]);

        return [
            'refund_payment_ids' => array_map(fn (Payment $payment) => (int) $payment->payment_id, $refundPayments),
            'summary' => $summaryAfter,
            'refund_amount_this_call' => round(array_sum(array_map(fn (Payment $payment) => (float) ($payment->amount ?? 0.0), $refundPayments)), 2),
            'currency' => $effectivePaymentCurrency,
        ];
    }

    /**
     * @param Collection<int,Payment> $payments
     */
    private function resolveReservationBranchId(Reservation $reservation, Collection $payments): int
    {
        $paymentBranchIds = $payments
            ->map(fn (Payment $payment) => $payment->branch_id)
            ->filter(fn ($branchId) => $branchId !== null && $branchId !== '')
            ->values()
            ->all();

        $paymentBranchId = $paymentBranchIds !== []
            ? $this->branchContextService->assertSingleBranch(
                $paymentBranchIds,
                'Payments for the reservation must belong to a single branch.',
                'payment_id',
                false
            )
            : null;

        if ($reservation->branch_id !== null && $reservation->branch_id !== '') {
            $reservationBranchId = $this->branchContextService->resolveBranchId($reservation->branch_id, false);

            if ($paymentBranchId !== null) {
                $this->branchContextService->assertSameBranch(
                    $reservationBranchId,
                    $paymentBranchId,
                    'Payments do not belong to the reservation branch.',
                    'payment_id',
                    false
                );
            }

            return $reservationBranchId;
        }

        return $paymentBranchId ?? $this->branchContextService->resolveBranchId(null, false);
    }

    private function assertRefundableStatus(string $currentStatus, bool $cancelAfterPayment): void
    {
        if ($cancelAfterPayment) {
            if (! in_array($currentStatus, ['Confirmed', 'Reserved', 'Completed'], true)) {
                throw ValidationException::withMessages([
                    'reservation' => ['Only Confirmed, Reserved, or Completed reservations can be refunded and cancelled.'],
                ]);
            }

            return;
        }

        if ($currentStatus !== 'Completed') {
            throw ValidationException::withMessages([
                'reservation' => ['Only Completed reservations can be refunded without cancellation.'],
            ]);
        }
    }
}
