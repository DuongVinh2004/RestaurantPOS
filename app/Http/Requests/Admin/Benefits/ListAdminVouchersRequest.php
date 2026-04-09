<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Benefits;

use Illuminate\Foundation\Http\FormRequest;

class ListAdminVouchersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_type' => ['nullable', 'string', 'in:Fixed,Percent,FreeItem'],
            'is_active' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('is_active')) {
            $merge['is_active'] = $this->boolean('is_active');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
