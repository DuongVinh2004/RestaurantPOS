<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Http\Resources;

use App\Modules\CheckoutPayments\Application\Services\CustomerReservationBillService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CustomerReservationBillResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly string $accessScope,
        private readonly CustomerReservationBillService $billService,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $orders = $this->billService->customerVisibleOrders(collect($this['orders'] ?? []));

        return [
            'reservation_id' => (int) ($this['bill']['reservation_id'] ?? 0),
            'access_scope' => $this->accessScope,
            'bill' => [
                'scope' => (string) ($this['bill']['scope'] ?? 'reservation'),
                'reservation_status' => (string) ($this['bill']['reservation_status'] ?? ''),
                'subtotal' => $this->money($this['bill']['subtotal'] ?? null),
                'discount' => $this->money($this['bill']['discount'] ?? null),
                'total_due' => $this->money($this['bill']['total_due'] ?? null),
                'currency' => (string) ($this['bill']['currency'] ?? 'VND'),
                'billed_at' => $this->iso($this['bill']['billed_at'] ?? null),
                'is_locked' => (bool) ($this['bill']['is_locked'] ?? false),
                'locked_total_due' => $this->money($this['bill']['locked_total_due'] ?? null),
                'locked_currency' => $this['bill']['locked_currency'] ?? null,
            ],
            'settlement' => [
                'payment_status' => (string) ($this['settlement']['payment_status'] ?? ''),
                'deposit_applied' => $this->money($this['settlement']['deposit_applied'] ?? null),
                'deposit_net' => $this->money($this['settlement']['deposit_net'] ?? null),
                'final_paid' => $this->money($this['settlement']['final_paid'] ?? null),
                'paid_total' => $this->money($this['settlement']['paid_total'] ?? null),
                'remaining_due' => $this->money($this['settlement']['remaining_due'] ?? null),
                'captured_total' => $this->money($this['settlement']['captured_total'] ?? null),
                'refunded_total' => $this->money($this['settlement']['refunded_total'] ?? null),
                'net_paid_total' => $this->money($this['settlement']['net_paid_total'] ?? null),
                'currency' => $this['settlement']['currency'] ?? null,
                'currencies' => $this['settlement']['currencies'] ?? [],
                'has_mixed_currencies' => (bool) ($this['settlement']['has_mixed_currencies'] ?? false),
            ],
            'orders' => $orders->map(function (array $order) {
                return [
                    'order_id' => (int) $order['order_id'],
                    'order_type' => (string) $order['order_type'],
                    'status' => (string) $order['status'],
                    'created_at' => $this->iso($order['created_at'] ?? null),
                    'items' => collect($order['items'] ?? [])->map(function ($item) {
                        return [
                            'order_item_id' => (int) $item->order_item_id,
                            'item_id' => (int) $item->item_id,
                            'item_name_snapshot' => (string) ($item->item_name_snapshot ?? ''),
                            'quantity' => (int) $item->quantity,
                            'status' => $item->status?->value ?? (string) $item->status,
                            'unit_price' => $this->money($item->unit_price ?? null),
                            'currency' => (string) ($item->currency ?? 'VND'),
                            'line_total' => $this->money($item->line_total ?? null),
                            'item' => $item->relationLoaded('item') && $item->item
                                ? [
                                    'item_id' => (int) $item->item->item_id,
                                    'code' => (string) $item->item->code,
                                    'name' => (string) $item->item->name,
                                ]
                                : null,
                        ];
                    })->values(),
                ];
            })->values(),
            'workflow' => [
                'settlement_scope' => (string) ($this['workflow']['settlement_scope'] ?? 'reservation'),
                'bill_source' => (string) ($this['workflow']['bill_source'] ?? 'reservation_financial_snapshot'),
                'order_role' => (string) ($this['workflow']['order_role'] ?? 'display_detail_only'),
                'payment_session_support' => $this['workflow']['payment_session_support'] ?? [
                    'create' => false,
                    'show' => false,
                    'refresh' => false,
                    'confirm' => false,
                ],
            ],
        ];
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }

    private function money(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Money::format($value, true);
    }
}
