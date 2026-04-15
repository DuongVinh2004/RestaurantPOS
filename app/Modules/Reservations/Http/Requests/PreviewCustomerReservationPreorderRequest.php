<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewCustomerReservationPreorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pre_order_items' => ['required', 'array', 'min:1', 'max:100'],
            'pre_order_items.*.item_id' => ['required', 'integer', 'min:1'],
            'pre_order_items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
