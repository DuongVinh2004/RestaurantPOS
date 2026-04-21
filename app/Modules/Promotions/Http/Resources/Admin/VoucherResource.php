<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Resources\Admin;

use App\SharedKernel\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class VoucherResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'voucher_id' => (int) $this->voucher_id,
            'code' => (string) $this->code,
            'description' => $this->description !== null ? (string) $this->description : null,
            'discount_type' => $this->discount_type instanceof \BackedEnum
                ? $this->discount_type->value
                : (string) $this->discount_type,
            'discount_value' => $this->discount_value !== null ? number_format((float) $this->discount_value, 2, '.', '') : null,
            'free_item' => [
                'item_id' => $this->free_item_id !== null ? (int) $this->free_item_id : null,
                'name' => $this->relationLoaded('freeItem') && $this->freeItem !== null
                    ? (string) $this->freeItem->name
                    : null,
                'quantity' => $this->free_item_qty !== null ? (int) $this->free_item_qty : null,
            ],
            'limits' => [
                'max_usage' => $this->max_usage !== null ? (int) $this->max_usage : null,
                'max_usage_per_user' => $this->max_usage_per_user !== null ? (int) $this->max_usage_per_user : null,
                'min_spend' => $this->min_spend !== null ? Money::format($this->min_spend, true) : null,
            ],
            'availability' => [
                'start_date' => $this->iso($this->start_date),
                'expiry_date' => $this->iso($this->expiry_date),
                'is_active' => (bool) $this->is_active,
            ],
            'usage_summary' => [
                'assigned_count' => (int) ($this->assigned_count ?? 0),
                'used_count' => (int) ($this->used_count ?? 0),
                'unused_count' => max(0, (int) ($this->assigned_count ?? 0) - (int) ($this->used_count ?? 0)),
                'has_assignments' => (int) ($this->assigned_count ?? 0) > 0,
            ],
            'created_by' => $this->created_by !== null ? (int) $this->created_by : null,
            'updated_by' => $this->updated_by !== null ? (int) $this->updated_by : null,
            'row_version' => (int) ($this->row_version ?? 1),
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
