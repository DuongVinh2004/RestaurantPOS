<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tableId = (int) $this->route('id');

        return [
            'row_version' => ['required', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'table_code' => ['sometimes', 'string', 'max:50', Rule::unique('restaurant_tables', 'table_code')->ignore($tableId, 'table_id')],
            'template_id' => ['sometimes', 'integer', 'min:1', 'exists:table_templates,template_id'],
            'zone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'pos_x' => ['sometimes', 'nullable', 'integer'],
            'pos_y' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'string', 'in:Available,Blocked,Maintenance'],
            'description' => ['sometimes', 'nullable', 'string', 'max:400'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_deleted' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('is_deleted')) {
            $merge['is_deleted'] = $this->boolean('is_deleted');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
