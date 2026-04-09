<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAdminPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'min:1', 'exists:suppliers,supplier_id'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'order_code' => ['nullable', 'string', 'max:50', Rule::unique('purchase_orders', 'order_code')],
            'purchase_order_status' => ['nullable', 'string', Rule::in(PurchaseOrderStatus::values())],
            'ordered_at' => ['nullable', 'date'],
            'expected_at' => ['nullable', 'date'],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ingredient_id' => ['required', 'integer', 'min:1', 'exists:ingredients,ingredient_id'],
            'lines.*.ordered_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_code' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
            'lines.*.sort_order' => ['nullable', 'integer'],
        ];
    }
}
