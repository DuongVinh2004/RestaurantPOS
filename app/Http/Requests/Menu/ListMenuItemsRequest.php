<?php

declare(strict_types=1);

namespace App\Http\Requests\Menu;

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
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . (int) config('booking.customer_menu_page_max', 100)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'preorder_only' => $this->boolean('preorder_only'),
        ]);
    }
}
