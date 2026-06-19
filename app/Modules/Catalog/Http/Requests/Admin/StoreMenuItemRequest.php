<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1', Rule::exists('menu_categories', 'category_id')],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('menu_items', 'code')],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'img_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_combo' => ['nullable', 'boolean'],
            'serving_size' => ['nullable', 'integer', 'min:1'],
            'combo_components' => ['nullable', 'array'],
            'combo_components.*.component_item_id' => ['required_with:combo_components', 'integer', 'exists:menu_items,item_id'],
            'combo_components.*.quantity' => ['required_with:combo_components', 'integer', 'min:1'],
            'is_available' => ['sometimes', 'boolean'],
            'is_preorder_enabled' => ['nullable', 'boolean'],
            'preorder_quota_per_day' => ['nullable', 'integer', 'min:0'],
            'preorder_cutoff_minutes' => ['nullable', 'integer', 'min:0'],
            'modifier_group_ids' => ['nullable', 'array'],
            'modifier_group_ids.*' => ['integer', 'exists:menu_modifier_groups,group_id'],
        ];

        return $rules;
    }
}
