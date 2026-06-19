<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\UseCases\PaymentSessions;

use App\Enums\PaymentStatus;
use App\Modules\Billing\Application\UseCases\Previews\SettlementAmountCalculator;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\ReservationBillPaymentSession;
use App\Modules\Payments\Domain\Policies\PaymentStatusTransitionPolicy;
use App\Modules\Payments\Infrastructure\Internal\PaymentProviderPayloadSanitizer;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use App\Support\DatabaseWriteConflictMapper;
use App\Support\ValidationExceptionFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ReservationBillPaymentService
{
    private readonly BranchContextService $branchContextService;

    public function __construct(
        private readonly SettlementAmountCalculator $settlementAmountCalculator,
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
        ?BranchContextService $branchContextService = null,
    ) {
        $this->branchContextService = $branchContextService ?? app(BranchContextService::class);
    }

    /**
     * @param  Collection<int,Payment>  $lockedPayments
     */
    public function captureSucceededCustomerSession(
        Reservation $reservation,
        ReservationBillPaymentSession $session,
        Collection $lockedPayments,
        ?int $actorUserId = null,
    ): Payment {
        $summary = PaymentSummary::fromPayments($lockedPayments);
        if (PaymentSummary::hasOverRefund($summary)) {
            throw ValidationExceptionFactory::make([
                'payments' => ['Payment state is inconsistent: refunded amount exceeds captured amount.'],
            ]);
        }

        $existingPayment = $this->replaySucceededCustomerSession($reservation, $session, $lockedPayments, $actorUserId);
        if ($existingPayment instanceof Payment) {
            return $existingPayment;
        }

        $bill = $this->summarizeLockedBill($reservation, $lockedPayments, (string) $session->currency);
        $outstandingMinor = Money::minorUnits($bill['outstanding_amount'] ?? 0, true);
        $amountMinor = Money::minorUnits($session->amount ?? 0, true);
        $outstanding = Money::minorToFloat($outstandingMinor);
        $amount = Money::minorToFloat($amountMinor);

        if ($outstandingMinor <= 0) {
            throw ValidationExceptionFactory::make([
                'bill' => ['Outstanding bill amount is already fully paid.'],
            ]);
        }

        if ($amountMinor <= 0) {
            throw ValidationExceptionFactory::make([
                'amount' => ['Bill payment amount must be greater than 0.'],
            ]);
        }

        if ($amountMinor > $outstandingMinor) {
            Log::channel('audit')->warning('bill_overpaid_by_customer_session', [
                'reservation_id' => $reservation->reservation_id,
                'session_id' => $session->bill_payment_session_id,
                'amount' => $amount,
                'outstanding_before' => $outstanding,
            ]);
        }

        $reservationBranchId = $this->resolveReservationBranchId($reservation, $lockedPayments);
        $reservation->branch_id = $reservationBranchId;

        $existingPayment = $session->linked_payment_id !== null
            ? Payment::query()->whereKey((int) $session->linked_payment_id)->first()
            : null;
        if ($existingPayment instanceof Payment) {
            if ($existingPayment->branch_id === null || $existingPayment->branch_id === '') {
                $existingPayment->branch_id = $reservationBranchId;
                $existingPayment->save();
            } else {
                $this->branchContextService->assertSameBranch(
                    $reservationBranchId,
                    $existingPayment->branch_id,
                    'Existing bill payment does not belong to the reservation branch.',
                    'payment_id',
                    false
                );
            }

            return $existingPayment;
        }

        $payment = new Payment;
        $payment->branch_id = $reservationBranchId;
        $payment->reservation_id = (int) $reservation->reservation_id;
        $payment->refund_of_payment_id = null;
        $payment->amount = Money::formatMinor($amountMinor);
        $payment->currency = (string) $bill['currency'];
        $payment->payment_method = trim((string) ($session->payment_method ?? 'Online')) ?: 'Online';
        $payment->payment_provider = trim((string) ($session->provider_code ?? 'simulated')) ?: 'simulated';
        $payment->payment_type = 'Final';
        $payment->status = $this->determinePaymentStatus($amount, $outstanding);
        $payment->transaction_code = trim((string) ($session->provider_payment_code ?? $session->provider_session_code));
        $payment->idempotency_key = $this->sessionPaymentIdempotencyKey($session);
        $payment->paid_at = Carbon::now('UTC');
        $payment->created_by = $actorUserId;
        $payment->updated_by = $actorUserId;
        $payment->notes = 'Customer-facing bill payment session recorded; awaiting staff settlement finalization.';
        $payment->provider_response_json = PaymentProviderPayloadSanitizer::sanitizePaymentResponseForStorage([
            'source' => 'customer_bill_payment_session',
            'bill_payment_session_id' => (int) $session->bill_payment_session_id,
            'provider_code' => (string) $session->provider_code,
            'provider_session_code' => (string) $session->provider_session_code,
            'provider_payment_code' => $session->provider_payment_code,
            'provider_payload' => $session->provider_payload_json,
        ]);
        try {
            $payment->save();
        } catch (QueryException $exception) {
            $existingPayment = $this->replaySucceededCustomerSession($reservation, $session, $lockedPayments, $actorUserId);
            if ($existingPayment instanceof Payment && $this->isDuplicateSessionPaymentConstraint($exception)) {
                return $existingPayment;
            }

            $mapped = DatabaseWriteConflictMapper::toValidationException($exception);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $exception;
        }

        $lockedPayments->push($payment);
        $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $actorUserId);
        $session->linked_payment_id = (int) $payment->payment_id;

        return $payment;
    }

    /**
     * @param  Collection<int,Payment>  $lockedPayments
     */
    public function replaySucceededCustomerSession(
        Reservation $reservation,
        ReservationBillPaymentSession $session,
        Collection $lockedPayments,
        ?int $actorUserId = null,
    ): ?Payment {
        $existingPayment = $this->findExistingSessionPayment($reservation, $session);
        if (! $existingPayment instanceof Payment) {
            return null;
        }

        $reservationBranchId = $this->resolveReservationBranchId($reservation, $lockedPayments);
        $reservation->branch_id = $reservationBranchId;
        $this->assertExistingSessionPaymentMatches($existingPayment, $reservation, $session, $reservationBranchId);
        $this->pushLockedPaymentIfMissing($lockedPayments, $existingPayment);
        $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $actorUserId);

        $session->linked_payment_id = (int) $existingPayment->payment_id;

        return $existingPayment;
    }

    private function findExistingSessionPayment(Reservation $reservation, ReservationBillPaymentSession $session): ?Payment
    {
        if ($session->linked_payment_id !== null) {
            /** @var Payment|null $linked */
            $linked = Payment::query()->whereKey((int) $session->linked_payment_id)->first();
            if ($linked instanceof Payment) {
                return $linked;
            }
        }

        $idempotencyKey = $this->sessionPaymentIdempotencyKey($session);
        /** @var Payment|null $byIdempotency */
        $byIdempotency = Payment::query()
            ->where('reservation_id', (int) $reservation->reservation_id)
            ->where('payment_type', 'Final')
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($byIdempotency instanceof Payment) {
            return $byIdempotency;
        }

        $transactionCode = trim((string) ($session->provider_payment_code ?? $session->provider_session_code ?? ''));
        $providerCode = trim((string) ($session->provider_code ?? ''));
        if ($transactionCode === '' || $providerCode === '') {
            return null;
        }

        /** @var Collection<int,Payment> $candidates */
        $candidates = Payment::query()
            ->where('reservation_id', (int) $reservation->reservation_id)
            ->where('payment_type', 'Final')
            ->where('payment_provider', $providerCode)
            ->where('transaction_code', $transactionCode)
            ->get();

        return $candidates->first(function (Payment $payment) use ($session): bool {
            $meta = is_array($payment->provider_response_json) ? $payment->provider_response_json : [];

            return (int) ($meta['bill_payment_session_id'] ?? 0) === (int) $session->bill_payment_session_id;
        });
    }

    private function assertExistingSessionPaymentMatches(
        Payment $payment,
        Reservation $reservation,
        ReservationBillPaymentSession $session,
        int $reservationBranchId
    ): void {
        if ((int) $payment->reservation_id !== (int) $reservation->reservation_id || (string) $payment->payment_type !== 'Final') {
            throw ValidationExceptionFactory::make([
                'payment_id' => ['Existing bill session payment does not belong to this reservation.'],
            ]);
        }

        if (Money::minorUnits($payment->amount ?? 0, true) !== Money::minorUnits($session->amount ?? 0, true)) {
            throw ValidationExceptionFactory::make([
                'amount' => ['Existing bill session payment amount does not match the payment session amount.'],
            ]);
        }

        $paymentCurrency = strtoupper(trim((string) ($payment->currency ?? '')));
        $sessionCurrency = strtoupper(trim((string) ($session->currency ?? '')));
        if ($paymentCurrency !== '' && $sessionCurrency !== '' && $paymentCurrency !== $sessionCurrency) {
            throw ValidationExceptionFactory::make([
                'currency' => ['Existing bill session payment currency does not match the payment session currency.'],
            ]);
        }

        $meta = is_array($payment->provider_response_json) ? $payment->provider_response_json : [];
        $recordedSessionId = (int) ($meta['bill_payment_session_id'] ?? 0);
        if ($recordedSessionId > 0 && $recordedSessionId !== (int) $session->bill_payment_session_id) {
            throw ValidationExceptionFactory::make([
                'payment_id' => ['Existing bill session payment is linked to a different payment session.'],
            ]);
        }

        if ($payment->branch_id === null || $payment->branch_id === '') {
            $payment->branch_id = $reservationBranchId;
            $payment->save();

            return;
        }

        $this->branchContextService->assertSameBranch(
            $reservationBranchId,
            $payment->branch_id,
            'Existing bill payment does not belong to the reservation branch.',
            'payment_id',
            false
        );
    }

    /**
     * @param  Collection<int,Payment>  $lockedPayments
     */
    private function pushLockedPaymentIfMissing(Collection $lockedPayments, Payment $payment): void
    {
        $paymentId = (int) $payment->payment_id;
        $exists = $lockedPayments->contains(fn (Payment $lockedPayment): bool => (int) $lockedPayment->payment_id === $paymentId);
        if (! $exists) {
            $lockedPayments->push($payment);
        }
    }

    private function sessionPaymentIdempotencyKey(ReservationBillPaymentSession $session): string
    {
        return 'customer-bill-session:'.(int) $session->bill_payment_session_id;
    }

    private function isDuplicateSessionPaymentConstraint(QueryException $exception): bool
    {
        return DatabaseWriteConflictMapper::isPaymentIdempotencyConflict($exception)
            || DatabaseWriteConflictMapper::isPaymentProviderTransactionConflict($exception);
    }

    /**
     * @param  Collection<int,Payment>  $payments
     * @return array<string,mixed>
     */
    public function summarizeLockedBill(Reservation $reservation, Collection $payments, string $requestedCurrency = ''): array
    {
        if ($reservation->billed_at === null || $reservation->final_bill_amount === null) {
            throw ValidationExceptionFactory::make([
                'bill' => ['Bill must be locked before customer self-payment is allowed.'],
            ]);
        }

        $totalDueMinor = Money::minorUnits($reservation->final_bill_amount ?? 0, true);
        $totalDue = Money::minorToFloat($totalDueMinor);
        if ($totalDueMinor <= 0) {
            throw ValidationExceptionFactory::make([
                'bill' => ['Reservation does not have a payable final bill.'],
            ]);
        }

        $currency = $this->resolveCurrency($reservation, $payments, $requestedCurrency);
        $settlement = $this->settlementAmountCalculator->buildSettlementAmounts($payments, $totalDue);
        $settledMinor = Money::minorUnits($settlement['settled_amount'] ?? 0, true);
        $outstandingMinor = array_key_exists('remaining_due', $settlement)
            ? Money::minorUnits($settlement['remaining_due'], true)
            : max(0, $totalDueMinor - $settledMinor);
        $settledAmount = Money::minorToFloat($settledMinor);
        $outstanding = Money::minorToFloat($outstandingMinor);
        $paymentStatus = $settledMinor >= $totalDueMinor
            ? PaymentStatus::Success->value
            : ($settledMinor > 0 ? PaymentStatus::Partial->value : PaymentStatus::Failed->value);

        return [
            'snapshot_mode' => 'locked',
            'billed_at' => $reservation->billed_at?->utc()->toIso8601String(),
            'total_due_amount' => number_format($totalDue, 0, '.', ''),
            'deposit_applied_amount' => number_format((float) ($settlement['deposit_applied_amount'] ?? 0.0), 0, '.', ''),
            'deposit_net_amount' => number_format((float) ($settlement['deposit_net_amount'] ?? 0.0), 0, '.', ''),
            'final_paid_amount' => number_format((float) ($settlement['final_paid_amount'] ?? 0.0), 0, '.', ''),
            'settled_amount' => number_format($settledAmount, 0, '.', ''),
            'outstanding_amount' => number_format($outstanding, 0, '.', ''),
            'currency' => $currency,
            'payment_status' => $paymentStatus,
        ];
    }

    /**
     * @param  Collection<int,Payment>  $payments
     */
    public function resolveCurrency(Reservation $reservation, Collection $payments, string $requestedCurrency = ''): string
    {
        $summary = PaymentSummary::summarizeCurrencies($payments, (string) ($reservation->bill_currency ?? ''));
        if (($summary['has_mixed_currencies'] ?? false) === true) {
            throw ValidationExceptionFactory::make([
                'currency' => ['Payments for the same reservation must use a single currency.'],
            ]);
        }

        $actual = strtoupper(trim((string) ($summary['currency'] ?? '')));
        $requested = strtoupper(trim($requestedCurrency));
        $reservationCurrency = strtoupper(trim((string) ($reservation->bill_currency ?? '')));

        if ($requested !== '' && $actual !== '' && $requested !== $actual) {
            throw ValidationExceptionFactory::make([
                'currency' => ['Payment currency does not match reservation bill currency.'],
            ]);
        }

        if ($requested !== '' && $reservationCurrency !== '' && $requested !== $reservationCurrency) {
            throw ValidationExceptionFactory::make([
                'currency' => ['Payment currency does not match reservation bill currency.'],
            ]);
        }

        if ($actual !== '') {
            return $actual;
        }

        if ($requested !== '') {
            return $requested;
        }

        return $reservationCurrency !== '' ? $reservationCurrency : 'VND';
    }

    private function determinePaymentStatus(float $amount, float $outstandingBefore): PaymentStatus
    {
        return PaymentStatusTransitionPolicy::captureStatusForAppliedAmount($amount, $outstandingBefore);
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
}
