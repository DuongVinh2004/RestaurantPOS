<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LoyaltyTier */
class LoyaltyTierAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tier_id' => (int) $this->tier_id,
            'tier_code' => (string) $this->tier_code,
            'tier_name' => (string) $this->tier_name,
            'min_points' => (int) $this->min_points,
            'benefits_json' => $this->benefits_json ?? [],
            'is_active' => (bool) $this->is_active,
            'row_version' => isset($this->row_version) ? (int) $this->row_version : null,
            'created_at' => optional($this->created_at)?->utc()?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->utc()?->toIso8601String(),
        ];
    }
}
