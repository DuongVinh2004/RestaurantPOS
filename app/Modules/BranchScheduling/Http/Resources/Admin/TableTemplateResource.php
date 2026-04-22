<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableTemplateResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'template_id' => (int) data_get($this, 'template_id'),
            'template_code' => data_get($this, 'template_code'),
            'seats' => (int) data_get($this, 'seats'),
            'description' => data_get($this, 'description'),
            'table_count' => (int) data_get($this, 'table_count', 0),
        ];
    }
}
