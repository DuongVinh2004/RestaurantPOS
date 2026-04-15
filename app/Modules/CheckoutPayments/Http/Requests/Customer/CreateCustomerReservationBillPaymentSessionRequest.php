<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerReservationBillPaymentSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'row_version' => ['required', 'integer', 'min:1'],
            'session_id' => ['nullable', 'string', 'max:100'],
            'provider_code' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
