<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPurchaseReceiptLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'receipt_line_id' => (int) $this->receipt_line_id,
            'purchase_order_line_id' => (int) $this->purchase_order_line_id,
            'ingredient_id' => (int) $this->ingredient_id,
            'ingredient' => $this->whenLoaded('ingredient', fn (): array => [
                'ingredient_id' => (int) $this->ingredient->ingredient_id,
                'code' => $this->ingredient->code !== null ? (string) $this->ingredient->code : null,
                'name' => (string) $this->ingredient->name,
                'unit_code' => (string) $this->ingredient->unit_code,
            ]),
            'received_quantity' => number_format((float) $this->received_quantity, 3, '.', ''),
            'unit_code' => (string) $this->unit_code,
            'unit_cost' => $this->unit_cost !== null ? number_format((float) $this->unit_cost, 3, '.', '') : null,
            'stock_movement_id' => $this->stock_movement_id !== null ? (int) $this->stock_movement_id : null,
            'notes' => $this->notes !== null ? (string) $this->notes : null,
        ];
    }
}
