<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\Admin\Benefits\AdminBenefitSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertAdminBenefitSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'setting_key' => ['required', 'string', Rule::in(AdminBenefitSettingService::SETTING_KEYS)],
            'value' => ['required'],
            'expected_updated_at' => ['nullable', 'date'],
        ];
    }
}
