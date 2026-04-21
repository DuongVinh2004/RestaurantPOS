<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_voucher_id' => (int) data_get($this->resource, 'user_voucher_id', 0),
            'voucher_id' => (int) data_get($this->resource, 'voucher_id', 0),
            'voucher_code' => (string) data_get($this->resource, 'voucher_code', ''),
            'description' => (string) data_get($this->resource, 'description', ''),
            'discount_type' => (string) data_get($this->resource, 'discount_type', ''),
            'discount_value' => data_get($this->resource, 'discount_value'),
            'min_spend' => data_get($this->resource, 'min_spend'),
            'free_item' => data_get($this->resource, 'free_item'),
            'assigned_at' => data_get($this->resource, 'assigned_at'),
            'used_at' => data_get($this->resource, 'used_at'),
            'used_reservation_id' => data_get($this->resource, 'used_reservation_id'),
            'starts_at' => data_get($this->resource, 'starts_at'),
            'expires_at' => data_get($this->resource, 'expires_at'),
            'is_used' => (bool) data_get($this->resource, 'is_used', false),
            'current_status' => (string) data_get($this->resource, 'current_status', ''),
            'is_usable_now' => (bool) data_get($this->resource, 'is_usable_now', false),
            'is_locked' => (bool) data_get($this->resource, 'is_locked', false),
            'is_locked_by_other' => (bool) data_get($this->resource, 'is_locked_by_other', false),
            'locked_until' => data_get($this->resource, 'locked_until'),
            'row_version' => data_get($this->resource, 'row_version') !== null ? (int) data_get($this->resource, 'row_version') : null,
            'is_currently_applied' => (bool) data_get($this->resource, 'is_currently_applied', false),
            'preview_discount_amount' => data_get($this->resource, 'preview_discount_amount'),
            'preview_subtotal_amount' => data_get($this->resource, 'preview_subtotal_amount'),
            'preview_currency' => data_get($this->resource, 'preview_currency'),
            'can_apply' => (bool) data_get($this->resource, 'can_apply', false),
            'applicability_reason_codes' => array_values((array) data_get($this->resource, 'applicability_reason_codes', [])),
            'applicability_reasons' => array_values((array) data_get($this->resource, 'applicability_reasons', [])),
        ];
    }
}
