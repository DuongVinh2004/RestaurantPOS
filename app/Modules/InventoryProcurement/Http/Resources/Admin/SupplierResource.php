<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'supplier_id' => (int) $this->supplier_id,
            'code' => $this->code !== null ? (string) $this->code : null,
            'name' => (string) $this->name,
            'contact_name' => $this->contact_name !== null ? (string) $this->contact_name : null,
            'phone' => $this->phone !== null ? (string) $this->phone : null,
            'email' => $this->email !== null ? (string) $this->email : null,
            'notes' => $this->notes !== null ? (string) $this->notes : null,
            'is_active' => (bool) $this->is_active,
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
