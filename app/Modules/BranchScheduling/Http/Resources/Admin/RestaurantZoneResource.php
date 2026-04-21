<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'zone' => data_get($this->resource, 'zone'),
            'zone_label' => (string) data_get($this->resource, 'zone_label', data_get($this->resource, 'zone', '')),
            'is_unzoned' => (bool) data_get($this->resource, 'is_unzoned', false),
            'table_count' => (int) data_get($this->resource, 'table_count', 0),
            'table_ids' => array_values((array) data_get($this->resource, 'table_ids', [])),
            'table_codes' => array_values((array) data_get($this->resource, 'table_codes', [])),
            'status_breakdown' => [
                'available' => (int) data_get($this->resource, 'status_breakdown.available', 0),
                'reserved' => (int) data_get($this->resource, 'status_breakdown.reserved', 0),
                'occupied' => (int) data_get($this->resource, 'status_breakdown.occupied', 0),
                'blocked' => (int) data_get($this->resource, 'status_breakdown.blocked', 0),
                'maintenance' => (int) data_get($this->resource, 'status_breakdown.maintenance', 0),
            ],
            'usage' => [
                'active_reservation_count' => (int) data_get($this->resource, 'usage.active_reservation_count', 0),
                'active_hold_count' => (int) data_get($this->resource, 'usage.active_hold_count', 0),
                'active_order_count' => (int) data_get($this->resource, 'usage.active_order_count', 0),
                'has_active_operational_links' => (bool) data_get($this->resource, 'usage.has_active_operational_links', false),
            ],
            'guards' => [
                'can_rename' => (bool) data_get($this->resource, 'guards.can_rename', false),
            ],
        ];
    }
}
