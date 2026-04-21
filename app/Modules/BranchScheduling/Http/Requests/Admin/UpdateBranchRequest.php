<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchId = (int) $this->route('id');

        return [
            'row_version' => ['required', 'integer', 'min:1'],
            'branch_code' => ['sometimes', 'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('branches', 'branch_code')->ignore($branchId, 'branch_id')],
            'branch_name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:400'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'business_hours' => ['sometimes', 'nullable', 'array'],
            'closure_windows' => ['sometimes', 'nullable', 'array'],
            'booking_policy' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('is_active')) {
            $merge['is_active'] = $this->boolean('is_active');
        }
        if ($this->has('is_default')) {
            $merge['is_default'] = $this->boolean('is_default');
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
