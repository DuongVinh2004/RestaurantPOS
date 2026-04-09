<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationDepositPaymentSession;
use App\Services\Branch\BranchContextService;
use App\Support\PaymentSummary;
use App\Support\ValidationExceptionFactory;
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
     * @param Collection<int,Payment> $lockedPayments
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

        $currency = $this->resolveCurrency($reservation, $lockedPayments, (string) $session->currency);
        $depositRequired = round(max(0.0, (float) ($reservation->deposit_required_amount ?? 0.0)), 2);
        $depositPaid = round(max(0.0, (float) ($summary['deposit_net_amount'] ?? 0.0)), 2);
        $outstanding = round(max(0.0, $depositRequired - $depositPaid), 2);
        $amount = round(max(0.0, (float) ($session->amount ?? 0.0)), 2);

        if ($depositRequired <= 0.0001) {
            throw ValidationExceptionFactory::make([
                'deposit' => ['Reservation does not require a deposit payment.'],
            ]);
        }

        if ((float) ($summary['final_captured_amount'] ?? 0.0) > 0.0001) {
            throw ValidationExceptionFactory::make([
                'deposit' => ['Reservation already has final settlement captured; deposit payment cannot be applied.'],
            ]);
        }

        if ($outstanding <= 0.0001) {
            throw ValidationExceptionFactory::make([
                'deposit' => ['Deposit is already fully paid.'],
            ]);
        }

        if ($amount <= 0.0001) {
            throw ValidationExceptionFactory::make([
                'amount' => ['Deposit payment amount must be greater than 0.'],
            ]);
        }

        if ($amount - $outstanding > 0.0001) {
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

        $payment = new Payment();
        $payment->branch_id = $reservationBranchId;
        $payment->reservation_id = (int) $reservation->reservation_id;
        $payment->refund_of_payment_id = null;
        $payment->amount = $amount;
        $payment->currency = $currency;
        $payment->payment_method = trim((string) ($session->payment_method ?? 'Online')) ?: 'Online';
        $payment->payment_provider = trim((string) ($session->provider_code ?? 'simulated')) ?: 'simulated';
        $payment->payment_type = 'Deposit';
        $payment->status = PaymentStatus::Success;
        $payment->transaction_code = trim((string) ($session->provider_payment_code ?? $session->provider_session_code));
        $payment->idempotency_key = 'customer-deposit-session:' . (int) $session->deposit_payment_session_id;
        $payment->paid_at = Carbon::now('UTC');
        $payment->created_by = $actorUserId;
        $payment->updated_by = $actorUserId;
        $payment->notes = 'Customer-facing deposit payment session applied.';
        $payment->provider_response_json = [
            'source' => 'customer_deposit_payment_session',
            'deposit_payment_session_id' => (int) $session->deposit_payment_session_id,
            'provider_code' => (string) $session->provider_code,
            'provider_session_code' => (string) $session->provider_session_code,
            'provider_payment_code' => $session->provider_payment_code,
            'provider_payload' => $session->provider_payload_json,
        ];
        $payment->save();

        $lockedPayments->push($payment);
        $summaryAfter = PaymentSummary::fromPayments($lockedPayments);
        $this->reservationFinancialSyncService->syncDepositSnapshot($reservation, $summaryAfter, false);
        $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $actorUserId);

        $session->linked_payment_id = (int) $payment->payment_id;

        return $payment;
    }

    /**
     * @param Collection<int,Payment> $payments
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
}
