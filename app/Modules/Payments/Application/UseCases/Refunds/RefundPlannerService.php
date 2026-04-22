<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\UseCases\Refunds;

use App\Modules\Billing\Application\UseCases\Previews\SettlementAmountCalculator;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Policies\PaymentStatusTransitionPolicy;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RefundPlannerService
{
    public function __construct(
        private readonly SettlementAmountCalculator $amountCalculator,
    ) {}

    /**
     * @param  array{deposit:float,final:float}  $availableByScope
     * @return array{deposit:float,final:float,total:float}
     */
    public function buildRefundPlan(
        string $refundScope,
        mixed $requestedRefundAmount,
        array $availableByScope,
        bool $cancelAfterPayment
    ): array {
        $availableDeposit = Money::minorUnits($availableByScope['deposit'] ?? 0, true);
        $availableFinal = Money::minorUnits($availableByScope['final'] ?? 0, true);

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
     * @param  Collection<int,Payment>  $payments
     * @return array<int,array{payment:Payment,amount:float}>
     */
    public function allocateRefundPaymentsBySource(Collection $payments, string $targetPaymentType, mixed $requestedAmount): array
    {
        $requestedMinor = Money::minorUnits($requestedAmount, true);
        if ($requestedMinor <= 0) {
            return [];
        }

        $captured = $payments
            ->filter(function (Payment $payment) use ($targetPaymentType): bool {
                $status = (string) ($payment->status?->value ?? $payment->status);
                $type = (string) ($payment->payment_type?->value ?? $payment->payment_type);

                return $type === $targetPaymentType && PaymentStatusTransitionPolicy::isRefundableSourceStatus($status);
            })
            ->sortByDesc(fn (Payment $payment) => (int) $payment->payment_id)
            ->values();

        $refundedBySource = [];
        foreach ($payments as $payment) {
            $status = (string) ($payment->status?->value ?? $payment->status);
            $type = (string) ($payment->payment_type?->value ?? $payment->payment_type);
            $sourceId = $payment->refund_of_payment_id !== null ? (int) $payment->refund_of_payment_id : null;

            if ($type !== 'Refund' || ! PaymentStatusTransitionPolicy::isRefundedStatus($status) || $sourceId === null) {
                continue;
            }

            $refundedBySource[$sourceId] = ($refundedBySource[$sourceId] ?? 0) + Money::minorUnits($payment->amount ?? 0, true);
        }

        $remainingMinor = $requestedMinor;
        $allocations = [];

        foreach ($captured as $sourcePayment) {
            $sourceMinor = Money::minorUnits($sourcePayment->amount ?? 0, true);
            $alreadyRefundedMinor = (int) ($refundedBySource[(int) $sourcePayment->payment_id] ?? 0);
            $availableMinor = max(0, $sourceMinor - $alreadyRefundedMinor);
            if ($availableMinor <= 0) {
                continue;
            }

            $allocationMinor = min($availableMinor, $remainingMinor);
            if ($allocationMinor <= 0) {
                continue;
            }

            $allocations[] = [
                'payment' => $sourcePayment,
                'amount' => Money::minorToFloat($allocationMinor),
            ];
            $remainingMinor -= $allocationMinor;

            if ($remainingMinor <= 0) {
                break;
            }
        }

        if ($remainingMinor > 0) {
            throw ValidationException::withMessages([
                'refund_amount' => ['Requested refund exceeds available refundable payments.'],
            ]);
        }

        return $allocations;
    }

    /**
     * @param  Collection<int,Payment>  $payments
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
    private function buildSingleScopePlan(string $scopeKey, mixed $requestedRefundAmount, int $availableMinor): array
    {
        $requestedMinor = $requestedRefundAmount !== null
            ? Money::minorUnits($requestedRefundAmount, true)
            : $availableMinor;
        if ($requestedMinor <= 0 || $requestedMinor > $availableMinor) {
            throw ValidationException::withMessages([
                'refund_amount' => ['Requested refund exceeds refundable balance for the selected scope.'],
            ]);
        }

        return [
            'deposit' => $scopeKey === 'deposit' ? Money::minorToFloat($requestedMinor) : 0.0,
            'final' => $scopeKey === 'final' ? Money::minorToFloat($requestedMinor) : 0.0,
            'total' => Money::minorToFloat($requestedMinor),
        ];
    }

    /**
     * @return array{deposit:float,final:float,total:float}
     */
    private function buildAllScopePlan(mixed $requestedRefundAmount, int $availableDepositMinor, int $availableFinalMinor, bool $cancelAfterPayment): array
    {
        $availableTotalMinor = $availableDepositMinor + $availableFinalMinor;
        if ($availableTotalMinor <= 0) {
            throw ValidationException::withMessages([
                'refund_amount' => ['There is no refundable payment to refund.'],
            ]);
        }

        $requestedMinor = $requestedRefundAmount !== null
            ? Money::minorUnits($requestedRefundAmount, true)
            : $availableTotalMinor;
        if ($requestedMinor <= 0 || $requestedMinor > $availableTotalMinor) {
            throw ValidationException::withMessages([
                'refund_amount' => ['Requested refund exceeds refundable balance.'],
            ]);
        }

        $plan = [
            'deposit' => 0,
            'final' => 0,
        ];

        if ($cancelAfterPayment || $requestedMinor > $availableDepositMinor) {
            $plan['deposit'] = $availableDepositMinor;
            $plan['final'] = $requestedMinor - $plan['deposit'];
        } else {
            $plan['deposit'] = $requestedMinor;
            $plan['final'] = 0;
        }

        return [
            'deposit' => Money::minorToFloat($plan['deposit']),
            'final' => Money::minorToFloat($plan['final']),
            'total' => Money::minorToFloat($requestedMinor),
        ];
    }
}
