<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class AdminMenuItemPriceResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'price_id' => (int) $this->price_id,
            'item_id' => (int) $this->item_id,
            'price' => number_format((float) $this->price, 2, '.', ''),
            'currency' => (string) ($this->currency ?? 'VND'),
            'effective_from' => $this->iso($this->effective_from),
            'effective_to' => $this->iso($this->effective_to),
            'item' => $this->whenLoaded('item', function () {
                if (! $this->item) {
                    return null;
                }

                return [
                    'item_id' => (int) $this->item->item_id,
                    'code' => $this->item->code,
                    'name' => $this->item->name,
                ];
            }),
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
