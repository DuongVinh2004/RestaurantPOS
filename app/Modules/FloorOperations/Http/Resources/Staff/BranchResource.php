<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Http\Resources\Staff;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'branch_id' => (int) $this->branch_id,
            'branch_code' => (string) $this->branch_code,
            'branch_name' => (string) $this->branch_name,
            'description' => $this->description !== null ? (string) $this->description : null,
            'timezone' => $this->timezone !== null ? (string) $this->timezone : null,
            'currency' => $this->currency !== null ? (string) $this->currency : null,
            'business_hours' => is_array($this->business_hours)
                ? $this->business_hours
                : (array) config('booking.branch_policy_defaults.business_hours', []),
            'closure_windows' => is_array($this->closure_windows)
                ? $this->closure_windows
                : (array) config('booking.branch_policy_defaults.closure_windows', []),
            'booking_policy' => is_array($this->booking_policy)
                ? $this->booking_policy
                : (array) config('booking.branch_policy_defaults.booking_policy', []),
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'row_version' => isset($this->row_version) ? (int) $this->row_version : null,
            'created_at' => $this->created_at?->utc()?->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()?->toIso8601String(),
        ];
    }
}

