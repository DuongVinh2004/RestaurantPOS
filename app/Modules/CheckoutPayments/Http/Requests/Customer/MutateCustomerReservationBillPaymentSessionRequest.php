<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MutateCustomerReservationBillPaymentSessionRequest extends FormRequest
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
            'simulation_outcome' => ['nullable', 'string', Rule::in(['pending', 'succeeded', 'failed'])],
        ];
    }
}
