<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashierShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_cash_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'row_version' => ['required', 'integer', 'min:1'],
            'staff_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
