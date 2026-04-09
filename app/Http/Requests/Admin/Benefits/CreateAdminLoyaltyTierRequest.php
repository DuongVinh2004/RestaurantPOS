<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Benefits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAdminLoyaltyTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tier_code' => ['required', 'string', 'max:50', Rule::unique('loyalty_tiers', 'tier_code')],
            'tier_name' => ['required', 'string', 'max:100'],
            'min_points' => ['required', 'integer', 'min:0'],
            'benefits_json' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['tier_code', 'tier_name'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $merge[$field] = trim((string) $this->input($field));
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
