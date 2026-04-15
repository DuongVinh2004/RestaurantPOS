<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Domain\Policies;

use App\Enums\ReservationOrderItemStatus;
use App\Modules\BenefitsLoyalty\Domain\Models\Voucher;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use BackedEnum;
use Illuminate\Validation\ValidationException;

final class VoucherRedemptionSupport
{
    /**
     * @param  iterable<ReservationOrder>  $orders
     * @return array{subtotal:float,currency:string,item_quantity_map:array<int,int>,item_unit_price_map:array<int,float>}
     */
    public static function summarizeOrders(iterable $orders): array
    {
        $subtotal = 0.0;
        $currency = null;
        $itemQuantityMap = [];
        $itemUnitPriceMap = [];

        foreach ($orders as $order) {
            $items = $order->relationLoaded('items') ? $order->items : collect();
            foreach ($items as $item) {
                $status = $item->status instanceof BackedEnum
                    ? (string) $item->status->value
                    : (string) $item->status;

                if ($status === ReservationOrderItemStatus::Cancelled->value) {
                    continue;
                }

                $lineTotal = round(max(0.0, (float) ($item->line_total ?? 0.0)), 2);
                $subtotal += $lineTotal;

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

                $unitPrice = round(max(0.0, (float) ($item->unit_price ?? 0.0)), 2);
                if ($unitPrice <= 0.0001 && $lineTotal > 0.0001) {
                    $unitPrice = round($lineTotal / $quantity, 2);
                }

                $itemQuantityMap[$itemId] = ($itemQuantityMap[$itemId] ?? 0) + $quantity;
                $itemUnitPriceMap[$itemId] = $unitPrice;
            }
        }

        return [
            'subtotal' => round($subtotal, 2),
            'currency' => $currency ?: 'VND',
            'item_quantity_map' => $itemQuantityMap,
            'item_unit_price_map' => $itemUnitPriceMap,
        ];
    }

    /**
     * @param  iterable<ReservationOrder>  $orders
     * @return array{discount_amount:float,subtotal:float,currency:string,reason:string}
     */
    public static function calculateDiscount(Voucher $voucher, iterable $orders): array
    {
        $summary = self::summarizeOrders($orders);
        $subtotal = (float) ($summary['subtotal'] ?? 0.0);
        $currency = (string) ($summary['currency'] ?? 'VND');
        $discountType = $voucher->discount_type instanceof BackedEnum
            ? (string) $voucher->discount_type->value
            : (string) $voucher->discount_type;

        $discount = 0.0;
        $reason = $discountType;

        if ($discountType === 'Fixed') {
            $discount = round(max(0.0, (float) ($voucher->discount_value ?? 0.0)), 2);
        } elseif ($discountType === 'Percent') {
            $percent = max(0.0, min(100.0, (float) ($voucher->discount_value ?? 0.0)));
            $discount = round($subtotal * $percent / 100, 2);
            $reason = sprintf('Percent %.2f%%', $percent);
        } elseif ($discountType === 'FreeItem') {
            $freeItemId = (int) ($voucher->free_item_id ?? 0);
            $freeQty = max(1, (int) ($voucher->free_item_qty ?? 1));
            $availableQty = (int) (($summary['item_quantity_map'][$freeItemId] ?? 0));
            $unitPrice = (float) (($summary['item_unit_price_map'][$freeItemId] ?? 0.0));
            $discount = round(max(0.0, min($availableQty, $freeQty) * $unitPrice), 2);
            $reason = sprintf('FreeItem #%d x%d', $freeItemId, min($availableQty, $freeQty));
        }

        $discount = round(min(max(0.0, $discount), $subtotal), 2);

        return [
            'discount_amount' => $discount,
            'subtotal' => round($subtotal, 2),
            'currency' => $currency ?: 'VND',
            'reason' => $reason,
        ];
    }
}
