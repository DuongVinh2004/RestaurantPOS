<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LegacyUpdateLoyaltyTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'row_version' => ['required', 'integer', 'min:1'],
            'tier_name' => ['sometimes', 'string', 'max:100'],
            'min_points' => ['sometimes', 'integer', 'min:0'],
            'benefits_json' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('tier_name') && is_string($this->input('tier_name'))) {
            $merge['tier_name'] = trim((string) $this->input('tier_name'));
        }

        if ($this->has('is_active')) {
            $merge['is_active'] = $this->boolean('is_active');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
