<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $orderedTotal = $this->ordered_total_quantity ?? $this->lines?->sum(static fn ($line) => (float) $line->ordered_quantity) ?? 0;
        $receivedTotal = $this->received_total_quantity ?? $this->lines?->sum(static fn ($line) => (float) $line->received_quantity) ?? 0;

        return [
            'purchase_order_id' => (int) $this->purchase_order_id,
            'branch_id' => isset($this->branch_id) ? (int) $this->branch_id : null,
            'branch' => $this->whenLoaded('branch', fn (): array => [
                'branch_id' => (int) $this->branch->branch_id,
                'branch_code' => (string) $this->branch->branch_code,
                'branch_name' => (string) $this->branch->branch_name,
                'is_default' => (bool) $this->branch->is_default,
            ]),
            'order_code' => (string) $this->order_code,
            'purchase_order_status' => $this->purchase_order_status?->value ?? (string) $this->purchase_order_status,
            'supplier_id' => (int) $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn (): array => [
                'supplier_id' => (int) $this->supplier->supplier_id,
                'code' => $this->supplier->code !== null ? (string) $this->supplier->code : null,
                'name' => (string) $this->supplier->name,
                'is_active' => (bool) $this->supplier->is_active,
            ]),
            'ordered_at' => $this->iso($this->ordered_at),
            'expected_at' => $this->iso($this->expected_at),
            'received_at' => $this->iso($this->received_at),
            'supplier_reference' => $this->supplier_reference !== null ? (string) $this->supplier_reference : null,
            'notes' => $this->notes !== null ? (string) $this->notes : null,
            'summary' => [
                'line_count' => (int) ($this->line_count ?? ($this->lines?->count() ?? 0)),
                'receipt_count' => (int) ($this->receipt_count ?? ($this->receipts?->count() ?? 0)),
                'ordered_total_quantity' => number_format((float) $orderedTotal, 3, '.', ''),
                'received_total_quantity' => number_format((float) $receivedTotal, 3, '.', ''),
                'remaining_total_quantity' => number_format(max(0, (float) $orderedTotal - (float) $receivedTotal), 3, '.', ''),
            ],
            'lines' => $this->whenLoaded('lines', fn () => PurchaseOrderLineResource::collection($this->lines)->toArray($request)),
            'receipts' => $this->whenLoaded('receipts', fn () => PurchaseReceiptResource::collection($this->receipts)->toArray($request)),
            'created_by' => $this->created_by !== null ? (int) $this->created_by : null,
            'updated_by' => $this->updated_by !== null ? (int) $this->updated_by : null,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
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
}
