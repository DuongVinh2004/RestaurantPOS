<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Application\Services;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\CheckoutPayments\Domain\ValueObjects\PaymentSummary;
use App\Support\ValidationExceptionFactory;
use Illuminate\Support\Facades\DB;

class ReservationFinancialSyncService
{
    /**
     * @return array{subtotal:float,discount:float,total_due:float,currency:string}
     */
    public function computeReservationBillSnapshot(int $reservationId, float $discountAmount, bool $lockOrders = true): array
    {
        $query = DB::table('reservation_orders as ro')
            ->join('reservation_order_items as roi', 'roi.order_id', '=', 'ro.order_id')
            ->where('ro.reservation_id', $reservationId)
            ->whereIn('ro.status', [ReservationOrderStatus::Active->value, ReservationOrderStatus::Completed->value])
            ->select(['roi.line_total', 'roi.currency', 'roi.status']);

        if ($lockOrders) {
            $query->lockForUpdate();
        }

        $items = $query->get();
        $subtotal = 0.0;
        $currency = null;

        foreach ($items as $item) {
            if ((string) ($item->status ?? '') === ReservationOrderItemStatus::Cancelled->value) {
                continue;
            }

            $subtotal += (float) ($item->line_total ?? 0.0);
            $itemCurrency = trim((string) ($item->currency ?? ''));
            if ($itemCurrency !== '') {
                if ($currency === null) {
                    $currency = $itemCurrency;
                } elseif ($currency !== $itemCurrency) {
                    throw ValidationExceptionFactory::make([
                        'currency' => sprintf('Mixed currency is not supported for reservation %d (%s vs %s).', $reservationId, $currency, $itemCurrency),
                    ]);
                }
            }
        }

        $discount = round(max(0.0, $discountAmount), 2);
        $subtotal = round(max(0.0, $subtotal), 2);
        $totalDue = round(max(0.0, $subtotal - $discount), 2);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total_due' => $totalDue,
            'currency' => $currency ?: 'VND',
        ];
    }

    public function syncReservationDiscountSnapshot(Reservation $reservation, float $totalDiscount, bool $lockOrders = true): void
    {
        $reservation->discount_amount = round(max(0.0, $totalDiscount), 2);

        if ($reservation->billed_at === null && $reservation->final_bill_amount === null) {
            return;
        }

        $snapshot = $this->computeReservationBillSnapshot((int) $reservation->reservation_id, (float) $reservation->discount_amount, $lockOrders);
        $reservation->final_bill_amount = (float) ($snapshot['total_due'] ?? 0.0);

        $currency = trim((string) ($snapshot['currency'] ?? ''));
        if ($currency !== '') {
            $reservation->bill_currency = $currency;
        }
    }

    /**
     * @param  array<string,float|int|string|null>  $paymentSummary
     */
    public function syncDepositSnapshot(Reservation $reservation, array $paymentSummary, bool $terminalForfeit = false): void
    {
        if (PaymentSummary::hasOverRefund($paymentSummary)) {
            throw ValidationExceptionFactory::make([
                'payments' => ['Payment state is inconsistent: refunded amount exceeds captured amount.'],
            ]);
        }

        $depositRequired = round(max(0.0, (float) ($reservation->deposit_required_amount ?? 0.0)), 2);
        $depositCaptured = round(max(0.0, (float) ($paymentSummary['deposit_captured_amount'] ?? 0.0)), 2);
        $depositRefunded = round(max(0.0, (float) ($paymentSummary['deposit_refunded_amount'] ?? 0.0)), 2);
        $depositNet = round(max(0.0, (float) ($paymentSummary['deposit_net_amount'] ?? 0.0)), 2);

        $reservation->deposit_paid_amount = $depositNet;
        $reservation->deposit_status = $this->resolveDepositStatus(
            depositRequired: $depositRequired,
            depositCaptured: $depositCaptured,
            depositRefunded: $depositRefunded,
            depositNet: $depositNet,
            terminalForfeit: $terminalForfeit,
        );
    }

    public function touchFinancialMutation(Reservation $reservation, ?int $actorUserId = null): void
    {
        $currentVersion = (int) ($reservation->row_version ?? 0);
        $reservation->row_version = $currentVersion > 0 ? $currentVersion + 1 : 1;

        if ($actorUserId !== null) {
            $reservation->updated_by = $actorUserId;
        }

        if (method_exists($reservation, 'freshTimestamp')) {
            $reservation->updated_at = $reservation->freshTimestamp();
        }

        $reservation->save();
    }

    private function resolveDepositStatus(float $depositRequired, float $depositCaptured, float $depositRefunded, float $depositNet, bool $terminalForfeit): string
    {
        if ($depositRequired <= 0.0001 && $depositCaptured <= 0.0001 && $depositNet <= 0.0001) {
            return 'NotRequired';
        }

        if ($terminalForfeit && $depositCaptured > 0.0001) {
            if ($depositNet <= 0.0001) {
                return 'Refunded';
            }

            return $depositRefunded > 0.0001 ? 'PartiallyRefunded' : 'Forfeited';
        }

        if ($depositRefunded > 0.0001) {
            if ($depositNet <= 0.0001) {
                return 'Refunded';
            }

            return 'PartiallyRefunded';
        }

        if ($depositRequired <= 0.0001) {
            return $depositNet > 0.0001 ? 'Paid' : 'NotRequired';
        }

        if ($depositNet + 0.0001 >= $depositRequired) {
            return 'Paid';
        }

        return 'Pending';
    }
}
