<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoyaltyTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tierId = (int) $this->route('id');

        return [
            'row_version' => ['required', 'integer', 'min:1'],
            'tier_code' => ['sometimes', 'string', 'max:50', Rule::unique('loyalty_tiers', 'tier_code')->ignore($tierId, 'tier_id')],
            'tier_name' => ['sometimes', 'string', 'max:100'],
            'min_points' => ['sometimes', 'integer', 'min:0'],
            'benefits_json' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
