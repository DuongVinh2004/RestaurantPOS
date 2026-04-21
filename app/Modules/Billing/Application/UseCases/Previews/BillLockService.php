<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\UseCases\Previews;

use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Carbon;
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
        $loyaltyDiscountMinor = Money::minorUnits($currentLoyaltyDiscountAmount($reservationId), true);
        $voucherDiscountMinor = Money::minorUnits($currentVoucherDiscountAmount($reservationId, true), true);
        $currentNonLoyaltyDiscountMinor = max(0, Money::minorUnits($reservation->discount_amount ?? 0, true) - $loyaltyDiscountMinor);
        $requestedNonLoyaltyDiscountMinor = $discountAmount !== null
            ? Money::minorUnits($discountAmount, true)
            : $currentNonLoyaltyDiscountMinor;
        $effectiveNonLoyaltyDiscountMinor = max($requestedNonLoyaltyDiscountMinor, $voucherDiscountMinor);
        $effectiveDiscount = Money::minorToFloat($effectiveNonLoyaltyDiscountMinor + $loyaltyDiscountMinor);

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
            $reservation->updated_at = Carbon::now('UTC');
            $reservation->saveQuietly();
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
