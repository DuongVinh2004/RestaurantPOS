<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\KitchenStationOutputMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminKitchenStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => $this->boolean('is_active'),
            ]);
        }
    }

    public function rules(): array
    {
        $stationId = is_numeric($this->route('station_id')) ? (int) $this->route('station_id') : null;

        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('kitchen_stations', 'code')->ignore($stationId, 'station_id')],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'output_mode' => ['sometimes', 'required', 'string', Rule::in(array_map(static fn (KitchenStationOutputMode $mode): string => $mode->value, KitchenStationOutputMode::cases()))],
            'printer_target' => ['sometimes', 'nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
