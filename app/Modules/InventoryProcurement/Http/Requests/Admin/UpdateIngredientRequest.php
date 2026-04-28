<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ingredientId = (int) $this->route('id');

        return [
            'row_version' => ['required', 'integer', 'min:1'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('ingredients', 'code')->ignore($ingredientId, 'ingredient_id')],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'unit_code' => ['sometimes', 'required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => $this->boolean('is_active'),
            ]);
        }
    }
}
