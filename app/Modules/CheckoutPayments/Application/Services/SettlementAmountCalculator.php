<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Application\Services;

use App\Enums\PaymentStatus;
use App\Modules\CheckoutPayments\Domain\ValueObjects\PaymentSummary;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SettlementAmountCalculator
{
    /**
     * @param  Collection<int,mixed>|null  $payments
     * @return array{deposit_net_amount:float,deposit_applied_amount:float,final_paid_amount:float,settled_amount:float,remaining_due:float}
     */
    public function buildSettlementAmounts(?Collection $payments, float $totalDue): array
    {
        $summary = PaymentSummary::fromPayments($payments ?? collect());
        $depositApplied = min((float) ($summary['deposit_net_amount'] ?? 0.0), $totalDue);
        $finalPaid = (float) ($summary['final_net_amount'] ?? 0.0);
        $settled = round($depositApplied + $finalPaid, 2);

        return [
            'deposit_net_amount' => round((float) ($summary['deposit_net_amount'] ?? 0.0), 2),
            'deposit_applied_amount' => round($depositApplied, 2),
            'final_paid_amount' => round($finalPaid, 2),
            'settled_amount' => round($settled, 2),
            'remaining_due' => round(max(0.0, $totalDue - $settled), 2),
        ];
    }

    public function attachTotals(
        ReservationOrder $order,
        ?float $subtotal = null,
        ?float $discount = null,
        ?float $totalDue = null,
        ?string $currency = null
    ): ReservationOrder {
        $computedSubtotal = round($subtotal ?? (float) $order->items()->sum('line_total'), 2);
        $computedDiscount = round(max(0.0, $discount ?? 0.0), 2);
        $computedTotalDue = round($totalDue ?? max(0.0, $computedSubtotal - $computedDiscount), 2);
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
        $paidAmount = round((float) ($settlement['settled_amount'] ?? 0.0), 2);
        $remaining = round((float) ($settlement['remaining_due'] ?? max(0.0, $computedTotalDue - $paidAmount)), 2);

        $order->setAttribute('subtotal_amount', $computedSubtotal);
        $order->setAttribute('discount_amount', $computedDiscount);
        $order->setAttribute('total_due_amount', $computedTotalDue);
        $order->setAttribute('currency', $currencyCode);
        $order->setAttribute('paid_amount', $paidAmount);
        $order->setAttribute('deposit_applied_amount', round((float) ($settlement['deposit_applied_amount'] ?? 0.0), 2));
        $order->setAttribute('deposit_net_amount', round((float) ($settlement['deposit_net_amount'] ?? 0.0), 2));
        $order->setAttribute('final_paid_amount', round((float) ($settlement['final_paid_amount'] ?? 0.0), 2));
        $order->setAttribute('outstanding_amount', $remaining);
        $order->setAttribute(
            'payment_status',
            $paidAmount + 0.0001 >= $computedTotalDue
                ? PaymentStatus::Success->value
                : ($paidAmount > 0 ? PaymentStatus::Partial->value : PaymentStatus::Failed->value)
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
