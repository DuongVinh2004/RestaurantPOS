<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Application\Services;

use App\Enums\PaymentStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Ordering\Domain\Models\ReservationOrder;

class CheckoutResponseFactory
{
    public function __construct(
        private readonly SettlementAmountCalculator $amountCalculator,
    ) {}

    public function attachTotals(
        ReservationOrder $order,
        ?float $subtotal = null,
        ?float $discount = null,
        ?float $totalDue = null,
        ?string $currency = null
    ): ReservationOrder {
        return $this->amountCalculator->attachTotals($order, $subtotal, $discount, $totalDue, $currency);
    }

    /**
     * @return array<string,mixed>
     */

    /**
     * @param  array<string,float>  $summary
     * @param  array<int,int>  $refundPaymentIds
     * @return array<string,mixed>
     */
    public function buildRefundResponse(
        Reservation $reservation,
        array $summary,
        array $refundPaymentIds,
        float $refundAmountThisCall,
        string $refundScope,
        bool $cancelled,
        string $currency
    ): array {
        return [
            'reservation' => $reservation,
            'refund' => [
                'refund_payment_ids' => array_values(array_map('intval', $refundPaymentIds)),
                'refund_amount' => number_format($refundAmountThisCall, 2, '.', ''),
                'currency' => $this->amountCalculator->normalizeCurrencyCode($currency, (string) ($reservation->bill_currency ?? 'VND')),
                'refund_scope' => $refundScope,
                'cancelled' => $cancelled,
                'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
                'payment_summary' => [
                    'deposit_captured' => number_format((float) ($summary['deposit_captured_amount'] ?? 0.0), 2, '.', ''),
                    'deposit_refunded' => number_format((float) ($summary['deposit_refunded_amount'] ?? 0.0), 2, '.', ''),
                    'deposit_net' => number_format((float) ($summary['deposit_net_amount'] ?? 0.0), 2, '.', ''),
                    'final_captured' => number_format((float) ($summary['final_captured_amount'] ?? 0.0), 2, '.', ''),
                    'final_refunded' => number_format((float) ($summary['final_refunded_amount'] ?? 0.0), 2, '.', ''),
                    'final_net' => number_format((float) ($summary['final_net_amount'] ?? 0.0), 2, '.', ''),
                    'captured_total' => number_format((float) ($summary['captured_amount'] ?? 0.0), 2, '.', ''),
                    'refunded_total' => number_format((float) ($summary['refunded_amount'] ?? 0.0), 2, '.', ''),
                    'net_paid_total' => number_format((float) ($summary['net_paid_amount'] ?? 0.0), 2, '.', ''),
                ],
            ],
        ];
    }

    public function buildCheckoutResponse(ReservationOrder $order, string $fallbackCurrency = 'VND'): array
    {
        $totalDue = round((float) ($order->getAttribute('total_due_amount') ?? 0.0), 2);
        $paid = round((float) ($order->getAttribute('paid_amount') ?? 0.0), 2);
        $depositApplied = round((float) ($order->getAttribute('deposit_applied_amount') ?? 0.0), 2);
        $finalPaid = round((float) ($order->getAttribute('final_paid_amount') ?? 0.0), 2);
        $outstanding = round((float) ($order->getAttribute('outstanding_amount') ?? max(0.0, $totalDue - $paid)), 2);

        return [
            'order_id' => (int) $order->order_id,
            'reservation_id' => (int) $order->reservation_id,
            'row_version' => (int) ($order->row_version ?? 1),
            'total_amount' => $totalDue,
            'currency' => (string) ($order->getAttribute('currency') ?? $fallbackCurrency),
            'paid_amount' => $paid,
            'deposit_applied_amount' => $depositApplied,
            'final_paid_amount' => $finalPaid,
            'outstanding_amount' => $outstanding,
            'payment_status' => $paid + 0.0001 >= $totalDue
                ? PaymentStatus::Success->value
                : ($paid > 0 ? PaymentStatus::Partial->value : PaymentStatus::Failed->value),
            'order_status' => $order->status?->value ?? (string) $order->status,
            'reservation_status' => $this->resolveReservationStatus($order),
        ];
    }

    private function resolveReservationStatus(ReservationOrder $order): ?string
    {
        if ($order->relationLoaded('reservation') && $order->reservation !== null) {
            $status = $order->reservation->status;

            return $status?->value ?? (is_string($status) ? $status : null);
        }

        $status = Reservation::query()->where('reservation_id', $order->reservation_id)->value('status');

        return is_string($status) ? $status : null;
    }
}
