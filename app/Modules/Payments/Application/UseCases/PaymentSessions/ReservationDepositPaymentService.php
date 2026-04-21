<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\UseCases\PaymentSessions;

use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Enums\PaymentStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\ReservationDepositPaymentSession;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\Payments\Infrastructure\Internal\PaymentProviderPayloadSanitizer;
use App\Support\DatabaseWriteConflictMapper;
use App\SharedKernel\Money\Money;
use App\Support\ValidationExceptionFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReservationDepositPaymentService
{
    private readonly BranchContextService $branchContextService;

    public function __construct(
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
        ReservationDepositPaymentSession $session,
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

        $currency = $this->resolveCurrency($reservation, $lockedPayments, (string) $session->currency);
        $depositRequiredMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);
        $depositPaidMinor = Money::minorUnits($summary['deposit_net_amount'] ?? 0, true);
        $outstandingMinor = max(0, $depositRequiredMinor - $depositPaidMinor);
        $amountMinor = Money::minorUnits($session->amount ?? 0, true);

        if ($depositRequiredMinor <= 0) {
            throw ValidationExceptionFactory::make([
                'deposit' => ['Reservation does not require a deposit payment.'],
            ]);
        }

        if (Money::minorUnits($summary['final_captured_amount'] ?? 0, true) > 0) {
            throw ValidationExceptionFactory::make([
                'deposit' => ['Reservation already has final settlement captured; deposit payment cannot be applied.'],
            ]);
        }

        if ($outstandingMinor <= 0) {
            throw ValidationExceptionFactory::make([
                'deposit' => ['Deposit is already fully paid.'],
            ]);
        }

        if ($amountMinor <= 0) {
            throw ValidationExceptionFactory::make([
                'amount' => ['Deposit payment amount must be greater than 0.'],
            ]);
        }

        if ($amountMinor > $outstandingMinor) {
            throw ValidationExceptionFactory::make([
                'amount' => ['Deposit payment amount exceeds the outstanding deposit balance.'],
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
                    'Existing deposit payment does not belong to the reservation branch.',
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
        $payment->currency = $currency;
        $payment->payment_method = trim((string) ($session->payment_method ?? 'Online')) ?: 'Online';
        $payment->payment_provider = trim((string) ($session->provider_code ?? 'simulated')) ?: 'simulated';
        $payment->payment_type = 'Deposit';
        $payment->status = PaymentStatus::Success;
        $payment->transaction_code = trim((string) ($session->provider_payment_code ?? $session->provider_session_code));
        $payment->idempotency_key = $this->sessionPaymentIdempotencyKey($session);
        $payment->paid_at = Carbon::now('UTC');
        $payment->created_by = $actorUserId;
        $payment->updated_by = $actorUserId;
        $payment->notes = 'Customer-facing deposit payment session applied.';
        $payment->provider_response_json = PaymentProviderPayloadSanitizer::sanitizePaymentResponseForStorage([
            'source' => 'customer_deposit_payment_session',
            'deposit_payment_session_id' => (int) $session->deposit_payment_session_id,
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
        $summaryAfter = PaymentSummary::fromPayments($lockedPayments);
        $this->reservationFinancialSyncService->syncDepositSnapshot($reservation, $summaryAfter, false);
        $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $actorUserId);

        $session->linked_payment_id = (int) $payment->payment_id;

        return $payment;
    }

    /**
     * @param  Collection<int,Payment>  $lockedPayments
     */
    public function replaySucceededCustomerSession(
        Reservation $reservation,
        ReservationDepositPaymentSession $session,
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

        $summaryAfter = PaymentSummary::fromPayments($lockedPayments);
        $this->reservationFinancialSyncService->syncDepositSnapshot($reservation, $summaryAfter, false);
        $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $actorUserId);

        $session->linked_payment_id = (int) $existingPayment->payment_id;

        return $existingPayment;
    }

    private function findExistingSessionPayment(Reservation $reservation, ReservationDepositPaymentSession $session): ?Payment
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
            ->where('payment_type', 'Deposit')
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
            ->where('payment_type', 'Deposit')
            ->where('payment_provider', $providerCode)
            ->where('transaction_code', $transactionCode)
            ->get();

        return $candidates->first(function (Payment $payment) use ($session): bool {
            $meta = is_array($payment->provider_response_json) ? $payment->provider_response_json : [];

            return (int) ($meta['deposit_payment_session_id'] ?? 0) === (int) $session->deposit_payment_session_id;
        });
    }

    private function assertExistingSessionPaymentMatches(
        Payment $payment,
        Reservation $reservation,
        ReservationDepositPaymentSession $session,
        int $reservationBranchId
    ): void {
        if ((int) $payment->reservation_id !== (int) $reservation->reservation_id || (string) $payment->payment_type !== 'Deposit') {
            throw ValidationExceptionFactory::make([
                'payment_id' => ['Existing deposit session payment does not belong to this reservation.'],
            ]);
        }

        if (Money::minorUnits($payment->amount ?? 0, true) !== Money::minorUnits($session->amount ?? 0, true)) {
            throw ValidationExceptionFactory::make([
                'amount' => ['Existing deposit session payment amount does not match the payment session amount.'],
            ]);
        }

        $paymentCurrency = strtoupper(trim((string) ($payment->currency ?? '')));
        $sessionCurrency = strtoupper(trim((string) ($session->currency ?? '')));
        if ($paymentCurrency !== '' && $sessionCurrency !== '' && $paymentCurrency !== $sessionCurrency) {
            throw ValidationExceptionFactory::make([
                'currency' => ['Existing deposit session payment currency does not match the payment session currency.'],
            ]);
        }

        $meta = is_array($payment->provider_response_json) ? $payment->provider_response_json : [];
        $recordedSessionId = (int) ($meta['deposit_payment_session_id'] ?? 0);
        if ($recordedSessionId > 0 && $recordedSessionId !== (int) $session->deposit_payment_session_id) {
            throw ValidationExceptionFactory::make([
                'payment_id' => ['Existing deposit session payment is linked to a different payment session.'],
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
            'Existing deposit payment does not belong to the reservation branch.',
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

    private function sessionPaymentIdempotencyKey(ReservationDepositPaymentSession $session): string
    {
        return 'customer-deposit-session:'.(int) $session->deposit_payment_session_id;
    }

    private function isDuplicateSessionPaymentConstraint(QueryException $exception): bool
    {
        return DatabaseWriteConflictMapper::isPaymentIdempotencyConflict($exception)
            || DatabaseWriteConflictMapper::isPaymentProviderTransactionConflict($exception);
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
