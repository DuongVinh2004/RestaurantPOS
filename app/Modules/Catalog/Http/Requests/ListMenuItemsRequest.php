<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListMenuItemsRequest extends FormRequest
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
            'service_time' => ['nullable', 'date'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'preorder_only' => ['nullable', 'boolean'],
            'is_best_seller' => ['nullable', 'boolean'],
            'is_combo' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('booking.customer_menu_page_max', 100)],
            'filter' => ['nullable', 'array'],
            'filter.is_best_seller' => ['nullable', 'boolean'],
            'filter.is_combo' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'preorder_only' => $this->boolean('preorder_only'),
            'is_best_seller' => $this->has('filter.is_best_seller') ? filter_var($this->input('filter.is_best_seller'), FILTER_VALIDATE_BOOLEAN) : $this->boolean('is_best_seller'),
            'is_combo' => $this->has('filter.is_combo') ? filter_var($this->input('filter.is_combo'), FILTER_VALIDATE_BOOLEAN) : $this->boolean('is_combo'),
        ]);
    }
}
