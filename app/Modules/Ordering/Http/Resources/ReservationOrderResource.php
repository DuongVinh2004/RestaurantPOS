<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Resources;

use App\Enums\PaymentStatus;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\SharedKernel\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReservationOrder */
class ReservationOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasComputedTotals = $this->hasAnyComputedTotals();
        $paymentStatus = $this->resolvePaymentStatus();

        $items = $this->whenLoaded('items', function () {
            return $this->items->map(function ($it) {
                return [
                    'order_item_id' => (int) $it->order_item_id,
                    'item_id' => (int) $it->item_id,
                    'quantity' => (int) $it->quantity,
                    'status' => $it->status?->value ?? (string) $it->status,
                    'parent_order_item_id' => $it->parent_order_item_id !== null ? (int) $it->parent_order_item_id : null,
                    'row_version' => isset($it->row_version) ? (int) $it->row_version : null,
                    'item_name_snapshot' => $it->item_name_snapshot,
                    'unit_price' => (string) ($it->unit_price ?? '0.00'),
                    'currency' => (string) ($it->currency ?? 'VND'),
                    'line_total' => (string) ($it->line_total ?? '0.00'),
                    'notes' => $it->notes,
                    'item' => $it->relationLoaded('item') && $it->item ? [
                        'name' => $it->item->name,
                        'code' => $it->item->code,
                    ] : null,
                ];
            })->values();
        });

        return [
            'order_id' => (int) $this->order_id,
            'reservation_id' => (int) $this->reservation_id,
            'order_type' => $this->order_type?->value ?? (string) $this->order_type,
            'status' => $this->status?->value ?? (string) $this->status,
            'row_version' => isset($this->row_version) ? (int) $this->row_version : null,
            'created_at' => optional($this->created_at)->toISOString(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by ?? null,
            'notes' => $this->notes ?? null,
            'workflow' => [
                'settlement_scope' => 'reservation',
                'canonical_bill_snapshot_action' => 'bill_snapshot',
                'legacy_bill_snapshot_action' => 'close',
            ],
            'payment_status' => $paymentStatus,
            'items' => $items,
            'totals' => [
                'subtotal' => $hasComputedTotals ? $this->formatComputedMoney('subtotal_amount') : null,
                'discount' => $hasComputedTotals ? $this->formatComputedMoney('discount_amount') : null,
                'total_due' => $hasComputedTotals ? $this->formatComputedMoney('total_due_amount') : null,
                'paid' => $hasComputedTotals ? $this->formatComputedMoney('paid_amount') : null,
                'deposit_applied' => $hasComputedTotals ? $this->formatComputedMoney('deposit_applied_amount') : null,
                'deposit_net' => $hasComputedTotals ? $this->formatComputedMoney('deposit_net_amount') : null,
                'final_paid' => $hasComputedTotals ? $this->formatComputedMoney('final_paid_amount') : null,
                'outstanding' => $hasComputedTotals ? $this->formatComputedMoney('outstanding_amount') : null,
                'currency' => $hasComputedTotals ? (string) ($this->getAttribute('currency') ?? 'VND') : null,
            ],
        ];
    }

    private function resolvePaymentStatus(): ?string
    {
        if ($this->getAttribute('payment_status') !== null) {
            return (string) $this->getAttribute('payment_status');
        }

        if (! $this->hasSettlementTotals()) {
            return null;
        }

        $paidMinor = Money::minorUnits($this->getAttribute('paid_amount'), true);
        $totalDueMinor = Money::minorUnits($this->getAttribute('total_due_amount'), true);

        if ($paidMinor >= $totalDueMinor) {
            return PaymentStatus::Success->value;
        }

        if ($paidMinor > 0) {
            return PaymentStatus::Partial->value;
        }

        return PaymentStatus::Failed->value;
    }

    private function hasSettlementTotals(): bool
    {
        return $this->getAttribute('total_due_amount') !== null
            && $this->getAttribute('paid_amount') !== null;
    }

    private function formatComputedMoney(string $attribute): ?string
    {
        $value = $this->getAttribute($attribute);
        if ($value === null) {
            return null;
        }

        return Money::format($value, true);
    }

    private function hasAnyComputedTotals(): bool
    {
        foreach ([
            'subtotal_amount',
            'discount_amount',
            'total_due_amount',
            'paid_amount',
            'deposit_applied_amount',
            'deposit_net_amount',
            'final_paid_amount',
            'outstanding_amount',
            'currency',
        ] as $attribute) {
            if ($this->getAttribute($attribute) !== null) {
                return true;
            }
        }

        return false;
    }
}
