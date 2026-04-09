<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminRestaurantZoneResource extends JsonResource
{
    /**
     * @param Request $request
     */
    public function toArray($request): array
    {
        return [
            'zone' => data_get($this, 'zone'),
            'zone_label' => (string) data_get($this, 'zone_label'),
            'is_unzoned' => (bool) data_get($this, 'is_unzoned', false),
            'table_count' => (int) data_get($this, 'table_count', 0),
            'status_breakdown' => [
                'available' => (int) data_get($this, 'status_breakdown.available', 0),
                'reserved' => (int) data_get($this, 'status_breakdown.reserved', 0),
                'occupied' => (int) data_get($this, 'status_breakdown.occupied', 0),
                'blocked' => (int) data_get($this, 'status_breakdown.blocked', 0),
                'maintenance' => (int) data_get($this, 'status_breakdown.maintenance', 0),
            ],
            'table_ids' => array_values(array_map('intval', (array) data_get($this, 'table_ids', []))),
            'table_codes' => array_values((array) data_get($this, 'table_codes', [])),
            'usage' => [
                'active_reservation_count' => (int) data_get($this, 'usage.active_reservation_count', 0),
                'active_hold_count' => (int) data_get($this, 'usage.active_hold_count', 0),
                'active_order_count' => (int) data_get($this, 'usage.active_order_count', 0),
                'has_active_operational_links' => (bool) data_get($this, 'usage.has_active_operational_links', false),
            ],
            'guards' => [
                'can_rename' => (bool) data_get($this, 'guards.can_rename', false),
            ],
        ];
    }
}
