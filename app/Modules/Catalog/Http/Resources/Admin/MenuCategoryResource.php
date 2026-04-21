<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuCategoryResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'category_id' => (int) $this->category_id,
            'name' => (string) $this->name,
            'description' => $this->description,
            'sort_order' => (int) ($this->sort_order ?? 0),
            'is_deleted' => (bool) ($this->is_deleted ?? false),
        ];
    }
}
