<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePurchaseOrderReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt_code' => ['nullable', 'string', 'max:50', Rule::unique('purchase_receipts', 'receipt_code')],
            'received_at' => ['nullable', 'date'],
            'supplier_document_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'min:1', 'exists:purchase_order_lines,po_line_id'],
            'lines.*.received_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_code' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
