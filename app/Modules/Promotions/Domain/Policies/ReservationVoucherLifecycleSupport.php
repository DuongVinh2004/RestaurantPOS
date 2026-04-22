<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Policies;

use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Promotions\Domain\Models\UserVoucher;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;

final class ReservationVoucherLifecycleSupport
{
    /**
     * @param  iterable<ReservationOrder>  $orders
     */
    public static function releaseVoucherAndDiscountSnapshot(
        Reservation $reservation,
        ?UserVoucher $userVoucher,
        iterable $orders,
        ReservationFinancialSyncService $reservationFinancialSyncService,
        ?int $actorUserId = null,
        bool $detachReservation = true,
        bool $persistReservation = false,
    ): float {
        if (! $userVoucher) {
            if ($detachReservation) {
                $reservation->applied_user_voucher_id = null;
            }

            if ($persistReservation) {
                $reservation->updated_by = $actorUserId;
                $reservation->save();
            }

            return 0.0;
        }

        $voucherDiscount = self::resolveAppliedVoucherDiscount($userVoucher, $orders);

        $userVoucher->lock_token = null;
        $userVoucher->locked_until = null;
        $userVoucher->updated_by = $actorUserId;

        if ((bool) ($userVoucher->is_used ?? false) || $userVoucher->used_reservation_id !== null || $userVoucher->used_amount !== null) {
            $userVoucher->is_used = false;
            $userVoucher->used_date = null;
            $userVoucher->used_reservation_id = null;
            $userVoucher->used_amount = null;
        }

        $userVoucher->save();

        if ($detachReservation) {
            $reservation->applied_user_voucher_id = null;
        }

        $reservationFinancialSyncService->syncReservationDiscountSnapshot(
            reservation: $reservation,
            totalDiscount: Money::minorToFloat(max(
                0,
                Money::minorUnits($reservation->discount_amount ?? 0, true) - Money::minorUnits($voucherDiscount, true)
            )),
            lockOrders: true,
        );

        if ($persistReservation) {
            $reservation->updated_by = $actorUserId;
            $reservation->save();
        }

        return $voucherDiscount;
    }

    /**
     * @param  iterable<ReservationOrder>  $orders
     */
    private static function resolveAppliedVoucherDiscount(UserVoucher $userVoucher, iterable $orders): float
    {
        $usedAmountMinor = Money::minorUnits($userVoucher->used_amount ?? 0, true);
        if ($usedAmountMinor > 0) {
            return Money::minorToFloat($usedAmountMinor);
        }

        if (! $userVoucher->voucher) {
            return 0.0;
        }

        return Money::toFloat(VoucherRedemptionSupport::calculateDiscount($userVoucher->voucher, $orders)['discount_amount'] ?? 0, true);
    }
}
