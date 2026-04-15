<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Http\Requests\Admin\Benefits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAdminVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('vouchers', 'code')],
            'description' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['required', 'string', 'in:Fixed,Percent,FreeItem'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'free_item_id' => ['nullable', 'integer', 'min:1', 'exists:menu_items,item_id'],
            'free_item_qty' => ['nullable', 'integer', 'min:1'],
            'max_usage' => ['nullable', 'integer', 'min:1'],
            'max_usage_per_user' => ['nullable', 'integer', 'min:1'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['description', 'discount_value', 'free_item_id', 'free_item_qty', 'max_usage', 'max_usage_per_user', 'min_spend', 'start_date', 'expiry_date'] as $field) {
            if ($this->has($field) && is_string($this->input($field)) && trim((string) $this->input($field)) === '') {
                $merge[$field] = null;
            }
        }

        if ($this->has('code') && is_string($this->input('code'))) {
            $merge['code'] = trim((string) $this->input('code'));
        }

        if ($this->has('description') && is_string($this->input('description'))) {
            $merge['description'] = trim((string) $this->input('description'));
        }

        if ($this->has('is_active')) {
            $merge['is_active'] = $this->boolean('is_active');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
