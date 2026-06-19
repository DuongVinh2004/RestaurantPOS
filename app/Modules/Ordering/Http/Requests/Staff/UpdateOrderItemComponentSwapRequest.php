<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderItemComponentSwapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_item_id' => ['required', 'integer', 'exists:menu_items,item_id'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'order_row_version' => ['nullable', 'integer'],
            'row_version' => ['nullable', 'integer'],
        ];
    }
}
