<?php

declare(strict_types=1);

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;

class PreviewMenuPreorderRequest extends FormRequest
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
        return [
            'start_time' => ['required', 'date'],
            'pre_order_items' => ['required', 'array', 'min:1', 'max:100'],
            'pre_order_items.*.item_id' => ['required', 'integer', 'min:1'],
            'pre_order_items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
