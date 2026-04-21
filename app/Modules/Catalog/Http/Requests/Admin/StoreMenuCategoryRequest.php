<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('menu_categories', 'name')],
            'description' => ['sometimes', 'nullable', 'string', 'max:400'],
            'sort_order' => ['sometimes', 'integer'],
            'is_deleted' => ['sometimes', 'boolean'],
        ];
    }
}
