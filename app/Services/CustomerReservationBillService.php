<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\PaymentSummary;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomerReservationBillService
{
    public function __construct(
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function getReservationBill(Reservation $reservation): array
    {
        $reservation->loadMissing([
            'orders.items.item',
            'payments.refundOfPayment',
        ]);

        $status = (string) ($reservation->status?->value ?? $reservation->status);
        if (! in_array($status, [
            ReservationStatus::Reserved->value,
            ReservationStatus::Completed->value,
        ], true)) {
            throw ValidationException::withMessages([
                'reservation' => ['Bill is only available for checked-in or completed reservations.'],
            ]);
        }

        $orders = $reservation->orders
            ->filter(function ($order) {
                $orderStatus = (string) ($order->status?->value ?? $order->status);

                return in_array($orderStatus, [
                    ReservationOrderStatus::Active->value,
                    ReservationOrderStatus::Completed->value,
                ], true);
            })
            ->sortBy('order_id')
            ->values();

        if ($orders->isEmpty()) {
            throw ValidationException::withMessages([
                'reservation' => ['No billable reservation orders were found for this reservation.'],
            ]);
        }

        $snapshot = $this->reservationFinancialSyncService->computeReservationBillSnapshot(
            (int) $reservation->reservation_id,
            (float) ($reservation->discount_amount ?? 0.0),
            false,
        );

        $paymentSummary = PaymentSummary::fromPayments($reservation->payments);
        $currencyMeta = PaymentSummary::summarizeCurrencies($reservation->payments, (string) ($snapshot['currency'] ?? 'VND'));

        $totalDue = round((float) ($snapshot['total_due'] ?? 0.0), 2);
        $depositNet = round(max(0.0, (float) ($paymentSummary['deposit_net_amount'] ?? 0.0)), 2);
        $finalNet = round(max(0.0, (float) ($paymentSummary['final_net_amount'] ?? 0.0)), 2);
        $depositApplied = round(min(max(0.0, $totalDue), $depositNet), 2);
        $settled = round(min(max(0.0, $totalDue), $depositApplied + $finalNet), 2);
        $remainingDue = round(max(0.0, $totalDue - $settled), 2);
        $paymentStatus = $settled + 0.0001 >= $totalDue
            ? PaymentStatus::Success->value
            : ($settled > 0.0001 ? PaymentStatus::Partial->value : PaymentStatus::Failed->value);

        return [
            'reservation' => $reservation,
            'orders' => $orders,
            'bill' => [
                'reservation_id' => (int) $reservation->reservation_id,
                'reservation_status' => $status,
                'scope' => 'reservation',
                'subtotal' => round((float) ($snapshot['subtotal'] ?? 0.0), 2),
                'discount' => round((float) ($snapshot['discount'] ?? 0.0), 2),
                'total_due' => $totalDue,
                'currency' => (string) ($snapshot['currency'] ?? 'VND'),
                'billed_at' => $reservation->billed_at,
                'is_locked' => $reservation->billed_at !== null && $reservation->final_bill_amount !== null,
                'locked_total_due' => $reservation->final_bill_amount !== null
                    ? round((float) $reservation->final_bill_amount, 2)
                    : null,
                'locked_currency' => $reservation->bill_currency !== null ? (string) $reservation->bill_currency : null,
            ],
            'settlement' => [
                'payment_status' => $paymentStatus,
                'deposit_applied' => $depositApplied,
                'deposit_net' => $depositNet,
                'final_paid' => $finalNet,
                'paid_total' => $settled,
                'remaining_due' => $remainingDue,
                'captured_total' => round((float) ($paymentSummary['captured_amount'] ?? 0.0), 2),
                'refunded_total' => round((float) ($paymentSummary['refunded_amount'] ?? 0.0), 2),
                'net_paid_total' => round((float) ($paymentSummary['net_paid_amount'] ?? 0.0), 2),
                'currency' => $currencyMeta['currency'] ?? (string) ($snapshot['currency'] ?? 'VND'),
                'currencies' => $currencyMeta['currencies'] ?? [],
                'has_mixed_currencies' => (bool) ($currencyMeta['has_mixed_currencies'] ?? false),
            ],
            'workflow' => [
                'settlement_scope' => 'reservation',
                'bill_source' => 'reservation_financial_snapshot',
                'order_role' => 'display_detail_only',
                'payment_session_support' => [
                    'create' => false,
                    'show' => false,
                    'refresh' => false,
                    'confirm' => false,
                ],
            ],
        ];
    }

    /**
     * @param Collection<int,mixed> $orders
     * @return Collection<int,mixed>
     */
    public function customerVisibleOrders(Collection $orders): Collection
    {
        return $orders->map(function ($order) {
            $items = collect($order->items ?? [])
                ->filter(function ($item) {
                    return (string) ($item->status?->value ?? $item->status) !== ReservationOrderItemStatus::Cancelled->value;
                })
                ->sortBy('order_item_id')
                ->values();

            return [
                'order_id' => (int) $order->order_id,
                'order_type' => $order->order_type?->value ?? (string) $order->order_type,
                'status' => $order->status?->value ?? (string) $order->status,
                'created_at' => $order->created_at,
                'items' => $items,
            ];
        })->values();
    }
}
