<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAdminBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('branches', 'branch_code')],
            'branch_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:400'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'max:10'],
            'business_hours' => ['sometimes', 'nullable', 'array'],
            'closure_windows' => ['sometimes', 'nullable', 'array'],
            'booking_policy' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'is_default' => $this->boolean('is_default'),
        ]);
    }
}
