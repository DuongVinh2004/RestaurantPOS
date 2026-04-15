<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundAndCancelReservationRequest extends FormRequest
{
    private const PAYMENT_PROVIDERS = ['MoMo', 'VNPay', 'Cash', 'Card', 'BankTransfer', 'Other'];

    private const REFUND_SCOPES = ['deposit', 'final', 'all'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'max:30'],
            'payment_provider' => ['nullable', 'string', 'max:50', Rule::in(self::PAYMENT_PROVIDERS)],
            'refund_scope' => ['nullable', 'string', Rule::in(self::REFUND_SCOPES)],
            'refund_amount' => ['nullable', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'transaction_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'reason' => ['nullable', 'string', 'max:255'],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
            'row_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
