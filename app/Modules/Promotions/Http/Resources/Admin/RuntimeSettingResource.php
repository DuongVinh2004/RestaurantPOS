<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class RuntimeSettingResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'setting_key' => (string) data_get($this->resource, 'setting_key'),
            'setting_type' => (string) data_get($this->resource, 'setting_type'),
            'default_value' => data_get($this->resource, 'default_value'),
            'runtime_value' => data_get($this->resource, 'runtime_value'),
            'effective_value' => data_get($this->resource, 'effective_value'),
            'source' => (string) data_get($this->resource, 'source'),
            'updated_by' => data_get($this->resource, 'updated_by') !== null ? (int) data_get($this->resource, 'updated_by') : null,
            'updated_at' => $this->iso(data_get($this->resource, 'updated_at')),
        ];
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }
}
