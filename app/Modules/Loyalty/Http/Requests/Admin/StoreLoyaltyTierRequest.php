<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoyaltyTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tier_code' => ['required', 'string', 'max:50', 'unique:loyalty_tiers,tier_code'],
            'tier_name' => ['required', 'string', 'max:100'],
            'min_points' => ['required', 'integer', 'min:0'],
            'benefits_json' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
