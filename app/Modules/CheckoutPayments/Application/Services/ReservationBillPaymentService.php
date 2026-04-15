<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Application\Services;

use App\Enums\PaymentStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\CheckoutPayments\Domain\Models\Payment;
use App\Modules\CheckoutPayments\Domain\Models\ReservationBillPaymentSession;
use App\Modules\CheckoutPayments\Domain\ValueObjects\PaymentSummary;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Support\ValidationExceptionFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

        $bill = $this->summarizeLockedBill($reservation, $lockedPayments, (string) $session->currency);
        $outstanding = round(max(0.0, (float) ($bill['outstanding_amount'] ?? 0.0)), 2);
        $amount = round(max(0.0, (float) ($session->amount ?? 0.0)), 2);

        if ($outstanding <= 0.0001) {
            throw ValidationExceptionFactory::make([
                'bill' => ['Outstanding bill amount is already fully paid.'],
            ]);
        }

        if ($amount <= 0.0001) {
            throw ValidationExceptionFactory::make([
                'amount' => ['Bill payment amount must be greater than 0.'],
            ]);
        }

        if ($amount - $outstanding > 0.0001) {
            throw ValidationExceptionFactory::make([
                'amount' => ['Bill payment amount exceeds the outstanding bill balance.'],
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
        $payment->amount = $amount;
        $payment->currency = (string) $bill['currency'];
        $payment->payment_method = trim((string) ($session->payment_method ?? 'Online')) ?: 'Online';
        $payment->payment_provider = trim((string) ($session->provider_code ?? 'simulated')) ?: 'simulated';
        $payment->payment_type = 'Final';
        $payment->status = $this->determinePaymentStatus($amount, $outstanding);
        $payment->transaction_code = trim((string) ($session->provider_payment_code ?? $session->provider_session_code));
        $payment->idempotency_key = 'customer-bill-session:'.(int) $session->bill_payment_session_id;
        $payment->paid_at = Carbon::now('UTC');
        $payment->created_by = $actorUserId;
        $payment->updated_by = $actorUserId;
        $payment->notes = 'Customer-facing bill payment session recorded; awaiting staff settlement finalization.';
        $payment->provider_response_json = [
            'source' => 'customer_bill_payment_session',
            'bill_payment_session_id' => (int) $session->bill_payment_session_id,
            'provider_code' => (string) $session->provider_code,
            'provider_session_code' => (string) $session->provider_session_code,
            'provider_payment_code' => $session->provider_payment_code,
            'provider_payload' => $session->provider_payload_json,
        ];
        $payment->save();

        $lockedPayments->push($payment);
        $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $actorUserId);
        $session->linked_payment_id = (int) $payment->payment_id;

        return $payment;
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

        $totalDue = round(max(0.0, (float) ($reservation->final_bill_amount ?? 0.0)), 2);
        if ($totalDue <= 0.0001) {
            throw ValidationExceptionFactory::make([
                'bill' => ['Reservation does not have a payable final bill.'],
            ]);
        }

        $currency = $this->resolveCurrency($reservation, $payments, $requestedCurrency);
        $settlement = $this->settlementAmountCalculator->buildSettlementAmounts($payments, $totalDue);
        $settledAmount = round((float) ($settlement['settled_amount'] ?? 0.0), 2);
        $outstanding = round((float) ($settlement['remaining_due'] ?? max(0.0, $totalDue - $settledAmount)), 2);
        $paymentStatus = $settledAmount + 0.0001 >= $totalDue
            ? PaymentStatus::Success->value
            : ($settledAmount > 0.0001 ? PaymentStatus::Partial->value : PaymentStatus::Failed->value);

        return [
            'snapshot_mode' => 'locked',
            'billed_at' => $reservation->billed_at?->utc()->toIso8601String(),
            'total_due_amount' => number_format($totalDue, 2, '.', ''),
            'deposit_applied_amount' => number_format((float) ($settlement['deposit_applied_amount'] ?? 0.0), 2, '.', ''),
            'deposit_net_amount' => number_format((float) ($settlement['deposit_net_amount'] ?? 0.0), 2, '.', ''),
            'final_paid_amount' => number_format((float) ($settlement['final_paid_amount'] ?? 0.0), 2, '.', ''),
            'settled_amount' => number_format($settledAmount, 2, '.', ''),
            'outstanding_amount' => number_format($outstanding, 2, '.', ''),
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
        return round($amount, 4) + 0.0001 >= round($outstandingBefore, 4)
            ? PaymentStatus::Success
            : PaymentStatus::Partial;
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
