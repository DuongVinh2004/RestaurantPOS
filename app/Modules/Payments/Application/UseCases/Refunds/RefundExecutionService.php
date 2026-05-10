<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\UseCases\Refunds;

use App\Enums\PaymentStatus;
use App\Modules\Billing\Application\UseCases\Previews\SettlementAmountCalculator;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use App\Support\AuditEvent;
use App\Support\Auth\StaffActorGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Dieu phoi refund tren reservation:
 * lap ke hoach hoan tien, tao payment refund, va neu can thi huy reservation sau refund.
 */
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
     * @param  array{deposit:float,final:float}  $availableByScope
     * @return array{deposit:float,final:float,total:float}
     */
    public function buildRefundPlan(string $refundScope, mixed $requestedRefundAmount, array $availableByScope, bool $cancelAfterPayment): array
    {
        return $this->refundPlannerService->buildRefundPlan($refundScope, $requestedRefundAmount, $availableByScope, $cancelAfterPayment);
    }

    /**
     * @param  Collection<int,Payment>  $payments
     * @return array<int,array{payment:Payment,amount:float}>
     */
    public function allocateRefundPaymentsBySource(Collection $payments, string $targetPaymentType, mixed $requestedAmount): array
    {
        return $this->refundPlannerService->allocateRefundPaymentsBySource($payments, $targetPaymentType, $requestedAmount);
    }

    /**
     * @param  Collection<int,ReservationOrder>  $orders
     * @param  Collection<int,Payment>  $payments
     * @param  callable(Reservation,array<string,mixed>,bool):void  $syncDepositSnapshot
     * @param  callable(Reservation,Collection<int,ReservationOrder>,?int,bool):float  $releaseAppliedVoucherLocked
     * @param  callable(Reservation,Collection<int,ReservationOrder>,array<int,int>,?int,?string):void  $cancelReservationLocked
     * @param  callable(QueryException):bool  $isDuplicatePaymentIdempotencyConstraint
     * @param  callable(QueryException):void  $throwIfDuplicatePaymentConstraint
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
        ?int $cashierShiftId = null,
    ): array {
        // Caller da lock reservation/orders/payments; ham nay xu ly logic refund tren snapshot da khoa.
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        $reservationId = (int) $reservation->reservation_id;
        $currentStatus = (string) ($reservation->status?->value ?? $reservation->status);
        $this->assertRefundableStatus($currentStatus, $cancelAfterPayment);
        $this->assertPaymentsBelongToReservation($reservationId, $payments);

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

        // Pha 1: chup payment summary hien tai de biet so du refund theo deposit/final.
        $summaryBefore = PaymentSummary::fromPayments($payments);
        $reservationBranchId = $this->resolveReservationBranchId($reservation, $payments);
        $reservation->branch_id = $reservationBranchId;
        $availableByScope = [
            'deposit' => (float) ($summaryBefore['deposit_net_amount'] ?? 0.0),
            'final' => (float) ($summaryBefore['final_net_amount'] ?? 0.0),
        ];

        if ($cancelAfterPayment && Money::minorUnits($availableByScope['deposit'] ?? 0, true) + Money::minorUnits($availableByScope['final'] ?? 0, true) <= 0) {
            throw ValidationException::withMessages([
                'refund_amount' => ['cancel-after-payment requires at least one refundable payment.'],
            ]);
        }

        // Refund plan chia ro deposit/final de xu ly dung nguon tien va dung gioi han.
        $refundPlan = $this->refundPlannerService->buildRefundPlan(
            refundScope: $refundScope,
            requestedRefundAmount: $refundAmount,
            availableByScope: $availableByScope,
            cancelAfterPayment: $cancelAfterPayment
        );

        $plannedFinalRefundMinor = Money::minorUnits($refundPlan['final'] ?? 0, true);
        $finalNetAfterMinor = max(0, Money::minorUnits($summaryBefore['final_net_amount'] ?? 0, true) - $plannedFinalRefundMinor);
        if ($cancelAfterPayment && $finalNetAfterMinor > 0) {
            throw ValidationException::withMessages([
                'refund_amount' => 'cancel-after-payment requires all remaining final payments to be refunded first.',
            ]);
        }

        /** @var array<int,Payment> $refundPayments */
        $refundPayments = [];
        // Pha 2: chia refund thanh cac allocation theo tung payment goc de audit/doi soat minh bach.
        foreach (['deposit' => 'Deposit', 'final' => 'Final'] as $scopeKey => $targetType) {
            $amountMinor = Money::minorUnits($refundPlan[$scopeKey] ?? 0, true);
            if ($amountMinor <= 0) {
                continue;
            }

            $allocations = $this->refundPlannerService->allocateRefundPaymentsBySource($payments, $targetType, Money::minorToFloat($amountMinor));
            foreach ($allocations as $index => $allocation) {
                /** @var Payment $sourcePayment */
                $sourcePayment = $allocation['payment'];
                $allocationMinor = Money::minorUnits($allocation['amount'] ?? 0, true);
                if ($allocationMinor <= 0) {
                    continue;
                }

                $suffix = sprintf('%s.%d', $scopeKey, $index + 1);
                $refundPayment = new Payment;
                $refundPayment->branch_id = $reservationBranchId;
                $refundPayment->cashier_shift_id = $cashierShiftId !== null && $cashierShiftId > 0 ? $cashierShiftId : null;
                $refundPayment->reservation_id = $reservationId;
                $refundPayment->refund_of_payment_id = (int) $sourcePayment->payment_id;
                $refundPayment->amount = Money::formatMinor($allocationMinor);
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
                    'cashier_shift_id' => $cashierShiftId !== null && $cashierShiftId > 0 ? $cashierShiftId : null,
                    'source_payment_id' => (int) $sourcePayment->payment_id,
                    'source_transaction_code' => $sourcePayment->transaction_code,
                ];

                // Moi allocation tao ra mot payment refund rieng de audit va doi soat duoc tung nguon.
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

        // Pha 3: tinh summary moi tren payments cu + refund payments vua tao.
        $paymentsAfter = $refundPayments === []
            ? $payments
            : $payments->concat(collect($refundPayments));
        $summaryAfter = PaymentSummary::fromPayments($paymentsAfter);

        $syncDepositSnapshot($reservation, $summaryAfter, $cancelAfterPayment);

        // cancel-after-payment la flow refund xong roi don sach reservation/order/table ngay trong cung transaction.
        if ($cancelAfterPayment) {
            $releaseAppliedVoucherLocked($reservation, $orders, $staffUserId, true);
            $cancelReservationLocked($reservation, $orders, $tableIds, $staffUserId, $cancelReason);
        }

        // Loyalty impact va reservation updated_by duoc xu ly sau khi summary refund da ro rang.
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
            'refund_amount' => Money::minorToFloat(Money::sumMinor($refundPayments, fn (Payment $payment): mixed => $payment->amount ?? 0, true)),
            'deposit_refunded_total' => (float) ($summaryAfter['deposit_refunded_amount'] ?? 0.0),
            'final_refunded_total' => (float) ($summaryAfter['final_refunded_amount'] ?? 0.0),
            'deposit_net_total' => (float) ($summaryAfter['deposit_net_amount'] ?? 0.0),
            'final_net_total' => (float) ($summaryAfter['final_net_amount'] ?? 0.0),
            'cancel_after_payment' => $cancelAfterPayment,
            'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
            'cancel_reason' => $cancelReason,
            'refund_reason' => $reason,
            'cashier_shift_id' => $cashierShiftId !== null && $cashierShiftId > 0 ? $cashierShiftId : null,
            'actor_user_id' => $staffUserId,
        ]);

        return [
            'refund_payment_ids' => array_map(fn (Payment $payment) => (int) $payment->payment_id, $refundPayments),
            'summary' => $summaryAfter,
            'refund_amount_this_call' => Money::minorToFloat(Money::sumMinor($refundPayments, fn (Payment $payment): mixed => $payment->amount ?? 0, true)),
            'currency' => $effectivePaymentCurrency,
        ];
    }

    /**
     * @param  Collection<int,Payment>  $payments
     */
    private function assertPaymentsBelongToReservation(int $reservationId, Collection $payments): void
    {
        if ($reservationId <= 0) {
            throw ValidationException::withMessages([
                'reservation_id' => ['Refund execution requires a persisted reservation.'],
            ]);
        }

        foreach ($payments as $payment) {
            $paymentReservationId = $payment->reservation_id;
            if ($paymentReservationId === null || $paymentReservationId === '' || (int) $paymentReservationId !== $reservationId) {
                throw ValidationException::withMessages([
                    'refund_of_payment_id' => ['Refund source payments must belong to the reservation being refunded.'],
                ]);
            }
        }
    }

    /**
     * @param  Collection<int,Payment>  $payments
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
