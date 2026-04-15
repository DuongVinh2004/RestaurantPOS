<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Http\Requests\Admin;

use App\Enums\VoucherDiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'unique:vouchers,code'],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'string', Rule::in(array_map(static fn (VoucherDiscountType $type) => $type->value, VoucherDiscountType::cases()))],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'free_item_id' => ['nullable', 'integer', 'min:1'],
            'free_item_qty' => ['nullable', 'integer', 'min:1'],
            'max_usage' => ['nullable', 'integer', 'min:1'],
            'max_usage_per_user' => ['nullable', 'integer', 'min:1'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        if (is_string($code)) {
            $this->merge(['code' => trim($code)]);
        }
    }
}
