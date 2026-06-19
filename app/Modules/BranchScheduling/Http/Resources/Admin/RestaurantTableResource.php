<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Resources\Admin;

use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RestaurantTable */
class RestaurantTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $template = $this->relationLoaded('template') ? $this->template : null;
        $usage = $this->relationLoaded('usage') ? $this->usage : collect();
        $guards = $this->relationLoaded('guards') ? $this->guards : collect();

        $status = $this->status?->value ?? (string) $this->status;
        $isActive = ! (bool) $this->is_deleted;
        $isAllocatable = $isActive && ! in_array($status, ['Blocked', 'Maintenance'], true);

        return [
            'table_id' => (int) $this->table_id,
            'branch_id' => isset($this->branch_id) ? (int) $this->branch_id : null,
            'branch' => $this->whenLoaded('branch', fn (): array => [
                'branch_id' => (int) $this->branch->branch_id,
                'branch_code' => (string) $this->branch->branch_code,
                'branch_name' => (string) $this->branch->branch_name,
                'is_default' => (bool) $this->branch->is_default,
            ]),
            'table_code' => (string) $this->table_code,
            'template_id' => $this->template_id !== null ? (int) $this->template_id : null,
            'capacity' => $template?->seats !== null ? (int) $template->seats : null,
            'seats' => $template?->seats !== null ? (int) $template->seats : null,
            'template' => $template ? [
                'template_id' => (int) $template->template_id,
                'template_code' => (string) ($template->template_code ?? ''),
                'seats' => (int) $template->seats,
                'description' => $template->description,
            ] : null,
            'zone' => $this->zone,
            'position' => [
                'x' => $this->pos_x !== null ? (int) $this->pos_x : null,
                'y' => $this->pos_y !== null ? (int) $this->pos_y : null,
            ],
            'pos_x' => $this->pos_x !== null ? (int) $this->pos_x : null,
            'pos_y' => $this->pos_y !== null ? (int) $this->pos_y : null,
            'status' => $status,
            'is_deleted' => (bool) $this->is_deleted,
            'is_active' => $isActive,
            'is_allocatable' => $isAllocatable,
            'usage' => [
                'active_reservation_count' => (int) data_get($usage, 'active_reservation_count', 0),
                'active_hold_count' => (int) data_get($usage, 'active_hold_count', 0),
                'active_order_count' => (int) data_get($usage, 'active_order_count', 0),
                'has_active_operational_links' => (bool) data_get($usage, 'has_active_operational_links', false),
            ],
            'guards' => [
                'can_archive' => (bool) data_get($guards, 'can_archive', false),
                'can_change_template' => (bool) data_get($guards, 'can_change_template', false),
                'can_change_runtime_status' => (bool) data_get($guards, 'can_change_runtime_status', false),
                'can_change_zone' => (bool) data_get($guards, 'can_change_zone', false),
                'can_change_table_code' => (bool) data_get($guards, 'can_change_table_code', false),
                'can_change_branch' => (bool) data_get($guards, 'can_change_branch', false),
            ],
            'description' => $this->description,
            'price' => $this->price !== null ? number_format((float) $this->price, 0, '.', '') : null,
            'row_version' => isset($this->row_version) ? (int) $this->row_version : null,
            'created_at' => optional($this->created_at)?->utc()?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->utc()?->toIso8601String(),
        ];
    }
}
