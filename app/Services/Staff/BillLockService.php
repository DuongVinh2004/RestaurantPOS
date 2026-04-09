<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationOrder;
use App\Services\ReservationFinancialSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillLockService
{
    public function __construct(
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
    ) {}

    public function lockBill(
        int $orderId,
        ?float $discountAmount,
        string $notes,
        ?int $staffUserId,
        ?int $expectedRowVersion,
        callable $assertExpectedOrderRowVersion,
        callable $currentLoyaltyDiscountAmount,
        callable $currentVoucherDiscountAmount,
        callable $attachTotals,
        bool $bumpReservationVersion = true,
    ): ReservationOrder {
        /** @var ReservationOrder $order */
        $order = ReservationOrder::query()->where('order_id', $orderId)->lockForUpdate()->firstOrFail();
        $assertExpectedOrderRowVersion($order, $expectedRowVersion);
        /** @var Reservation $reservation */
        $reservation = Reservation::query()->where('reservation_id', $order->reservation_id)->lockForUpdate()->firstOrFail();

        if (($order->status?->value ?? (string) $order->status) !== ReservationOrderStatus::Active->value) {
            throw ValidationException::withMessages(['order_id' => 'Only active orders can be closed.']);
        }
        if (($reservation->status?->value ?? (string) $reservation->status) !== ReservationStatus::Reserved->value) {
            throw ValidationException::withMessages(['reservation' => 'Reservation must be in service (Reserved) to close bill.']);
        }
        if (($order->order_type?->value ?? (string) $order->order_type) !== ReservationOrderType::OnSpot->value) {
            throw ValidationException::withMessages(['order_id' => 'Only on-spot orders can be used as checkout anchor.']);
        }

        $reservationId = (int) $reservation->reservation_id;
        $loyaltyDiscount = max(0.0, round((float) $currentLoyaltyDiscountAmount($reservationId), 2));
        $voucherDiscount = max(0.0, round((float) $currentVoucherDiscountAmount($reservationId, true), 2));
        $currentNonLoyaltyDiscount = max(0.0, round((float) ($reservation->discount_amount ?? 0.0) - $loyaltyDiscount, 2));
        $requestedNonLoyaltyDiscount = $discountAmount !== null ? max(0.0, round($discountAmount, 2)) : $currentNonLoyaltyDiscount;
        $effectiveNonLoyaltyDiscount = max($requestedNonLoyaltyDiscount, $voucherDiscount);
        $effectiveDiscount = round($effectiveNonLoyaltyDiscount + $loyaltyDiscount, 2);

        $snapshot = $this->reservationFinancialSyncService->computeReservationBillSnapshot($reservationId, $effectiveDiscount, true);
        $subtotal = (float) ($snapshot['subtotal'] ?? 0.0);
        $discount = (float) ($snapshot['discount'] ?? 0.0);
        $totalDue = (float) ($snapshot['total_due'] ?? 0.0);
        $currencyCode = (string) ($snapshot['currency'] ?? 'VND');

        $reservation->discount_amount = $discount;
        $reservation->final_bill_amount = $totalDue;
        $reservation->bill_currency = $currencyCode;
        $reservation->billed_at = Carbon::now('UTC');
        $reservation->updated_by = $staffUserId;

        if ($bumpReservationVersion) {
            $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $staffUserId);
        } else {
            DB::table('reservations')
                ->where('reservation_id', (int) $reservation->reservation_id)
                ->update([
                    'discount_amount' => $reservation->discount_amount,
                    'final_bill_amount' => $reservation->final_bill_amount,
                    'bill_currency' => $reservation->bill_currency,
                    'billed_at' => $reservation->billed_at,
                    'updated_by' => $reservation->updated_by,
                    'updated_at' => Carbon::now('UTC'),
                ]);
            $reservation->syncOriginal();
        }

        $order->notes = trim($notes) !== '' ? trim($notes) : $order->notes;
        $order->updated_by = $staffUserId;
        $order->save();

        /** @var ReservationOrder $result */
        $result = $attachTotals($order, $subtotal, $discount, $totalDue, $currencyCode);

        return $result;
    }
}
