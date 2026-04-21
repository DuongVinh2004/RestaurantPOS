<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class PurchaseReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lines = $this->whenLoaded('lines');
        $lineCollection = $lines instanceof \Illuminate\Support\Collection ? $lines : collect();

        return [
            'receipt_id' => (int) $this->receipt_id,
            'branch_id' => isset($this->branch_id) ? (int) $this->branch_id : null,
            'purchase_order_id' => (int) $this->purchase_order_id,
            'receipt_code' => (string) $this->receipt_code,
            'receipt_status' => $this->receipt_status?->value ?? (string) $this->receipt_status,
            'received_at' => $this->iso($this->received_at),
            'supplier_document_no' => $this->supplier_document_no !== null ? (string) $this->supplier_document_no : null,
            'notes' => $this->notes !== null ? (string) $this->notes : null,
            'summary' => [
                'line_count' => $lineCollection->count(),
                'received_total_quantity' => number_format((float) $lineCollection->sum(static fn ($line) => (float) $line->received_quantity), 3, '.', ''),
            ],
            'lines' => $this->whenLoaded('lines', fn () => PurchaseReceiptLineResource::collection($this->lines)->toArray($request)),
            'created_by' => $this->created_by !== null ? (int) $this->created_by : null,
            'created_at' => $this->iso($this->created_at),
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
