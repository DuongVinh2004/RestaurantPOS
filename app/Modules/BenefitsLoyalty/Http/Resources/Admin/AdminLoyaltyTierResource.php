<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class AdminLoyaltyTierResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tier_id' => (int) $this->tier_id,
            'tier_code' => (string) $this->tier_code,
            'tier_name' => (string) $this->tier_name,
            'min_points' => (int) $this->min_points,
            'benefits_json' => $this->benefits_json,
            'is_active' => (bool) $this->is_active,
            'users_count' => (int) ($this->users_count ?? 0),
            'row_version' => (int) ($this->row_version ?? 1),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
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
