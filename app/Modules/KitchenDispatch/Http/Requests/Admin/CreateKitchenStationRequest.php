<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Http\Requests\Admin;

use App\Enums\KitchenStationOutputMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateKitchenStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'branch_id')->where('is_active', true)],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('kitchen_stations', 'code')],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'output_mode' => ['required', 'string', Rule::in(array_map(static fn (KitchenStationOutputMode $mode): string => $mode->value, KitchenStationOutputMode::cases()))],
            'printer_target' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
