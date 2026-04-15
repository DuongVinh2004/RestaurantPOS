<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class CloseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'row_version' => ['required', 'integer', 'min:1'],
            'staff_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
