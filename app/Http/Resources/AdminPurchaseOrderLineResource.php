<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPurchaseOrderLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $orderedQuantity = (float) $this->ordered_quantity;
        $receivedQuantity = (float) $this->received_quantity;

        return [
            'po_line_id' => (int) $this->po_line_id,
            'ingredient_id' => (int) $this->ingredient_id,
            'ingredient' => $this->whenLoaded('ingredient', fn (): array => [
                'ingredient_id' => (int) $this->ingredient->ingredient_id,
                'code' => $this->ingredient->code !== null ? (string) $this->ingredient->code : null,
                'name' => (string) $this->ingredient->name,
                'unit_code' => (string) $this->ingredient->unit_code,
            ]),
            'ordered_quantity' => number_format($orderedQuantity, 3, '.', ''),
            'received_quantity' => number_format($receivedQuantity, 3, '.', ''),
            'remaining_quantity' => number_format(max(0, $orderedQuantity - $receivedQuantity), 3, '.', ''),
            'unit_code' => (string) $this->unit_code,
            'unit_cost' => $this->unit_cost !== null ? number_format((float) $this->unit_cost, 3, '.', '') : null,
            'notes' => $this->notes !== null ? (string) $this->notes : null,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
