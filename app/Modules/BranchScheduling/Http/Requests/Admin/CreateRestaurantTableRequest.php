<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateRestaurantTableRequest extends FormRequest
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
        return [
            'table_code' => ['required', 'string', 'max:50', 'unique:restaurant_tables,table_code'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'template_id' => ['required', 'integer', 'min:1', 'exists:table_templates,template_id'],
            'zone' => ['nullable', 'string', 'max:50'],
            'pos_x' => ['nullable', 'integer'],
            'pos_y' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:Available,Blocked,Maintenance'],
            'description' => ['nullable', 'string', 'max:400'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_deleted' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_deleted' => $this->boolean('is_deleted'),
        ]);
    }
}
