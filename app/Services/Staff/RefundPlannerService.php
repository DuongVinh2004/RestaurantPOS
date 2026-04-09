<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RefundPlannerService
{
    public function __construct(
        private readonly SettlementAmountCalculator $amountCalculator,
    ) {}

    /**
     * @param array{deposit:float,final:float} $availableByScope
     * @return array{deposit:float,final:float,total:float}
     */
    public function buildRefundPlan(
        string $refundScope,
        ?float $requestedRefundAmount,
        array $availableByScope,
        bool $cancelAfterPayment
    ): array {
        $availableDeposit = round(max(0.0, (float) ($availableByScope['deposit'] ?? 0.0)), 2);
        $availableFinal = round(max(0.0, (float) ($availableByScope['final'] ?? 0.0)), 2);

        return match ($refundScope) {
            'deposit' => $this->buildSingleScopePlan('deposit', $requestedRefundAmount, $availableDeposit),
            'final' => $this->buildSingleScopePlan('final', $requestedRefundAmount, $availableFinal),
            'all' => $this->buildAllScopePlan($requestedRefundAmount, $availableDeposit, $availableFinal, $cancelAfterPayment),
            default => throw ValidationException::withMessages([
                'refund_scope' => ['refund_scope must be one of deposit, final, all.'],
            ]),
        };
    }

    /**
     * @param Collection<int,Payment> $payments
     * @return array<int,array{payment:Payment,amount:float}>
     */
    public function allocateRefundPaymentsBySource(Collection $payments, string $targetPaymentType, float $requestedAmount): array
    {
        $requestedAmount = round($requestedAmount, 2);
        if ($requestedAmount <= 0.0001) {
            return [];
        }

        $captured = $payments
            ->filter(function (Payment $payment) use ($targetPaymentType): bool {
                $status = (string) ($payment->status?->value ?? $payment->status);
                $type = (string) ($payment->payment_type?->value ?? $payment->payment_type);

                return $type === $targetPaymentType && in_array($status, ['Success', 'Partial'], true);
            })
            ->sortByDesc(fn (Payment $payment) => (int) $payment->payment_id)
            ->values();

        $refundedBySource = [];
        foreach ($payments as $payment) {
            $status = (string) ($payment->status?->value ?? $payment->status);
            $type = (string) ($payment->payment_type?->value ?? $payment->payment_type);
            $sourceId = $payment->refund_of_payment_id !== null ? (int) $payment->refund_of_payment_id : null;

            if ($type !== 'Refund' || $status !== 'Refunded' || $sourceId === null) {
                continue;
            }

            $refundedBySource[$sourceId] = round(($refundedBySource[$sourceId] ?? 0.0) + (float) ($payment->amount ?? 0.0), 2);
        }

        $remaining = $requestedAmount;
        $allocations = [];

        foreach ($captured as $sourcePayment) {
            $sourceAmount = round((float) ($sourcePayment->amount ?? 0.0), 2);
            $alreadyRefunded = round((float) ($refundedBySource[(int) $sourcePayment->payment_id] ?? 0.0), 2);
            $available = round(max(0.0, $sourceAmount - $alreadyRefunded), 2);
            if ($available <= 0.0001) {
                continue;
            }

            $allocation = round(min($available, $remaining), 2);
            if ($allocation <= 0.0001) {
                continue;
            }

            $allocations[] = [
                'payment' => $sourcePayment,
                'amount' => $allocation,
            ];
            $remaining = round($remaining - $allocation, 2);

            if ($remaining <= 0.0001) {
                break;
            }
        }

        if ($remaining > 0.0001) {
            throw ValidationException::withMessages([
                'refund_amount' => ['Requested refund exceeds available refundable payments.'],
            ]);
        }

        return $allocations;
    }

    /**
     * @param Collection<int,Payment> $payments
     */
    public function resolveRefundCurrency(Collection $payments, string $targetPaymentType, string $fallbackCurrency, Reservation $reservation): string
    {
        $targetPayment = $payments
            ->filter(fn (Payment $payment) => (string) ($payment->payment_type?->value ?? $payment->payment_type) === $targetPaymentType)
            ->sortByDesc(fn (Payment $payment) => (int) $payment->payment_id)
            ->first();

        return trim((string) ($targetPayment?->currency ?? '')) !== ''
            ? (string) $targetPayment->currency
            : $this->amountCalculator->normalizeCurrencyCode((string) ($reservation->bill_currency ?? $fallbackCurrency), 'VND');
    }

    public function makeDerivedKey(string $base, string $suffix, int $maxLength): ?string
    {
        $base = trim($base);
        $suffix = trim($suffix);
        if ($base === '') {
            return null;
        }

        $candidate = $suffix !== '' ? sprintf('%s.%s', $base, $suffix) : $base;
        if (mb_strlen($candidate) <= $maxLength) {
            return $candidate;
        }

        if ($suffix === '') {
            return mb_substr($candidate, 0, $maxLength);
        }

        $separatorLength = 1;
        $suffixLength = mb_strlen($suffix);
        $baseLimit = $maxLength - $separatorLength - $suffixLength;
        if ($baseLimit <= 0) {
            return mb_substr($candidate, 0, $maxLength);
        }

        return sprintf('%s.%s', mb_substr($base, 0, $baseLimit), $suffix);
    }

    /**
     * @return array{deposit:float,final:float,total:float}
     */
    private function buildSingleScopePlan(string $scopeKey, ?float $requestedRefundAmount, float $availableAmount): array
    {
        $requested = $requestedRefundAmount !== null ? round(max(0.0, $requestedRefundAmount), 2) : $availableAmount;
        if ($requested <= 0.0001 || $requested - $availableAmount > 0.0001) {
            throw ValidationException::withMessages([
                'refund_amount' => ['Requested refund exceeds refundable balance for the selected scope.'],
            ]);
        }

        return [
            'deposit' => $scopeKey === 'deposit' ? $requested : 0.0,
            'final' => $scopeKey === 'final' ? $requested : 0.0,
            'total' => $requested,
        ];
    }

    /**
     * @return array{deposit:float,final:float,total:float}
     */
    private function buildAllScopePlan(?float $requestedRefundAmount, float $availableDeposit, float $availableFinal, bool $cancelAfterPayment): array
    {
        $availableTotal = round($availableDeposit + $availableFinal, 2);
        if ($availableTotal <= 0.0001) {
            throw ValidationException::withMessages([
                'refund_amount' => ['There is no refundable payment to refund.'],
            ]);
        }

        $requested = $requestedRefundAmount !== null ? round(max(0.0, $requestedRefundAmount), 2) : $availableTotal;
        if ($requested <= 0.0001 || $requested - $availableTotal > 0.0001) {
            throw ValidationException::withMessages([
                'refund_amount' => ['Requested refund exceeds refundable balance.'],
            ]);
        }

        $plan = [
            'deposit' => 0.0,
            'final' => 0.0,
        ];

        if ($cancelAfterPayment || $requested > $availableDeposit + 0.0001) {
            $plan['deposit'] = $availableDeposit;
            $plan['final'] = round($requested - $plan['deposit'], 2);
        } else {
            $plan['deposit'] = $requested;
            $plan['final'] = 0.0;
        }

        return [
            'deposit' => round($plan['deposit'], 2),
            'final' => round($plan['final'], 2),
            'total' => round($requested, 2),
        ];
    }
}
