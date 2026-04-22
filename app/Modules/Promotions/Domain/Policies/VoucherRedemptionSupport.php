<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Policies;

use App\Enums\ReservationOrderItemStatus;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Promotions\Domain\Models\Voucher;
use App\SharedKernel\Money\Money;
use BackedEnum;
use Illuminate\Validation\ValidationException;

final class VoucherRedemptionSupport
{
    /**
     * @param  iterable<ReservationOrder>  $orders
     * @return array{subtotal:float,currency:string,item_quantity_map:array<int,int>,item_unit_price_minor_map:array<int,int>}
     */
    public static function summarizeOrders(iterable $orders): array
    {
        $subtotalMinor = 0;
        $currency = null;
        $itemQuantityMap = [];
        $itemUnitPriceMinorMap = [];

        foreach ($orders as $order) {
            $items = $order->relationLoaded('items') ? $order->items : collect();
            foreach ($items as $item) {
                $status = $item->status instanceof BackedEnum
                    ? (string) $item->status->value
                    : (string) $item->status;

                if ($status === ReservationOrderItemStatus::Cancelled->value) {
                    continue;
                }

                $lineTotalMinor = Money::minorUnits($item->line_total ?? 0, true);
                $subtotalMinor += $lineTotalMinor;

                $itemCurrency = trim((string) ($item->currency ?? ''));
                if ($itemCurrency !== '') {
                    if ($currency === null) {
                        $currency = $itemCurrency;
                    } elseif ($currency !== $itemCurrency) {
                        throw ValidationException::withMessages([
                            'currency' => sprintf('Mixed currency is not supported within a reservation order set (%s vs %s).', $currency, $itemCurrency),
                        ]);
                    }
                }

                $itemId = (int) ($item->item_id ?? 0);
                $quantity = max(0, (int) ($item->quantity ?? 0));
                if ($itemId <= 0 || $quantity <= 0) {
                    continue;
                }

                $unitPriceMinor = Money::minorUnits($item->unit_price ?? 0, true);
                if ($unitPriceMinor <= 0 && $lineTotalMinor > 0) {
                    $unitPriceMinor = intdiv($lineTotalMinor + intdiv($quantity, 2), $quantity);
                }

                $itemQuantityMap[$itemId] = ($itemQuantityMap[$itemId] ?? 0) + $quantity;
                $itemUnitPriceMinorMap[$itemId] = $unitPriceMinor;
            }
        }

        return [
            'subtotal' => Money::minorToFloat($subtotalMinor),
            'currency' => $currency ?: 'VND',
            'item_quantity_map' => $itemQuantityMap,
            'item_unit_price_minor_map' => $itemUnitPriceMinorMap,
        ];
    }

    /**
     * @param  iterable<ReservationOrder>  $orders
     * @return array{discount_amount:float,subtotal:float,currency:string,reason:string}
     */
    public static function calculateDiscount(Voucher $voucher, iterable $orders): array
    {
        $summary = self::summarizeOrders($orders);
        $subtotalMinor = Money::minorUnits($summary['subtotal'] ?? 0, true);
        $currency = (string) ($summary['currency'] ?? 'VND');
        $discountType = $voucher->discount_type instanceof BackedEnum
            ? (string) $voucher->discount_type->value
            : (string) $voucher->discount_type;

        $discountMinor = 0;
        $reason = $discountType;

        if ($discountType === 'Fixed') {
            $discountMinor = Money::minorUnits($voucher->discount_value ?? 0, true);
        } elseif ($discountType === 'Percent') {
            $percent = max(0.0, min(100.0, (float) ($voucher->discount_value ?? 0.0)));
            $basisPoints = max(0, min(10000, (int) round($percent * 100)));
            $discountMinor = intdiv(($subtotalMinor * $basisPoints) + 5000, 10000);
            $reason = sprintf('Percent %.2f%%', $percent);
        } elseif ($discountType === 'FreeItem') {
            $freeItemId = (int) ($voucher->free_item_id ?? 0);
            $freeQty = max(1, (int) ($voucher->free_item_qty ?? 1));
            $availableQty = (int) (($summary['item_quantity_map'][$freeItemId] ?? 0));
            $unitPriceMinor = (int) (($summary['item_unit_price_minor_map'][$freeItemId] ?? 0));
            $discountMinor = max(0, min($availableQty, $freeQty) * $unitPriceMinor);
            $reason = sprintf('FreeItem #%d x%d', $freeItemId, min($availableQty, $freeQty));
        }

        $discountMinor = min(max(0, $discountMinor), $subtotalMinor);

        return [
            'discount_amount' => Money::minorToFloat($discountMinor),
            'subtotal' => Money::minorToFloat($subtotalMinor),
            'currency' => $currency ?: 'VND',
            'reason' => $reason,
        ];
    }
}
