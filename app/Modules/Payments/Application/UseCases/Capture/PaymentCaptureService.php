<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\UseCases\Capture;

use App\Enums\PaymentStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\Billing\Application\UseCases\Previews\CheckoutResponseFactory;
use App\Modules\Billing\Application\UseCases\Previews\SettlementAmountCalculator;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Policies\PaymentStatusTransitionPolicy;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use App\Support\AuditEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PaymentCaptureService
{
    private readonly BranchContextService $branchContextService;

    public function __construct(
        private readonly SettlementAmountCalculator $amountCalculator,
        private readonly CheckoutResponseFactory $checkoutResponseFactory,
        private readonly NotificationOutboxService $notificationOutboxService,
        ?BranchContextService $branchContextService = null,
    ) {
        $this->branchContextService = $branchContextService ?? app(BranchContextService::class);
    }

    public function determinePaymentStatus(float $paidAmount, float $remainingDue): PaymentStatus
    {
        return PaymentStatusTransitionPolicy::captureStatusForAppliedAmount($paidAmount, $remainingDue);
    }

    public function isSettled(float $paidAmount, float $remainingDue): bool
    {
        return Money::minorUnits($paidAmount, true) >= Money::minorUnits($remainingDue, true);
    }

    /**
     * @param  callable(int,float):array{0:float,1:float,2:float,3:string}  $computeReservationBillSnapshot
     * @param  callable(int,string):?ReservationOrder  $findExistingOrderPaymentReplay
     * @param  callable(int,string,string,int):void  $paymentReplayCachePut
     * @param  callable(QueryException):bool  $isDuplicatePaymentIdempotencyConstraint
     * @param  callable(QueryException):void  $throwIfDuplicatePaymentConstraint
     * @param  callable(Reservation,?int):void  $completeReservationSettlement
     * @param  callable(Reservation,?int):void  $touchFinancialMutation
     */
    public function executeLocked(
        ReservationOrder $order,
        Reservation $reservation,
        callable $computeReservationBillSnapshot,
        callable $findExistingOrderPaymentReplay,
        callable $paymentReplayCachePut,
        callable $isDuplicatePaymentIdempotencyConstraint,
        callable $throwIfDuplicatePaymentConstraint,
        callable $completeReservationSettlement,
        callable $touchFinancialMutation,
        string $paymentMethod,
        float $paidAmount,
        string $currency,
        string $transactionCode,
        string $paymentProvider,
        string $notes,
        ?int $staffUserId,
        string $idempotencyKey,
        ?string $requestFingerprint = null,
    ): ReservationOrder {
        if (($reservation->status?->value ?? (string) $reservation->status) !== ReservationStatus::Reserved->value) {
            throw ValidationException::withMessages(['reservation' => 'Reservation must be in service (Reserved) to pay.']);
        }
        if (($order->status?->value ?? (string) $order->status) !== ReservationOrderStatus::Active->value) {
            throw ValidationException::withMessages(['order_id' => 'Only active orders can be paid.']);
        }
        if (($order->order_type?->value ?? (string) $order->order_type) !== ReservationOrderType::OnSpot->value) {
            throw ValidationException::withMessages(['order_id' => 'Only on-spot orders can be used as payment anchor.']);
        }

        $this->assertIdempotencyKeyFitsStorage($idempotencyKey);

        [$subtotal, $discount, $totalDue, $currencyCode] = $computeReservationBillSnapshot(
            (int) $reservation->reservation_id,
            (float) ($reservation->discount_amount ?? 0.0)
        );
        $paymentCurrency = $this->amountCalculator->normalizeCurrencyCode($currency, $currencyCode);
        $billCurrency = $this->amountCalculator->normalizeCurrencyCode($currencyCode, 'VND');
        if ($paymentCurrency !== $billCurrency) {
            throw ValidationException::withMessages([
                'currency' => ['Payment currency must match reservation bill currency.'],
            ]);
        }

        $lockedPayments = Payment::query()
            ->where('reservation_id', $reservation->reservation_id)
            ->lockForUpdate()
            ->get(['payment_id', 'amount', 'payment_type', 'status', 'provider_response_json', 'currency', 'refund_of_payment_id']);
        $this->amountCalculator->assertPaymentsSingleCurrency($lockedPayments, $billCurrency, 'currency');
        $reservationBranchId = $this->resolveReservationBranchId($reservation, $lockedPayments);
        $reservation->branch_id = $reservationBranchId;

        $settlementBefore = $this->amountCalculator->buildSettlementAmounts($lockedPayments, $totalDue);
        $remainingDueMinor = Money::minorUnits($settlementBefore['remaining_due'] ?? 0, true);
        $paidAmountMinor = Money::minorUnits($paidAmount, true);
        $remainingDue = Money::minorToFloat($remainingDueMinor);

        if ($remainingDueMinor <= 0) {
            $completeReservationSettlement($reservation, $staffUserId);
            $order = $order->fresh() ?? $order;
            $reservation->refresh()->loadMissing('user', 'tables', 'payments');
            $this->notificationOutboxService->enqueueCheckoutCompleted($reservation);

            return $this->checkoutResponseFactory->attachTotals($order, $subtotal, $discount, $totalDue, $currencyCode);
        }

        if ($paidAmountMinor > $remainingDueMinor) {
            throw ValidationException::withMessages(['paid_amount' => 'paid_amount cannot exceed remaining_due.']);
        }
        if ($paidAmountMinor <= 0) {
            throw ValidationException::withMessages(['paid_amount' => 'paid_amount must be greater than 0 when there is remaining balance.']);
        }

        $payment = new Payment;
        $payment->branch_id = $reservationBranchId;
        $payment->reservation_id = $reservation->reservation_id;
        $payment->amount = Money::formatMinor($paidAmountMinor);
        $payment->currency = $paymentCurrency;
        $payment->payment_method = $paymentMethod;
        $payment->payment_provider = trim($paymentProvider) !== '' ? trim($paymentProvider) : 'Other';
        $payment->payment_type = 'Final';
        $payment->transaction_code = $transactionCode !== '' ? $transactionCode : null;
        $payment->idempotency_key = $idempotencyKey !== '' ? $idempotencyKey : null;
        $payment->created_by = $staffUserId;
        $payment->notes = trim($notes) !== '' ? trim($notes) : null;
        $payment->paid_at = Carbon::now('UTC');
        $payment->status = $this->determinePaymentStatus(Money::minorToFloat($paidAmountMinor), $remainingDue);
        $payment->provider_response_json = [
            'action' => 'capture',
            'request_idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            'request_fingerprint' => $requestFingerprint !== null && trim($requestFingerprint) !== '' ? trim($requestFingerprint) : null,
            'order_id' => (int) $order->order_id,
            'reservation_id' => (int) $reservation->reservation_id,
            'payment_provider' => trim($paymentProvider) !== '' ? trim($paymentProvider) : 'Other',
            'payment_method' => trim($paymentMethod),
        ];

        try {
            $payment->save();
        } catch (QueryException $e) {
            if ($idempotencyKey !== '' && $isDuplicatePaymentIdempotencyConstraint($e)) {
                /** @var Payment|null $existing */
                $existing = Payment::query()
                    ->where('reservation_id', (int) $reservation->reservation_id)
                    ->where('payment_type', 'Final')
                    ->where('idempotency_key', $idempotencyKey)
                    ->orderBy('payment_id')
                    ->first();

                if ($existing) {
                    $meta = is_array($existing->provider_response_json) ? $existing->provider_response_json : [];
                    $recordedFingerprint = trim((string) ($meta['request_fingerprint'] ?? ''));
                    if ($requestFingerprint !== null && trim($requestFingerprint) !== '' && $recordedFingerprint !== '' && ! hash_equals($recordedFingerprint, trim($requestFingerprint))) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => ['This idempotency key is already bound to a different payment request payload.'],
                        ]);
                    }
                }

                $replayed = $findExistingOrderPaymentReplay((int) $order->order_id, $idempotencyKey);
                if ($replayed !== null) {
                    return $replayed;
                }
            }

            $throwIfDuplicatePaymentConstraint($e);
            throw $e;
        }

        if ($idempotencyKey !== '') {
            $paymentReplayCachePut((int) $order->order_id, $idempotencyKey, (string) $order->order_id, 3600);
        }

        $paymentsAfterSave = Payment::query()
            ->where('reservation_id', $reservation->reservation_id)
            ->lockForUpdate()
            ->get(['payment_id', 'amount', 'payment_type', 'status', 'provider_response_json', 'currency', 'refund_of_payment_id']);
        $settlementAfter = $this->amountCalculator->buildSettlementAmounts($paymentsAfterSave, $totalDue);
        if ($this->isSettled((float) $settlementAfter['settled_amount'], $totalDue)) {
            $completeReservationSettlement($reservation, $staffUserId);
            $order = $order->fresh() ?? $order;
            $reservation->refresh()->loadMissing('user', 'tables', 'payments');
            $this->notificationOutboxService->enqueueCheckoutCompleted($reservation);
        } else {
            $touchFinancialMutation($reservation, $staffUserId);
        }

        AuditEvent::info('staff_order_payment_recorded', [
            'order_id' => (int) $order->order_id,
            'reservation_id' => (int) $reservation->reservation_id,
            'payment_id' => (int) $payment->payment_id,
            'payment_status' => (string) ($payment->status?->value ?? $payment->status),
            'paid_amount' => Money::minorToFloat($paidAmountMinor),
            'total_due' => $totalDue,
            'deposit_applied_amount_before' => (float) ($settlementBefore['deposit_applied_amount'] ?? 0.0),
            'remaining_due_before' => $remainingDue,
            'remaining_due_after' => (float) ($settlementAfter['remaining_due'] ?? 0.0),
            'currency' => (string) $payment->currency,
            'actor_user_id' => $staffUserId,
        ]);

        return $this->checkoutResponseFactory->attachTotals($order, $subtotal, $discount, $totalDue, $currencyCode);
    }

    /**
     * @param  iterable<int,Payment>  $payments
     */
    private function resolveReservationBranchId(Reservation $reservation, iterable $payments): int
    {
        $paymentBranchIds = [];
        foreach ($payments as $payment) {
            if ($payment->branch_id === null || $payment->branch_id === '') {
                continue;
            }

            $paymentBranchIds[] = $payment->branch_id;
        }

        $paymentBranchId = $paymentBranchIds !== []
            ? $this->branchContextService->assertSingleBranch(
                $paymentBranchIds,
                'Existing payments for the reservation must belong to a single branch.',
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
                    'Existing payments do not belong to the reservation branch.',
                    'payment_id',
                    false
                );
            }

            return $reservationBranchId;
        }

        return $paymentBranchId ?? $this->branchContextService->resolveBranchId(null, false);
    }

    private function assertIdempotencyKeyFitsStorage(string $idempotencyKey): void
    {
        $trimmed = trim($idempotencyKey);
        if ($trimmed === '') {
            return;
        }

        if (mb_strlen($trimmed) > Payment::IDEMPOTENCY_KEY_MAX_LENGTH) {
            throw ValidationException::withMessages([
                'idempotency_key' => [
                    sprintf(
                        'Idempotency-Key may not exceed %d characters for payment capture.',
                        Payment::IDEMPOTENCY_KEY_MAX_LENGTH,
                    ),
                ],
            ]);
        }
    }
}
