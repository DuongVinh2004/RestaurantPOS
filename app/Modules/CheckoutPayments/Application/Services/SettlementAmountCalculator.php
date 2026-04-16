<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Application\Services;

use App\Enums\PaymentStatus;
use App\Modules\CheckoutPayments\Domain\ValueObjects\PaymentSummary;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SettlementAmountCalculator
{
    /**
     * @param  Collection<int,mixed>|null  $payments
     * @return array{deposit_net_amount:float,deposit_applied_amount:float,final_paid_amount:float,settled_amount:float,remaining_due:float}
     */
    public function buildSettlementAmounts(?Collection $payments, mixed $totalDue): array
    {
        $summary = PaymentSummary::fromPayments($payments ?? collect());
        $totalDueMinor = Money::minorUnits($totalDue, true);
        $depositNetMinor = Money::minorUnits($summary['deposit_net_amount'] ?? 0, true);
        $finalPaidMinor = Money::minorUnits($summary['final_net_amount'] ?? 0, true);
        $depositAppliedMinor = min($depositNetMinor, $totalDueMinor);
        $settledMinor = $depositAppliedMinor + $finalPaidMinor;

        return [
            'deposit_net_amount' => Money::minorToFloat($depositNetMinor),
            'deposit_applied_amount' => Money::minorToFloat($depositAppliedMinor),
            'final_paid_amount' => Money::minorToFloat($finalPaidMinor),
            'settled_amount' => Money::minorToFloat($settledMinor),
            'remaining_due' => Money::minorToFloat(max(0, $totalDueMinor - $settledMinor)),
        ];
    }

    public function attachTotals(
        ReservationOrder $order,
        ?float $subtotal = null,
        ?float $discount = null,
        ?float $totalDue = null,
        ?string $currency = null
    ): ReservationOrder {
        $computedSubtotalMinor = Money::minorUnits($subtotal ?? $order->items()->sum('line_total'), true);
        $computedDiscountMinor = Money::minorUnits($discount ?? 0, true);
        $computedTotalDueMinor = $totalDue !== null
            ? Money::minorUnits($totalDue, true)
            : max(0, $computedSubtotalMinor - $computedDiscountMinor);
        $computedSubtotal = Money::minorToFloat($computedSubtotalMinor);
        $computedDiscount = Money::minorToFloat($computedDiscountMinor);
        $computedTotalDue = Money::minorToFloat($computedTotalDueMinor);
        $currencyCode = $this->normalizeCurrencyCode($currency ?? (string) ($order->reservation?->bill_currency ?? ''), 'VND');

        /** @var Collection<int,mixed> $payments */
        if ($order->relationLoaded('reservation')
            && $order->reservation !== null
            && $order->reservation->relationLoaded('payments')
        ) {
            /** @var Collection<int,mixed> $payments */
            $payments = collect($order->reservation->getRelation('payments'));
        } else {
            $payments = $order->reservation
                ? $order->reservation->payments()->get(['payment_id', 'amount', 'payment_type', 'status', 'provider_response_json', 'currency', 'refund_of_payment_id'])
                : collect();
        }

        $settlement = $this->buildSettlementAmounts($payments, $computedTotalDue);
        $paidAmountMinor = Money::minorUnits($settlement['settled_amount'] ?? 0, true);
        $remainingMinor = array_key_exists('remaining_due', $settlement)
            ? Money::minorUnits($settlement['remaining_due'], true)
            : max(0, $computedTotalDueMinor - $paidAmountMinor);
        $paidAmount = Money::minorToFloat($paidAmountMinor);
        $remaining = Money::minorToFloat($remainingMinor);

        $order->setAttribute('subtotal_amount', $computedSubtotal);
        $order->setAttribute('discount_amount', $computedDiscount);
        $order->setAttribute('total_due_amount', $computedTotalDue);
        $order->setAttribute('currency', $currencyCode);
        $order->setAttribute('paid_amount', $paidAmount);
        $order->setAttribute('deposit_applied_amount', Money::toFloat($settlement['deposit_applied_amount'] ?? 0, true));
        $order->setAttribute('deposit_net_amount', Money::toFloat($settlement['deposit_net_amount'] ?? 0, true));
        $order->setAttribute('final_paid_amount', Money::toFloat($settlement['final_paid_amount'] ?? 0, true));
        $order->setAttribute('outstanding_amount', $remaining);
        $order->setAttribute(
            'payment_status',
            $paidAmountMinor >= $computedTotalDueMinor
                ? PaymentStatus::Success->value
                : ($paidAmountMinor > 0 ? PaymentStatus::Partial->value : PaymentStatus::Failed->value)
        );

        return $order;
    }

    public function normalizeCurrencyCode(?string $currency, string $fallback = 'VND'): string
    {
        $normalized = strtoupper(trim((string) $currency));

        $normalizedFallback = strtoupper(trim($fallback));

        return $normalized !== '' ? $normalized : ($normalizedFallback !== '' ? $normalizedFallback : 'VND');
    }

    /**
     * @param  iterable<mixed>  $payments
     */
    public function assertPaymentsSingleCurrency(iterable $payments, ?string $expectedCurrency = null, string $field = 'currency'): ?string
    {
        $normalizedExpected = $expectedCurrency !== null
            ? $this->normalizeCurrencyCode($expectedCurrency, 'VND')
            : null;
        $detected = [];

        foreach ($payments as $payment) {
            $currency = $this->normalizeCurrencyCode(data_get($payment, 'currency'), $normalizedExpected ?? 'VND');
            if ($currency === '') {
                continue;
            }

            $detected[$currency] = true;
        }

        $currencies = array_keys($detected);
        sort($currencies);

        if ($normalizedExpected !== null && $currencies !== [] && $currencies !== [$normalizedExpected]) {
            throw ValidationException::withMessages([
                $field => ['Payment currency must match reservation bill currency.'],
            ]);
        }

        if (count($currencies) > 1) {
            throw ValidationException::withMessages([
                $field => ['All payments for one reservation must use the same currency.'],
            ]);
        }

        return $currencies[0] ?? $normalizedExpected;
    }
}
