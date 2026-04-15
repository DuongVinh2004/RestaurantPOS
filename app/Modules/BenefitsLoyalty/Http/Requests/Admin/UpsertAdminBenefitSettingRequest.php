<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Http\Requests\Admin;

use App\Modules\BenefitsLoyalty\Application\Services\AdminBenefitSettingService;
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
