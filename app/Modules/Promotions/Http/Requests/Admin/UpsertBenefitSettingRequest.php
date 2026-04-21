<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Requests\Admin;

use App\Modules\Loyalty\Application\UseCases\Settings\LoyaltyRuntimeSettingService;
use App\Modules\Promotions\Application\UseCases\Benefits\BenefitRuntimeSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertBenefitSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $settingKeys = array_values(array_unique(array_merge(
            LoyaltyRuntimeSettingService::SETTING_KEYS,
            BenefitRuntimeSettingService::SETTING_KEYS,
        )));

        return [
            'setting_key' => ['required', 'string', Rule::in($settingKeys)],
            'value' => ['required'],
            'expected_updated_at' => ['nullable', 'date'],
        ];
    }
}
