<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Http\Requests\Admin\Benefits;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAdminBenefitRuntimeSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'setting_key' => ['required', 'string', 'max:100'],
            'value' => ['required'],
            'expected_updated_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('setting_key') && is_string($this->input('setting_key'))) {
            $this->merge([
                'setting_key' => trim((string) $this->input('setting_key')),
            ]);
        }
    }
}
