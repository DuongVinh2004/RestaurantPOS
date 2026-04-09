<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @deprecated Compatibility request for the legacy aggregated admin menu controller.
 */
class UpdateAdminMenuCategoryRequest extends FormRequest
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
        $categoryId = (int) ($this->route('category_id') ?? $this->route('id'));

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('menu_categories', 'name')->ignore($categoryId, 'category_id')],
            'description' => ['sometimes', 'nullable', 'string', 'max:400'],
            'sort_order' => ['sometimes', 'integer'],
            'is_deleted' => ['sometimes', 'boolean'],
        ];
    }
}
