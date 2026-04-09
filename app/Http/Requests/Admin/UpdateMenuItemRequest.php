<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $itemId = (int) $this->route('item_id');

        $rules = [
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1', Rule::exists('menu_categories', 'category_id')],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('menu_items', 'code')->ignore($itemId, 'item_id')],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'img_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_available' => ['sometimes', 'boolean'],
            'is_preorder_enabled' => ['sometimes', 'boolean'],
            'preorder_quota_per_day' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'preorder_cutoff_minutes' => ['sometimes', 'integer', 'min:0'],
        ];

        return $rules;
    }
}
