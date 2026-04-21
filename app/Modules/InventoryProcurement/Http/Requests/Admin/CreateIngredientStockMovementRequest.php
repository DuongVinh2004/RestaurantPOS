<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Http\Requests\Admin;

use App\Modules\InventoryProcurement\Domain\Models\IngredientStockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateIngredientStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movement_type' => ['required', 'string', Rule::in(IngredientStockMovement::supportedTypes())],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_code' => ['nullable', 'string', 'max:20'],
            'reference_type' => ['nullable', 'string', 'max:50'],
            'reference_id' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
