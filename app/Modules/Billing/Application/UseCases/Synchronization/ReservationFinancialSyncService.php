<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\UseCases\Synchronization;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use App\Support\ValidationExceptionFactory;
use Illuminate\Support\Facades\DB;

/**
 * Dong bo cac snapshot tai chinh cua reservation:
 * subtotal, discount, total_due, deposit state, va dau vet mutation.
 */
class ReservationFinancialSyncService
{
    /**
     * @return array{subtotal:float,discount:float,total_due:float,currency:string}
     */
    public function computeReservationBillSnapshot(int $reservationId, float $discountAmount, bool $lockOrders = true): array
    {
        // Snapshot bill duoc tinh tu order item chua bi cancel va chi chap nhan mot loai currency.
        $query = DB::table('reservation_orders as ro')
            ->join('reservation_order_items as roi', 'roi.order_id', '=', 'ro.order_id')
            ->where('ro.reservation_id', $reservationId)
            ->whereIn('ro.status', [ReservationOrderStatus::Active->value, ReservationOrderStatus::Completed->value])
            ->select(['roi.line_total', 'roi.currency', 'roi.status']);

        if ($lockOrders) {
            $query->lockForUpdate();
        }

        $items = $query->get();
        $subtotalMinor = 0;
        $currency = null;

        foreach ($items as $item) {
            // Cancelled items khong con dong gop vao subtotal, nhung van duoc giu trong lich su order.
            if ((string) ($item->status ?? '') === ReservationOrderItemStatus::Cancelled->value) {
                continue;
            }

            $subtotalMinor += Money::minorUnits($item->line_total ?? 0, true);
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

        // Snapshot luon doi ve minor units truoc de tranh sai so khi cong/tru bill.
        $discountMinor = Money::minorUnits($discountAmount, true);
        $totalDueMinor = max(0, $subtotalMinor - $discountMinor);

        return [
            'subtotal' => Money::minorToFloat($subtotalMinor),
            'discount' => Money::minorToFloat($discountMinor),
            'total_due' => Money::minorToFloat($totalDueMinor),
            'currency' => $currency ?: 'VND',
        ];
    }

    public function syncReservationDiscountSnapshot(Reservation $reservation, float $totalDiscount, bool $lockOrders = true): void
    {
        // Khi bill da duoc lock, doi discount phai keo theo final_bill_amount va bill_currency.
        $reservation->discount_amount = Money::format($totalDiscount, true);

        // Neu bill chua lock thi chi can cap nhat discount_amount; final snapshot se duoc tinh sau.
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
        // Deposit snapshot giup reservation biet dang no, da du, hay da hoan mot phan.
        if (PaymentSummary::hasOverRefund($paymentSummary)) {
            throw ValidationExceptionFactory::make([
                'payments' => ['Payment state is inconsistent: refunded amount exceeds captured amount.'],
            ]);
        }

        $depositRequiredMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);
        $depositCapturedMinor = Money::minorUnits($paymentSummary['deposit_captured_amount'] ?? 0, true);
        $depositRefundedMinor = Money::minorUnits($paymentSummary['deposit_refunded_amount'] ?? 0, true);
        $depositNetMinor = Money::minorUnits($paymentSummary['deposit_net_amount'] ?? 0, true);

        // deposit_paid_amount va deposit_status luon di cung nhau de UI khong phai tu suy lai.
        $reservation->deposit_paid_amount = Money::formatMinor($depositNetMinor);
        $reservation->deposit_status = $this->resolveDepositStatus(
            depositRequiredMinor: $depositRequiredMinor,
            depositCapturedMinor: $depositCapturedMinor,
            depositRefundedMinor: $depositRefundedMinor,
            depositNetMinor: $depositNetMinor,
            terminalForfeit: $terminalForfeit,
        );
    }

    public function touchFinancialMutation(Reservation $reservation, ?int $actorUserId = null): void
    {
        // Cham vao reservation de cac client/documents biet bill state vua thay doi.
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

    private function resolveDepositStatus(int $depositRequiredMinor, int $depositCapturedMinor, int $depositRefundedMinor, int $depositNetMinor, bool $terminalForfeit): string
    {
        // State machine nay quy doi snapshot payment thanh status de reservation doc/loc nhanh.
        if ($depositRequiredMinor <= 0 && $depositCapturedMinor <= 0 && $depositNetMinor <= 0) {
            return 'NotRequired';
        }

        if ($terminalForfeit && $depositCapturedMinor > 0) {
            if ($depositNetMinor <= 0) {
                return 'Refunded';
            }

            return $depositRefundedMinor > 0 ? 'PartiallyRefunded' : 'Forfeited';
        }

        if ($depositRefundedMinor > 0) {
            if ($depositNetMinor <= 0) {
                return 'Refunded';
            }

            return 'PartiallyRefunded';
        }

        if ($depositRequiredMinor <= 0) {
            return $depositNetMinor > 0 ? 'Paid' : 'NotRequired';
        }

        if ($depositNetMinor >= $depositRequiredMinor) {
            return 'Paid';
        }

        return 'Pending';
    }
}
