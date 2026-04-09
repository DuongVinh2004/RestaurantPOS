<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CustomerMenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'item_id' => (int) $this->item_id,
            'category_id' => $this->category_id !== null ? (int) $this->category_id : null,
            'category_name' => $this->category_name !== null ? (string) $this->category_name : null,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'img_url' => $this->img_url,
            'is_available' => (bool) $this->is_available,
            'price' => [
                'price_id' => $this->current_price_id !== null ? (int) $this->current_price_id : null,
                'amount' => $this->money($this->current_price_amount),
                'currency' => $this->current_price_currency !== null ? (string) $this->current_price_currency : null,
                'effective_from' => $this->iso($this->current_price_effective_from),
                'effective_to' => $this->iso($this->current_price_effective_to),
            ],
            'preorder' => [
                'enabled' => (bool) ($this->is_preorder_enabled ?? false),
                'cutoff_minutes' => (int) ($this->preorder_cutoff_minutes ?? 0),
                'quota_per_day' => $this->preorder_quota_per_day !== null ? (int) $this->preorder_quota_per_day : null,
                'requires_preview_validation' => true,
            ],
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }

    private function money(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
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
