<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncMenuItemRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'row_version' => ['required', 'integer', 'min:1'],
            'lines' => ['required', 'array'],
            'lines.*.ingredient_id' => ['required', 'integer', 'distinct', Rule::exists('ingredients', 'ingredient_id')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_code' => ['nullable', 'string', 'max:20'],
            'lines.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
