<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayReservationDepositRequest extends FormRequest
{
    private const PAYMENT_PROVIDERS = ['MoMo', 'VNPay', 'Cash', 'Card', 'BankTransfer', 'Other'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'max:30'],
            'payment_provider' => ['nullable', 'string', 'max:50', Rule::in(self::PAYMENT_PROVIDERS)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'transaction_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'row_version' => ['required', 'integer', 'min:1'],
            'staff_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
