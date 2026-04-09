<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Benefits;

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
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'discount_type' => ['sometimes', 'string', 'in:Fixed,Percent,FreeItem'],
            'discount_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'free_item_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:menu_items,item_id'],
            'free_item_qty' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_usage' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_usage_per_user' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'min_spend' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'expiry_date' => ['sometimes', 'nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['code', 'description'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $merge[$field] = trim((string) $this->input($field));
            }
        }

        foreach (['description', 'discount_value', 'free_item_id', 'free_item_qty', 'max_usage', 'max_usage_per_user', 'min_spend', 'start_date', 'expiry_date'] as $field) {
            if ($this->has($field) && is_string($this->input($field)) && trim((string) $this->input($field)) === '') {
                $merge[$field] = null;
            }
        }

        if ($this->has('is_active')) {
            $merge['is_active'] = $this->boolean('is_active');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
