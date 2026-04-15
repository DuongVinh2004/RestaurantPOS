<?php

namespace App\Modules\BranchScheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class RestaurantTableResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $pivot = null;

        if (isset($this->pivot)) {
            $pivot = [
                'reservation_id'       => data_get($this->pivot, 'reservation_id'),
                'table_id'             => data_get($this->pivot, 'table_id'),
                'reservation_table_id' => data_get($this->pivot, 'reservation_table_id'),
            ];
        }

        return [
            'table_id'    => $this->table_id,
            'branch_id'   => $this->branch_id !== null ? (int) $this->branch_id : null,
            'table_code'  => $this->table_code,
            'template_id' => $this->template_id,
            'seats'       => data_get($this, 'seats'), // alias tá»« join table_templates.seats

            'zone'        => $this->zone,
            'pos_x'       => $this->pos_x,
            'pos_y'       => $this->pos_y,
            'status'      => $this->status?->value ?? (string) $this->status,
            'description' => $this->description,
            'price'       => $this->price, // decimal:2 cast á»Ÿ model
            'row_version' => isset($this->row_version) ? (int) $this->row_version : null,

            'pivot'       => $pivot,

            'created_at'  => $this->serializeDateTime($this->created_at),
            'updated_at'  => $this->serializeDateTime($this->updated_at),
        ];
    }

    private function serializeDateTime(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc()->toIso8601String();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }

}
