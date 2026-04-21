<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListRestaurantTablesRequest extends FormRequest
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
            'zone' => ['nullable', 'string', 'max:50'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'status' => ['nullable', 'string', 'in:Available,Reserved,Occupied,Blocked,Maintenance'],
            'template_id' => ['nullable', 'integer', 'min:1', 'exists:table_templates,template_id'],
            'include_deleted' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_deleted' => $this->boolean('include_deleted'),
        ]);
    }
}
