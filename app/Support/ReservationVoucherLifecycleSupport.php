<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Reservation;
use App\Models\ReservationOrder;
use App\Models\UserVoucher;
use App\Services\ReservationFinancialSyncService;

final class ReservationVoucherLifecycleSupport
{
    /**
     * @param iterable<ReservationOrder> $orders
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
            totalDiscount: max(0.0, round((float) ($reservation->discount_amount ?? 0.0) - $voucherDiscount, 2)),
            lockOrders: true,
        );

        if ($persistReservation) {
            $reservation->updated_by = $actorUserId;
            $reservation->save();
        }

        return $voucherDiscount;
    }

    /**
     * @param iterable<ReservationOrder> $orders
     */
    private static function resolveAppliedVoucherDiscount(UserVoucher $userVoucher, iterable $orders): float
    {
        $usedAmount = round(max(0.0, (float) ($userVoucher->used_amount ?? 0.0)), 2);
        if ($usedAmount > 0.0001) {
            return $usedAmount;
        }

        if (! $userVoucher->voucher) {
            return 0.0;
        }

        return round((float) (VoucherRedemptionSupport::calculateDiscount($userVoucher->voucher, $orders)['discount_amount'] ?? 0.0), 2);
    }
}
