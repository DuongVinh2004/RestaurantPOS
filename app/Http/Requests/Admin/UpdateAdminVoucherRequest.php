<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\VoucherDiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $voucherId = (int) $this->route('id');

        return [
            'row_version' => ['required', 'integer', 'min:1'],
            'code' => ['sometimes', 'string', 'max:100', Rule::unique('vouchers', 'code')->ignore($voucherId, 'voucher_id')],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'discount_type' => ['sometimes', 'string', Rule::in(array_map(static fn (VoucherDiscountType $type) => $type->value, VoucherDiscountType::cases()))],
            'discount_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'free_item_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'free_item_qty' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_usage' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_usage_per_user' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'min_spend' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'expiry_date' => ['sometimes', 'nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
